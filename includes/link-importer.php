<?php
/**
 * Oroma TV link importer — reads a story from another web page and brings it across, photos included.
 *
 *   $reader = new OromaTV_Link_Reader();
 *   $page   = $reader->read($url);                  // title, description, date, text blocks and photo blocks, in page order
 *   $done   = $reader->importPhotos($page);         // downloads the photos into uploads/articles/Y/m/ (resized, checked)
 *   $html   = $reader->toHtml($done['blocks']);     // the story as editor-friendly HTML (photos with a caption line under them)
 *
 * Used by admin/ai-generate.php ("Import from a link"): either the story is kept as it is, or the AI rewrites it and the photos
 * are placed through the rewritten text.
 *
 * Safety: only http(s) pages on public addresses are fetched (every redirect is re-checked), pages are capped at 3 MB and photos
 * at 8 MB, and every photo is verified as a real image before it is stored. The page's HTML is never stored as it is — it is
 * rebuilt from a small allowlist of tags (paragraphs, headings, lists, quotes, bold/italic, links, photos).
 */
class OromaTV_Link_Reader
{
    public const MAX_PAGE_BYTES   = 3 * 1024 * 1024;
    public const MAX_IMAGE_BYTES  = 8 * 1024 * 1024;
    public const MAX_IMAGES       = 12;
    public const MIN_IMAGE_WIDTH  = 240;   // smaller pictures are icons and logos, not story photos
    public const MAX_STORED_WIDTH = 1600;  // wider photos are scaled down (keeps pages fast)

    private const SKIP_TAGS = ['script', 'style', 'noscript', 'nav', 'footer', 'aside', 'form', 'svg', 'button', 'select', 'iframe', 'template', 'header', 'dialog'];
    private const SKIP_CLASS = '/(^|[\s_-])(share|sharing|social|related|comments?|newsletter|breadcrumbs?|crumb|sidebar|cookie|advert|promo|subscribe|author-box|pagination|pager|toolbar|actions?|cta|btn|button)([\s_-]|$)/i';

    // ── safe fetching ──────────────────────────────────────────

    /** Only plain http(s) addresses that point at the public internet (blocks localhost, private networks, link-local). */
    public static function isUrlSafe(string $url): bool
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

    /** Turns a link found on a page into an absolute address. */
    public static function resolveUrl(string $base, string $link): ?string
    {
        $link = trim(html_entity_decode($link, ENT_QUOTES, 'UTF-8'));
        if ($link === '' || preg_match('~^(javascript|data|mailto|tel|#)~i', $link)) {
            return null;
        }
        if (preg_match('~^https?://~i', $link)) {
            return $link;
        }
        $b = parse_url($base);
        if (!$b || empty($b['host'])) {
            return null;
        }
        $origin = ($b['scheme'] ?? 'https') . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($link, '//')) {
            return ($b['scheme'] ?? 'https') . ':' . $link;
        }
        if (str_starts_with($link, '/')) {
            return $origin . $link;
        }
        if (str_starts_with($link, '?')) {
            return $origin . ($b['path'] ?? '/') . $link;
        }
        $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/');
        $path = $dir . $link;
        // collapse ./ and ../
        $out = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($out);
            } elseif ($seg !== '.') {
                $out[] = $seg;
            }
        }
        return $origin . implode('/', $out);
    }

    /** GET a page or file. Follows up to 4 redirects by hand so every hop can be checked. Returns the body or null. */
    private function fetch(string $url, int $maxBytes, ?string $referer, ?string &$finalUrl = null, ?string &$contentType = null): ?string
    {
        for ($hop = 0; $hop <= 4; $hop++) {
            if (!self::isUrlSafe($url)) {
                return null;
            }
            $body = '';
            $location = null;
            $type = null;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OromaTVBot/1.0)',
                CURLOPT_HTTPHEADER => array_values(array_filter(['Accept: text/html,image/*;q=0.9,*/*;q=0.5', $referer ? 'Referer: ' . $referer : null])),
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$location, &$type) {
                    if (stripos($line, 'location:') === 0) {
                        $location = trim(substr($line, 9));
                    } elseif (stripos($line, 'content-type:') === 0) {
                        $type = strtolower(trim(substr($line, 13)));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, $maxBytes) {
                    $body .= $chunk;
                    return strlen($body) > $maxBytes ? -1 : strlen($chunk); // -1 aborts: too big
                },
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (in_array($code, [301, 302, 303, 307, 308], true) && $location) {
                $next = self::resolveUrl($url, $location);
                if (!$next) {
                    return null;
                }
                $url = $next;
                continue;
            }
            if ($code < 200 || $code >= 300 || $body === '' || strlen($body) > $maxBytes) {
                return null;
            }
            $finalUrl = $url;
            $contentType = $type;
            return $body;
        }
        return null;
    }

    // ── reading a page ─────────────────────────────────────────

    /**
     * Reads a page. Returns null if it cannot be fetched, else:
     *   final_url, source_name, title, description, og_image, published ('Y-m-d H:i:s' or null),
     *   blocks   [ ['type'=>'p','html'=>…] | ['type'=>'h2'|'h3','text'=>…] | ['type'=>'ul'|'ol','items'=>[html…]]
     *              | ['type'=>'quote','html'=>…] | ['type'=>'img','url'=>…,'alt'=>…,'caption'=>…] ]  (in page order)
     *   text     the story as plain text (for the AI)
     */
    public function read(string $url): ?array
    {
        $final = null;
        $type = null;
        $html = $this->fetch($url, self::MAX_PAGE_BYTES, null, $final, $type);
        if ($html === null || !$final) {
            return null;
        }

        // Pages are normally UTF-8; convert the odd one that is not.
        $charset = null;
        if ($type && preg_match('/charset=([\w-]+)/', $type, $m)) {
            $charset = $m[1];
        } elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $m)) {
            $charset = $m[1];
        }
        if ($charset && !preg_match('/^utf-?8$/i', $charset) && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
            if ($converted !== false) {
                $html = $converted;
            }
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);

        $meta = function (string $name) use ($xp): string {
            $n = $xp->query('//meta[@property="' . $name . '" or @name="' . $name . '"]/@content')->item(0);
            return $n ? trim(html_entity_decode($n->nodeValue, ENT_QUOTES, 'UTF-8')) : '';
        };

        $sourceName = $meta('og:site_name');
        $title = $meta('og:title');
        if ($title === '') {
            $h1 = $xp->query('//h1')->item(0);
            $title = $h1 ? $this->clean($h1->textContent) : '';
        }
        if ($title === '') {
            $t = $xp->query('//title')->item(0);
            $title = $t ? $this->clean($t->textContent) : '';
            // "Story title | Site name" -> "Story title"
            $title = preg_replace('/\s*[|\x{2013}\x{2014}-]\s*[^|\x{2013}\x{2014}-]{2,60}$/u', '', $title) ?: $title;
        }
        if ($sourceName === '') {
            $sourceName = (string) preg_replace('/^www\./i', '', (string) parse_url($final, PHP_URL_HOST));
        }
        $description = $meta('og:description') ?: $meta('description');
        $description = trim(preg_replace('/\s*(…|\.\.\.)$/u', '…', $description));

        // The part of the page that holds the story: the biggest <article>, else <main>, else the whole body.
        $root = null;
        $best = 0;
        foreach ($xp->query('//article') as $a) {
            $len = mb_strlen($a->textContent);
            if ($len > $best) {
                $best = $len;
                $root = $a;
            }
        }
        $root = $root
            ?? $xp->query('//main')->item(0)
            ?? $xp->query('//*[@role="main"]')->item(0)
            ?? $xp->query('//body')->item(0);
        if (!$root) {
            return null;
        }

        $blocks = [];
        $seenImages = [];
        $this->walk($root, $final, $blocks, $seenImages);

        // Drop a block that only repeats the headline; trim trailing empties.
        $blocks = array_values(array_filter($blocks, function ($b) use ($title) {
            return !($b['type'] === 'h2' && mb_strtolower($b['text']) === mb_strtolower($title));
        }));

        $text = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'p' || $b['type'] === 'quote') {
                $text[] = trim(html_entity_decode(strip_tags($b['html']), ENT_QUOTES, 'UTF-8'));
            } elseif ($b['type'] === 'h2' || $b['type'] === 'h3') {
                $text[] = $b['text'];
            } elseif ($b['type'] === 'ul' || $b['type'] === 'ol') {
                foreach ($b['items'] as $li) {
                    $text[] = '- ' . trim(html_entity_decode(strip_tags($li), ENT_QUOTES, 'UTF-8'));
                }
            }
        }
        $plain = implode("\n\n", $text);
        if (mb_strlen($plain) < 200) {
            return null; // too little text: probably a login page, an error page or an app shell
        }

        $ogImage = $meta('og:image') ?: $meta('twitter:image');
        $ogImage = $ogImage !== '' ? self::resolveUrl($final, $ogImage) : null;

        return [
            'final_url'   => $final,
            'source_name' => $sourceName,
            'title'       => $title,
            'description' => $description,
            'og_image'    => $ogImage,
            'published'   => $this->findDate($xp, $root, $meta),
            'blocks'      => $blocks,
            'text'        => mb_substr($plain, 0, 12000),
        ];
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
    }

    /** The story's publish date: machine-readable markup first, then a written date near the top ("20 October 2026"). */
    private function findDate(DOMXPath $xp, DOMNode $root, callable $meta): ?string
    {
        $candidates = [$meta('article:published_time'), $meta('og:article:published_time'), $meta('datePublished')];
        foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
            if (preg_match('/"datePublished"\s*:\s*"([^"]+)"/', $s->textContent, $m)) {
                $candidates[] = $m[1];
            }
        }
        $time = $xp->query('.//time/@datetime', $root)->item(0);
        if ($time) {
            $candidates[] = $time->nodeValue;
        }
        $head = mb_substr($this->clean($root->textContent), 0, 700);
        // (not \b: a page's header often runs the pieces together, as in "International20 October 2026")
        if (preg_match('/(?<!\d)(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(20\d{2})/i', $head, $m)) {
            $candidates[] = $m[0];
        }
        foreach ($candidates as $c) {
            $ts = $c !== '' ? strtotime($c) : false;
            if ($ts && $ts > strtotime('2000-01-01') && $ts < time() + 86400) {
                // A date with no time of day becomes mid-morning, so the story does not sort oddly.
                return preg_match('/\d{1,2}:\d{2}/', $c) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d 09:00:00', $ts);
            }
        }
        return null;
    }

    /** Collects the story's blocks in page order. */
    private function walk(DOMNode $node, string $base, array &$blocks, array &$seenImages): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::SKIP_TAGS, true)) {
                continue;
            }
            $marker = $child->getAttribute('class') . ' ' . $child->getAttribute('id');
            if (trim($marker) !== '' && preg_match(self::SKIP_CLASS, $marker)) {
                continue;
            }
            if ($child->getAttribute('aria-hidden') === 'true' || $child->hasAttribute('hidden')) {
                continue;
            }

            switch ($tag) {
                case 'p':
                    $html = $this->inline($child, $base);
                    if (trim(strip_tags($html)) !== '') {
                        $blocks[] = ['type' => 'p', 'html' => $html];
                    }
                    foreach ($child->getElementsByTagName('img') as $img) {
                        $this->addImage($img, $base, $blocks, $seenImages, null);
                    }
                    break;
                case 'h1':
                    break; // the title is taken separately
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                    $text = $this->clean($child->textContent);
                    if ($text !== '') {
                        $blocks[] = ['type' => $tag === 'h2' ? 'h2' : 'h3', 'text' => $text];
                    }
                    break;
                case 'ul':
                case 'ol':
                    $items = [];
                    foreach ($child->childNodes as $li) {
                        if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                            $h = $this->inline($li, $base);
                            if (trim(strip_tags($h)) !== '') {
                                $items[] = $h;
                            }
                        }
                    }
                    if ($items) {
                        $blocks[] = ['type' => $tag, 'items' => $items];
                    }
                    break;
                case 'blockquote':
                    $html = $this->inline($child, $base);
                    if (trim(strip_tags($html)) !== '') {
                        $blocks[] = ['type' => 'quote', 'html' => $html];
                    }
                    break;
                case 'figure':
                    $img = $child->getElementsByTagName('img')->item(0);
                    $cap = $child->getElementsByTagName('figcaption')->item(0);
                    if ($img) {
                        $this->addImage($img, $base, $blocks, $seenImages, $cap ? $this->clean($cap->textContent) : null);
                    }
                    break;
                case 'img':
                    $this->addImage($child, $base, $blocks, $seenImages, null);
                    break;
                default:
                    $this->walk($child, $base, $blocks, $seenImages);
            }
        }
    }

    /** Adds a photo block if the <img> is a real story photo. */
    private function addImage(DOMElement $img, string $base, array &$blocks, array &$seenImages, ?string $caption): void
    {
        $meta = strtolower($img->getAttribute('class') . ' ' . $img->getAttribute('id') . ' ' . $img->getAttribute('src'));
        if (preg_match('/(logo|icon|avatar|sprite|pixel|tracking|emoji|spinner)/', $meta)) {
            return;
        }
        $w = (int) $img->getAttribute('width');
        $h = (int) $img->getAttribute('height');
        if (($w > 0 && $w < 100) || ($h > 0 && $h < 100)) {
            return;
        }
        $url = $this->bestImageUrl($img, $base);
        if (!$url || isset($seenImages[$url])) {
            return;
        }
        $seenImages[$url] = true;

        if ($caption === null) {
            $a = $img->parentNode instanceof DOMElement && strtolower($img->parentNode->tagName) === 'a' ? $img->parentNode : null;
            $caption = $a && $a->getAttribute('data-caption') !== '' ? $this->clean($a->getAttribute('data-caption')) : null;
        }
        $blocks[] = ['type' => 'img', 'url' => $url, 'alt' => $this->clean($img->getAttribute('alt')), 'caption' => $caption !== '' ? $caption : null];
    }

    /** The sharpest version of a photo the page offers: the link around it, else the biggest srcset entry, else src. */
    private function bestImageUrl(DOMElement $img, string $base): ?string
    {
        $isFile = fn(string $u) => (bool) preg_match('~\.(jpe?g|png|webp|gif)(\?|#|$)~i', parse_url($u, PHP_URL_PATH) ?? '');

        $parent = $img->parentNode;
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'a') {
            $href = self::resolveUrl($base, $parent->getAttribute('href'));
            if ($href && $isFile($href)) {
                return $href;
            }
        }
        $best = null;
        $bestSize = 0;
        foreach (['srcset', 'data-srcset'] as $attr) {
            foreach (explode(',', $img->getAttribute($attr)) as $entry) {
                $bits = preg_split('/\s+/', trim($entry));
                if (empty($bits[0])) {
                    continue;
                }
                $size = isset($bits[1]) ? (float) $bits[1] : 1; // "1600w" or "2x"
                if ($size >= $bestSize) {
                    $bestSize = $size;
                    $best = $bits[0];
                }
            }
        }
        foreach ([$best, $img->getAttribute('data-src'), $img->getAttribute('data-lazy-src'), $img->getAttribute('data-original'), $img->getAttribute('src')] as $cand) {
            if ($cand) {
                $abs = self::resolveUrl($base, $cand);
                if ($abs && !preg_match('~\.(svg|ico)(\?|$)~i', $abs)) {
                    return $abs;
                }
            }
        }
        return null;
    }

    /** The inside of an element as safe inline HTML: text, bold, italic, links and line breaks only. */
    private function inline(DOMNode $node, string $base): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMText) {
                $out .= htmlspecialchars(preg_replace('/\s+/u', ' ', $c->nodeValue), ENT_QUOTES, 'UTF-8');
            } elseif ($c instanceof DOMElement) {
                $t = strtolower($c->tagName);
                if (in_array($t, ['script', 'style', 'img', 'svg', 'noscript', 'button'], true)) {
                    continue;
                }
                $inner = $this->inline($c, $base);
                if (trim(strip_tags($inner)) === '' && $t !== 'br') {
                    $out .= $inner;
                    continue;
                }
                if ($t === 'strong' || $t === 'b') {
                    $out .= '<strong>' . $inner . '</strong>';
                } elseif ($t === 'em' || $t === 'i') {
                    $out .= '<em>' . $inner . '</em>';
                } elseif ($t === 'br') {
                    $out .= '<br>';
                } elseif ($t === 'a') {
                    $href = self::resolveUrl($base, $c->getAttribute('href'));
                    $out .= $href ? '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $inner . '</a>' : $inner;
                } elseif ($t === 'p') {
                    $out .= $inner . ' ';
                } elseif (in_array($t, ['h2', 'h3', 'h4', 'h5', 'h6'], true)) { // a heading inside a list item: bold, with its text on the next line
                    $out .= '<strong>' . $inner . '</strong><br>';
                } else {
                    $out .= $inner;
                }
            }
        }
        return trim(preg_replace('/ {2,}/', ' ', $out));
    }

    // ── photos ─────────────────────────────────────────────────

    /**
     * Downloads one photo into uploads/articles/Y/m/ (scaled down if very wide). Returns
     * ['path' => 'uploads/articles/2026/10/abc.jpg', 'sha1' => …, 'width' => …, 'height' => …] or null.
     */
    public function saveImage(string $url, ?string $referer): ?array
    {
        $bytes = $this->fetch($url, self::MAX_IMAGE_BYTES, $referer);
        if ($bytes === null) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info) {
            return null;
        }
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null;
        if (!$ext || $info[0] < self::MIN_IMAGE_WIDTH) {
            return null;
        }
        $sha = sha1($bytes);
        [$w, $h] = [$info[0], $info[1]];

        // Very wide photos are scaled down; GIFs are left alone so animations survive.
        if ($w > self::MAX_STORED_WIDTH && $ext !== 'gif' && function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($bytes);
            if ($src) {
                if ($ext === 'jpg' && function_exists('exif_read_data')) { // a phone photo carries its rotation in a tag that re-saving would drop
                    $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
                    $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
                    if ($angle) {
                        $rot = imagerotate($src, $angle, 0);
                        if ($rot) {
                            $src = $rot;
                            [$w, $h] = [imagesx($src), imagesy($src)];
                        }
                    }
                }
                $nh = (int) round($h * self::MAX_STORED_WIDTH / $w);
                $dst = imagecreatetruecolor(self::MAX_STORED_WIDTH, $nh);
                if ($ext !== 'jpg') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, self::MAX_STORED_WIDTH, $nh, $w, $h);
                ob_start();
                $ok = $ext === 'jpg' ? imagejpeg($dst, null, 85) : ($ext === 'png' ? imagepng($dst, null, 6) : (function_exists('imagewebp') ? imagewebp($dst, null, 85) : false));
                $resized = ob_get_clean();
                if ($ok && $resized !== '' && $resized !== false) {
                    $bytes = $resized;
                    [$w, $h] = [self::MAX_STORED_WIDTH, $nh];
                }
            }
        }

        $subdir = '/articles/' . date('Y') . '/' . date('m');
        $dir = UPLOAD_DIR . $subdir;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bytes) === false) {
            return null;
        }
        return ['path' => UPLOAD_URL . $subdir . '/' . $name, 'sha1' => $sha, 'width' => $w, 'height' => $h];
    }

    /**
     * Downloads the photos of a read page. Returns:
     *   featured  ['path', 'caption', 'alt'] or null — the cover photo (the lead photo, else the page's social image)
     *   blocks    the page's blocks with every photo replaced by its stored copy (photos that failed are left out)
     *   saved, failed   counts
     */
    public function importPhotos(array $page, int $max = self::MAX_IMAGES): array
    {
        $referer = $page['final_url'];
        $blocks = $page['blocks'];
        $bySha = [];   // sha1 -> stored info (so the same photo is stored once)
        $saved = 0;
        $failed = 0;

        $store = function (string $url) use (&$bySha, &$saved, &$failed, $referer, $max): ?array {
            if ($saved >= $max) {
                return null;
            }
            $r = $this->saveImage($url, $referer);
            if (!$r) {
                $failed++;
                return null;
            }
            if (isset($bySha[$r['sha1']])) { // same picture again: keep one file
                @unlink(dirname(UPLOAD_DIR) . '/' . $r['path']);
                return $bySha[$r['sha1']];
            }
            $saved++;
            return $bySha[$r['sha1']] = $r;
        };

        $out = [];
        $featured = null;
        $seenText = false;
        foreach ($blocks as $b) {
            if ($b['type'] !== 'img') {
                $seenText = $seenText || in_array($b['type'], ['p', 'ul', 'ol', 'quote'], true);
                $out[] = $b;
                continue;
            }
            $r = $store($b['url']);
            if (!$r) {
                continue;
            }
            // A photo that comes before any text is the story's lead photo: it becomes the cover, not a repeat in the body.
            if (!$seenText && !$featured) {
                $featured = ['path' => $r['path'], 'caption' => $b['caption'], 'alt' => $b['alt'], 'sha1' => $r['sha1']];
                continue;
            }
            $b['path'] = $r['path'];
            $b['sha1'] = $r['sha1'];
            $out[] = $b;
        }

        // No lead photo in the page? Use the page's social-sharing picture as the cover (once, not again in the body).
        if (!$featured && !empty($page['og_image'])) {
            $r = $store($page['og_image']);
            if ($r) {
                $featured = ['path' => $r['path'], 'caption' => null, 'alt' => $page['title'], 'sha1' => $r['sha1']];
                $out = array_values(array_filter($out, fn($b) => !($b['type'] === 'img' && ($b['sha1'] ?? '') === $r['sha1'])));
            }
        }
        // Still nothing, but the body has photos: the first one becomes the cover.
        if (!$featured) {
            foreach ($out as $i => $b) {
                if ($b['type'] === 'img') {
                    $featured = ['path' => $b['path'], 'caption' => $b['caption'], 'alt' => $b['alt'], 'sha1' => $b['sha1']];
                    unset($out[$i]);
                    break;
                }
            }
            $out = array_values($out);
        }

        return ['featured' => $featured, 'blocks' => $out, 'saved' => $saved, 'failed' => $failed];
    }

    // ── building the story ─────────────────────────────────────

    /** One photo as editor-friendly HTML: the picture in its own paragraph, the caption in italics right under it. */
    public static function photoHtml(array $b): string
    {
        $src = h(img_url($b['path']));
        $alt = h($b['alt'] ?? '');
        $html = '<p><img src="' . $src . '" alt="' . $alt . '"></p>';
        if (!empty($b['caption'])) {
            $html .= '<p><em>' . h($b['caption']) . '</em></p>';
        }
        return $html;
    }

    /** The page's blocks as HTML, in order. */
    public function toHtml(array $blocks): string
    {
        $out = '';
        foreach ($blocks as $b) {
            switch ($b['type']) {
                case 'p':
                    $out .= '<p>' . $b['html'] . '</p>';
                    break;
                case 'h2':
                case 'h3':
                    $out .= '<' . $b['type'] . '>' . h($b['text']) . '</' . $b['type'] . '>';
                    break;
                case 'ul':
                case 'ol':
                    $out .= '<' . $b['type'] . '><li>' . implode('</li><li>', $b['items']) . '</li></' . $b['type'] . '>';
                    break;
                case 'quote':
                    $out .= '<blockquote>' . $b['html'] . '</blockquote>';
                    break;
                case 'img':
                    if (!empty($b['path'])) {
                        $out .= self::photoHtml($b);
                    }
                    break;
            }
        }
        return $out;
    }

    /** Places photos through a body of HTML (the AI's rewrite), spread evenly after its paragraphs. */
    public static function interleavePhotos(string $html, array $photoBlocks): string
    {
        $photoBlocks = array_values($photoBlocks);
        $n = count($photoBlocks);
        if ($n === 0) {
            return $html;
        }
        preg_match_all('~</p>~i', $html, $m, PREG_OFFSET_CAPTURE);
        $ends = array_map(fn($x) => $x[1] + 4, $m[0]);
        $p = count($ends);
        if ($p < 2) {
            return $html . implode('', array_map([self::class, 'photoHtml'], $photoBlocks));
        }
        // photo i goes after paragraph floor((i+1) * p / (n+1)), counting from 1 (never before the first or after the last)
        $inserts = [];
        foreach ($photoBlocks as $i => $b) {
            $after = max(1, min($p - 1, (int) floor(($i + 1) * $p / ($n + 1)) ?: 1));
            $inserts[$after][] = self::photoHtml($b);
        }
        krsort($inserts);
        foreach ($inserts as $after => $list) {
            $at = $ends[$after - 1];
            $html = substr($html, 0, $at) . implode('', $list) . substr($html, $at);
        }
        return $html;
    }
}
