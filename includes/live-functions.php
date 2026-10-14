<?php
/**
 * Oroma TV — the live schedule: programmes that go on air by themselves, movie premieres, the off-air playlist,
 * and who is watching.
 *
 *   live_programs  A programme the admin schedules: title, stream link, start time (and optionally an end).
 *                  kind  show | movie | event          what it is (movies get a "Premiere" look)
 *                  mode  premiere | live               premiere = a recording everyone watches IN SYNC from the server clock
 *                                                      (join late and you land mid-programme, like TV);
 *                                                      live = a real-time stream (everyone is at the live edge anyway)
 *   offair_items   The off-air playlist: videos that play on a loop whenever nothing is live.
 *   live_viewers   Anonymous heartbeats from the app and the website — "how many people are watching right now".
 *
 * What is on air is decided in one place, resolve_live(), used by the website and the mobile app's API, in this order:
 *   1) a scheduled programme that is running   2) the "main channel" switch (admin → Streams)   3) nothing —
 * and then the screen is never left empty: the off-air playlist plays, and if that is empty, previous shows do.
 *
 * All times are the site's own time (Africa/Nairobi, set in config.php), stored as plain "Y-m-d H:i:s" text.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/previous-shows-functions.php';

/** A programme with no end time that nobody ended by hand is cut off after this many hours. */
const LIVE_MAX_HOURS = 12;
/** Bump when the tables below change; the migration runs once and remembers it in the settings table. */
const LIVE_SCHEMA_VERSION = '2';
/** A viewer counts as "watching now" if their device checked in within this many seconds (devices ping every 30 s). */
const VIEWER_WINDOW_SECONDS = 75;

// ── tables ─────────────────────────────────────────────────────

/**
 * Creates / upgrades the tables so deploying needs no manual SQL (database/live-schedule.sql has the same statements).
 * Never throws: a database user without the CREATE / ALTER right simply skips this, and the pages then say what is missing.
 */
function ensure_live_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (get_setting('live_schema_v', '0') === LIVE_SCHEMA_VERSION) {
            return; // already up to date — the normal case, one cheap query
        }
    } catch (Throwable $e) {
        return;
    }

    $m = db()->mysqli;
    $run = static function (string $sql) use ($m): void {
        try {
            $m->query($sql);
        } catch (Throwable $e) {
            error_log('[live-schedule] ' . $e->getMessage());
        }
    };
    $addColumn = static function (string $table, string $column, string $definition) use ($m, $run): void {
        try {
            $res = $m->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if ($res && $res->num_rows === 0) {
                $run("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        } catch (Throwable $e) {
            error_log('[live-schedule] ' . $e->getMessage());
        }
    };

    $run("CREATE TABLE IF NOT EXISTS `live_programs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(200) NOT NULL,
            `description` VARCHAR(500) DEFAULT NULL,
            `provider` ENUM('youtube','embed') NOT NULL,
            `stream_url` VARCHAR(500) NOT NULL,
            `video_id` VARCHAR(20) DEFAULT NULL,
            `starts_at` DATETIME NOT NULL,
            `ends_at` DATETIME DEFAULT NULL,
            `ended_at` DATETIME DEFAULT NULL,
            `created_by` INT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_live_programs_starts` (`starts_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $addColumn('live_programs', 'mode', "ENUM('premiere','live') NOT NULL DEFAULT 'premiere'");
    $addColumn('live_programs', 'kind', "ENUM('show','movie','event') NOT NULL DEFAULT 'show'");
    $addColumn('live_programs', 'poster_url', 'VARCHAR(500) DEFAULT NULL');
    $addColumn('live_programs', 'series_id', 'INT UNSIGNED DEFAULT NULL');
    $addColumn('live_programs', 'archived', 'TINYINT(1) NOT NULL DEFAULT 0');
    $addColumn('live_programs', 'peak_viewers', 'INT UNSIGNED NOT NULL DEFAULT 0');

    $run("CREATE TABLE IF NOT EXISTS `offair_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `video_id` VARCHAR(20) NOT NULL,
            `source_url` VARCHAR(500) NOT NULL,
            `thumbnail_url` VARCHAR(500) DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_offair_order` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $run("CREATE TABLE IF NOT EXISTS `live_viewers` (
            `viewer_id` VARCHAR(64) NOT NULL,
            `scope` VARCHAR(24) NOT NULL,
            `platform` ENUM('app','web') NOT NULL DEFAULT 'app',
            `first_seen` DATETIME NOT NULL,
            `last_seen` DATETIME NOT NULL,
            PRIMARY KEY (`viewer_id`, `scope`),
            KEY `idx_viewers_seen` (`last_seen`),
            KEY `idx_viewers_scope` (`scope`, `last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Previous Shows can now hold movies too.
    $addColumn('previous_shows', 'kind', "ENUM('show','movie') NOT NULL DEFAULT 'show'");

    try {
        set_setting('live_schema_v', LIVE_SCHEMA_VERSION);
    } catch (Throwable $e) {
        error_log('[live-schedule] ' . $e->getMessage());
    }
}

/** True when the live tables exist (used by the admin pages to show a helpful message instead of an error). */
function live_schema_ready(): bool
{
    ensure_live_schema();
    try {
        db()->mysqli->query('SELECT mode, kind, series_id, archived, peak_viewers FROM live_programs LIMIT 1');
        db()->mysqli->query('SELECT 1 FROM offair_items LIMIT 1');
        db()->mysqli->query('SELECT 1 FROM live_viewers LIMIT 1');
        db()->mysqli->query('SELECT kind FROM previous_shows LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// ── stream links ───────────────────────────────────────────────

/**
 * Understands what an admin pasted into a "stream link" box and returns how to play it, or null if it is not usable.
 *   • any YouTube link (youtu.be/…, youtube.com/watch?v=…, /live/…, /embed/…, /shorts/…) or a bare 11-character video id
 *   • a pasted <iframe …> embed code (its src is used)
 *   • any other https:// address, treated as an embeddable player page (for example a Dacast player)
 * Returns ['provider' => 'youtube'|'embed', 'stream_url' => string, 'video_id' => ?string].
 */
function parse_stream_link(string $input): ?array
{
    $in = trim($input);
    if ($in !== '' && stripos($in, '<iframe') !== false && preg_match('~src\s*=\s*["\']([^"\']+)["\']~i', $in, $m)) {
        $in = trim(html_entity_decode($m[1]));
    }
    if ($in === '' || mb_strlen($in) > 500) {
        return null;
    }

    $videoId = extract_youtube_id($in);
    if ($videoId !== '') {
        return ['provider' => 'youtube', 'stream_url' => get_youtube_watch_url($videoId), 'video_id' => $videoId];
    }

    if (preg_match('~^https://[^\s]+$~i', $in) && filter_var($in, FILTER_VALIDATE_URL)) {
        return ['provider' => 'embed', 'stream_url' => $in, 'video_id' => null];
    }
    return null;
}

/**
 * Looks at a YouTube video and says how it should be scheduled: 'live' for a real-time stream (running now, or scheduled by
 * YouTube to go live), 'premiere' for anything else (a recording, or a stream that has finished). Best effort — if YouTube
 * cannot be reached it answers 'premiere' and the admin can change it.
 */
function detect_youtube_mode(string $videoId): string
{
    $context = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => 'Mozilla/5.0 (compatible; OromaTV/1.0)', 'ignore_errors' => true]]);
    $html = @file_get_contents('https://www.youtube.com/watch?v=' . rawurlencode($videoId), false, $context);
    if (!is_string($html) || $html === '') {
        return 'premiere';
    }
    return (str_contains($html, '"isLiveNow":true') || str_contains($html, '"isUpcoming":true')) ? 'live' : 'premiere';
}

/** What a programme is, as words. */
function live_kind_label(string $kind): string
{
    return ['show' => 'Programme', 'movie' => 'Movie premiere', 'event' => 'Special event'][$kind] ?? 'Programme';
}

/**
 * The address a browser iframe should load for a programme. For a pre-recorded ("premiere") YouTube video, $offset is how many
 * seconds into it the viewer should start so that everybody is at the same moment.
 */
function live_program_embed_url(array $program, bool $autoplay = true, int $offset = 0): string
{
    if ($program['provider'] === 'youtube') {
        return 'https://www.youtube.com/embed/' . rawurlencode($program['video_id'])
            . '?rel=0&modestbranding=1&playsinline=1' . ($autoplay ? '&autoplay=1' : '') . ($offset > 0 ? '&start=' . $offset : '');
    }
    return $program['stream_url'];
}

/** The picture for a programme card: the admin's poster if there is one, else YouTube's thumbnail, else null. */
function live_program_thumbnail(array $program): ?string
{
    if (!empty($program['poster_url'])) {
        return $program['poster_url'];
    }
    return $program['provider'] === 'youtube' && $program['video_id'] ? get_youtube_thumbnail($program['video_id'], 'hqdefault') : null;
}

// ── programmes ─────────────────────────────────────────────────

/** The moment a programme stops being on air: the earliest of its manual end, its end time, and the 12-hour safety cut-off. */
function live_program_effective_end(array $p): string
{
    $ends = [date('Y-m-d H:i:s', strtotime($p['starts_at'] . ' +' . LIVE_MAX_HOURS . ' hours'))];
    foreach (['ends_at', 'ended_at'] as $key) {
        if (!empty($p[$key])) {
            $ends[] = $p[$key];
        }
    }
    sort($ends);
    return $ends[0];
}

/** 'upcoming' (not started yet), 'live' (on air right now) or 'ended'. */
function live_program_state(array $p, ?string $now = null): string
{
    $now = $now ?? date('Y-m-d H:i:s');
    if (!empty($p['ended_at']) && $p['ended_at'] <= $now) {
        return 'ended';
    }
    if ($p['starts_at'] > $now) {
        return 'upcoming';
    }
    return $now < live_program_effective_end($p) ? 'live' : 'ended';
}

/** How many seconds into a pre-recorded programme "now" is (0 for a real-time stream or one that has not started). */
function live_program_offset(array $p, ?string $now = null): int
{
    if (($p['mode'] ?? 'premiere') !== 'premiere') {
        return 0;
    }
    $now = $now ?? date('Y-m-d H:i:s');
    return max(0, strtotime($now) - strtotime($p['starts_at']));
}

/** The programme that is on air right now (the most recently started, if several overlap), or null. */
function get_live_program_now(?string $now = null): ?array
{
    $now = $now ?? date('Y-m-d H:i:s');
    $stmt = db()->prepare('SELECT * FROM live_programs WHERE starts_at <= ? ORDER BY starts_at DESC LIMIT 30');
    $stmt->execute([$now]);
    foreach ($stmt->fetchAll() as $p) {
        if (live_program_state($p, $now) === 'live') {
            return $p;
        }
    }
    return null;
}

/** The next programme that has not started yet, or null. */
function get_next_live_program(?string $now = null): ?array
{
    return get_upcoming_programs(24 * 366, 1, $now)[0] ?? null;
}

/** Programmes that have not started yet, soonest first (only those starting within $hours hours). */
function get_upcoming_programs(int $hours = 72, int $limit = 12, ?string $now = null): array
{
    $now = $now ?? date('Y-m-d H:i:s');
    $until = date('Y-m-d H:i:s', strtotime($now) + $hours * 3600);
    $stmt = db()->prepare('SELECT * FROM live_programs WHERE starts_at > ? AND starts_at <= ? AND ended_at IS NULL ORDER BY starts_at ASC LIMIT ' . (int) $limit);
    $stmt->execute([$now, $until]);
    return $stmt->fetchAll();
}

/** One programme by id, or null. */
function get_live_program(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM live_programs WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Everything the admin schedule page lists: ['live' => [...], 'upcoming' => [...], 'recent' => [...]]. */
function list_live_programs(): array
{
    $now = date('Y-m-d H:i:s');
    $rows = db()->query('SELECT * FROM live_programs ORDER BY starts_at DESC LIMIT 300')->fetchAll();
    $out = ['live' => [], 'upcoming' => [], 'recent' => []];
    foreach ($rows as $p) {
        $state = live_program_state($p, $now);
        $p['state'] = $state;
        if ($state === 'live') {
            $out['live'][] = $p;
        } elseif ($state === 'upcoming') {
            $out['upcoming'][] = $p;
        } elseif (count($out['recent']) < 15) {
            $out['recent'][] = $p;
        }
    }
    $out['upcoming'] = array_reverse($out['upcoming']); // soonest first
    return $out;
}

/** Programmes that overlap the time window [$start, $end) (excluding programme $exceptId and the rest of its series). */
function find_overlapping_programs(string $start, string $end, int $exceptId = 0, int $exceptSeries = 0): array
{
    $stmt = db()->prepare('SELECT * FROM live_programs WHERE starts_at < ? AND ended_at IS NULL AND id != ? AND (series_id IS NULL OR series_id != ?) ORDER BY starts_at ASC LIMIT 60');
    $stmt->execute([$end, $exceptId, $exceptSeries]);
    $hits = [];
    foreach ($stmt->fetchAll() as $p) {
        if (live_program_effective_end($p) > $start) {
            $hits[] = $p;
        }
    }
    return $hits;
}

/**
 * Creates the extra occurrences of a repeating programme. $row is the first one (already saved, with its id); $repeat is
 * 'daily', 'weekdays' (Monday to Friday) or 'weekly'. Each occurrence keeps the same time of day and length.
 * Returns how many occurrences exist in total (including the first).
 */
function create_programme_series(array $row, string $repeat): int
{
    $limits = ['daily' => 60, 'weekdays' => 40, 'weekly' => 26];
    if (!isset($limits[$repeat])) {
        return 1;
    }
    $seriesId = $row['series_id'] ?: $row['id'];
    db()->prepare('UPDATE live_programs SET series_id = ? WHERE id = ?')->execute([$seriesId, $row['id']]);

    $length = $row['ends_at'] ? strtotime($row['ends_at']) - strtotime($row['starts_at']) : null;
    $insert = db()->prepare(
        'INSERT INTO live_programs (title, description, provider, stream_url, video_id, starts_at, ends_at, created_by, created_at, mode, kind, poster_url, series_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $count = 1;
    $cursor = strtotime($row['starts_at']);
    $safety = 0;
    while ($count < $limits[$repeat] && $safety++ < 400) {
        $cursor = strtotime($repeat === 'weekly' ? '+1 week' : '+1 day', $cursor);
        if ($repeat === 'weekdays' && (int) date('N', $cursor) >= 6) {
            continue;
        }
        $starts = date('Y-m-d H:i:s', $cursor);
        $insert->execute([
            $row['title'], $row['description'], $row['provider'], $row['stream_url'], $row['video_id'], $starts,
            $length ? date('Y-m-d H:i:s', $cursor + $length) : null, $row['created_by'], date('Y-m-d H:i:s'),
            $row['mode'], $row['kind'], $row['poster_url'], $seriesId,
        ]);
        $count++;
    }
    return $count;
}

/**
 * The next $hours hours as a schedule: the programmes (blocks) and the gaps between them. A gap is time when nothing is
 * scheduled, so the off-air playlist would play. Gaps under 10 minutes are ignored. Open-ended programmes are treated as
 * lasting until the next programme starts.
 * Returns ['from', 'to', 'blocks' => [...programmes + planned_end...], 'gaps' => [['from','to','minutes'], ...]].
 */
function schedule_overview(int $hours = 48): array
{
    $from = date('Y-m-d H:i:s');
    $to = date('Y-m-d H:i:s', time() + $hours * 3600);

    $stmt = db()->prepare('SELECT * FROM live_programs WHERE ended_at IS NULL AND starts_at < ? ORDER BY starts_at ASC LIMIT 400');
    $stmt->execute([$to]);
    $blocks = [];
    $rows = $stmt->fetchAll();
    foreach ($rows as $i => $p) {
        $end = live_program_effective_end($p);
        if (!$p['ends_at']) { // open-ended: lasts until the next programme starts (or the 12-hour cap)
            $nextStart = $rows[$i + 1]['starts_at'] ?? null;
            if ($nextStart && $nextStart > $p['starts_at'] && $nextStart < $end) {
                $end = $nextStart;
            }
        }
        if ($end <= $from) {
            continue; // already over
        }
        $p['planned_end'] = $end;
        $p['state'] = live_program_state($p, $from);
        $blocks[] = $p;
    }

    $gaps = [];
    $cursor = $from;
    foreach ($blocks as $b) {
        if ($b['starts_at'] > $cursor) {
            $gaps[] = ['from' => $cursor, 'to' => min($b['starts_at'], $to)];
        }
        $cursor = max($cursor, $b['planned_end']);
        if ($cursor >= $to) {
            break;
        }
    }
    if ($cursor < $to) {
        $gaps[] = ['from' => $cursor, 'to' => $to];
    }
    $gaps = array_values(array_filter(array_map(static function ($g) {
        $g['minutes'] = (int) round((strtotime($g['to']) - strtotime($g['from'])) / 60);
        return $g;
    }, $gaps), static fn($g) => $g['minutes'] >= 10));

    return ['from' => $from, 'to' => $to, 'blocks' => $blocks, 'gaps' => $gaps];
}

/**
 * Programmes that have finished are added to Previous Shows (movies under "Movies"), so a premiere stays available on demand.
 * Runs at most every 5 minutes, and only for YouTube programmes. Switch it off under Automation.
 */
function archive_finished_programs(): void
{
    static $ran = false;
    if ($ran || !live_flag('live_auto_archive', true)) {
        return;
    }
    $ran = true;
    if ((int) get_setting('live_last_archive', '0') > time() - 300) {
        return;
    }
    set_setting('live_last_archive', (string) time());

    $now = date('Y-m-d H:i:s');
    $rows = db()->query("SELECT * FROM live_programs WHERE archived = 0 AND provider = 'youtube' AND video_id IS NOT NULL AND starts_at < '$now' ORDER BY starts_at ASC LIMIT 25")->fetchAll();
    foreach ($rows as $p) {
        if (live_program_state($p, $now) !== 'ended') {
            continue;
        }
        $exists = db()->prepare('SELECT id, kind FROM previous_shows WHERE video_id = ? LIMIT 1');
        $exists->execute([$p['video_id']]);
        if ($found = $exists->fetch()) {
            if ($p['kind'] === 'movie' && $found['kind'] !== 'movie') {
                db()->prepare("UPDATE previous_shows SET kind = 'movie' WHERE id = ?")->execute([$found['id']]);
            }
        } else {
            $max = (int) db()->query('SELECT COALESCE(MAX(display_order), 0) AS m FROM previous_shows')->fetch()['m'];
            db()->prepare(
                'INSERT INTO previous_shows (youtube_url, video_id, title, guest_name, description, thumbnail_url, views, upload_date, is_featured, is_active, display_order, kind)
                 VALUES (?,?,?,?,?,?,0,?,0,1,?,?)'
            )->execute([
                $p['stream_url'], $p['video_id'], $p['title'], null, $p['description'], live_program_thumbnail($p),
                date('Y-m-d', strtotime($p['starts_at'])), $max + 1, $p['kind'] === 'movie' ? 'movie' : 'show',
            ]);
        }
        db()->prepare('UPDATE live_programs SET archived = 1 WHERE id = ?')->execute([$p['id']]);
    }
}

// ── off-air playlist ───────────────────────────────────────────

/** The off-air playlist in play order. */
function get_offair_items(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM offair_items' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

/** Previous shows as playlist items — the safety net that keeps the screen from ever being empty. */
function get_fallback_shows(int $limit = 20): array
{
    $stmt = db()->query('SELECT id, title, video_id, thumbnail_url FROM previous_shows WHERE is_active = 1 AND video_id <> \'\' ORDER BY is_featured DESC, display_order ASC, created_at DESC LIMIT ' . (int) $limit);
    return $stmt->fetchAll();
}

// ── admin settings ─────────────────────────────────────────────

/** A yes/no switch from the admin (stored in the settings table as "1" / "0"). */
function live_flag(string $key, bool $default = true): bool
{
    return get_setting($key, $default ? '1' : '0') !== '0';
}

/**
 * The "main channel": the player shown when the manual switch is on (and by the website when nothing is scheduled).
 * The admin can paste its link on the Streams page; until then it is the player set in config.php.
 * Returns ['provider', 'video_id', 'stream_url', 'embed_url', 'watch_url', 'custom' => bool].
 */
function main_channel(bool $autoplay = false): array
{
    $custom = trim((string) get_setting('live_main_url', ''));
    $customParsed = $custom !== '' ? parse_stream_link($custom) : null;
    $parsed = $customParsed
        ?? parse_stream_link(OROMA_LIVE_EMBED_URL)
        ?? ['provider' => 'embed', 'stream_url' => OROMA_LIVE_EMBED_URL, 'video_id' => null];

    $parsed['custom'] = $customParsed !== null;
    $parsed['embed_url'] = live_program_embed_url($parsed, $autoplay);
    $parsed['watch_url'] = $parsed['provider'] === 'youtube' ? get_youtube_watch_url($parsed['video_id']) : null;
    return $parsed;
}

// ── what is on air ─────────────────────────────────────────────

/**
 * The one answer to "what is on air?". Returns:
 *   source          'program' (a scheduled programme is running) · 'main' (the manual main-channel switch is on) · 'none' (off air)
 *   program         the running programme row, when source = 'program'
 *   next            the next scheduled programme, or null
 *   playlist        what to play when off air — never empty if there is anything to play: the admin's off-air playlist,
 *                   else previous shows (see playlist_source)
 *   playlist_source 'playlist' | 'shows' | 'none'
 *   now             the "Y-m-d H:i:s" moment this was worked out for
 * Never throws: if the tables are missing it behaves as if nothing were scheduled.
 */
function resolve_live(): array
{
    static $cache = null; // the answer is worked out once per page load
    if ($cache !== null) {
        return $cache;
    }
    ensure_live_schema();
    $now = date('Y-m-d H:i:s');
    $program = $next = null;
    $playlist = [];
    $playlistSource = 'none';

    try {
        archive_finished_programs();
    } catch (Throwable $e) {
        error_log('[live-schedule] archive: ' . $e->getMessage());
    }

    // The admin can pause the schedule altogether (Streams → Live Control → Automation).
    if (live_flag('live_schedule_enabled', true)) {
        try {
            $program = get_live_program_now($now);
            $next = get_next_live_program($now);
        } catch (Throwable $e) {
            error_log('[live-schedule] ' . $e->getMessage());
        }
    }

    $manual = get_setting('stream_status', 'offline') === 'live';
    $source = $program ? 'program' : ($manual ? 'main' : 'none');

    if ($source === 'none' && live_flag('offair_enabled', true)) {
        try {
            $playlist = get_offair_items(true);
            $playlistSource = $playlist ? 'playlist' : 'none';
            if (!$playlist && live_flag('offair_use_shows', true)) {
                $shows = get_fallback_shows(20);
                $playlist = array_map(static fn($s) => ['id' => 's' . $s['id'], 'title' => $s['title'], 'video_id' => $s['video_id'], 'thumbnail_url' => $s['thumbnail_url']], $shows);
                $playlistSource = $playlist ? 'shows' : 'none';
            }
        } catch (Throwable $e) {
            error_log('[live-schedule] ' . $e->getMessage());
        }
    }

    return $cache = [
        'source'          => $source,
        'program'         => $program,
        'next'            => $next,
        'playlist'        => $playlist,
        'playlist_source' => $playlistSource,
        'shuffle'         => live_flag('offair_shuffle', false),
        'now'             => $now,
    ];
}

/** True when something is on air right now (a running programme or the main channel's switch). */
function live_is_on_air(): bool
{
    return resolve_live()['source'] !== 'none';
}

/** The key viewers are counted under for what is on air: 'p12' (programme 12), 'main', or 'offair'. */
function live_scope(array $resolved): string
{
    if ($resolved['source'] === 'program') {
        return 'p' . $resolved['program']['id'];
    }
    return $resolved['source'] === 'main' ? 'main' : 'offair';
}

// ── who is watching ────────────────────────────────────────────

/**
 * A device checks in ("heartbeat") every 30 seconds while it is watching. Anonymous: only a random id the device made up.
 * Returns how many devices are watching live right now.
 */
function record_viewer(string $viewerId, string $platform, string $scope): int
{
    $now = date('Y-m-d H:i:s');
    $platform = $platform === 'web' ? 'web' : 'app';
    db()->prepare(
        'INSERT INTO live_viewers (viewer_id, scope, platform, first_seen, last_seen) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen), platform = VALUES(platform)'
    )->execute([$viewerId, $scope, $platform, $now, $now]);

    $counts = live_viewer_counts();
    if (preg_match('/^p(\d+)$/', $scope, $m)) {
        db()->prepare('UPDATE live_programs SET peak_viewers = GREATEST(peak_viewers, ?) WHERE id = ?')->execute([$counts['now'], (int) $m[1]]);
    }
    if (random_int(1, 100) === 1) { // tidy up now and then: forget devices not seen for a week
        db()->prepare('DELETE FROM live_viewers WHERE last_seen < ?')->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]);
    }
    return $counts['now'];
}

/**
 * Devices watching right now, split by platform. "now" counts people on a live programme or the main channel;
 * "offair" counts people on the off-air playlist.
 */
function live_viewer_counts(): array
{
    $since = date('Y-m-d H:i:s', time() - VIEWER_WINDOW_SECONDS);
    $out = ['now' => 0, 'app' => 0, 'web' => 0, 'offair' => 0];
    try {
        $stmt = db()->prepare(
            "SELECT platform, (scope = 'offair') AS offair, COUNT(DISTINCT viewer_id) AS n FROM live_viewers WHERE last_seen >= ? GROUP BY platform, (scope = 'offair')"
        );
        $stmt->execute([$since]);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['offair']) {
                $out['offair'] += (int) $row['n'];
            } else {
                $out[$row['platform']] += (int) $row['n'];
                $out['now'] += (int) $row['n'];
            }
        }
    } catch (Throwable $e) {
        error_log('[live-schedule] viewers: ' . $e->getMessage());
    }
    return $out;
}

/** Different devices that watched one scope (e.g. 'p12') at any time. */
function live_unique_viewers(string $scope): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(DISTINCT viewer_id) AS n FROM live_viewers WHERE scope = ?');
        $stmt->execute([$scope]);
        return (int) $stmt->fetch()['n'];
    } catch (Throwable $e) {
        return 0;
    }
}
