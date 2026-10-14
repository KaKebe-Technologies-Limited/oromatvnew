<?php
/**
 * Oroma TV mobile API — shared helpers: JSON output, request helpers, absolute URLs,
 * rate limiting and the serializers that turn database rows into the shapes the app expects.
 *
 * Response conventions (same as the FaithFellows API, so the app's helpers work the same way):
 *   list   -> { "data": [...], "meta": { "current_page", "last_page", "per_page", "total" } }
 *   single -> { "data": {...} }
 *   error  -> { "message": "...", "errors": { "field": ["..."] } }   (HTTP 4xx / 5xx)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/previous-shows-functions.php';
require_once __DIR__ . '/../includes/live-functions.php';
require_once __DIR__ . '/../includes/radio-functions.php';

// The site's config shows PHP errors on a local machine; that would corrupt the JSON, so keep them in the log.
ini_set('display_errors', '0');
// This API never needs a login session — release the session lock the shared config opened.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// ── output ─────────────────────────────────────────────────────

function api_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    // The native app does not need CORS; the Expo web preview in a desktop browser does.
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
}

function json_out($payload, int $status = 200)
{
    api_headers();
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function json_error(string $message, int $status = 400, array $errors = [])
{
    $body = ['message' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    json_out($body, $status);
}

/** A paged list in the shared { data, meta } shape. */
function json_paged(array $items, int $page, int $perPage, int $total)
{
    json_out([
        'data' => $items,
        'meta' => [
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / max(1, $perPage))),
            'per_page'     => $perPage,
            'total'        => $total,
        ],
    ]);
}

// ── request ────────────────────────────────────────────────────

/** JSON body (or form body) of a POST as an array. */
function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function query_int(string $key, int $default, int $min, int $max): int
{
    return max($min, min($max, (int) ($_GET[$key] ?? $default)));
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Allow at most $max hits per $windowSeconds for this visitor and $bucket (e.g. "comment").
 * Counters live in small files in the system temp folder; if that is not writable the limit is skipped.
 */
function rate_limit(string $bucket, int $max, int $windowSeconds): void
{
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oroma_rl_' . md5($bucket . '|' . client_ip()) . '.json';
    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        $hits = is_array($decoded) ? $decoded : [];
    }
    $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - $windowSeconds));
    if (count($hits) >= $max) {
        json_error('Too many attempts. Please wait a while and try again.', 429);
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
}

// ── absolute URLs ──────────────────────────────────────────────

/**
 * Where this API is being served from, e.g. http://192.168.0.103/oromatvnew while testing on a phone,
 * or https://oromatv.com on the live site. Only a local machine trusts the request's own Host header;
 * the live site always answers with its fixed public address.
 */
function api_origin(): string
{
    global $is_local;
    if (empty($is_local)) {
        return rtrim(OROMA_PUBLIC_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH;
}

/** Turn a site-relative path ("/oromatvnew/uploads/x.jpg" or "uploads/x.jpg") into a full URL the phone can load. */
function absolute_url(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $origin = api_origin();
    // BASE_PATH is already part of $origin (and of paths the site helpers return) — don't double it.
    if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH . '/')) {
        $path = substr($path, strlen(BASE_PATH));
    }
    return $origin . '/' . ltrim($path, '/');
}

/** The public web address of an article, for sharing (always the real website, never a LAN address). */
function article_web_url(string $slug): string
{
    return rtrim(OROMA_PUBLIC_URL, '/') . '/article.php?slug=' . rawurlencode($slug);
}

// ── serializers ────────────────────────────────────────────────

function iso_date(?string $mysql): ?string
{
    if (!$mysql) {
        return null;
    }
    $ts = strtotime($mysql);
    return $ts ? date('c', $ts) : null; // keeps the site's Africa/Nairobi offset, so phones show the right moment
}

/** Card / list-row shape for an article. $a needs the columns from the get_*_articles() helpers (category_*, author_name). */
function serialize_article(array $a): array
{
    $thumb = article_thumb_src($a['featured_image'] ?? null, (int) $a['id'], 800, 450);
    return [
        'id'            => (int) $a['id'],
        'slug'          => $a['slug'],
        'title'         => $a['title'],
        'excerpt'       => $a['excerpt'] ?: make_excerpt($a['content'] ?? '', 140),
        'image'         => absolute_url($thumb),
        'category'      => !empty($a['category_name']) ? ['name' => $a['category_name'], 'slug' => $a['category_slug'] ?? null, 'color' => category_color($a['category_slug'] ?? null)] : null,
        'author'        => $a['author_name'] ?? 'Oroma TV',
        'published_at'  => iso_date($a['created_at'] ?? null),
        'reading_time'  => reading_time($a['content'] ?? ''),
        'views'         => (int) ($a['views'] ?? 0),
        'web_url'       => article_web_url($a['slug']),
    ];
}

function serialize_show(array $s): array
{
    return [
        'id'         => (int) $s['id'],
        'video_id'   => $s['video_id'],
        'title'      => $s['title'],
        'guest'      => $s['guest_name'] ?: null,
        'description'=> $s['description'] ?: null,
        'thumbnail'  => $s['thumbnail_url'] ?: get_youtube_thumbnail($s['video_id']),
        'featured'   => (bool) $s['is_featured'],
        'views'      => (int) $s['views'],
        'date'       => $s['upload_date'] ?: iso_date($s['created_at'] ?? null),
        'watch_url'  => get_youtube_watch_url($s['video_id']),
        'embed_url'  => get_youtube_embed_url($s['video_id'], false),
        'kind'       => $s['kind'] ?? 'show',   // show | movie
        'share_url'  => rtrim(OROMA_PUBLIC_URL, '/') . '/show.php?id=' . (int) $s['id'],
    ];
}

function serialize_category(array $c): array
{
    return [
        'id'    => (int) $c['id'],
        'name'  => $c['name'],
        'slug'  => $c['slug'],
        'color' => category_color($c['slug']),
        'icon'  => $c['icon'] ?: 'fa-folder', // Font Awesome name, as stored for the website; the app maps it to its own icons
        'count' => isset($c['article_count']) ? (int) $c['article_count'] : null,
    ];
}

/** A channel from the admin "Streams" page (YouTube or radio). Never exposes anything beyond what the website would show. */
function serialize_stream(array $s): array
{
    $source = trim($s['url_or_id']);
    $isRadio = $s['type'] === 'radio';
    // A radio source is either a web player page to embed, or a direct audio stream the app can play natively.
    $direct = $isRadio && preg_match('~\.(mp3|aac|ogg|opus|m3u8)(\?|$)~i', $source);
    $videoId = $isRadio ? '' : youtube_video_id($source);

    return [
        'id'        => (int) $s['id'],
        'type'      => $s['type'],
        'name'      => $s['name'],
        'icon'      => $s['icon'] ?: null,
        'kind'      => $isRadio ? ($direct ? 'audio' : 'embed') : 'embed',
        'url'       => $isRadio ? $source : youtube_embed_url($source, false),
        'video_id'  => $isRadio ? null : $videoId,
        'watch_url' => $isRadio ? null : get_youtube_watch_url($videoId),
        'thumbnail' => $isRadio ? null : get_youtube_thumbnail($videoId),
    ];
}

/** A scheduled programme as the app shows it in the schedule guide and on the premiere screen. */
function serialize_programme(array $p): array
{
    return [
        'id'          => (int) $p['id'],
        'title'       => $p['title'],
        'description' => $p['description'] ?: null,
        'kind'        => $p['kind'] ?? 'show',          // show | movie | event
        'kind_label'  => live_kind_label($p['kind'] ?? 'show'),
        'mode'        => $p['mode'] ?? 'premiere',      // premiere (everyone in sync) | live (real-time stream)
        'provider'    => $p['provider'],
        'thumbnail'   => absolute_url(live_program_thumbnail($p)),
        'starts_at'   => iso_date($p['starts_at']),
        'ends_at'     => iso_date($p['ends_at']),
        'share_url'   => rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php?p=' . (int) $p['id'],
    ];
}

/**
 * What is on air, in the shape the app uses (see includes/live-functions.php → resolve_live()):
 *   status    'live' when something is on air (a scheduled programme, or the main channel's manual switch), else 'offline'
 *   stage     the thing on air, or null when off air:
 *               { id, kind: 'program'|'main', programme_kind: show|movie|event, mode: 'premiere'|'live', title, description,
 *                 provider: 'youtube'|'embed', video_id, embed_url, watch_url, thumbnail, starts_at, ends_at, effective_end,
 *                 offset_seconds }
 *             For mode 'premiere' (a recording) every viewer must be at the same moment: offset_seconds is how far into the
 *             video "now" is, worked out on the server — so someone who opens the app late joins mid-programme, like TV.
 *   next      the next scheduled programme (or null);  schedule: the programmes coming in the next 72 hours
 *   playlist  what plays when off air — the off-air playlist, else previous shows (playlist_source says which); never empty
 *             while there is anything to play
 *   watching  how many devices are watching live right now (the app also pings to be counted: POST /live/ping)
 *   radio     whether the "Listen to Q FM" button should show
 *   server_time  the server's clock, so the app can count down and stay in sync even if the phone's clock is off
 */
function api_live_snapshot(): array
{
    $r = resolve_live();
    $stage = null;

    if ($r['source'] === 'program') {
        $p = $r['program'];
        $stage = [
            'kind'           => 'program',
            'id'             => 'p' . $p['id'],
            'programme_id'   => (int) $p['id'],
            'programme_kind' => $p['kind'] ?? 'show',
            'mode'           => $p['mode'] ?? 'premiere',
            'title'          => $p['title'],
            'description'    => $p['description'] ?: null,
            'provider'       => $p['provider'],
            'video_id'       => $p['video_id'],
            'embed_url'      => live_program_embed_url($p, true, live_program_offset($p, $r['now'])),
            'watch_url'      => $p['provider'] === 'youtube' ? get_youtube_watch_url($p['video_id']) : null,
            'thumbnail'      => absolute_url(live_program_thumbnail($p)),
            'starts_at'      => iso_date($p['starts_at']),
            'ends_at'        => iso_date($p['ends_at']),
            'effective_end'  => iso_date(live_program_effective_end($p)),
            'offset_seconds' => live_program_offset($p, $r['now']),
            'share_url'      => rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php?p=' . (int) $p['id'],
        ];
    } elseif ($r['source'] === 'main') {
        $m = main_channel(true);
        $stage = [
            'kind'           => 'main',
            'id'             => 'main',
            'programme_id'   => null,
            'programme_kind' => 'show',
            'mode'           => 'live',
            'title'          => SITE_NAME . ' Live',
            'description'    => null,
            'provider'       => $m['provider'],
            'video_id'       => $m['video_id'],
            'embed_url'      => $m['embed_url'],
            'watch_url'      => $m['watch_url'],
            'thumbnail'      => $m['video_id'] ? get_youtube_thumbnail($m['video_id']) : null,
            'starts_at'      => null,
            'ends_at'        => null,
            'effective_end'  => null,
            'offset_seconds' => 0,
            'share_url'      => rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php',
        ];
    }

    $schedule = [];
    try {
        $schedule = array_map('serialize_programme', get_upcoming_programs(72, 12, $r['now']));
    } catch (Throwable $e) {
        error_log('[api] schedule: ' . $e->getMessage());
    }

    $counts = live_viewer_counts();
    $radioReady = false;
    try {
        require_once __DIR__ . '/../includes/radio-functions.php';
        $radioReady = radio_source_link() !== '' && radio_parse_source(radio_source_link()) !== null;
    } catch (Throwable $e) {
        error_log('[api] radio: ' . $e->getMessage());
    }

    return [
        'status'          => $stage ? 'live' : 'offline',
        'stage'           => $stage,
        'next'            => $schedule[0] ?? ($r['next'] ? serialize_programme($r['next']) : null),
        'schedule'        => $schedule,
        'playlist'        => array_map(static fn($i) => [
            'id'        => (string) $i['id'],
            'title'     => $i['title'],
            'video_id'  => $i['video_id'],
            'thumbnail' => $i['thumbnail_url'] ?: get_youtube_thumbnail($i['video_id']),
        ], $r['playlist']),
        'playlist_source' => $r['playlist_source'],
        'shuffle'         => $r['shuffle'],
        'watching'        => $counts['now'],
        'radio'           => ['enabled' => $radioReady, 'name' => radio_display_name() ?: 'Q FM'],
        'server_time'     => date('c'),
    ];
}

/** Article body prepared for the app: site-relative images made absolute so they load on a phone. */
function article_body_html(string $html): string
{
    $html = normalize_content_image_urls($html);
    return preg_replace_callback(
        '/(<img[^>]*\ssrc=")([^"]+)(")/i',
        static fn($m) => $m[1] . absolute_url(html_entity_decode($m[2])) . $m[3],
        $html
    );
}
