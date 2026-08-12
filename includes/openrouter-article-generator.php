<?php
/**
 * Oroma TV AI Content Assistant (OpenRouter) — turns a source URL into a draft
 * article that REPORTS ON the source (with attribution baked in), not a
 * disguised copy of it. See admin/ai-generate.php for how this is used.
 */
class OromaTV_AI_Content_Generator
{
    private string $apiKey;
    private string $model;
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(string $apiKey, string $model = 'openai/gpt-oss-20b:free')
    {
        $this->apiKey = $apiKey;
        $this->model = $model !== '' ? $model : 'openai/gpt-oss-20b:free';
    }

    /**
     * Fetch a URL, send it to OpenRouter, and return a ready-to-store draft.
     * Always returns ['success' => bool, ...].
     */
    public function generateDraft(string $url, string $extraContext = ''): array
    {
        if (trim($url) === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Please enter a valid URL.'];
        }

        $fetched = $this->fetchUrlContent($url);
        if (!$fetched) {
            return ['success' => false, 'error' => 'Could not fetch readable content from that URL. It may block automated requests, redirect to a private address, or have too little text on the page.'];
        }

        $prompt = $this->buildPrompt($fetched['text'], $fetched['final_url'], $fetched['source_name'], $extraContext);
        $result = $this->callOpenRouter($prompt);
        if (!$result['success']) {
            return $result;
        }

        $d = $result['data'];
        $sourceName = trim((string) ($d['source_name'] ?? '')) ?: ($fetched['source_name'] ?: (string) parse_url($fetched['final_url'], PHP_URL_HOST));

        // Attribution is added here in code — not left to the model to remember —
        // so every draft is guaranteed to credit and link the original source.
        $attribution = sprintf(
            '<p class="ai-source-note"><em>This article is based on reporting by <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>.</em></p>',
            htmlspecialchars($fetched['final_url'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8')
        );

        return [
            'success'      => true,
            'headline'     => trim((string) ($d['headline'] ?? '')),
            'excerpt'      => trim((string) ($d['excerpt'] ?? '')),
            'content_html' => $attribution . (string) ($d['body_html'] ?? ''),
            'image_idea'   => trim((string) ($d['image_idea'] ?? '')),
            'source_url'   => $fetched['final_url'],
            'source_name'  => $sourceName,
        ];
    }

    /** Reject anything that isn't a plain http(s) URL pointing at a public address (basic SSRF guard). */
    private function isUrlSafe(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        $host = $parts['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** Fetch a URL and reduce it to plain, readable text plus a best-guess publisher name. */
    private function fetchUrlContent(string $url): ?array
    {
        if (!$this->isUrlSafe($url)) {
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OromaTVBot/1.0)',
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        curl_close($ch);

        if ($html === false || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }
        // The final URL (after redirects) also needs to land on a public address.
        if (!$this->isUrlSafe($finalUrl)) {
            return null;
        }

        $sourceName = null;
        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $sourceName = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $sourceName = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }

        // Drop non-content chrome before flattening to text.
        $stripped = preg_replace('#<(script|style|noscript|header|footer|nav|form|svg)[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(strip_tags($stripped), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text) < 200) {
            return null;
        }
        if (mb_strlen($text) > 12000) {
            $text = mb_substr($text, 0, 12000);
        }

        return ['text' => $text, 'source_name' => $sourceName, 'final_url' => $finalUrl];
    }

    private function buildPrompt(string $content, string $sourceUrl, ?string $sourceName, string $extraContext): string
    {
        $sourceLabel = $sourceName ?: (string) parse_url($sourceUrl, PHP_URL_HOST);

        $lines = [
            "You are a news writer for OROMA TV, a Ugandan news platform. You are writing a piece "
            . "that REPORTS ON a story originally published by \"$sourceLabel\" ($sourceUrl). This is "
            . "NOT your own first-hand reporting — you are summarizing and contextualizing someone else's "
            . "reporting for the Oroma TV audience, the way a wire desk or news aggregator would.",

            "STRICT ATTRIBUTION RULES:\n"
            . "- Naturally attribute the facts to the original source throughout the body text, e.g. "
            . "\"according to $sourceLabel\", \"$sourceLabel reports that...\", \"as first reported by $sourceLabel\".\n"
            . "- Never imply Oroma TV conducted original interviews, obtained exclusive access, or witnessed events first-hand.\n"
            . "- Do not fabricate quotes, statistics, or details that are not present in the source content below.",

            "SOURCE CONTENT TO REPORT ON:\n\"\"\"\n$content\n\"\"\"",
        ];

        if (trim($extraContext) !== '') {
            $lines[] = 'ADDITIONAL INSTRUCTIONS FROM THE EDITOR: ' . trim($extraContext);
        }

        $lines[] = 'Write the piece in your own words (not copied phrasing from the source), with a '
            . 'Uganda-relevant angle only where it is genuinely relevant. body_html should use only '
            . '<p>, <h2>, <h3>, <ul>, <li>, and <blockquote> tags, and should NOT repeat the headline as an <h1>.';

        $lines[] = 'Respond with ONLY a single valid JSON object (no markdown code fences, no commentary '
            . 'before or after it) with exactly these keys: "headline" (string), "excerpt" (string, one or '
            . 'two sentences, under 200 characters), "body_html" (string), "source_name" (string — the best '
            . 'guess at the original publisher/outlet name), "image_idea" (string — a one-sentence suggestion '
            . 'for a fitting featured image).';

        return implode("\n\n", $lines);
    }

    private function callOpenRouter(string $promptText): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You always respond with a single valid JSON object and nothing else — no markdown fences, no commentary.'],
                ['role' => 'user', 'content' => $promptText],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.6,
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . (defined('SITE_URL') ? SITE_URL : 'https://oromatv.com'),
                'X-Title: Oroma TV AI Writer',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 90,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'Network error contacting OpenRouter: ' . $curlErr];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $msg = $data['error']['message'] ?? substr($response, 0, 300);
            return ['success' => false, 'error' => 'OpenRouter API error: ' . $msg];
        }

        $text = $data['choices'][0]['message']['content'] ?? null;
        if (!$text) {
            return ['success' => false, 'error' => 'OpenRouter returned an unexpected response.'];
        }

        // Some models wrap JSON in ```json fences despite instructions — strip those before decoding.
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);

        $parsed = json_decode($clean, true);
        if (!is_array($parsed) || empty($parsed['headline']) || empty($parsed['body_html'])) {
            return ['success' => false, 'error' => 'OpenRouter response was not valid structured JSON.'];
        }

        return ['success' => true, 'data' => $parsed];
    }
}
