<?php
/**
 * Oroma TV mobile API — "My profile" routes. Loaded after api/bootstrap.php (json_out, json_error, request_body, body_text, rate_limit,
 * api_guard_write_origin come from there). A profile belongs to one phone and has no login: the phone is given a secret token when the
 * profile is created and sends it in the X-Profile-Token header afterwards. The token is shown once; only its SHA-256 is stored.
 *
 *   POST   /api/v1/profile               route_profile_create        201 { message, data: { profile, token } } · 409 profile_exists · 422 errors
 *   GET    /api/v1/profile               route_profile_get           200 { data: { profile } } · 401 bad_token
 *   PATCH  /api/v1/profile               route_profile_update        200 { data: { profile } } · 403 profile_blocked
 *   POST   /api/v1/profile/photo         route_profile_photo         multipart field "photo"; the picture waits for staff approval
 *   DELETE /api/v1/profile/photo         route_profile_photo_delete
 *   POST   /api/v1/profile/push-token    route_profile_push_token    { token, platform: ios|android, consent_push? }
 *   POST   /api/v1/profile/email/resend  route_profile_email_resend
 *   DELETE /api/v1/profile               route_profile_delete        200 { message }
 *
 * Errors keep the API's shape { message, errors?: { field: [text] } } and add "code" for 401 (bad_token), 403 (profile_blocked) and
 * 409 (profile_exists). profile_from_request() is for other API code (the comment route) that wants to know whose phone this is.
 * Answers about a profile are never cacheable (a CDN must not hand one person's profile to another).
 */

require_once __DIR__ . '/../includes/profiles.php';
require_once __DIR__ . '/../includes/mail-updates.php';
require_once __DIR__ . '/../includes/push.php';

// ── output ─────────────────────────────────────────────────────

/** Like json_out(), but private and uncacheable: these answers hold a person's own details. */
function profile_out(array $payload, int $status = 200): void
{
    api_headers();
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Vary: X-Profile-Token', false);
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** An error answer { message, errors?, code? }. */
function profile_error(string $message, int $status, array $errors = [], ?string $code = null): void
{
    $body = ['message' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    if ($code !== null) {
        $body['code'] = $code;
    }
    profile_out($body, $status);
}

/** The profile object in the standard { data: { profile } } answer; $extra goes beside "data" (a message), $dataExtra inside it (the token). */
function profile_out_profile(int $id, int $status = 200, array $extra = [], array $dataExtra = []): void
{
    $row = profile_get($id);
    if (!$row) {
        profile_error('Your profile could not be verified on this phone.', 401, [], 'bad_token');
    }
    profile_out($extra + ['data' => ['profile' => profile_serialize($row)] + $dataExtra], $status);
}

// ── who is asking ──────────────────────────────────────────────

/** The secret token the phone sent in X-Profile-Token ('' when there is none). */
function profile_request_token(): string
{
    $v = $_SERVER['HTTP_X_PROFILE_TOKEN'] ?? '';
    if ($v === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'X-Profile-Token') === 0) {
                $v = $value;
                break;
            }
        }
    }
    return is_string($v) ? trim($v) : '';
}

/**
 * The profile whose token came with this request, or null (no token, a wrong one, profiles not set up, or - unless $allowBlocked - a blocked
 * profile). Never throws and never answers, so the comment route can call it and carry on without a profile.
 */
function profile_from_request(bool $allowBlocked = false): ?array
{
    $token = profile_request_token();
    if ($token === '') {
        return null;
    }
    try {
        ensure_profile_schema();
        if (!profile_schema_ready()) {
            return null;
        }
        $row = profile_find_by_token($token);
    } catch (Throwable $e) {
        error_log('[profiles] ' . $e->getMessage());
        return null;
    }
    if ($row && !$allowBlocked && !empty($row['blocked_at'])) {
        return null;
    }
    return $row;
}

/** 503 when the profile tables cannot be made (the database user may not CREATE); otherwise makes sure they exist. */
function profile_api_ready(): void
{
    ensure_profile_schema();
    if (!profile_schema_ready()) {
        profile_error('Profiles are temporarily unavailable. Please try again later.', 503);
    }
}

/**
 * The profile for this request, or a 401 (the same answer for a missing, malformed, wrong or unknown token) or, for a blocked profile
 * on a route that changes something, a 403.
 */
function profile_api_auth(bool $allowBlocked = false): array
{
    $row = profile_from_request(true);
    if (!$row) {
        profile_error('Your profile could not be verified on this phone.', 401, [], 'bad_token');
    }
    if (!$allowBlocked && !empty($row['blocked_at'])) {
        profile_error('This profile cannot be changed.', 403, [], 'profile_blocked');
    }
    return $row;
}

/** A php.ini size ("8M") in bytes; 0 when it is unset or unlimited. */
function profile_api_ini_bytes(string $key): int
{
    $v = trim((string) ini_get($key));
    if ($v === '' || $v === '-1') {
        return 0;
    }
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n << 30,
        'm' => $n << 20,
        'k' => $n << 10,
        default => $n,
    };
}

// ── routes ─────────────────────────────────────────────────────

/**
 * POST { viewer_id, display_name, email?, phone?, topics?: { breaking, live, shows }, consent_push?, consent_email?, accepted_terms: true }
 * Makes the profile and returns it with its secret token (this is the only time the token is shown).
 */
function route_profile_create(): void
{
    api_guard_write_origin();
    rate_limit('profile-try', 30, 3600); // generous: every attempt runs the name filter, so even failed ones are counted
    profile_api_ready();
    $in = request_body();

    $viewerId = ugc_clean_viewer_id(body_text($in, 'viewer_id'));
    $name     = body_text($in, 'display_name');
    $email    = body_text($in, 'email');
    $phone    = body_text($in, 'phone');

    $errors = [];
    if ($viewerId === null) {
        $errors['viewer_id'] = ['Something went wrong with this phone\'s ID. Please restart the app and try again.'];
    }
    if (profile_bool($in['accepted_terms'] ?? null) !== true) {
        $errors['accepted_terms'] = ['Please agree to the Terms of Use to save your profile.'];
    }
    [$cleanName, $nameError] = profile_validate_name($name);
    if ($nameError !== null) {
        $errors['display_name'] = [$nameError];
    }
    [$cleanEmail, $emailError] = profile_validate_email($email);
    if ($emailError !== null) {
        $errors['email'] = [$emailError];
    }
    [$cleanPhone, $phoneError] = profile_normalise_phone($phone);
    if ($phoneError !== null) {
        $errors['phone'] = [$phoneError];
    }
    $topics = PROFILE_TOPICS_ALL;
    if (array_key_exists('topics', $in)) {
        [$topics, $topicError] = profile_topics_from_input($in['topics'], PROFILE_TOPICS_ALL);
        if ($topicError !== null) {
            $errors['topics'] = [$topicError];
        }
    }
    $consent = ['consent_push' => false, 'consent_email' => false];
    foreach ($consent as $key => $_) {
        if (array_key_exists($key, $in) && $in[$key] !== null) {
            $b = profile_bool($in[$key]);
            if ($b === null) {
                $errors[$key] = ['This must be true or false.'];
            } else {
                $consent[$key] = $b;
            }
        }
    }
    if ($consent['consent_email'] && $cleanEmail === null && !isset($errors['email'])) {
        $errors['consent_email'] = ['Add an email address to get email updates.'];
    }
    if ($errors) {
        profile_error(reset($errors)[0], 422, $errors);
    }

    if (profile_find_by_viewer((string) $viewerId)) {
        profile_error('A profile already exists for this phone.', 409, [], 'profile_exists'); // nothing about it is revealed
    }
    rate_limit('profile-create', 5, 3600); // only real creations are counted from here on
    $made = profile_create([
        'viewer_id' => $viewerId, 'display_name' => $cleanName, 'email' => $cleanEmail, 'phone' => $cleanPhone, 'topics' => $topics,
        'consent_push' => $consent['consent_push'], 'consent_email' => $consent['consent_email'],
    ]);
    if ($made === null) { // another request made it a moment ago
        profile_error('A profile already exists for this phone.', 409, [], 'profile_exists');
    }
    if ($cleanEmail !== null) {
        try {
            profile_queue_verification($made['profile']);
        } catch (Throwable $e) {
            error_log('[profiles] confirmation mail: ' . $e->getMessage());
        }
    }
    profile_out_profile((int) $made['profile']['id'], 201, ['message' => 'Your profile has been saved.'], ['token' => $made['token']]);
}

/** GET — the profile of this phone. Also the way the app checks that its token still works. */
function route_profile_get(): void
{
    profile_api_ready();
    $p = profile_api_auth(true);
    profile_touch_seen($p);
    profile_out(['data' => ['profile' => profile_serialize($p)]]);
}

/**
 * PATCH { display_name?, email?, phone?, topics?, consent_push?, consent_email? } — only the fields sent change.
 * A new or changed address is unconfirmed until its link is used (a mail is sent); consent_email needs an address; switching
 * consent_push off forgets the push token. A blocked profile gets 403 profile_blocked.
 */
function route_profile_update(): void
{
    api_guard_write_origin();
    rate_limit('profile-patch', 60, 3600);
    profile_api_ready();
    $p = profile_api_auth();
    $in = request_body();
    $known = ['display_name', 'email', 'phone', 'topics', 'consent_push', 'consent_email'];
    if (!array_intersect($known, array_keys($in))) {
        profile_error('Please send at least one field to change.', 422, ['profile' => ['Nothing to change.']]);
    }

    $errors = [];
    $set = [];
    $now = profile_now();
    $finalEmail = ($p['email'] ?? '') !== '' ? (string) $p['email'] : null;
    $emailChanged = false;

    if (array_key_exists('display_name', $in)) {
        [$cleanName, $err] = profile_validate_name(body_text($in, 'display_name'));
        if ($err !== null) {
            $errors['display_name'] = [$err];
        } else {
            $set['display_name'] = $cleanName;
        }
    }
    if (array_key_exists('email', $in)) {
        [$cleanEmail, $err] = profile_validate_email(body_text($in, 'email'));
        if ($err !== null) {
            $errors['email'] = [$err];
        } elseif ($cleanEmail !== $finalEmail) {
            $emailChanged = true;
            $finalEmail = $cleanEmail;
            $set['email'] = $cleanEmail;
            $set['email_verified_at'] = null;
            $set['email_token_hash'] = null;
            $set['email_token_expires'] = null;
        }
    }
    if (array_key_exists('phone', $in)) {
        [$cleanPhone, $err] = profile_normalise_phone(body_text($in, 'phone'));
        if ($err !== null) {
            $errors['phone'] = [$err];
        } else {
            $set['phone'] = $cleanPhone;
        }
    }
    if (array_key_exists('topics', $in)) {
        [$bits, $err] = profile_topics_from_input($in['topics'], (int) $p['topics']);
        if ($err !== null) {
            $errors['topics'] = [$err];
        } else {
            $set['topics'] = $bits;
        }
    }
    if (array_key_exists('consent_push', $in)) {
        $b = profile_bool($in['consent_push']);
        if ($b === null) {
            $errors['consent_push'] = ['This must be true or false.'];
        } elseif ($b) {
            $set['consent_push'] = 1;
            $set['consent_at'] = $now;
        } else {
            $set['consent_push'] = 0;
            $set['push_token'] = null;
            $set['push_platform'] = null;
            $set['push_updated_at'] = $now;
        }
    }
    if (array_key_exists('consent_email', $in)) {
        $b = profile_bool($in['consent_email']);
        if ($b === null) {
            $errors['consent_email'] = ['This must be true or false.'];
        } elseif ($b && $finalEmail === null) {
            $errors['consent_email'] = ['Add an email address to get email updates.'];
        } else {
            $set['consent_email'] = $b ? 1 : 0;
            if ($b) {
                $set['consent_at'] = $now;
            }
        }
    } elseif ($finalEmail === null && (int) $p['consent_email'] === 1) {
        $set['consent_email'] = 0; // the address was removed, so the e-mail updates stop with it
    }
    if ($errors) {
        profile_error(reset($errors)[0], 422, $errors);
    }

    if ($set) {
        profile_update((int) $p['id'], $set);
    }
    $fresh = profile_get((int) $p['id']);
    if ($emailChanged && $fresh && ($fresh['email'] ?? '') !== '') {
        try {
            profile_queue_verification($fresh);
        } catch (Throwable $e) {
            error_log('[profiles] confirmation mail: ' . $e->getMessage());
        }
    }
    profile_out_profile((int) $p['id']);
}

/**
 * POST multipart/form-data, field "photo" (JPEG, PNG or WebP, at most 4 MB, at least 128 x 128). The picture is cropped to a square,
 * resized to 512 x 512 and re-saved as a JPEG (which drops EXIF/GPS); it is 'pending' and shown only to its owner until staff approve it.
 * 10 a day per profile and 30 a day per network.
 */
function route_profile_photo(): void
{
    api_guard_write_origin();
    rate_limit('profile-photo-ip', 30, 86400);
    profile_api_ready();
    $p = profile_api_auth();
    $wait = profile_rate_hit('photo|' . (int) $p['id'], 10, 86400);
    if ($wait > 0) {
        header('Retry-After: ' . $wait);
        profile_error('You have changed your photo too many times today. Please try again tomorrow.', 429);
    }

    $tooBig = ['photo' => ['That photo is too large. Please choose one under 4 MB.']];
    $postMax = profile_api_ini_bytes('post_max_size');
    if ($postMax > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMax) {
        profile_error($tooBig['photo'][0], 413, $tooBig); // PHP threw the whole body away
    }
    $file = $_FILES['photo'] ?? null;
    if (!is_array($file) || is_array($file['tmp_name'] ?? null)) {
        profile_error('Please choose a photo.', 422, ['photo' => ['Please choose a photo.']]);
    }
    $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        profile_error($tooBig['photo'][0], 413, $tooBig);
    }
    if ($code !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        profile_error('Your photo did not upload. Please try again.', 422, ['photo' => ['Your photo did not upload. Please try again.']]);
    }

    $res = profile_set_photo((int) $p['id'], (string) $file['tmp_name']);
    if (!$res['ok']) {
        profile_error($res['error'], (int) $res['status'], ['photo' => [$res['error']]]);
    }
    profile_out_profile((int) $p['id']);
}

/** DELETE — removes the picture and its file; the photo status goes back to 'none'. */
function route_profile_photo_delete(): void
{
    api_guard_write_origin();
    rate_limit('profile-patch', 60, 3600);
    profile_api_ready();
    $p = profile_api_auth(true); // a blocked person may still take their own picture away
    profile_remove_photo((int) $p['id']);
    profile_out_profile((int) $p['id']);
}

/**
 * POST { token, platform: ios|android, consent_push? } — stores the phone's Expo push token. Notifications are switched on (and the
 * phone starts getting updates) only when the body also says consent_push true, which the app sends after the person turned the switch on.
 */
function route_profile_push_token(): void
{
    api_guard_write_origin();
    rate_limit('profile-push', 30, 3600);
    profile_api_ready();
    $p = profile_api_auth();
    $in = request_body();
    $token = body_text($in, 'token');
    $platform = strtolower(body_text($in, 'platform'));

    $errors = [];
    if (!push_valid_token($token)) {
        $errors['token'] = ['This does not look like a push token.'];
    }
    if (!in_array($platform, ['ios', 'android'], true)) {
        $errors['platform'] = ['The platform must be ios or android.'];
    }
    $consent = null;
    if (array_key_exists('consent_push', $in) && $in['consent_push'] !== null) {
        $consent = profile_bool($in['consent_push']);
        if ($consent === null) {
            $errors['consent_push'] = ['This must be true or false.'];
        }
    }
    if ($errors) {
        profile_error(reset($errors)[0], 422, $errors);
    }
    profile_set_push_token((int) $p['id'], $token, $platform, $consent === true ? true : null);
    profile_out_profile((int) $p['id']);
}

/** POST — sends the confirmation mail again (5 an hour; at most 5 a day per profile). */
function route_profile_email_resend(): void
{
    api_guard_write_origin();
    rate_limit('profile-resend', 5, 3600);
    profile_api_ready();
    $p = profile_api_auth();
    if (($p['email'] ?? '') === '') {
        profile_error('Add an email address first.', 422, ['email' => ['Add an email address first.']]);
    }
    if (!empty($p['email_verified_at'])) {
        profile_out_profile((int) $p['id'], 200, ['message' => 'Your email address is already confirmed.']);
    }
    try {
        $result = profile_queue_verification($p);
    } catch (Throwable $e) {
        error_log('[profiles] confirmation mail: ' . $e->getMessage());
        profile_error('We could not send the email just now. Please try again later.', 503);
    }
    if ($result === 'profile_cap') {
        profile_error('We already sent several confirmation emails today. Please check your inbox and spam folder, or try again tomorrow.', 429);
    }
    // 'address_cap' is answered like a success: it must not tell this person that others used the same address
    profile_out_profile((int) $p['id'], 200, ['message' => 'We have sent a confirmation email. Please check your inbox.']);
}

/** DELETE — removes the profile, its picture file and queued mails; the comments stay, without the picture. */
function route_profile_delete(): void
{
    api_guard_write_origin();
    rate_limit('profile-delete', 20, 3600);
    profile_api_ready();
    $p = profile_api_auth(true);
    profile_delete((int) $p['id']);
    profile_out(['message' => 'Your profile has been deleted.']);
}
