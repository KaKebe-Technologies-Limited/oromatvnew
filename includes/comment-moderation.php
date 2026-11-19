<?php
/**
 * Comment moderation: the rules the App Store (guideline 1.2) and Google Play (User Generated Content policy) require of an app
 * with public comments. Used by the API (api/routes.php), the website (comment-submit.php, article.php) and the admin
 * (admin/comments.php, dashboard, nav badge).
 *
 *   - Filter:  a new comment that trips the filter (includes/comment-filter.php) or comes from a banned commenter is HELD
 *              ('pending') for a moderator instead of being published; nothing is rejected or deleted.
 *   - Report:  anyone can report a published comment (reason + a one-way hash of the device); when 3 different networks have
 *              open reports it is hidden ('pending') until a moderator looks; the admin sees every open report and is e-mailed
 *              (throttled) when one arrives. Terms and privacy say we aim to review reports within 24 hours.
 *   - Ban:     a moderator can ban a commenter by name, e-mail and device: their future comments are held for review. Name and
 *              e-mail bans expire after 90 days, device bans after 30, unless an admin makes them permanent.
 * No raw IP address or user-agent is ever stored: only HMAC hashes (see ugc_salt() for the secret key and COMMENT_HASH_PEPPER).
 * The schema creates itself on first use (ensure_comment_schema), like includes/live-functions.php; database/comment-moderation.sql
 * holds the same statements for a database user that cannot create tables.
 *
 * comments.status stays ('pending','approved','spam'): 'pending' = held or auto-hidden, 'spam' = hidden by a moderator.
 * comments.hold_reason says why: filter:<tag> | banned | reports | reviewed (a moderator approved it, so reports cannot re-hide it).
 *
 * Who is "the same visitor": ugc_client_ip() gives an IPv4 address as it is and an IPv6 address as its /64 (a phone or a home
 * gets a whole /64, so ::1 and ::2 inside it are one person). It uses REMOTE_ADDR only and never trusts a forwarded header,
 * because anyone can send one. If the host's CDN hides the real address, admin/comments.php (Rules tab) shows what to check.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/comment-filter.php';

const COMMENT_SCHEMA_VERSION    = '1';
const COMMENT_REPORT_THRESHOLD  = 3;   // different networks with open reports before a comment is hidden automatically
const COMMENT_REPORT_DAILY_CAP  = 5;   // a network that reported more different comments than this in 24 h stops counting toward auto-hiding
const COMMENT_BAN_DAYS          = 90;  // name and e-mail bans: a namesake must not be held for ever (an admin can make a ban permanent)
const COMMENT_DEVICE_BAN_DAYS   = 30;  // mobile networks share addresses, so a device ban expires sooner
const COMMENT_NOTIFY_FIRST_PER_HOUR  = 4; // admin e-mails for the first report of a comment, per hour
const COMMENT_NOTIFY_HIDDEN_PER_HOUR = 6; // admin e-mails for a comment that got hidden by reports, per hour
const COMMENT_REPORT_REASONS    = ['spam', 'abusive', 'misleading', 'other'];
const COMMENT_HELD_MESSAGE      = 'Thanks! Your comment needs a quick check by our team because it may break our community rules. It will appear once it is approved.';

// Cyrillic and Greek letters that pass for Latin ones, so "Оkello" (Cyrillic O) is the same name as "Okello".
const UGC_NAME_LOOKALIKES = [
    "\u{0430}" => 'a', "\u{0432}" => 'b', "\u{0435}" => 'e', "\u{043A}" => 'k', "\u{043C}" => 'm', "\u{043D}" => 'h', "\u{043E}" => 'o',
    "\u{0440}" => 'p', "\u{0441}" => 'c', "\u{0442}" => 't', "\u{0443}" => 'y', "\u{0445}" => 'x', "\u{0456}" => 'i', "\u{0458}" => 'j',
    "\u{0455}" => 's', "\u{0501}" => 'd', "\u{04BB}" => 'h', "\u{051B}" => 'q', "\u{051D}" => 'w',
    "\u{03B1}" => 'a', "\u{03B2}" => 'b', "\u{03B5}" => 'e', "\u{03B7}" => 'n', "\u{03B9}" => 'i', "\u{03BA}" => 'k', "\u{03BD}" => 'v',
    "\u{03BF}" => 'o', "\u{03C1}" => 'p', "\u{03C4}" => 't', "\u{03C5}" => 'u', "\u{03C7}" => 'x',
];

// ── schema ─────────────────────────────────────────────────────

/**
 * True when the columns and tables moderation needs exist. $recheck = true looks again after a change.
 * The version flag in settings is only ever written after the probes below passed, so when it is there they are skipped:
 * a normal request costs one settings query instead of four.
 */
function comment_schema_ready(bool $recheck = false): bool
{
    static $ready = null;
    if ($ready !== null && !$recheck) {
        return $ready;
    }
    try {
        if (!$recheck && get_setting('comment_schema_v', '0') === COMMENT_SCHEMA_VERSION) {
            return $ready = true;
        }
        db()->mysqli->query('SELECT device_hash, hold_reason FROM comments LIMIT 1')->free();
        db()->mysqli->query('SELECT 1 FROM comment_reports LIMIT 1')->free();
        db()->mysqli->query('SELECT 1 FROM comment_bans LIMIT 1')->free();
        return $ready = true;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

/**
 * Creates the moderation columns and tables when missing. Never throws: a missing CREATE/ALTER right just leaves it "not ready"
 * and everything falls back to plain comments. After a failed attempt it does not try again for an hour (a marker file in the
 * temp folder, so no database write is needed) instead of repeating the DDL and the error_log lines on every request.
 * $verify = true (the admin Comments page) looks at the real tables even when the version flag is set, and retries at once.
 */
function ensure_comment_schema(bool $verify = false): void
{
    static $done = false;
    if ($done && !$verify) {
        return;
    }
    $done = true;

    try {
        if ($verify ? comment_schema_ready(true) : comment_schema_ready()) {
            return; // up to date: the normal case
        }
    } catch (Throwable $e) {
        return;
    }
    $failedMarker = ugc_temp_file('schema-failed');
    if (!$verify && is_file($failedMarker) && filemtime($failedMarker) > time() - 3600) {
        return; // tried and failed less than an hour ago
    }

    $m = db()->mysqli;
    $run = static function (string $sql) use ($m): void {
        try {
            $m->query($sql);
        } catch (Throwable $e) {
            error_log('[comment-moderation] ' . $e->getMessage());
        }
    };
    $addColumn = static function (string $column, string $definition) use ($m, $run): void {
        try {
            $res = $m->query("SHOW COLUMNS FROM `comments` LIKE '$column'");
            if ($res && $res->num_rows === 0) {
                $run("ALTER TABLE `comments` ADD COLUMN `$column` $definition");
            }
        } catch (Throwable $e) {
            error_log('[comment-moderation] ' . $e->getMessage());
        }
    };

    $addColumn('device_hash', 'CHAR(32) DEFAULT NULL');
    $addColumn('hold_reason', 'VARCHAR(24) DEFAULT NULL');

    $reportsTable = static fn(string $fk) => "CREATE TABLE IF NOT EXISTS `comment_reports` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `comment_id` INT NOT NULL,
            `reporter_hash` CHAR(32) NOT NULL,
            `ip_hash` CHAR(32) NOT NULL,
            `reason` ENUM('spam','abusive','misleading','other') NOT NULL,
            `platform` ENUM('app','web') NOT NULL DEFAULT 'app',
            `created_at` DATETIME NOT NULL,
            `resolved_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_report_once` (`comment_id`, `reporter_hash`),
            KEY `idx_report_open` (`resolved_at`, `comment_id`)$fk
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $run($reportsTable(",\n            CONSTRAINT `fk_report_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE"));
    if (!comment_table_exists('comment_reports')) {
        $run($reportsTable('')); // the foreign key could not be made (older table type): reports are then deleted by the code instead
    }

    $run("CREATE TABLE IF NOT EXISTS `comment_bans` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `kind` ENUM('name','email','device') NOT NULL,
            `value` VARCHAR(150) NOT NULL,
            `note` VARCHAR(200) DEFAULT NULL,
            `created_by` INT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `expires_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_ban` (`kind`, `value`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Only remember "done" when it really is; otherwise remember the failure for an hour and the admin sees a notice.
    if (comment_schema_ready(true)) {
        @unlink($failedMarker);
        try {
            set_setting('comment_schema_v', COMMENT_SCHEMA_VERSION);
        } catch (Throwable $e) {
            error_log('[comment-moderation] ' . $e->getMessage());
        }
    } else {
        @file_put_contents($failedMarker, (string) time());
    }
}

function comment_table_exists(string $table): bool
{
    try {
        $res = db()->mysqli->query("SHOW TABLES LIKE '" . db()->mysqli->real_escape_string($table) . "'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// ── small shared state in the temp folder ──────────────────────

/**
 * Path of a small counter file in the system temp folder (rate limits, mail throttle, schema failure marker). The name is a
 * keyed hash, so it carries no readable address. Files are named oroma_rl_*.json; deleting them just resets the counters.
 */
function ugc_temp_file(string $name): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oroma_rl_' . hash_hmac('md5', $name, ugc_pepper() ?: 'oroma-rl') . '.json';
}

/**
 * Counts one hit of this visitor in $bucket (e.g. "comment") and says whether it may go ahead: returns 0 when allowed, else
 * the seconds until the oldest hit in the window runs out. The counter is locked while it is updated, so parallel requests
 * cannot slip past the limit. If the temp folder is not writable the limit is skipped. Shared by the API and the website.
 */
function ugc_rate_hit(string $bucket, int $max, int $windowSeconds): int
{
    $fh = @fopen(ugc_temp_file($bucket . '|' . ugc_client_ip()), 'c+');
    if (!$fh) {
        return 0;
    }
    try {
        flock($fh, LOCK_EX);
        $decoded = json_decode((string) stream_get_contents($fh), true);
        $now = time();
        $hits = array_values(array_filter(is_array($decoded) ? $decoded : [], static fn($t) => is_int($t) && $t > $now - $windowSeconds));
        if (count($hits) >= $max) {
            return max(1, $hits[0] + $windowSeconds - $now);
        }
        $hits[] = $now;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($hits));
        return 0;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

// ── who is who (hashes only) ───────────────────────────────────

/** An address as "one visitor": IPv4 as it is, IPv4-in-IPv6 as the IPv4, any other IPv6 as its /64 prefix ("2001:db8:1:2::/64"). */
function ugc_normalize_ip(string $ip): string
{
    $bin = filter_var($ip, FILTER_VALIDATE_IP) !== false ? @inet_pton($ip) : false;
    if ($bin === false) {
        return '0.0.0.0';
    }
    if (strlen($bin) === 16 && str_starts_with($bin, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
        $bin = substr($bin, 12); // ::ffff:a.b.c.d
    }
    if (strlen($bin) === 4) {
        return (string) inet_ntop($bin);
    }
    return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
}

/**
 * The visitor's address, normalised (see ugc_normalize_ip). REMOTE_ADDR only: a forwarded header can be forged by anyone, so it
 * is never trusted. A local test machine may pretend to be several people with the X-Test-IP header; the live site never does.
 */
function ugc_client_ip(): string
{
    global $is_local;
    if (!empty($is_local) && !empty($_SERVER['HTTP_X_TEST_IP']) && filter_var($_SERVER['HTTP_X_TEST_IP'], FILTER_VALIDATE_IP)) {
        return ugc_normalize_ip((string) $_SERVER['HTTP_X_TEST_IP']);
    }
    return ugc_normalize_ip((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

/**
 * Optional server-only secret ("pepper") for the hashes. To use one, put
 *     define('COMMENT_HASH_PEPPER', '<at least 16 random characters>');
 * in an untracked config.local.php next to config.php (it is in .gitignore), or set the COMMENT_HASH_PEPPER environment variable.
 * When present it is the hash key, so a database dump or SQL injection alone cannot be used to test guesses against the stored
 * hashes; without it the key is the salt kept in the settings table. Set it before release: changing it later makes existing
 * device bans and report de-duplication stop matching.
 */
function ugc_pepper(): string
{
    static $pepper = null;
    if ($pepper !== null) {
        return $pepper;
    }
    $local = __DIR__ . '/../config.local.php';
    if (!defined('COMMENT_HASH_PEPPER') && is_file($local)) {
        try {
            require_once $local;
        } catch (Throwable $e) {
            error_log('[comment-moderation] config.local.php: ' . $e->getMessage());
        }
    }
    $value = defined('COMMENT_HASH_PEPPER') ? (string) COMMENT_HASH_PEPPER : (string) getenv('COMMENT_HASH_PEPPER');
    if ($value !== '' && strlen($value) < 16) {
        error_log('[comment-moderation] COMMENT_HASH_PEPPER is shorter than 16 characters and was ignored.');
        $value = '';
    }
    return $pepper = $value;
}

/** The secret that makes the hashes one-way: the pepper if there is one, else a salt made once and kept in the settings table. */
function ugc_salt(): string
{
    static $salt = null;
    if ($salt !== null) {
        return $salt;
    }
    $pepper = ugc_pepper();
    if ($pepper !== '') {
        return $salt = $pepper;
    }
    $salt = (string) get_setting('comment_hash_salt', '');
    if ($salt === '') {
        $stmt = db()->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
        $stmt->execute(['comment_hash_salt', bin2hex(random_bytes(16))]);
        $salt = (string) get_setting('comment_hash_salt', '');
    }
    if ($salt === '') {
        // Never hash with an empty key (that would be unsalted): fall back to a key derived from the database login, which a data dump does not contain.
        error_log('[comment-moderation] no hash salt available; using a derived key.');
        $salt = hash('sha256', 'oroma-ugc|' . DB_NAME . '|' . DB_USER . '|' . DB_PASS);
    }
    return $salt;
}

function ugc_hash(string $value): string
{
    return substr(hash_hmac('sha256', $value, ugc_salt()), 0, 32);
}

/** A plain token: letters, digits, "_" and "-", 8 to 64 long. \A and \z, because "$" would let a trailing newline through. */
function ugc_valid_viewer_id(?string $id): bool
{
    return $id !== null && (bool) preg_match('/\A[A-Za-z0-9_-]{8,64}\z/', $id);
}

/** The app's anonymous viewer id from a request, trimmed ("id\n" is the same device as "id"), or null when it is not a plain token. */
function ugc_clean_viewer_id(mixed $id): ?string
{
    if (!is_string($id)) {
        return null;
    }
    $id = trim($id);
    return ugc_valid_viewer_id($id) ? $id : null;
}

/**
 * One-way code for "this device", 32 characters: the app's anonymous viewer id when it sent one ("v" + hash), else the network
 * address + browser ("w" + hash). The first character says which, because a "w" code is shared by everyone behind the same
 * address and browser string and must never be used to hide anybody's existing comments (see comment_ban_from_comment).
 */
function ugc_device_hash(?string $viewerId = null): string
{
    $viewerId = ugc_clean_viewer_id($viewerId);
    if ($viewerId !== null) {
        return 'v' . substr(ugc_hash('v|' . $viewerId), 0, 31);
    }
    return 'w' . substr(ugc_hash('w|' . ugc_client_ip() . '|' . mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200)), 0, 31);
}

/** Does this device code identify one app install (true) rather than a shared address + browser (false)? */
function ugc_device_is_viewer(?string $deviceHash): bool
{
    return $deviceHash !== null && str_starts_with($deviceHash, 'v');
}

/** One-way code for the visitor's network (the normalised address), used to count how many different networks reported a comment. */
function ugc_ip_hash(): string
{
    return ugc_hash('i|' . ugc_client_ip());
}

/** A name as a ban compares it: no case, accents, spaces, punctuation or trailing digits, look-alike letters folded ("Okello.", "OKELLO2", "Оkello" -> "okello"). */
function ugc_name_key(string $name): string
{
    $n = strtr(ugc_norm($name), UGC_NAME_LOOKALIKES);
    $n = preg_replace('/(?<=\p{L})\d+(?=[^\p{L}\d]|$)/u', '', $n) ?? $n;              // "okello2" -> "okello"
    $n = strtr($n, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't']); // "0kello" -> "okello"
    return preg_replace('/[^\p{L}]+/u', '', $n) ?? '';
}

/** An e-mail as a ban compares it: lower case, "+tag" dropped, and for Gmail also the dots ("O.Kello+x@googlemail.com" -> "okello@gmail.com"). */
function ugc_email_key(?string $email): string
{
    $e = mb_strtolower(trim((string) $email));
    $at = strrpos($e, '@');
    if ($at === false || $at === 0) {
        return $e;
    }
    $local = substr($e, 0, $at);
    $domain = substr($e, $at + 1);
    $plus = strpos($local, '+');
    if ($plus !== false && $plus > 0) {
        $local = substr($local, 0, $plus);
    }
    if ($domain === 'googlemail.com') {
        $domain = 'gmail.com';
    }
    if ($domain === 'gmail.com') {
        $local = str_replace('.', '', $local);
    }
    return $local . '@' . $domain;
}

// ── posting a comment ──────────────────────────────────────────

/** Is this name, e-mail or device banned right now? */
function comment_is_banned(string $name, ?string $email, ?string $deviceHash): bool
{
    if (!comment_schema_ready()) {
        return false;
    }
    try {
        $stmt = db()->prepare(
            "SELECT 1 FROM comment_bans WHERE (expires_at IS NULL OR expires_at > ?)
             AND ((kind = 'name' AND value = ?) OR (kind = 'email' AND value = ?) OR (kind = 'device' AND value = ?)) LIMIT 1"
        );
        $stmt->execute([date('Y-m-d H:i:s'), ugc_name_key($name), ugc_email_key($email), (string) $deviceHash]);
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        error_log('[comment-moderation] ban check: ' . $e->getMessage());
        return false;
    }
}

/** [status, hold_reason] for a new comment: ['approved', null] to publish, ['pending', reason] to hold it for review. */
function comment_moderate(string $name, ?string $email, string $comment, ?string $deviceHash): array
{
    if (comment_is_banned($name, $email, $deviceHash)) {
        return ['pending', 'banned'];
    }
    $tag = ugc_filter_check($name, $comment, ugc_lines(get_setting('comment_blocklist', '')), ugc_lines(get_setting('comment_allowed_domains', '')));
    return $tag === null ? ['approved', null] : ['pending', 'filter:' . $tag];
}

/**
 * Did this DEVICE post this exact comment on this story in the last 10 minutes (double taps, copy-paste spam)? It is scoped to the
 * device so that nobody is told that someone ELSE already posted the same words. Without the moderation columns there is no device
 * to compare, so the check is skipped (the rate limit still applies).
 */
function comment_is_duplicate(int $articleId, string $comment, string $deviceHash): bool
{
    if (!comment_schema_ready()) {
        return false;
    }
    $stmt = db()->prepare('SELECT id FROM comments WHERE article_id = ? AND device_hash = ? AND comment = ? AND created_at > ? LIMIT 1');
    $stmt->execute([$articleId, $deviceHash, $comment, date('Y-m-d H:i:s', time() - 600)]);
    return (bool) $stmt->fetch();
}

/** Stores a comment and returns its id. Falls back to the old columns when the moderation columns are not there yet. */
function comment_insert(int $articleId, string $name, ?string $email, string $comment, string $status, ?string $hold, ?string $deviceHash, string $now): int
{
    if (comment_schema_ready()) {
        db()->prepare(
            'INSERT INTO comments (article_id, name, email, comment, status, hold_reason, device_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$articleId, $name, $email ?: null, $comment, $status, $hold, $deviceHash, $now]);
    } else {
        db()->prepare(
            'INSERT INTO comments (article_id, name, email, comment, status, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$articleId, $name, $email ?: null, $comment, $status, $now]);
    }
    return (int) db()->lastInsertId();
}

// ── reports ────────────────────────────────────────────────────

/**
 * Records a report. Returns ['state' => 'created'|'duplicate'|'missing', 'hidden' => bool, 'open' => int].
 * 'missing' = no such published comment. One report per device per comment; $reason must be one of COMMENT_REPORT_REASONS.
 */
function comment_report_add(int $commentId, string $reason, ?string $viewerId, string $platform): array
{
    $stmt = db()->prepare(
        "SELECT c.id, c.comment FROM comments c JOIN articles a ON a.id = c.article_id
         WHERE c.id = ? AND c.status = 'approved' AND a.status = 'published'"
    );
    $stmt->execute([$commentId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['state' => 'missing', 'hidden' => false, 'open' => 0];
    }

    try {
        db()->prepare(
            'INSERT INTO comment_reports (comment_id, reporter_hash, ip_hash, reason, platform, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$commentId, ugc_device_hash($viewerId), ugc_ip_hash(), $reason, $platform === 'web' ? 'web' : 'app', date('Y-m-d H:i:s')]);
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) { // the same device already reported this comment
            return ['state' => 'duplicate', 'hidden' => false, 'open' => 0];
        }
        throw $e;
    }

    $count = db()->prepare('SELECT COUNT(*) AS n FROM comment_reports WHERE comment_id = ? AND resolved_at IS NULL');
    $count->execute([$commentId]);
    $open = (int) $count->fetch()['n'];

    if (comment_report_network_count($commentId) >= COMMENT_REPORT_THRESHOLD) {
        // Held until a moderator looks. A comment a moderator already approved ('reviewed') is not hidden again by repeat reports.
        db()->prepare(
            "UPDATE comments SET status = 'pending', hold_reason = 'reports' WHERE id = ? AND status = 'approved' AND (hold_reason IS NULL OR hold_reason <> 'reviewed')"
        )->execute([$commentId]);
    }
    $now = db()->prepare('SELECT status FROM comments WHERE id = ?');
    $now->execute([$commentId]);
    $hidden = ($now->fetch()['status'] ?? 'approved') !== 'approved';

    // E-mail only for the first report of a comment, or when reports hid it (and never more than a few an hour, see comment_notify_slot).
    if ($hidden || $open === 1) {
        comment_notify_admin($reason, $open, (string) $row['comment'], $hidden);
    }
    return ['state' => 'created', 'hidden' => $hidden, 'open' => $open];
}

/**
 * How many different networks have an open report on this comment. One person with several addresses is harder to count
 * than a crowd, so a network that already reported more than COMMENT_REPORT_DAILY_CAP different comments in the last 24 hours
 * does not count: one source can contribute to only a few automatic hides a day. Its reports are still kept and shown to the admin.
 */
function comment_report_network_count(int $commentId): int
{
    $nets = db()->prepare('SELECT DISTINCT ip_hash FROM comment_reports WHERE comment_id = ? AND resolved_at IS NULL');
    $nets->execute([$commentId]);
    $since = date('Y-m-d H:i:s', time() - 86400);
    $total = 0;
    foreach ($nets->fetchAll() as $n) {
        $busy = db()->prepare('SELECT COUNT(DISTINCT comment_id) AS n FROM comment_reports WHERE ip_hash = ? AND created_at > ?');
        $busy->execute([$n['ip_hash'], $since]);
        if ((int) $busy->fetch()['n'] <= COMMENT_REPORT_DAILY_CAP) {
            $total++;
        }
    }
    return $total;
}

/**
 * Lets at most $perHour e-mails of this $kind through an hour (a counter file in the temp folder, shared by all requests).
 * Returns how many reports were NOT e-mailed since the last one that went out (0 or more), or -1 when this one must not be sent.
 */
function comment_notify_slot(string $kind, int $perHour): int
{
    $fh = @fopen(ugc_temp_file('notify|' . $kind), 'c+');
    if (!$fh) {
        return 0;
    }
    try {
        flock($fh, LOCK_EX);
        $data = json_decode((string) stream_get_contents($fh), true);
        $now = time();
        $hits = array_values(array_filter(is_array($data['hits'] ?? null) ? $data['hits'] : [], static fn($t) => is_int($t) && $t > $now - 3600));
        $skipped = is_int($data['skipped'] ?? null) ? $data['skipped'] : 0;
        if (count($hits) >= $perHour) {
            $result = -1;
            $skipped++;
        } else {
            $result = $skipped;
            $skipped = 0;
            $hits[] = $now;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode(['hits' => $hits, 'skipped' => $skipped]));
        return $result;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/**
 * E-mail to the site address for the first report of a comment, or when reports hid it. No reporter details are included.
 * Throttled so a flood of reports cannot flood the inbox or use up the hosting's mail quota (the contact form shares it);
 * the admin menu badge and dashboard always show every open report.
 */
function comment_notify_admin(string $reason, int $open, string $text, bool $hidden): void
{
    try {
        $skipped = $hidden
            ? comment_notify_slot('hidden', COMMENT_NOTIFY_HIDDEN_PER_HOUR)
            : comment_notify_slot('first', COMMENT_NOTIFY_FIRST_PER_HOUR);
        if ($skipped < 0) {
            return;
        }
        $excerpt = mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 200);
        $body = 'A comment was reported (reason: ' . $reason . ', open reports: ' . $open . ')' . ($hidden ? ' and has been hidden until it is reviewed' : '') . ".\n\n\"" . $excerpt . "\"\n\n"
            . 'Review: ' . OROMA_PUBLIC_URL . "/admin/comments.php?status=reported\n"
            . ($skipped > 0 ? "\n" . $skipped . ' other report' . ($skipped === 1 ? ' was' : 's were') . " not e-mailed to keep this inbox usable; they are all in the same list.\n" : '');
        @mail(SITE_EMAIL, '[' . SITE_NAME . '] Comment ' . ($hidden ? 'hidden by reports' : 'reported'), $body, "From: noreply@oromatv.com\r\nContent-Type: text/plain; charset=UTF-8\r\n");
    } catch (Throwable $e) {
        error_log('[comment-moderation] notify: ' . $e->getMessage());
    }
}

/** A moderator has dealt with a comment: its open reports stop counting. */
function comment_resolve_reports(int $commentId): void
{
    if (!comment_schema_ready()) {
        return;
    }
    db()->prepare('UPDATE comment_reports SET resolved_at = ? WHERE comment_id = ? AND resolved_at IS NULL')->execute([date('Y-m-d H:i:s'), $commentId]);
}

// ── bans ───────────────────────────────────────────────────────

/**
 * Bans whoever wrote a comment (name, e-mail, device) and hides their other comments. Returns how many comments were hidden.
 * Name and e-mail bans last COMMENT_BAN_DAYS, or for ever when $permanent (the admin page only allows that for admins); a
 * device ban always lasts COMMENT_DEVICE_BAN_DAYS. Banning someone again extends the ban (an expired row would otherwise stay expired).
 * Existing comments are hidden only when they share a signal that points at one person: the same app install (viewer-id
 * device code), the same e-mail or the same name. A "w" device code (shared address + browser) holds FUTURE comments only,
 * never hides anyone's published ones. A comment a moderator already approved is left alone.
 */
function comment_ban_from_comment(int $commentId, ?int $adminId, bool $permanent = false): int
{
    $stmt = db()->prepare('SELECT name, email, device_hash FROM comments WHERE id = ?');
    $stmt->execute([$commentId]);
    $c = $stmt->fetch();
    if (!$c) {
        return 0;
    }
    $now = date('Y-m-d H:i:s');
    $until = $permanent ? null : date('Y-m-d H:i:s', time() + COMMENT_BAN_DAYS * 86400);
    $note = 'from comment #' . $commentId;
    $insert = db()->prepare(
        'INSERT INTO comment_bans (kind, value, note, created_by, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE note = VALUES(note), created_by = VALUES(created_by), created_at = VALUES(created_at),
           expires_at = IF(expires_at IS NULL OR VALUES(expires_at) IS NULL, NULL, GREATEST(expires_at, VALUES(expires_at)))'
    );

    $nameKey = ugc_name_key((string) $c['name']);
    if (mb_strlen($nameKey) < 2) {
        $nameKey = '';
    } else {
        $insert->execute(['name', mb_substr($nameKey, 0, 150), $note, $adminId, $now, $until]);
    }
    $emailKey = ugc_email_key((string) $c['email']);
    if ($emailKey !== '') {
        $insert->execute(['email', mb_substr($emailKey, 0, 150), $note, $adminId, $now, $until]);
    }
    $device = (string) ($c['device_hash'] ?? '');
    if ($device !== '') {
        $insert->execute(['device', $device, $note, $adminId, $now, date('Y-m-d H:i:s', time() + COMMENT_DEVICE_BAN_DAYS * 86400)]);
    }

    // Hide the same person's other comments, then settle the reports on them. The match is made here (not in SQL) so that
    // "O.Kello+x@gmail.com" and "okello@gmail.com", or "Okello." and "okello2", count as the same. Newest 5000 comments is plenty.
    $viewerDevice = ugc_device_is_viewer($device) ? $device : '';
    $rows = db()->query("SELECT id, name, email, device_hash, hold_reason FROM comments WHERE status IN ('approved','pending') ORDER BY id DESC LIMIT 5000")->fetchAll();
    $ids = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if ($id === $commentId) {
            $ids[] = $id;
            continue;
        }
        if (($r['hold_reason'] ?? '') === 'reviewed') {
            continue;
        }
        if (($viewerDevice !== '' && (string) $r['device_hash'] === $viewerDevice)
            || ($emailKey !== '' && ugc_email_key((string) $r['email']) === $emailKey)
            || ($nameKey !== '' && ugc_name_key((string) $r['name']) === $nameKey)) {
            $ids[] = $id;
        }
    }
    foreach ($ids as $id) {
        db()->prepare("UPDATE comments SET status = 'spam' WHERE id = ?")->execute([$id]);
        comment_resolve_reports($id);
    }
    return count($ids);
}

// ── counts for the admin ───────────────────────────────────────

/** Comments with at least one open report (joined to comments, so reports left behind by a deleted article are not counted). */
function reported_comment_count(): int
{
    try {
        ensure_comment_schema();
        if (!comment_schema_ready()) {
            return 0;
        }
        return (int) db()->query(
            'SELECT COUNT(DISTINCT r.comment_id) AS n FROM comment_reports r JOIN comments c ON c.id = r.comment_id WHERE r.resolved_at IS NULL'
        )->fetch()['n'];
    } catch (Throwable $e) {
        return 0;
    }
}

/** What the admin menu badge shows: comments waiting for a decision (held + reported but still public). */
function comment_attention_count(): int
{
    try {
        $pending = (int) db()->query("SELECT COUNT(*) AS n FROM comments WHERE status = 'pending'")->fetch()['n'];
        ensure_comment_schema();
        if (!comment_schema_ready()) {
            return $pending;
        }
        $public = (int) db()->query(
            "SELECT COUNT(DISTINCT r.comment_id) AS n FROM comment_reports r JOIN comments c ON c.id = r.comment_id WHERE r.resolved_at IS NULL AND c.status = 'approved'"
        )->fetch()['n'];
        return $pending + $public;
    } catch (Throwable $e) {
        return 0;
    }
}
