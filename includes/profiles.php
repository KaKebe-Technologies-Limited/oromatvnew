<?php
/**
 * App profiles ("My profile" in the mobile app). A profile is OPTIONAL and belongs to one phone: there is no password and no sign-in.
 * The phone gets a secret token once, when the profile is created; only its SHA-256 is stored here. The API (api/profile-routes.php),
 * the public pages (verify-email.php, unsubscribe.php) and the admin (admin/profiles.php, admin/updates.php) all use this file.
 *
 *   - Photo:   every new or changed picture is 'pending' (visible only to its owner and staff) until a staff member approves it,
 *              because it is shown next to the person's comments. Pictures are re-encoded with GD, which also drops EXIF/GPS.
 *   - Updates: push and e-mail updates are opt-in (consent_push / consent_email) and by topic (bit 1 breaking, 2 live and premieres,
 *              4 new shows and movies). E-mail also needs a confirmed address. See includes/push.php and includes/mail-updates.php.
 *   - Schema:  creates itself on first use (ensure_profile_schema), like includes/comment-moderation.php; database/profiles.sql holds
 *              the same statements for a database user that cannot create tables.
 * No function here sends output or exits, so it works from the API, a page and the command line.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/comment-moderation.php'; // name filter, name key, temp-file counters

const PROFILE_SCHEMA_VERSION    = '1';
const PROFILE_TERMS_VERSION     = '2026-11-18'; // the "Last updated" date of terms.php the person agreed to
const PROFILE_TOPIC_BREAKING    = 1;
const PROFILE_TOPIC_LIVE        = 2;
const PROFILE_TOPIC_SHOWS       = 4;
const PROFILE_TOPICS_ALL        = 7;
const PROFILE_PHOTO_MAX_BYTES   = 4194304;  // 4 MB upload
const PROFILE_PHOTO_MIN_SIDE    = 128;
const PROFILE_PHOTO_MAX_PIXELS  = 24000000; // refused before decoding: a decoded image takes about 4 bytes per pixel of memory
const PROFILE_PHOTO_SIZE        = 512;
const PROFILE_PHOTO_QUALITY     = 82;
const PROFILE_EMAIL_TOKEN_HOURS = 48;
const PROFILE_REJECT_REASONS    = ['Not a clear photo of you', 'Not appropriate', 'Shows another person', 'Other'];
const PROFILE_ADDRESS_LINE      = 'Oroma TV, Oyam Road, Lira City, Uganda';
// Everything an admin list needs; never the token hashes.
const PROFILE_LIST_COLUMNS      = 'id, viewer_id, display_name, email, email_verified_at, phone, photo_file, photo_status, photo_reject_reason, photo_updated_at,
    push_token, push_platform, push_updated_at, consent_push, consent_email, consent_at, topics, terms_version, unsubscribe_token,
    blocked_at, created_at, updated_at, last_seen_at';

// ── schema ─────────────────────────────────────────────────────

/** The statements that make the tables (the same text is in database/profiles.sql). */
function profile_schema_statements(): array
{
    $tail = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'app_profiles' => "CREATE TABLE IF NOT EXISTS `app_profiles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `viewer_id` VARCHAR(64) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `display_name` VARCHAR(60) NOT NULL DEFAULT '',
            `email` VARCHAR(190) DEFAULT NULL,
            `email_verified_at` DATETIME DEFAULT NULL,
            `email_token_hash` CHAR(64) DEFAULT NULL,
            `email_token_expires` DATETIME DEFAULT NULL,
            `phone` VARCHAR(24) DEFAULT NULL,
            `photo_file` VARCHAR(80) DEFAULT NULL,
            `photo_status` ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
            `photo_reject_reason` VARCHAR(120) DEFAULT NULL,
            `photo_updated_at` DATETIME DEFAULT NULL,
            `push_token` VARCHAR(255) DEFAULT NULL,
            `push_platform` ENUM('ios','android') DEFAULT NULL,
            `push_updated_at` DATETIME DEFAULT NULL,
            `consent_push` TINYINT(1) NOT NULL DEFAULT 0,
            `consent_email` TINYINT(1) NOT NULL DEFAULT 0,
            `consent_at` DATETIME DEFAULT NULL,
            `topics` TINYINT UNSIGNED NOT NULL DEFAULT 7,
            `terms_version` VARCHAR(16) DEFAULT NULL,
            `unsubscribe_token` CHAR(32) NOT NULL,
            `blocked_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `last_seen_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_viewer` (`viewer_id`),
            KEY `idx_token` (`token_hash`),
            KEY `idx_email` (`email`),
            KEY `idx_photo` (`photo_status`),
            KEY `idx_push` (`push_platform`),
            KEY `idx_unsub` (`unsubscribe_token`),
            KEY `idx_email_token` (`email_token_hash`)
        ) $tail",
        'profile_updates' => "CREATE TABLE IF NOT EXISTS `profile_updates` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `kind` ENUM('push','email') NOT NULL,
            `title` VARCHAR(120) NOT NULL,
            `body` TEXT NOT NULL,
            `link_type` VARCHAR(16) NOT NULL DEFAULT 'home',
            `link_value` VARCHAR(255) NOT NULL DEFAULT '',
            `topic` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `recipients` INT NOT NULL DEFAULT 0,
            `sent` INT NOT NULL DEFAULT 0,
            `failed` INT NOT NULL DEFAULT 0,
            `created_by` INT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_kind_created` (`kind`, `created_at`)
        ) $tail",
        'profile_email_queue' => "CREATE TABLE IF NOT EXISTS `profile_email_queue` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `update_id` INT UNSIGNED DEFAULT NULL,
            `profile_id` INT UNSIGNED NOT NULL,
            `to_email` VARCHAR(190) NOT NULL,
            `subject` VARCHAR(190) NOT NULL,
            `body_text` MEDIUMTEXT NOT NULL,
            `body_html` MEDIUMTEXT NOT NULL,
            `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
            `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `last_error` VARCHAR(190) DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `sent_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`, `id`),
            KEY `idx_profile` (`profile_id`),
            KEY `idx_update` (`update_id`),
            KEY `idx_to_created` (`to_email`, `created_at`)
        ) $tail",
    ];
}

/**
 * True when the tables and the comments.profile_id column exist. $recheck = true looks again after a change.
 * The version flag in settings is written only after the probes passed, so when it is there they are skipped (one settings query per request).
 */
function profile_schema_ready(bool $recheck = false): bool
{
    static $ready = null;
    if ($ready !== null && !$recheck) {
        return $ready;
    }
    try {
        if (!$recheck && get_setting('profile_schema_version', '0') === PROFILE_SCHEMA_VERSION) {
            return $ready = true;
        }
        $m = db()->mysqli;
        $m->query('SELECT id, viewer_id, token_hash, photo_status, push_token, consent_email, topics, unsubscribe_token, blocked_at FROM app_profiles LIMIT 1')->free();
        $m->query('SELECT id, kind, recipients, sent, failed FROM profile_updates LIMIT 1')->free();
        $m->query('SELECT id, status, attempts FROM profile_email_queue LIMIT 1')->free();
        $m->query('SELECT profile_id FROM comments LIMIT 1')->free();
        return $ready = true;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

/**
 * Creates the profile tables and comments.profile_id when missing. Never throws: a missing CREATE/ALTER right leaves profiles "not ready"
 * (the API answers 503 and comments carry on without avatars). After a failed attempt it does not try again for an hour (a marker file in
 * the temp folder), instead of repeating the DDL and the log lines on every request. $verify = true retries at once (admin pages).
 */
function ensure_profile_schema(bool $verify = false): void
{
    static $done = false;
    if ($done && !$verify) {
        return;
    }
    $done = true;

    try {
        if ($verify ? profile_schema_ready(true) : profile_schema_ready()) {
            return; // up to date: the normal case
        }
    } catch (Throwable $e) {
        return;
    }
    $failedMarker = ugc_temp_file('profile-schema-failed');
    if (!$verify && is_file($failedMarker) && filemtime($failedMarker) > time() - 3600) {
        return; // tried and failed less than an hour ago
    }

    $m = db()->mysqli;
    $run = static function (string $sql) use ($m): void {
        try {
            $m->query($sql);
        } catch (Throwable $e) {
            error_log('[profiles] ' . $e->getMessage());
        }
    };
    foreach (profile_schema_statements() as $sql) {
        $run($sql);
    }

    try {
        $col = $m->query("SHOW COLUMNS FROM `comments` LIKE 'profile_id'");
        if ($col && $col->num_rows === 0) {
            $run('ALTER TABLE `comments` ADD COLUMN `profile_id` INT UNSIGNED NULL');
        }
        $idx = $m->query("SHOW INDEX FROM `comments` WHERE Key_name = 'idx_profile'");
        if ($idx && $idx->num_rows === 0) {
            $run('ALTER TABLE `comments` ADD KEY `idx_profile` (`profile_id`)');
        }
    } catch (Throwable $e) {
        error_log('[profiles] ' . $e->getMessage());
    }

    // Only remember "done" when it really is; otherwise remember the failure for an hour.
    if (profile_schema_ready(true)) {
        @unlink($failedMarker);
        try {
            set_setting('profile_schema_version', PROFILE_SCHEMA_VERSION);
        } catch (Throwable $e) {
            error_log('[profiles] ' . $e->getMessage());
        }
    } else {
        @file_put_contents($failedMarker, (string) time());
    }
}

// ── small helpers ──────────────────────────────────────────────

function profile_now(): string
{
    return date('Y-m-d H:i:s');
}

/** A whole-number setting kept inside min..max, with a default when it is missing or not a number. */
function profile_setting_int(string $key, int $default, int $min, int $max): int
{
    $v = get_setting($key, null);
    $n = ($v !== null && preg_match('/\A\s*\d+\s*\z/', $v)) ? (int) $v : $default;
    return max($min, min($max, $n));
}

/** Rows changed by the last INSERT/UPDATE/DELETE on this connection. */
function profile_affected(): int
{
    return max(0, (int) db()->mysqli->affected_rows);
}

/** true/false from true, false, 1, 0, "1", "0", "true", "false"; null when it is anything else. */
function profile_bool(mixed $v): ?bool
{
    if (is_bool($v)) {
        return $v;
    }
    if ($v === 1 || $v === '1' || $v === 0 || $v === '0') {
        return (bool) $v;
    }
    if (is_string($v)) {
        $s = strtolower(trim($v));
        if ($s === 'true') {
            return true;
        }
        if ($s === 'false') {
            return false;
        }
    }
    return null;
}

function profile_iso(?string $mysql): ?string
{
    $ts = $mysql ? strtotime($mysql) : false;
    return $ts ? date('c', $ts) : null;
}

/** Where the website is, for links in e-mails and picture URLs: the fixed public address on the live site, this machine while testing. */
function profile_site_url(): string
{
    global $is_local;
    return !empty($is_local) ? rtrim(SITE_URL, '/') : rtrim(OROMA_PUBLIC_URL, '/');
}

/**
 * Allow at most $max hits of $key per $windowSeconds, whoever asks (a counter file in the temp folder, like ugc_rate_hit but keyed by
 * a profile instead of the visitor's address). Returns 0 when allowed, else the seconds to wait. Skipped if the temp folder is not writable.
 */
function profile_rate_hit(string $key, int $max, int $windowSeconds): int
{
    $fh = @fopen(ugc_temp_file('profile|' . $key), 'c+');
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

// ── topics ─────────────────────────────────────────────────────

/** Topic bits -> {breaking, live, shows} as the API shows them. */
function profile_topics_to_array(int $bits): array
{
    return [
        'breaking' => ($bits & PROFILE_TOPIC_BREAKING) !== 0,
        'live'     => ($bits & PROFILE_TOPIC_LIVE) !== 0,
        'shows'    => ($bits & PROFILE_TOPIC_SHOWS) !== 0,
    ];
}

/**
 * Applies a {breaking, live, shows} object (any of the keys) to the current bits. Returns [bits, error|null].
 * A key that is not true/false is refused; unknown keys are ignored.
 */
function profile_topics_from_input(mixed $value, int $current = PROFILE_TOPICS_ALL): array
{
    if (!is_array($value)) {
        return [$current, 'Please send the topics as breaking, live and shows.'];
    }
    $bits = $current & PROFILE_TOPICS_ALL;
    foreach (['breaking' => PROFILE_TOPIC_BREAKING, 'live' => PROFILE_TOPIC_LIVE, 'shows' => PROFILE_TOPIC_SHOWS] as $key => $bit) {
        if (!array_key_exists($key, $value)) {
            continue;
        }
        $on = profile_bool($value[$key]);
        if ($on === null) {
            return [$current, 'Each topic must be true or false.'];
        }
        $bits = $on ? ($bits | $bit) : ($bits & ~$bit);
    }
    return [$bits, null];
}

/** "Breaking news, Live broadcasts and premieres" for the admin; "None" when no bit is set. */
function profile_topics_label(int $bits): string
{
    $out = [];
    if ($bits & PROFILE_TOPIC_BREAKING) {
        $out[] = 'Breaking news';
    }
    if ($bits & PROFILE_TOPIC_LIVE) {
        $out[] = 'Live and premieres';
    }
    if ($bits & PROFILE_TOPIC_SHOWS) {
        $out[] = 'New shows and movies';
    }
    return $out ? implode(', ', $out) : 'None';
}

/** The audience topic of an update: 0 (everyone who opted in) or one of the single bits. */
function profile_valid_topic(int $topic): bool
{
    return in_array($topic, [0, PROFILE_TOPIC_BREAKING, PROFILE_TOPIC_LIVE, PROFILE_TOPIC_SHOWS], true);
}

// ── validation ─────────────────────────────────────────────────

/** Does an active ban (comment_bans, by name key) stop this name? A missing table or an error means "no ban". */
function profile_name_is_banned(string $name): bool
{
    try {
        $key = ugc_name_key($name);
        if ($key === '') {
            return false;
        }
        $stmt = db()->prepare("SELECT 1 FROM comment_bans WHERE kind = 'name' AND value = ? AND (expires_at IS NULL OR expires_at > ?) LIMIT 1");
        $stmt->execute([mb_substr($key, 0, 150), profile_now()]);
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * A display name: 2-40 characters, no control or invisible characters, passes the comment filter and is not banned.
 * Returns [clean name, null] or ['', message]. The message for a filter hit or a ban is the same, so nobody learns which.
 */
function profile_validate_name(string $raw): array
{
    $lengthMsg = 'Please enter a name of 2 to 40 characters.';
    if (!mb_check_encoding($raw, 'UTF-8')) {
        return ['', $lengthMsg];
    }
    $name = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    $len = mb_strlen($name);
    if ($len < 2 || $len > 40) {
        return ['', $lengthMsg];
    }
    // control characters, bidi overrides, zero-width space, word joiner, BOM and soft hyphen: invisible text is a way to pose as someone else
    if (preg_match('/[\p{Cc}\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}\x{00AD}]/u', $name)) {
        return ['', 'Your name contains characters we cannot use.'];
    }
    if (!preg_match('/[\p{L}\p{N}]/u', $name)) {
        return ['', $lengthMsg];
    }
    $different = 'Please choose a different name.';
    if (profile_name_is_banned($name)) {
        return ['', $different];
    }
    $held = ugc_filter_check($name, '', ugc_lines(get_setting('comment_blocklist', '')), ugc_lines(get_setting('comment_allowed_domains', '')));
    if ($held !== null) {
        return ['', $different];
    }
    return [$name, null];
}

/** An e-mail address: lower case, at most 190 characters, plain ASCII (no quoted or IP forms), no control characters. '' gives [null, null]. */
function profile_validate_email(string $raw): array
{
    $msg = 'Please enter a valid email address, or leave it blank.';
    if ($raw === '') {
        return [null, null];
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $raw)) { // a CR or LF would let a header be injected into the mail
        return [null, $msg];
    }
    $email = strtolower($raw);
    if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || !preg_match('/\A[a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+\z/', $email)) {
        return [null, $msg];
    }
    return [$email, null];
}

/**
 * A phone number as +<country code><number>. Accepts digits, spaces, dashes and one leading "+". Uganda formats 0772 033 333,
 * 772033333, 256772033333 and +256 772 033 333 all become +256772033333; any other number needs its own "+" (or "00") country code.
 * 8 to 15 digits. '' gives [null, null]. Returns [number|null, error|null].
 */
function profile_normalise_phone(string $raw): array
{
    $msg = 'Please enter a valid phone number, for example 0772 033 333 or +256 772 033 333.';
    $raw = trim($raw);
    if ($raw === '') {
        return [null, null];
    }
    if (!preg_match('/\A\+?[0-9 \-]+\z/', $raw)) {
        return [null, $msg];
    }
    $plus = $raw[0] === '+';
    $d = preg_replace('/[^0-9]/', '', $raw);
    if (!$plus && str_starts_with($d, '00')) {
        $plus = true;
        $d = substr($d, 2);
    }
    if ($plus) {
        if (str_starts_with($d, '2560') && strlen($d) === 13) {
            $d = '256' . substr($d, 4); // "+256 0772 ..." (the 0 is for calls inside Uganda)
        }
    } elseif (strlen($d) === 10 && $d[0] === '0') {
        $d = '256' . substr($d, 1);
    } elseif (strlen($d) === 9 && $d[0] === '7') {
        $d = '256' . $d;
    } elseif (strlen($d) === 12 && str_starts_with($d, '256')) {
        // already complete
    } else {
        return [null, $msg];
    }
    $len = strlen($d);
    if ($len < 8 || $len > 15 || $d[0] === '0' || (str_starts_with($d, '256') && $len !== 12)) {
        return [null, $msg];
    }
    return ['+' . $d, null];
}

/** "o****@gmail.com" for pages that must not show a whole address to whoever holds a link. */
function profile_mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false || $at < 1) {
        return '';
    }
    return substr($email, 0, 1) . str_repeat('*', min(6, max(1, $at - 1))) . substr($email, $at);
}

/** A text for a staff-written reason or note: no control characters, one line, at most $max characters. */
function profile_clean_line(string $s, int $max): string
{
    $s = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $s) ?? '';
    return trim(mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? ''), 0, $max));
}

// ── rows ───────────────────────────────────────────────────────

function profile_new_token(): string
{
    return bin2hex(random_bytes(32));
}

function profile_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function profile_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM app_profiles WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function profile_find_by_viewer(string $viewerId): ?array
{
    $stmt = db()->prepare('SELECT * FROM app_profiles WHERE viewer_id = ?');
    $stmt->execute([$viewerId]);
    return $stmt->fetch() ?: null;
}

/**
 * The profile that owns this secret token, or null (wrong, malformed, unknown: all the same answer). The token is looked up by its
 * SHA-256, which cannot be steered from outside, and compared again in constant time.
 */
function profile_find_by_token(string $token): ?array
{
    if (!preg_match('/\A[0-9a-f]{64}\z/', $token)) {
        return null;
    }
    $hash = profile_token_hash($token);
    $stmt = db()->prepare('SELECT * FROM app_profiles WHERE token_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return ($row && hash_equals((string) $row['token_hash'], $hash)) ? $row : null;
}

/**
 * Makes a profile. $d: viewer_id, display_name, email?, phone?, topics? (bits), consent_push?, consent_email? (all already validated).
 * Returns ['profile' => row, 'token' => the secret token (the only time it exists in clear)] or null when this viewer id already has one.
 */
function profile_create(array $d): ?array
{
    $token = profile_new_token();
    $now = profile_now();
    $push = !empty($d['consent_push']) ? 1 : 0;
    $mail = !empty($d['consent_email']) ? 1 : 0;
    try {
        db()->prepare(
            'INSERT INTO app_profiles (viewer_id, token_hash, display_name, email, phone, topics, consent_push, consent_email, consent_at,
                terms_version, unsubscribe_token, created_at, updated_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (string) $d['viewer_id'], profile_token_hash($token), (string) $d['display_name'],
            $d['email'] ?? null, $d['phone'] ?? null, (int) ($d['topics'] ?? PROFILE_TOPICS_ALL) & PROFILE_TOPICS_ALL,
            $push, $mail, ($push || $mail) ? $now : null, PROFILE_TERMS_VERSION, bin2hex(random_bytes(16)), $now, $now, $now,
        ]);
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) { // this viewer id already has a profile
            return null;
        }
        throw $e;
    }
    $row = profile_get((int) db()->lastInsertId());
    return $row ? ['profile' => $row, 'token' => $token] : null;
}

/** Changes plain columns of a profile (a fixed list, so a caller can never name an arbitrary column) and stamps updated_at. */
function profile_update(int $id, array $fields): void
{
    static $allowed = [
        'display_name', 'email', 'email_verified_at', 'email_token_hash', 'email_token_expires', 'phone', 'topics',
        'consent_push', 'consent_email', 'consent_at', 'push_token', 'push_platform', 'push_updated_at', 'last_seen_at',
    ];
    $sets = [];
    $params = [];
    foreach ($fields as $column => $value) {
        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException('profile_update: column not allowed');
        }
        $sets[] = "`$column` = ?";
        $params[] = is_bool($value) ? (int) $value : $value;
    }
    $sets[] = '`updated_at` = ?';
    $params[] = profile_now();
    $params[] = $id;
    db()->prepare('UPDATE app_profiles SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
}

/** The phone has seen the server: remember it (at most once an hour, so reads stay cheap). */
function profile_touch_seen(array $profile): void
{
    $last = $profile['last_seen_at'] ? strtotime((string) $profile['last_seen_at']) : 0;
    if ($last < time() - 3600) {
        db()->prepare('UPDATE app_profiles SET last_seen_at = ? WHERE id = ?')->execute([profile_now(), (int) $profile['id']]);
    }
}

/** Stores a push token for a profile, and takes the same token away from any other profile (a reinstall makes a new profile on the same phone). */
function profile_set_push_token(int $id, string $token, string $platform, ?bool $consent): void
{
    db()->prepare('UPDATE app_profiles SET push_token = NULL, push_platform = NULL, push_updated_at = ? WHERE push_token = ? AND id <> ?')
        ->execute([profile_now(), $token, $id]);
    $fields = ['push_token' => $token, 'push_platform' => $platform, 'push_updated_at' => profile_now()];
    if ($consent === true) {
        $fields['consent_push'] = 1;
        $fields['consent_at'] = profile_now();
    }
    profile_update($id, $fields);
}

/** The person switched notifications off: forget the token. */
function profile_clear_push(int $id): void
{
    profile_update($id, ['consent_push' => 0, 'push_token' => null, 'push_platform' => null, 'push_updated_at' => profile_now()]);
}

// ── what the app sees ──────────────────────────────────────────

/**
 * Where a profile picture is, for $viewer:
 *   'owner'  the person themself: their pending or approved picture (nothing when blocked, rejected or none)
 *   'public' everyone else (comment avatars): only an approved picture of a profile that is not blocked
 *   'staff'  the admin: any picture that has a file
 */
function profile_photo_url(array $row, string $viewer = 'owner'): ?string
{
    $file = (string) ($row['photo_file'] ?? '');
    if ($file === '' || profile_photo_path($file) === null) {
        return null;
    }
    $status = (string) ($row['photo_status'] ?? 'none');
    $blocked = !empty($row['blocked_at']);
    $visible = match ($viewer) {
        'staff'  => true,
        'public' => $status === 'approved' && !$blocked,
        default  => !$blocked && in_array($status, ['pending', 'approved'], true),
    };
    return $visible ? profile_site_url() . '/uploads/profiles/' . $file : null;
}

/** The picture URL for a comment avatar (approved and not blocked), else null. */
function profile_avatar_url(array $row): ?string
{
    return profile_photo_url($row, 'public');
}

/** profile id => avatar URL for many profiles at once (comment lists); profiles without a public picture are left out. */
function profile_avatar_urls(array $profileIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $profileIds), static fn($i) => $i > 0)));
    if (!$ids || !profile_schema_ready()) {
        return [];
    }
    $out = [];
    foreach (array_chunk($ids, 200) as $chunk) {
        $stmt = db()->prepare(
            "SELECT id, photo_file, photo_status, blocked_at FROM app_profiles WHERE photo_status = 'approved' AND blocked_at IS NULL AND id IN ("
            . implode(',', array_fill(0, count($chunk), '?')) . ')'
        );
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $r) {
            $url = profile_avatar_url($r);
            if ($url !== null) {
                $out[(int) $r['id']] = $url;
            }
        }
    }
    return $out;
}

/** The profile object P of the API (see the build spec), as the OWNER sees it. Never contains a token or a hash. */
function profile_serialize(array $r): array
{
    $email = ($r['email'] ?? '') !== '' ? (string) $r['email'] : null;
    $phone = ($r['phone'] ?? '') !== '' ? (string) $r['phone'] : null;
    $status = (string) $r['photo_status'];
    return [
        'id'                  => (int) $r['id'],
        'display_name'        => (string) $r['display_name'],
        'email'               => $email,
        'email_verified'      => $email !== null && !empty($r['email_verified_at']),
        'phone'               => $phone,
        'photo_url'           => profile_photo_url($r, 'owner'),
        'photo_status'        => $status,
        'photo_reject_reason' => $status === 'rejected' && ($r['photo_reject_reason'] ?? '') !== '' ? (string) $r['photo_reject_reason'] : null,
        'consent_push'        => (bool) $r['consent_push'],
        'consent_email'       => (bool) $r['consent_email'],
        'topics'              => profile_topics_to_array((int) $r['topics']),
        'push_registered'     => ($r['push_token'] ?? '') !== '',
        'created_at'          => profile_iso((string) $r['created_at']),
    ];
}

// ── e-mail confirmation and unsubscribing ──────────────────────

/** A fresh confirmation token for the profile's current address, valid 48 hours. Returns the token (only its hash is stored). */
function profile_issue_email_token(int $id): string
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE app_profiles SET email_token_hash = ?, email_token_expires = ?, updated_at = ? WHERE id = ?')
        ->execute([profile_token_hash($token), date('Y-m-d H:i:s', time() + PROFILE_EMAIL_TOKEN_HOURS * 3600), profile_now(), $id]);
    return $token;
}

/**
 * The link in the confirmation mail was used. True when the address is (now or already) confirmed with this token, false when the token
 * is unknown, expired, or was replaced by a newer one (the address was changed or a new mail was asked for).
 */
function profile_verify_email(string $token): bool
{
    if (!preg_match('/\A[0-9a-f]{64}\z/', $token)) {
        return false;
    }
    $stmt = db()->prepare('SELECT id, email, email_verified_at, email_token_expires FROM app_profiles WHERE email_token_hash = ? LIMIT 1');
    $stmt->execute([profile_token_hash($token)]);
    $row = $stmt->fetch();
    if (!$row || ($row['email'] ?? '') === '') {
        return false;
    }
    if (!empty($row['email_verified_at'])) {
        return true; // a second click, or a mail scanner that came first
    }
    if (empty($row['email_token_expires']) || $row['email_token_expires'] < profile_now()) {
        return false;
    }
    db()->prepare('UPDATE app_profiles SET email_verified_at = ?, updated_at = ? WHERE id = ? AND email_verified_at IS NULL')
        ->execute([profile_now(), profile_now(), (int) $row['id']]);
    return true;
}

/** The profile an unsubscribe link belongs to (the page shows a masked address from it), or null. */
function profile_by_unsubscribe_token(string $token): ?array
{
    if (!preg_match('/\A[0-9a-f]{32}\z/', $token)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM app_profiles WHERE unsubscribe_token = ? LIMIT 1');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/**
 * Stops updates for the profile behind an unsubscribe link. $scope 'email' switches e-mail updates off; 'all' also switches
 * notifications off and forgets the push token. True when the link is valid (also if they were already off), false when it is not.
 */
function profile_unsubscribe(string $unsubscribeToken, string $scope): bool
{
    $p = profile_by_unsubscribe_token($unsubscribeToken);
    if (!$p) {
        return false;
    }
    if ($scope === 'all') {
        profile_update((int) $p['id'], ['consent_email' => 0, 'consent_push' => 0, 'push_token' => null, 'push_platform' => null, 'push_updated_at' => profile_now()]);
        return true;
    }
    if ($scope === 'email') {
        profile_update((int) $p['id'], ['consent_email' => 0]);
        return true;
    }
    return false;
}

// ── pictures ───────────────────────────────────────────────────

/** uploads/profiles, made on first use together with the files that keep it from running scripts or listing pictures. '' if it cannot be made. */
function profile_photo_dir(): string
{
    $dir = rtrim(UPLOAD_DIR, '/\\') . '/profiles';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('[profiles] cannot create ' . $dir);
        return '';
    }
    $guard = $dir . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, implode("\n", [
            '# Profile pictures only: no script may run from here and the folder cannot be listed.',
            'Options -Indexes',
            '<IfModule mod_php.c>',
            '    php_flag engine off',
            '</IfModule>',
            '<IfModule mod_php7.c>',
            '    php_flag engine off',
            '</IfModule>',
            '<IfModule mod_php8.c>',
            '    php_flag engine off',
            '</IfModule>',
            '<FilesMatch "(?i)\.(php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|exe)$">',
            '    <IfModule mod_authz_core.c>',
            '        Require all denied',
            '    </IfModule>',
            '    <IfModule !mod_authz_core.c>',
            '        Order allow,deny',
            '        Deny from all',
            '    </IfModule>',
            '</FilesMatch>',
            '<IfModule mod_headers.c>',
            '    Header set X-Content-Type-Options "nosniff"',
            '    Header set Cache-Control "public, max-age=86400"',
            '</IfModule>',
            '',
        ]));
    }
    if (!is_file($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/** Full path of a stored picture, only for a name this code made (24 hex characters + .jpg); null for anything else, so a bad database value cannot reach another file. */
function profile_photo_path(string $file): ?string
{
    if (!preg_match('/\A[0-9a-f]{24}\.jpg\z/', $file)) {
        return null;
    }
    return rtrim(UPLOAD_DIR, '/\\') . '/profiles/' . $file;
}

function profile_photo_unlink(?string $file): void
{
    $path = $file !== null && $file !== '' ? profile_photo_path($file) : null;
    if ($path !== null && is_file($path)) {
        @unlink($path);
    }
}

/** memory_limit in bytes, -1 for none. */
function profile_memory_limit(): int
{
    $v = trim((string) ini_get('memory_limit'));
    if ($v === '' || $v === '-1') {
        return -1;
    }
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n << 30,
        'm' => $n << 20,
        'k' => $n << 10,
        default => $n,
    };
}

/**
 * Turns an uploaded file into the stored picture: checks it really is a JPEG, PNG or WebP (finfo and getimagesize, not the file name),
 * size and shape limits before anything is decoded, centre-crops a square, resizes to 512 x 512 and writes a new JPEG with GD (quality 82,
 * which drops EXIF/GPS and anything hidden in the file). Returns ['ok' => true, 'file' => name] or ['ok' => false, 'status' => http code, 'error' => message].
 */
function profile_photo_process(string $tmpPath): array
{
    $fail = static fn(string $msg, int $status = 422): array => ['ok' => false, 'status' => $status, 'error' => $msg];

    if (!is_file($tmpPath) || !is_readable($tmpPath)) {
        return $fail('Please choose a photo.');
    }
    $bytes = filesize($tmpPath);
    if ($bytes === false || $bytes < 100) {
        return $fail('That file is not a valid photo.');
    }
    if ($bytes > PROFILE_PHOTO_MAX_BYTES) {
        return $fail('That photo is too large. Please choose one under 4 MB.', 413);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    $types = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];
    if (!is_string($mime) || !isset($types[$mime])) {
        return $fail('Please choose a JPEG, PNG or WebP photo.');
    }
    $info = @getimagesize($tmpPath);
    if (!is_array($info) || ($info[2] ?? 0) !== $types[$mime]) {
        return $fail('That file is not a valid photo.');
    }
    $w = (int) $info[0];
    $h = (int) $info[1];
    if ($w < PROFILE_PHOTO_MIN_SIDE || $h < PROFILE_PHOTO_MIN_SIDE) {
        return $fail('That photo is too small. Please choose one at least 128 x 128 pixels.');
    }
    if ($w * $h > PROFILE_PHOTO_MAX_PIXELS) {
        return $fail('That photo is too large to process. Please choose a smaller one (up to 24 megapixels).');
    }
    $limit = profile_memory_limit();
    $need = (int) ($w * $h * 4 * 1.25) + 16 * 1048576;
    if ($limit !== -1 && $need > $limit - memory_get_usage(true)) {
        return $fail('That photo is too large to process. Please choose a smaller one.');
    }
    $dir = profile_photo_dir();
    if ($dir === '') {
        return $fail('Photo uploads are not available right now. Please try again later.', 503);
    }

    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmpPath),
        default        => @imagecreatefromwebp($tmpPath),
    };
    if (!($src instanceof GdImage)) {
        return $fail('That file is not a valid photo.');
    }

    // Phones store "which way up" in EXIF. The crop is a centred square, which is the same square once the picture is turned, so
    // the turn is done on the small 512 px result, not on the full-size picture.
    $orientation = 1;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmpPath, 'IFD0');
        $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $orientation = ($o >= 2 && $o <= 8) ? $o : 1;
    }

    $side = min($w, $h);
    $size = PROFILE_PHOTO_SIZE;
    $dst = imagecreatetruecolor($size, $size);
    if (!($dst instanceof GdImage)) {
        return $fail('That photo could not be processed.', 500);
    }
    imagefilledrectangle($dst, 0, 0, $size - 1, $size - 1, (int) imagecolorallocate($dst, 255, 255, 255)); // transparent PNG/WebP sit on white
    $ok = imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
    unset($src); // free the full-size picture before anything else is allocated
    if (!$ok) {
        return $fail('That photo could not be processed.', 500);
    }
    $dst = profile_photo_orient($dst, $orientation);

    $file = bin2hex(random_bytes(12)) . '.jpg';
    $path = $dir . '/' . $file;
    imageinterlace($dst, true);
    if (!@imagejpeg($dst, $path, PROFILE_PHOTO_QUALITY) || !is_file($path) || filesize($path) < 100) {
        @unlink($path);
        return $fail('Your photo could not be saved. Please try again.', 500);
    }
    return ['ok' => true, 'file' => $file];
}

/** Turns a picture the way EXIF orientation $o (2..8) says it must be shown. */
function profile_photo_orient(GdImage $img, int $o): GdImage
{
    $turn = static function (GdImage $i, float $angle): GdImage {
        $r = imagerotate($i, $angle, 0);
        return $r instanceof GdImage ? $r : $i;
    };
    switch ($o) {
        case 2:
            imageflip($img, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $img = $turn($img, 180);
            break;
        case 4:
            imageflip($img, IMG_FLIP_VERTICAL);
            break;
        case 5: // transpose
            $img = $turn($img, -90);
            imageflip($img, IMG_FLIP_HORIZONTAL);
            break;
        case 6: // shown 90 degrees clockwise
            $img = $turn($img, -90);
            break;
        case 7: // transverse
            $img = $turn($img, -90);
            imageflip($img, IMG_FLIP_VERTICAL);
            break;
        case 8: // shown 90 degrees counter-clockwise
            $img = $turn($img, 90);
            break;
    }
    return $img;
}

/**
 * Saves an uploaded picture for a profile as 'pending' (waiting for staff) and deletes the previous file.
 * Returns the result of profile_photo_process(), plus nothing else on success.
 */
function profile_set_photo(int $id, string $tmpPath): array
{
    $row = profile_get($id);
    if (!$row) {
        return ['ok' => false, 'status' => 404, 'error' => 'That profile does not exist.'];
    }
    $res = profile_photo_process($tmpPath);
    if (!$res['ok']) {
        return $res;
    }
    $now = profile_now();
    try {
        db()->prepare(
            "UPDATE app_profiles SET photo_file = ?, photo_status = 'pending', photo_reject_reason = NULL, photo_updated_at = ?, updated_at = ? WHERE id = ?"
        )->execute([$res['file'], $now, $now, $id]);
    } catch (Throwable $e) {
        profile_photo_unlink($res['file']);
        throw $e;
    }
    profile_photo_unlink($row['photo_file'] ?? null);
    return $res;
}

/** Takes the picture away (the owner's Remove button, or staff): row back to 'none' and the file deleted. False when there is no such profile. */
function profile_remove_photo(int $id): bool
{
    $row = profile_get($id);
    if (!$row) {
        return false;
    }
    $now = profile_now();
    db()->prepare("UPDATE app_profiles SET photo_file = NULL, photo_status = 'none', photo_reject_reason = NULL, photo_updated_at = ?, updated_at = ? WHERE id = ?")
        ->execute([$now, $now, $id]);
    profile_photo_unlink($row['photo_file'] ?? null);
    return true;
}

// ── staff actions ──────────────────────────────────────────────

/** Approves a waiting picture, so it shows next to the person's comments. False when nothing was waiting. */
function profile_approve_photo(int $id): bool
{
    $now = profile_now();
    db()->prepare(
        "UPDATE app_profiles SET photo_status = 'approved', photo_reject_reason = NULL, photo_updated_at = ?, updated_at = ?
         WHERE id = ? AND photo_status = 'pending' AND photo_file IS NOT NULL"
    )->execute([$now, $now, $id]);
    return profile_affected() > 0;
}

/**
 * Rejects a waiting (or an approved) picture with a reason the owner sees; the file is deleted because it will not be shown again.
 * False when there was no picture to reject.
 */
function profile_reject_photo(int $id, string $reason): bool
{
    $row = profile_get($id);
    if (!$row || !in_array($row['photo_status'], ['pending', 'approved'], true)) {
        return false;
    }
    $reason = profile_clean_line($reason, 120);
    $reason = $reason !== '' ? $reason : 'Not appropriate';
    $now = profile_now();
    db()->prepare(
        "UPDATE app_profiles SET photo_file = NULL, photo_status = 'rejected', photo_reject_reason = ?, photo_updated_at = ?, updated_at = ?
         WHERE id = ? AND photo_status IN ('pending','approved')"
    )->execute([$reason, $now, $now, $id]);
    if (profile_affected() > 0) {
        profile_photo_unlink($row['photo_file'] ?? null);
        return true;
    }
    return false;
}

/** Puts an approved picture back to 'pending' (hidden again until staff look), e.g. after several people reported it. */
function profile_set_photo_pending(int $id): bool
{
    $now = profile_now();
    db()->prepare("UPDATE app_profiles SET photo_status = 'pending', photo_updated_at = ?, updated_at = ? WHERE id = ? AND photo_status = 'approved'")
        ->execute([$now, $now, $id]);
    return profile_affected() > 0;
}

/** Blocks a profile: its picture is hidden, it cannot be changed, and it gets no updates. */
function profile_block(int $id): bool
{
    db()->prepare('UPDATE app_profiles SET blocked_at = ?, updated_at = ? WHERE id = ? AND blocked_at IS NULL')->execute([profile_now(), profile_now(), $id]);
    return profile_affected() > 0;
}

function profile_unblock(int $id): bool
{
    db()->prepare('UPDATE app_profiles SET blocked_at = NULL, updated_at = ? WHERE id = ? AND blocked_at IS NOT NULL')->execute([profile_now(), $id]);
    return profile_affected() > 0;
}

/**
 * Deletes a profile and everything tied to it: the row, the picture file and its queued e-mails. The comments it wrote stay, without the
 * picture (comments.profile_id becomes NULL). False when there is no such profile.
 */
function profile_delete(int $id): bool
{
    $row = profile_get($id);
    if (!$row) {
        return false;
    }
    $m = db()->mysqli;
    $m->begin_transaction();
    try {
        db()->prepare('UPDATE comments SET profile_id = NULL WHERE profile_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM profile_email_queue WHERE profile_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM app_profiles WHERE id = ?')->execute([$id]);
        $m->commit();
    } catch (Throwable $e) {
        $m->rollback();
        throw $e;
    }
    profile_photo_unlink($row['photo_file'] ?? null);
    return true;
}

// ── lists and counts for the admin ─────────────────────────────

/**
 * A page of profiles. $opts: q (name, e-mail or phone; a phone can be typed in any of the accepted formats), photo ('none', 'pending',
 * 'approved', 'rejected'), blocked (true = only blocked), page, per_page (default 25, at most 100).
 * Returns ['rows' => [...], 'total' => n, 'page' => n, 'pages' => n, 'per_page' => n]. Rows never carry token hashes.
 */
function profile_list(array $opts = []): array
{
    $where = ['1 = 1'];
    $params = [];
    $q = trim((string) ($opts['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . strtr(mb_substr($q, 0, 100), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $parts = ["display_name LIKE ? ESCAPE '!'", "email LIKE ? ESCAPE '!'", "phone LIKE ? ESCAPE '!'"];
        array_push($params, $like, $like, $like);
        [$phone] = profile_normalise_phone($q);
        if ($phone !== null) {
            $parts[] = 'phone = ?';
            $params[] = $phone;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    $photo = (string) ($opts['photo'] ?? '');
    if (in_array($photo, ['none', 'pending', 'approved', 'rejected'], true)) {
        $where[] = 'photo_status = ?';
        $params[] = $photo;
    }
    if (!empty($opts['blocked'])) {
        $where[] = 'blocked_at IS NOT NULL';
    }
    $perPage = max(1, min(100, (int) ($opts['per_page'] ?? 25)));
    $sqlWhere = implode(' AND ', $where);

    $count = db()->prepare("SELECT COUNT(*) AS n FROM app_profiles WHERE $sqlWhere");
    $count->execute($params);
    $total = (int) ($count->fetch()['n'] ?? 0);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, (int) ($opts['page'] ?? 1)));

    $stmt = db()->prepare('SELECT ' . PROFILE_LIST_COLUMNS . " FROM app_profiles WHERE $sqlWhere ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$perPage, ($page - 1) * $perPage]));
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}

/** Pictures waiting for a decision, oldest first. */
function profile_pending_photos(int $limit = 50, int $offset = 0): array
{
    $stmt = db()->prepare(
        "SELECT id, display_name, photo_file, photo_status, photo_updated_at, blocked_at FROM app_profiles
         WHERE photo_status = 'pending' AND photo_file IS NOT NULL ORDER BY photo_updated_at ASC, id ASC LIMIT ? OFFSET ?"
    );
    $stmt->execute([max(1, $limit), max(0, $offset)]);
    return $stmt->fetchAll();
}

/** One profile for the admin detail page (no token hashes), or null. */
function profile_admin_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT ' . PROFILE_LIST_COLUMNS . ' FROM app_profiles WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Totals for the admin tabs and badges: total, pending, approved, rejected, blocked, push (phones that can get a notification), email (confirmed and opted in). */
function profile_counts(): array
{
    $r = db()->query(
        "SELECT COUNT(*) AS total, SUM(photo_status = 'pending') AS pending, SUM(photo_status = 'approved') AS approved,
                SUM(photo_status = 'rejected') AS rejected, SUM(blocked_at IS NOT NULL) AS blocked,
                SUM(consent_push = 1 AND push_token IS NOT NULL AND blocked_at IS NULL) AS push,
                SUM(consent_email = 1 AND email_verified_at IS NOT NULL AND blocked_at IS NULL) AS email
         FROM app_profiles"
    )->fetch() ?: [];
    return array_map('intval', $r + ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'blocked' => 0, 'push' => 0, 'email' => 0]);
}

/** Number of pictures waiting for staff (the badge). 0 when the tables are not there. */
function profile_pending_count(): int
{
    try {
        if (!profile_schema_ready()) {
            return 0;
        }
        return (int) db()->query("SELECT COUNT(*) AS n FROM app_profiles WHERE photo_status = 'pending' AND photo_file IS NOT NULL")->fetch()['n'];
    } catch (Throwable $e) {
        return 0;
    }
}

/** The SQL condition (and parameters) of who an update on $channel ('push' or 'email') reaches for $topic (0 = everyone who opted in). */
function profile_audience_where(string $channel, int $topic): array
{
    $sql = $channel === 'push'
        ? "consent_push = 1 AND push_token IS NOT NULL AND push_token <> '' AND blocked_at IS NULL"
        : "consent_email = 1 AND email IS NOT NULL AND email <> '' AND email_verified_at IS NOT NULL AND blocked_at IS NULL";
    $params = [];
    if ($topic > 0) {
        $sql .= ' AND (topics & ?) <> 0';
        $params[] = $topic;
    }
    return [$sql, $params];
}

/** How many phones and e-mail addresses an update for $topic would reach: ['push' => n, 'email' => n]. */
function profile_audience_counts(int $topic = 0): array
{
    $out = [];
    foreach (['push', 'email'] as $channel) {
        [$sql, $params] = profile_audience_where($channel, $topic);
        $stmt = db()->prepare("SELECT COUNT(*) AS n FROM app_profiles WHERE $sql");
        $stmt->execute($params);
        $out[$channel] = (int) ($stmt->fetch()['n'] ?? 0);
    }
    return $out;
}

/** The audience per channel and topic in one go: ['push' => [0 => all, 1 => breaking, 2 => live, 4 => shows], 'email' => [...]]. */
function profile_audience_table(): array
{
    $out = [];
    foreach (['push', 'email'] as $channel) {
        [$sql] = profile_audience_where($channel, 0);
        $r = db()->query(
            "SELECT COUNT(*) AS everyone, SUM((topics & 1) <> 0) AS t1, SUM((topics & 2) <> 0) AS t2, SUM((topics & 4) <> 0) AS t4
             FROM app_profiles WHERE $sql"
        )->fetch() ?: [];
        $out[$channel] = [0 => (int) ($r['everyone'] ?? 0), 1 => (int) ($r['t1'] ?? 0), 2 => (int) ($r['t2'] ?? 0), 4 => (int) ($r['t4'] ?? 0)];
    }
    return $out;
}

/**
 * The next people an update reaches: up to $limit rows with id greater than $afterId, in id order (id, display_name, email, push_token,
 * unsubscribe_token, topics). $channel is 'push' or 'email'; $topic 0 = everyone who opted in.
 */
function profile_audience(string $channel, int $topic, int $afterId = 0, int $limit = 100): array
{
    [$sql, $params] = profile_audience_where($channel === 'push' ? 'push' : 'email', $topic);
    $stmt = db()->prepare(
        "SELECT id, display_name, email, push_token, unsubscribe_token, topics FROM app_profiles WHERE id > ? AND $sql ORDER BY id ASC LIMIT ?"
    );
    $stmt->execute(array_merge([$afterId], $params, [max(1, $limit)]));
    return $stmt->fetchAll();
}

// ── CSV export (admin only) ────────────────────────────────────

/** The header row of the CSV export. */
function profile_export_header(): array
{
    return ['name', 'email', 'email_confirmed', 'phone', 'push', 'email_updates', 'topics', 'created'];
}

/** A cell that a spreadsheet will not run as a formula: a leading = + - @ tab or CR gets an apostrophe in front. */
function profile_csv_safe(?string $value): string
{
    $value = (string) $value;
    return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
}

/** One export row for a profile row. */
function profile_export_row(array $r): array
{
    return [
        profile_csv_safe($r['display_name']),
        profile_csv_safe($r['email']),
        !empty($r['email_verified_at']) ? 'yes' : 'no',
        profile_csv_safe($r['phone']),
        ($r['consent_push'] && ($r['push_token'] ?? '') !== '') ? 'yes' : 'no',
        ($r['consent_email'] && !empty($r['email_verified_at'])) ? 'yes' : 'no',
        profile_topics_label((int) $r['topics']),
        $r['created_at'],
    ];
}

/** Every profile as export rows, read 500 at a time so a long list does not fill the memory. Use with foreach after the header. */
function profile_export_rows(): Generator
{
    $after = 0;
    while (true) {
        $stmt = db()->prepare('SELECT ' . PROFILE_LIST_COLUMNS . ' FROM app_profiles WHERE id > ? ORDER BY id ASC LIMIT 500');
        $stmt->execute([$after]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return;
        }
        foreach ($rows as $r) {
            $after = (int) $r['id'];
            yield profile_export_row($r);
        }
    }
}

// ── updates (what staff send) ──────────────────────────────────

/**
 * Checks where an update opens. $type is home | live | article (slug) | show (id) | premiere (id) | url (an https://oromatv.com address).
 * Returns [type, value, error|null]; the value is cleaned (an id as digits, a slug as given, '' for home and live).
 */
function profile_validate_link(string $type, string $value): array
{
    $value = trim($value);
    switch ($type) {
        case 'home':
        case 'live':
            return [$type, '', null];
        case 'article':
            return preg_match('/\A[A-Za-z0-9-]{1,190}\z/', $value) ? [$type, $value, null] : [$type, '', 'Please choose the story to open.'];
        case 'show':
        case 'premiere':
            return preg_match('/\A[1-9][0-9]{0,9}\z/', $value) ? [$type, $value, null] : [$type, '', 'Please choose what to open.'];
        case 'url':
            $parts = parse_url($value);
            $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
            $ok = is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ($host === 'oromatv.com' || $host === 'www.oromatv.com')
                && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
                && strlen($value) <= 255 && !preg_match('/[\x00-\x20\x7F"<>\\\\]/', $value);
            return $ok ? [$type, $value, null] : [$type, '', 'Please enter an address that starts with https://oromatv.com/'];
    }
    return [$type, '', 'Please choose where the update opens.'];
}

/** The data part of a push message for a (validated) link, as the app reads it. */
function profile_link_data(string $type, string $value): array
{
    return match ($type) {
        'article'  => ['type' => 'article', 'slug' => $value],
        'show'     => ['type' => 'show', 'id' => (int) $value],
        'premiere' => ['type' => 'premiere', 'id' => (int) $value],
        'live'     => ['type' => 'live'],
        'url'      => ['type' => 'url', 'url' => $value],
        default    => ['type' => 'home'],
    };
}

/** The web address behind a (validated) link, for the button in an e-mail. */
function profile_link_url(string $type, string $value): string
{
    $site = profile_site_url();
    return match ($type) {
        'article'  => $site . '/article.php?slug=' . rawurlencode($value),
        'show'     => $site . '/show.php?id=' . (int) $value,
        'premiere' => $site . '/watch.php?p=' . (int) $value,
        'live'     => $site . '/watch.php',
        'url'      => $value,
        default    => $site . '/',
    };
}

/**
 * Checks the words of an update for one channel. Returns field => message (empty when fine): title 1-65 characters, message 1-178 for a
 * push or 1-5000 for an e-mail, a valid link, a valid topic.
 */
function profile_update_errors(string $kind, string $title, string $body, string $linkType, string $linkValue, int $topic): array
{
    $errors = [];
    $title = trim($title);
    $body = trim($body);
    if (!mb_check_encoding($title . $body, 'UTF-8') || $title === '' || mb_strlen($title) > 65 || preg_match('/[\x00-\x1F\x7F]/', $title)) {
        $errors['title'] = 'Please enter a title of up to 65 characters.';
    }
    $max = $kind === 'push' ? 178 : 5000;
    if ($body === '' || mb_strlen($body) > $max) {
        $errors['body'] = 'Please enter a message of up to ' . $max . ' characters.';
    }
    [, , $linkError] = profile_validate_link($linkType, $linkValue);
    if ($linkError !== null) {
        $errors['link'] = $linkError;
    }
    if (!profile_valid_topic($topic)) {
        $errors['topic'] = 'Please choose who this is for.';
    }
    return $errors;
}

/** Writes one row to the update history (recipients/sent/failed start at the numbers given) and returns its id. */
function profile_update_create(string $kind, string $title, string $body, string $linkType, string $linkValue, int $topic, ?int $createdBy, int $recipients = 0): int
{
    db()->prepare(
        'INSERT INTO profile_updates (kind, title, body, link_type, link_value, topic, recipients, sent, failed, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)'
    )->execute([$kind === 'push' ? 'push' : 'email', mb_substr(trim($title), 0, 120), trim($body), $linkType, $linkValue, $topic, $recipients, $createdBy, profile_now()]);
    return (int) db()->lastInsertId();
}

function profile_update_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM profile_updates WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Adds to an update's counters (recipients, sent, failed). */
function profile_update_bump(int $id, int $recipients, int $sent, int $failed): void
{
    db()->prepare('UPDATE profile_updates SET recipients = recipients + ?, sent = sent + ?, failed = failed + ? WHERE id = ?')->execute([$recipients, $sent, $failed, $id]);
}

/** The newest updates, for the admin history table. */
function profile_update_history(int $limit = 50): array
{
    $stmt = db()->prepare('SELECT id, kind, title, link_type, topic, recipients, sent, failed, created_by, created_at FROM profile_updates ORDER BY id DESC LIMIT ?');
    $stmt->execute([max(1, min(500, $limit))]);
    return $stmt->fetchAll();
}

/**
 * The daily limit for $kind ('push' or 'email'): ['cap' => limit, 'used' => updates sent today, 'left' => still allowed, 'reached' => bool].
 * "Today" is the site's calendar day. Test sends are not logged as updates, so they never count. The limits are the settings
 * profile_push_daily_cap (default 3) and profile_email_daily_cap (default 2).
 */
function profile_update_cap_status(string $kind): array
{
    $kind = $kind === 'push' ? 'push' : 'email';
    $cap = profile_setting_int($kind === 'push' ? 'profile_push_daily_cap' : 'profile_email_daily_cap', $kind === 'push' ? 3 : 2, 1, 50);
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM profile_updates WHERE kind = ? AND created_at >= ?');
    $stmt->execute([$kind, date('Y-m-d 00:00:00')]);
    $used = (int) ($stmt->fetch()['n'] ?? 0);
    return ['cap' => $cap, 'used' => $used, 'left' => max(0, $cap - $used), 'reached' => $used >= $cap];
}
