<?php
/**
 * Oroma TV — "Listen to Q FM": finds the radio station's live stream and its information, fresh each time.
 *
 * The admin pastes ONE link (Admin → Streams & Live → Channels → "Q FM — listen live") and the server works out the rest:
 *   • a Radio Garden link        https://radio.garden/listen/<station>/<id>   → the stream and the station's name / place come
 *                                 from Radio Garden itself
 *   • the station's own player   https://<host>/public/<station>/embed         → an AzuraCast radio server, which also tells us
 *                                 the song that is playing and how many people are listening
 *   • a direct stream address    https://…/stream.mp3  (or .aac / .m3u8 …)    → used as it is
 * If nothing is pasted, it falls back to the first Radio channel already in the Channels list.
 *
 * Results are cached for 30 seconds (the song title changes, the rest rarely), and if the station's server cannot be reached
 * the last good answer is reused, so the Listen button keeps working through a short outage.
 */
require_once __DIR__ . '/functions.php';

const RADIO_CACHE_SECONDS = 30;

/** A small HTTP GET with a browser-like identity and a short timeout; returns the body or null. */
function radio_http_get(string $url, int $timeout = 6, array $headers = []): ?string
{
    $headers = array_merge(['Accept: application/json, text/plain, */*'], $headers);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OromaTV/1.0; +https://oromatv.com)',
            CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
    }
    $context = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'user_agent' => 'Mozilla/5.0 (compatible; OromaTV/1.0)', 'header' => implode("\r\n", $headers)]]);
    $body = @file_get_contents($url, false, $context);
    return $body === false ? null : $body;
}

function radio_http_json(string $url, int $timeout = 6): ?array
{
    $body = radio_http_get($url, $timeout);
    $json = $body !== null ? json_decode($body, true) : null;
    return is_array($json) ? $json : null;
}

/**
 * Works out what kind of link was pasted. Returns one of
 *   ['type' => 'radiogarden', 'id' => '…', 'listen_url' => '…']
 *   ['type' => 'azuracast', 'base' => 'https://host', 'short' => 'station']
 *   ['type' => 'direct', 'url' => '…']
 * or null if it is not a usable https link.
 */
function radio_parse_source(string $input): ?array
{
    $in = trim($input);
    if ($in !== '' && stripos($in, '<iframe') !== false && preg_match('~src\s*=\s*["\']([^"\']+)["\']~i', $in, $m)) {
        $in = trim(html_entity_decode($m[1]));
    }
    if (!preg_match('~^https://[^\s]+$~i', $in) || !filter_var($in, FILTER_VALIDATE_URL)) {
        return null;
    }
    $host = strtolower((string) parse_url($in, PHP_URL_HOST));
    $path = (string) parse_url($in, PHP_URL_PATH);

    if ($host === 'radio.garden' || str_ends_with($host, '.radio.garden')) {
        // …/listen/<name>/<id>   …/api/ara/content/listen/<id>/channel.mp3   …/api/ara/content/channel/<id>
        if (preg_match('~/(?:listen/[^/]+|api/ara/content/(?:listen|channel))/([A-Za-z0-9_-]{6,16})(?:/|$)~', $path, $m)
            || preg_match('~/listen/([A-Za-z0-9_-]{6,16})/?$~', $path, $m)) {
            return ['type' => 'radiogarden', 'id' => $m[1], 'listen_url' => 'https://radio.garden/listen/_/' . $m[1]];
        }
        return null;
    }
    if (preg_match('~^(.*)/public/([A-Za-z0-9_-]+)(?:/|$)~', $path, $m)) { // AzuraCast public page / embed
        return ['type' => 'azuracast', 'base' => 'https://' . $host . (parse_url($in, PHP_URL_PORT) ? ':' . parse_url($in, PHP_URL_PORT) : '') . $m[1], 'short' => $m[2]];
    }
    return ['type' => 'direct', 'url' => $in];
}

/** The link to use: the admin's pasted link, else the first Radio channel in the Channels list. */
function radio_source_link(): string
{
    $set = trim((string) get_setting('qfm_radio_url', ''));
    if ($set !== '') {
        return $set;
    }
    try {
        $row = db()->query("SELECT url_or_id FROM streams WHERE type = 'radio' AND is_active = 1 ORDER BY is_default DESC, sort_order ASC, id ASC LIMIT 1")->fetch();
        return $row ? (string) $row['url_or_id'] : '';
    } catch (Throwable $e) {
        return '';
    }
}

/** Name shown on the button; the admin can set it, otherwise the station tells us (or "Q FM"). */
function radio_display_name(): string
{
    return trim((string) get_setting('qfm_radio_name', ''));
}

/** Reads the song title out of an Icecast / Shoutcast stream ("ICY" metadata), if the stream carries it. Best effort. */
function radio_icy_title(string $streamUrl): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $metaInt = 0;
    $buffer = '';
    $ch = curl_init($streamUrl);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 7, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OromaTV/1.0)', CURLOPT_HTTPHEADER => ['Icy-MetaData: 1'],
        CURLOPT_HEADERFUNCTION => static function ($c, $line) use (&$metaInt) {
            if (stripos($line, 'icy-metaint:') === 0) {
                $metaInt = (int) trim(substr($line, 12));
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($c, $chunk) use (&$buffer, &$metaInt) {
            if ($metaInt <= 0) {
                return 0; // this stream carries no titles: stop at once
            }
            $buffer .= $chunk;
            return strlen($buffer) > $metaInt + 4200 ? 0 : strlen($chunk); // enough to hold the first metadata block
        },
    ]);
    curl_exec($ch);
    curl_close($ch);

    if ($metaInt <= 0 || strlen($buffer) <= $metaInt) {
        return null;
    }
    $length = ord($buffer[$metaInt]) * 16;
    $meta = $length > 0 ? substr($buffer, $metaInt + 1, $length) : '';
    if (preg_match("~StreamTitle='(.*?)';~s", $meta, $m)) {
        $title = trim(mb_convert_encoding($m[1], 'UTF-8', 'UTF-8, ISO-8859-1'));
        return $title !== '' ? $title : null;
    }
    return null;
}

/**
 * Everything the Listen button needs, fresh (cached 30 s): ['enabled', 'name', 'place', 'frequency', 'stream_url', 'listen_url',
 * 'source', 'now_playing' => ['title','artist','art']|null, 'listeners', 'checked_at', 'error'].
 * $force = true skips the cache (the admin's "Check now").
 */
function radio_resolve(bool $force = false): array
{
    $link = radio_source_link();
    $empty = ['enabled' => false, 'name' => 'Q FM', 'place' => null, 'frequency' => null, 'stream_url' => null, 'listen_url' => null, 'source' => null, 'now_playing' => null, 'listeners' => null, 'checked_at' => date('c'), 'error' => null];
    $source = $link !== '' ? radio_parse_source($link) : null;
    if (!$source) {
        $empty['error'] = $link === '' ? 'No radio link has been set yet.' : 'That link could not be used.';
        return $empty;
    }

    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oroma_radio_' . md5($link . '|' . radio_display_name()) . '.json';
    $cached = is_file($cacheFile) ? json_decode((string) @file_get_contents($cacheFile), true) : null;
    if (!$force && is_array($cached) && ($cached['_at'] ?? 0) > time() - RADIO_CACHE_SECONDS) {
        unset($cached['_at']);
        return $cached;
    }

    $out = $empty;
    $out['enabled'] = true;
    $out['source'] = $source['type'];
    $name = radio_display_name();

    if ($source['type'] === 'radiogarden') {
        $out['stream_url'] = 'https://radio.garden/api/ara/content/listen/' . $source['id'] . '/channel.mp3';
        $out['listen_url'] = $source['listen_url'];
        $info = radio_http_json('https://radio.garden/api/ara/content/channel/' . $source['id']);
        $data = $info['data'] ?? [];
        if ($data) {
            $out['name'] = $name !== '' ? $name : ($data['title'] ?? 'Q FM');
            $out['place'] = trim(($data['place']['title'] ?? '') . (isset($data['country']['title']) ? ', ' . $data['country']['title'] : ''), ', ') ?: null;
        } else {
            $out['name'] = $name !== '' ? $name : 'Q FM';
            $out['error'] = 'Radio Garden did not answer for station details; the stream still plays.';
        }
    } elseif ($source['type'] === 'azuracast') {
        $api = radio_http_json($source['base'] . '/api/nowplaying/' . rawurlencode($source['short']));
        $station = $api['station'] ?? [];
        $mount = $station['mounts'][0]['url'] ?? ($station['listen_url'] ?? null);
        $out['stream_url'] = $mount ?: null;
        $out['listen_url'] = $station['public_player_url'] ?? ($source['base'] . '/public/' . $source['short']);
        $out['name'] = $name !== '' ? $name : ($station['name'] ?? 'Q FM');
        $song = $api['now_playing']['song'] ?? null;
        if ($song && (($song['title'] ?? '') !== '' || ($song['artist'] ?? '') !== '')) {
            $out['now_playing'] = ['title' => $song['title'] ?? null, 'artist' => $song['artist'] ?? null, 'art' => $song['art'] ?? null];
        }
        $out['listeners'] = isset($api['listeners']['current']) ? (int) $api['listeners']['current'] : null;
        if (!$out['stream_url']) {
            // The details did not come back; every AzuraCast server publishes its default stream at /listen/<station>/radio.mp3.
            $out['stream_url'] = $source['base'] . '/listen/' . rawurlencode($source['short']) . '/radio.mp3';
            $out['error'] = 'The station\'s details did not load, so the standard stream address is being used.';
        }
    } else {
        $out['stream_url'] = $source['url'];
        $out['listen_url'] = $source['url'];
        $out['name'] = $name !== '' ? $name : 'Q FM';
    }

    // Song title straight from the stream, for sources that did not already tell us (Radio Garden, direct streams).
    if (!$out['now_playing'] && $out['stream_url'] && $source['type'] !== 'azuracast') {
        $title = radio_icy_title($out['stream_url']);
        if ($title) {
            $parts = explode(' - ', $title, 2);
            $out['now_playing'] = count($parts) === 2 ? ['artist' => $parts[0], 'title' => $parts[1], 'art' => null] : ['artist' => null, 'title' => $title, 'art' => null];
        }
    }

    if (preg_match('/\b(\d{2,3}\.\d)\b/', $out['name'], $m)) {
        $out['frequency'] = $m[1] . ' FM';
    }
    $out['checked_at'] = date('c');

    // Details did not load (the station's server is unreachable)? Reuse the last good answer, so the button survives a short outage.
    if ($out['error'] && is_array($cached) && empty($cached['error']) && !empty($cached['stream_url'])) {
        unset($cached['_at']);
        $cached['error'] = 'Showing the last known details; the station could not be reached just now.';
        return $cached;
    }

    @file_put_contents($cacheFile, json_encode($out + ['_at' => time()]), LOCK_EX);
    return $out;
}

/** The last answer radio_resolve() got (any age), without touching the network — or null if it has never been asked. */
function radio_cached(): ?array
{
    $link = radio_source_link();
    if ($link === '') {
        return null;
    }
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oroma_radio_' . md5($link . '|' . radio_display_name()) . '.json';
    $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (!is_array($data)) {
        return null;
    }
    $data['_age'] = time() - (int) ($data['_at'] ?? 0);
    unset($data['_at']);
    return $data;
}
