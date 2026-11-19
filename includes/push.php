<?php
/**
 * Push notifications through the Expo push service (https://docs.expo.dev/push-notifications/sending-notifications/).
 * Opt-in only (Apple 4.5.4): a phone is reached only when its owner switched notifications on (app_profiles.consent_push) and chose the
 * topic. Staff send from admin/updates.php; every real send is logged in profile_updates and counts toward the daily cap.
 *
 *   push_send_batch($messages)          sends messages (100 per request) and cleans up tokens Expo says are dead
 *   push_start_update(...)              checks an update, enforces the daily cap and logs it
 *   push_run_update($id, $afterId)      sends a logged update in chunks within a time budget (resumable, so a big audience never times out)
 *   push_send_update(...)               start + run in one go, for small audiences
 *   push_send_test($profileId, ...)     one message to one phone, not logged and not counted
 *
 * Tests point the sender at a local fake server by defining PUSH_API_URL before this file is used; nothing else changes the address.
 */
require_once __DIR__ . '/profiles.php';

const PUSH_DEFAULT_URL = 'https://exp.host/--/api/v2/push/send';
const PUSH_TOKEN_PATTERN = '/\A(?:Expo|Exponent)PushToken\[[A-Za-z0-9_-]+\]\z/';

/** Where messages are posted: Expo's service, unless a test defined PUSH_API_URL. */
function push_endpoint(): string
{
    return defined('PUSH_API_URL') ? (string) PUSH_API_URL : PUSH_DEFAULT_URL;
}

/** Is this the shape of an Expo push token (ExponentPushToken[...] or ExpoPushToken[...])? */
function push_valid_token(string $token): bool
{
    return strlen($token) <= 255 && preg_match(PUSH_TOKEN_PATTERN, $token) === 1;
}

/** Forget a push token Expo says is dead (the app was uninstalled or the token expired); the app registers a new one when it is next opened. */
function push_forget_token(string $token): void
{
    db()->prepare('UPDATE app_profiles SET push_token = NULL, push_platform = NULL, push_updated_at = ? WHERE push_token = ?')->execute([profile_now(), $token]);
}

/**
 * POSTs up to 100 messages to the push service. Returns [tickets|null, error|null]: the "data" list (one ticket per message, in
 * order), or null and a short reason when the request as a whole failed. The access token (setting expo_access_token) is optional.
 */
function push_post(array $messages): array
{
    if (!function_exists('curl_init')) {
        return [null, 'curl is not available'];
    }
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    $access = trim((string) get_setting('expo_access_token', ''));
    if ($access !== '' && !preg_match('/[\x00-\x1F\x7F\s]/', $access)) {
        $headers[] = 'Authorization: Bearer ' . $access;
    }
    $json = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $ch = curl_init(push_endpoint());
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    unset($ch);

    if ($body === false) {
        return [null, 'could not reach the push service' . ($curlError !== '' ? ' (' . $curlError . ')' : '')];
    }
    $decoded = json_decode((string) $body, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
        $detail = is_array($decoded) && isset($decoded['errors'][0]['message']) ? ': ' . mb_substr((string) $decoded['errors'][0]['message'], 0, 120) : '';
        return [null, 'push service answered HTTP ' . $status . $detail];
    }
    // Expo answers a single message with an object, not a list.
    $data = $decoded['data'];
    if (isset($data['status'])) {
        $data = [$data];
    }
    return [array_values($data), null];
}

/**
 * Sends messages. Each message: to (an Expo push token), title, body, data (an array: see profile_link_data); sound, channel and the
 * length limits are set here. Returns ['sent' => n, 'failed' => n, 'invalid' => n, 'errors' => [first few reasons]]:
 *   sent     Expo accepted the message
 *   failed   anything else (a bad token, the service down, a ticket with an error); 'invalid' of them were tokens Expo said are dead
 *            (DeviceNotRegistered), which are also cleared from their profiles
 * Receipts are not read.
 */
function push_send_batch(array $messages): array
{
    $result = ['sent' => 0, 'failed' => 0, 'invalid' => 0, 'errors' => []];
    $valid = [];
    foreach ($messages as $m) {
        $to = is_array($m) && is_string($m['to'] ?? null) ? $m['to'] : '';
        if (!push_valid_token($to)) {
            $result['failed']++;
            $result['errors'][] = 'a message had no valid push token';
            continue;
        }
        $data = is_array($m['data'] ?? null) && $m['data'] ? $m['data'] : ['type' => 'home'];
        $valid[] = [
            'to'        => $to,
            'title'     => mb_substr((string) ($m['title'] ?? ''), 0, 65),
            'body'      => mb_substr((string) ($m['body'] ?? ''), 0, 178),
            'sound'     => 'default',
            'channelId' => 'updates',
            'data'      => $data,
        ];
    }
    foreach (array_chunk($valid, 100) as $chunk) {
        [$tickets, $error] = push_post($chunk);
        if ($tickets === null) {
            $result['failed'] += count($chunk);
            $result['errors'][] = (string) $error;
            error_log('[push] ' . $error);
            continue;
        }
        foreach ($chunk as $i => $msg) {
            $ticket = $tickets[$i] ?? null;
            if (is_array($ticket) && ($ticket['status'] ?? '') === 'ok') {
                $result['sent']++;
                continue;
            }
            $result['failed']++;
            $code = is_array($ticket) ? (string) ($ticket['details']['error'] ?? '') : '';
            if ($code === 'DeviceNotRegistered') {
                $result['invalid']++;
                push_forget_token($msg['to']);
            } else {
                $result['errors'][] = $code !== '' ? $code : (is_array($ticket) ? mb_substr((string) ($ticket['message'] ?? 'error'), 0, 120) : 'no ticket');
            }
        }
    }
    $result['errors'] = array_slice(array_values(array_unique($result['errors'])), 0, 5);
    return $result;
}

/** How many pushes may still go out today: ['cap', 'used', 'left', 'reached'] (see profile_update_cap_status). */
function push_cap_status(): array
{
    return profile_update_cap_status('push');
}

/** The number of phones a push for $topic (0 = everyone who opted in) would reach. */
function push_audience_count(int $topic): int
{
    return profile_audience_counts($topic)['push'];
}

/**
 * Checks a push update, enforces the daily cap and logs it. Returns
 *   ['ok' => true, 'update_id' => id, 'recipients' => n]   (send it with push_run_update)
 *   ['ok' => false, 'error' => message, 'errors' => field => message]   (nothing was logged)
 * $linkType/$linkValue: see profile_validate_link. $topic: 0 = everyone who opted in, or one topic bit.
 */
function push_start_update(string $title, string $body, string $linkType, string $linkValue, int $topic, ?int $createdBy): array
{
    $errors = profile_update_errors('push', $title, $body, $linkType, $linkValue, $topic);
    if ($errors) {
        return ['ok' => false, 'error' => reset($errors), 'errors' => $errors];
    }
    $cap = push_cap_status();
    if ($cap['reached']) {
        return ['ok' => false, 'error' => 'The daily limit of ' . $cap['cap'] . ' notification' . ($cap['cap'] === 1 ? '' : 's') . ' has been reached. Please try again tomorrow, so people are not flooded.', 'errors' => []];
    }
    $count = push_audience_count($topic);
    if ($count < 1) {
        return ['ok' => false, 'error' => 'No phones have asked for this kind of notification yet.', 'errors' => []];
    }
    [$type, $value] = profile_validate_link($linkType, $linkValue);
    $id = profile_update_create('push', $title, $body, $type, $value, $topic, $createdBy, $count);
    return ['ok' => true, 'update_id' => $id, 'recipients' => $count];
}

/**
 * Sends a logged push update to the people after $afterId (0 on the first call), chunk by chunk, until all are done or $maxSeconds
 * have passed. Returns ['update_id', 'done' => bool, 'last_id' => the id to pass as $afterId next time, 'sent', 'failed' => totals so far,
 * 'recipients', 'errors']. Call again with last_id while done is false. An update that was already fully sent is never sent twice.
 */
function push_run_update(int $updateId, int $afterId = 0, int $maxSeconds = 20): array
{
    @set_time_limit($maxSeconds + 30);
    $update = profile_update_get($updateId);
    if (!$update || $update['kind'] !== 'push') {
        return ['update_id' => $updateId, 'done' => true, 'last_id' => $afterId, 'sent' => 0, 'failed' => 0, 'recipients' => 0, 'errors' => ['That update does not exist.']];
    }
    $deadline = microtime(true) + max(1, $maxSeconds);
    $data = profile_link_data((string) $update['link_type'], (string) $update['link_value']);
    $last = $afterId;
    $errors = [];
    $done = ((int) $update['sent'] + (int) $update['failed']) >= (int) $update['recipients'];

    while (!$done) {
        $people = profile_audience('push', (int) $update['topic'], $last, 100);
        if (!$people) {
            $done = true;
            break;
        }
        $messages = [];
        foreach ($people as $p) {
            $last = (int) $p['id'];
            $messages[] = ['to' => $p['push_token'], 'title' => $update['title'], 'body' => $update['body'], 'data' => $data];
        }
        $r = push_send_batch($messages);
        profile_update_bump($updateId, 0, $r['sent'], $r['failed']);
        $errors = array_merge($errors, $r['errors']);
        if (count($people) < 100) {
            $done = true;
        } elseif (microtime(true) > $deadline) {
            break;
        }
    }
    $fresh = profile_update_get($updateId) ?: $update;
    return [
        'update_id'  => $updateId,
        'done'       => $done,
        'last_id'    => $last,
        'sent'       => (int) $fresh['sent'],
        'failed'     => (int) $fresh['failed'],
        'recipients' => (int) $fresh['recipients'],
        'errors'     => array_slice(array_values(array_unique($errors)), 0, 5),
    ];
}

/**
 * Start and send a push update in one call (for an audience that fits the time budget; otherwise keep calling push_run_update with
 * last_id). Returns the push_start_update failure, or the push_run_update result with 'ok' => true.
 */
function push_send_update(string $title, string $body, string $linkType, string $linkValue, int $topic, ?int $createdBy): array
{
    $start = push_start_update($title, $body, $linkType, $linkValue, $topic, $createdBy);
    if (!$start['ok']) {
        return $start;
    }
    return ['ok' => true] + push_run_update($start['update_id']);
}

/**
 * One notification to one profile's phone, to check how it looks (admin "Send test to me"). It is not logged and does not count toward
 * the daily cap. The profile must have switched notifications on and have a phone registered. Returns ['ok' => bool, 'error' => ...].
 */
function push_send_test(int $profileId, string $title, string $body, string $linkType, string $linkValue): array
{
    $errors = profile_update_errors('push', $title, $body, $linkType, $linkValue, 0);
    if ($errors) {
        return ['ok' => false, 'error' => reset($errors)];
    }
    $p = profile_get($profileId);
    if (!$p || !$p['consent_push'] || ($p['push_token'] ?? '') === '' || !empty($p['blocked_at'])) {
        return ['ok' => false, 'error' => 'That profile has not switched notifications on from a phone.'];
    }
    [$type, $value] = profile_validate_link($linkType, $linkValue);
    $r = push_send_batch([['to' => $p['push_token'], 'title' => $title, 'body' => $body, 'data' => profile_link_data($type, $value)]]);
    return $r['sent'] === 1
        ? ['ok' => true, 'error' => null]
        : ['ok' => false, 'error' => $r['invalid'] > 0 ? 'That phone is no longer registered for notifications.' : 'The notification could not be sent' . ($r['errors'] ? ' (' . $r['errors'][0] . ')' : '') . '.'];
}
