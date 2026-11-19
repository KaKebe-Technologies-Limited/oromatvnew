<?php
/**
 * E-mail for app profiles: the confirmation mail and the updates staff send. Everything goes through the table profile_email_queue and is
 * sent in small batches (hosting limits how many mails an hour may leave), by the admin's "Send now" or by cron/process-email-queue.php.
 *
 *   profile_queue_verification($profile)    queue (and try at once) the "confirm your email" mail
 *   profile_email_start_update(...)         check an e-mail update, enforce the daily cap, log it and queue one mail per person
 *   profile_queue_update($updateId, ...)    the queueing part of that, for callers that logged the update themselves
 *   profile_process_queue($limit, $afterId) send the next batch
 *   mail_updates_send_test(...)             one test mail to an address, not queued, not logged
 *
 * Only profiles with a CONFIRMED address, e-mail updates switched on, not blocked, and the topic ticked ever get an update, and this
 * is checked again at the moment of sending (somebody who unsubscribed after the mail was queued gets nothing).
 * Mails are plain mail() messages: UTF-8, multipart text + HTML, every header cleaned of CR/LF. Tests do not send: define
 * PROFILE_MAIL_CAPTURE as a file path and every message is appended to it as one JSON line instead (PROFILE_MAIL_CAPTURE_FAIL, a text
 * to look for in the address, makes those sends fail, to test retries).
 */
require_once __DIR__ . '/profiles.php';

const MAIL_VERIFY_PER_PROFILE_DAY = 5; // confirmation mails per profile in 24 hours
const MAIL_VERIFY_PER_ADDRESS_DAY = 3; // ... and per address, over all profiles: nobody can be mail-bombed through the app
const MAIL_MAX_ATTEMPTS           = 3;
const MAIL_KEEP_DAYS              = 30;

// ── building and sending one message ───────────────────────────

/** A header value on one line: every control character (CR, LF, NUL ...) becomes a space, so nothing typed anywhere can add a header. */
function mail_clean_header(string $value): string
{
    return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
}

/** A header text with non-ASCII letters as RFC 2047 encoded words (folded with CRLF + space), or as it is when it is plain ASCII. */
function mail_encode_header_text(string $text): string
{
    $text = mail_clean_header($text);
    if ($text === '' || preg_match('/\A[\x20-\x7E]*\z/', $text)) {
        return $text;
    }
    $words = [];
    $chunk = '';
    foreach (mb_str_split($text) as $ch) {
        if (strlen($chunk . $ch) > 42) {
            $words[] = $chunk;
            $chunk = '';
        }
        $chunk .= $ch;
    }
    if ($chunk !== '') {
        $words[] = $chunk;
    }
    return implode("\r\n ", array_map(static fn(string $w): string => '=?UTF-8?B?' . base64_encode($w) . '?=', $words));
}

/** Line endings the way mail needs them. */
function mail_crlf(string $s): string
{
    return (string) preg_replace('/\r\n|\r|\n/', "\r\n", $s);
}

/**
 * Sends one message with mail() (or captures it, see the top of this file). $extraHeaders are "Name: value" lines (List-Unsubscribe ...).
 * The address must be a plain, valid one: anything with a control character or a second address is refused. Returns true when the
 * mail system accepted the message.
 */
function mail_send(string $to, string $subject, string $text, string $html, array $extraHeaders = []): bool
{
    [$address] = profile_validate_email($to);
    if ($address === null) {
        error_log('[mail-updates] refused an invalid address');
        return false;
    }
    $boundary = 'oroma_' . bin2hex(random_bytes(12));
    $headers = [
        'From: ' . mail_encode_header_text(SITE_NAME) . ' <' . mail_clean_header(SITE_EMAIL) . '>',
        'Reply-To: ' . mail_clean_header(SITE_EMAIL),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    foreach ($extraHeaders as $line) {
        $headers[] = mail_clean_header((string) $line);
    }
    $body = "This is a multi-part message in MIME format.\r\n"
        . "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode(mail_crlf($text)) . "\r\n"
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode(mail_crlf($html)) . "\r\n"
        . "--$boundary--\r\n";
    $encodedSubject = mail_encode_header_text($subject);
    $headerBlock = implode("\r\n", $headers);

    if (defined('PROFILE_MAIL_CAPTURE')) {
        if (defined('PROFILE_MAIL_CAPTURE_FAIL') && PROFILE_MAIL_CAPTURE_FAIL !== '' && str_contains($address, (string) PROFILE_MAIL_CAPTURE_FAIL)) {
            return false;
        }
        $line = json_encode(['to' => $address, 'subject' => $encodedSubject, 'headers' => $headerBlock, 'body' => $body, 'text' => $text, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return file_put_contents((string) PROFILE_MAIL_CAPTURE, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    }
    $ok = @mail($address, $encodedSubject, $body, $headerBlock);
    if (!$ok) {
        error_log('[mail-updates] mail() did not accept a message');
    }
    return $ok;
}

/** The common look of our mails: maroon bar with the name, the content, a small grey footer. */
function mail_html_page(string $title, string $contentHtml, string $footerHtml): string
{
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f1ec;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#222222;">'
        . '<tr><td style="background:#800000;padding:16px 24px;color:#ffffff;font-size:20px;font-weight:bold;border-radius:8px 8px 0 0;">' . h(SITE_NAME) . '</td></tr>'
        . '<tr><td style="padding:24px;font-size:16px;line-height:1.5;">' . $contentHtml . '</td></tr>'
        . '<tr><td style="padding:16px 24px;background:#faf7f2;font-size:12px;line-height:1.5;color:#666666;border-radius:0 0 8px 8px;">' . $footerHtml . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_button(string $url, string $label): string
{
    return '<p style="margin:24px 0;"><a href="' . h($url) . '" style="display:inline-block;background:#800000;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;">' . h($label) . '</a></p>';
}

/** [subject, text, html] of the confirmation mail for a profile and its fresh token. */
function mail_build_verification(array $profile, string $token): array
{
    $url = profile_site_url() . '/verify-email.php?t=' . $token;
    $name = mail_clean_header((string) $profile['display_name']);
    $subject = 'Confirm your email for ' . SITE_NAME . ' updates';
    $text = "Hello $name,\n\n"
        . 'Please confirm your email address so ' . SITE_NAME . " can send you the updates you asked for:\n\n$url\n\n"
        . 'The link works for ' . PROFILE_EMAIL_TOKEN_HOURS . " hours. If you did not ask for this, just ignore this message: nothing will be sent to you.\n\n"
        . PROFILE_ADDRESS_LINE . "\n";
    $html = mail_html_page(
        $subject,
        '<p style="margin:0 0 12px;">Hello ' . h($name) . ',</p>'
        . '<p style="margin:0 0 12px;">Please confirm your email address so ' . h(SITE_NAME) . ' can send you the updates you asked for.</p>'
        . mail_button($url, 'Confirm my email')
        . '<p style="margin:0;font-size:14px;color:#555555;">The link works for ' . PROFILE_EMAIL_TOKEN_HOURS . ' hours. If the button does not work, copy this address into your browser:<br>' . h($url) . '</p>',
        'If you did not ask for this, just ignore this message: nothing will be sent to you.<br>' . h(PROFILE_ADDRESS_LINE)
    );
    return [$subject, $text, $html];
}

/**
 * [subject, text, html, unsubscribe url|null] of an update mail for one profile (null = a test mail, which has no unsubscribe link).
 * The footer says why the person got it, how to stop, and where we are.
 */
function mail_build_update(array $update, ?array $profile, bool $isTest = false): array
{
    $title = mail_clean_header((string) $update['title']);
    $body = trim((string) $update['body']);
    $link = profile_link_url((string) $update['link_type'], (string) $update['link_value']);
    $unsub = $profile !== null ? profile_site_url() . '/unsubscribe.php?t=' . $profile['unsubscribe_token'] : null;

    $why = $isTest
        ? 'This is a test message from the ' . SITE_NAME . ' admin. It was not sent to the audience.'
        : 'You are getting this email because you chose email updates in the ' . SITE_NAME . ' app.';
    $text = $title . "\n\n" . $body . "\n\nOpen: " . $link . "\n\n-- \n" . $why . "\n"
        . ($unsub !== null ? 'Stop these emails: ' . $unsub . "\n" : '')
        . PROFILE_ADDRESS_LINE . "\n";
    $html = mail_html_page(
        $title,
        '<h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#800000;">' . h($title) . '</h1>'
        . '<p style="margin:0;">' . nl2br(h($body), false) . '</p>'
        . mail_button($link, 'Open in ' . SITE_NAME),
        h($why) . '<br>'
        . ($unsub !== null ? '<a href="' . h($unsub) . '" style="color:#800000;">Stop these emails</a><br>' : '')
        . h(PROFILE_ADDRESS_LINE)
    );
    return [($isTest ? '[Test] ' : '') . mb_substr($title, 0, 180), $text, $html, $unsub];
}

// ── the queue ──────────────────────────────────────────────────

/**
 * Queues the confirmation mail for a profile's current address (a fresh 48-hour token replaces any older one) and tries to send it at
 * once; if that fails it stays queued for the next batch. Returns:
 *   'sent'         it went out now
 *   'queued'       it is waiting for a batch
 *   'no_email'     the profile has no address
 *   'verified'     the address is already confirmed
 *   'profile_cap'  this profile already had 5 confirmation mails in 24 hours
 *   'address_cap'  this address already had 3 in 24 hours (over all profiles; callers answer as if it worked, so nobody learns who else used the address)
 */
function profile_queue_verification(array $profile, bool $sendNow = true): string
{
    $email = (string) ($profile['email'] ?? '');
    if ($email === '') {
        return 'no_email';
    }
    if (!empty($profile['email_verified_at'])) {
        return 'verified';
    }
    $since = date('Y-m-d H:i:s', time() - 86400);
    $perProfile = db()->prepare('SELECT COUNT(*) AS n FROM profile_email_queue WHERE profile_id = ? AND update_id IS NULL AND created_at > ?');
    $perProfile->execute([(int) $profile['id'], $since]);
    if ((int) $perProfile->fetch()['n'] >= MAIL_VERIFY_PER_PROFILE_DAY) {
        return 'profile_cap';
    }
    $perAddress = db()->prepare('SELECT COUNT(*) AS n FROM profile_email_queue WHERE to_email = ? AND update_id IS NULL AND created_at > ?');
    $perAddress->execute([$email, $since]);
    if ((int) $perAddress->fetch()['n'] >= MAIL_VERIFY_PER_ADDRESS_DAY) {
        return 'address_cap';
    }

    // An older confirmation mail still waiting carries a token that is about to stop working: drop it.
    db()->prepare("UPDATE profile_email_queue SET status = 'failed', last_error = 'superseded', body_text = '', body_html = '' WHERE profile_id = ? AND update_id IS NULL AND status = 'queued'")
        ->execute([(int) $profile['id']]);

    $token = profile_issue_email_token((int) $profile['id']);
    [$subject, $text, $html] = mail_build_verification($profile, $token);
    db()->prepare(
        'INSERT INTO profile_email_queue (update_id, profile_id, to_email, subject, body_text, body_html, status, attempts, created_at) VALUES (NULL, ?, ?, ?, ?, ?, \'queued\', 0, ?)'
    )->execute([(int) $profile['id'], $email, mb_substr($subject, 0, 190), $text, $html, profile_now()]);
    $queueId = (int) db()->lastInsertId();

    if ($sendNow) {
        $row = mail_queue_row($queueId);
        if ($row && mail_process_row($row) === 'sent') {
            return 'sent';
        }
    }
    return 'queued';
}

/** One queue row with the facts about its profile that decide whether it may still be sent (p_* columns), or null. */
function mail_queue_row(int $id): ?array
{
    $stmt = db()->prepare(mail_queue_select() . ' WHERE q.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function mail_queue_select(): string
{
    return 'SELECT q.id, q.update_id, q.profile_id, q.to_email, q.subject, q.body_text, q.body_html, q.attempts,
                   p.id AS p_id, p.email AS p_email, p.email_verified_at AS p_verified, p.consent_email AS p_consent,
                   p.blocked_at AS p_blocked, p.unsubscribe_token AS p_unsub
            FROM profile_email_queue q LEFT JOIN app_profiles p ON p.id = q.profile_id';
}

/** Closes a queue row as sent or failed, wipes a confirmation mail's body (it holds a live token) and counts it on its update. */
function mail_queue_finish(array $q, string $status, ?string $error): void
{
    $isUpdate = $q['update_id'] !== null;
    $wipe = $isUpdate ? '' : ', body_text = \'\', body_html = \'\'';
    db()->prepare("UPDATE profile_email_queue SET status = ?, last_error = ?, sent_at = ?$wipe WHERE id = ?")
        ->execute([$status, $error !== null ? mb_substr($error, 0, 190) : null, $status === 'sent' ? profile_now() : null, (int) $q['id']]);
    if ($isUpdate) {
        profile_update_bump((int) $q['update_id'], 0, $status === 'sent' ? 1 : 0, $status === 'failed' ? 1 : 0);
    }
}

/**
 * Sends one queued row. The row is claimed first (attempts + 1 only if nobody else changed it), so two senders at once - the admin's
 * button and the cron job - never send the same mail twice. Returns 'sent', 'failed' (gave up), 'retry' (stays queued),
 * 'skipped' (no longer wanted: unsubscribed, blocked, address changed, profile deleted) or 'busy' (somebody else has it).
 */
function mail_process_row(array $q): string
{
    db()->prepare("UPDATE profile_email_queue SET attempts = attempts + 1 WHERE id = ? AND status = 'queued' AND attempts = ?")->execute([(int) $q['id'], (int) $q['attempts']]);
    if (profile_affected() !== 1) {
        return 'busy';
    }
    $attempt = (int) $q['attempts'] + 1;
    $isUpdate = $q['update_id'] !== null;

    $skip = null;
    if ($q['p_id'] === null) {
        $skip = 'profile deleted';
    } elseif (!empty($q['p_blocked'])) {
        $skip = 'profile blocked';
    } elseif (strcasecmp((string) $q['p_email'], (string) $q['to_email']) !== 0) {
        $skip = 'address changed';
    } elseif ($isUpdate && (!$q['p_consent'] || empty($q['p_verified']))) {
        $skip = 'unsubscribed or not confirmed';
    } elseif (!$isUpdate && !empty($q['p_verified'])) {
        $skip = 'already confirmed';
    }
    if ($skip !== null) {
        mail_queue_finish($q, 'failed', 'skipped: ' . $skip);
        return 'skipped';
    }

    $extra = [];
    if ($isUpdate) {
        $unsub = profile_site_url() . '/unsubscribe.php?t=' . $q['p_unsub'];
        $extra[] = 'List-Unsubscribe: <' . $unsub . '>, <mailto:' . SITE_EMAIL . '?subject=unsubscribe>';
        $extra[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
        $extra[] = 'Precedence: bulk';
    } else {
        $extra[] = 'Auto-Submitted: auto-generated';
    }
    if (mail_send((string) $q['to_email'], (string) $q['subject'], (string) $q['body_text'], (string) $q['body_html'], $extra)) {
        mail_queue_finish($q, 'sent', null);
        return 'sent';
    }
    if ($attempt >= MAIL_MAX_ATTEMPTS) {
        mail_queue_finish($q, 'failed', 'the mail system refused the message');
        return 'failed';
    }
    db()->prepare('UPDATE profile_email_queue SET last_error = ? WHERE id = ?')->execute(['the mail system refused the message', (int) $q['id']]);
    return 'retry';
}

/**
 * Sends the next batch of queued mails (rows with id greater than $afterId, in order). $limit defaults to the setting profile_email_batch
 * (20), at most 200. Returns ['processed' => rows looked at, 'sent', 'failed', 'retry', 'skipped', 'last_id' => pass as $afterId to
 * continue, 'remaining' => mails still queued]. A mail that fails stays queued and is tried again by a later run, up to 3 times;
 * keep calling with last_id until 'processed' is 0 to go through the whole queue once.
 */
function profile_process_queue(int $limit = 0, int $afterId = 0): array
{
    $limit = $limit > 0 ? min(200, $limit) : profile_setting_int('profile_email_batch', 20, 1, 200);
    @set_time_limit(120);
    $stmt = db()->prepare(mail_queue_select() . " WHERE q.status = 'queued' AND q.id > ? ORDER BY q.id ASC LIMIT ?");
    $stmt->execute([$afterId, $limit]);
    $out = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'retry' => 0, 'skipped' => 0, 'last_id' => $afterId, 'remaining' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $out['last_id'] = (int) $row['id'];
        $out['processed']++;
        $r = mail_process_row($row);
        if (isset($out[$r])) {
            $out[$r]++;
        }
    }
    $out['remaining'] = mail_queue_status()['queued'];
    return $out;
}

/** How full the queue is: ['queued' => n, 'sent' => n, 'failed' => n]. */
function mail_queue_status(): array
{
    $out = ['queued' => 0, 'sent' => 0, 'failed' => 0];
    $stmt = db()->query('SELECT status, COUNT(*) AS n FROM profile_email_queue GROUP BY status');
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}

/** Deletes finished queue rows older than $days (they keep an address and a subject for no reason). Returns how many. */
function mail_queue_prune(int $days = MAIL_KEEP_DAYS): int
{
    db()->prepare("DELETE FROM profile_email_queue WHERE status <> 'queued' AND created_at < ?")->execute([date('Y-m-d H:i:s', time() - max(1, $days) * 86400)]);
    return profile_affected();
}

// ── updates ────────────────────────────────────────────────────

/** How many e-mail updates may still go out today: ['cap', 'used', 'left', 'reached'] (see profile_update_cap_status). */
function mail_cap_status(): array
{
    return profile_update_cap_status('email');
}

/**
 * Puts one update mail per person in the queue for an update already logged with profile_update_create (kind 'email'). $audience is a
 * list of profile rows (id, email, unsubscribe_token, display_name); null = everyone who qualifies for the update's topic right now.
 * An update is queued only once. Sets the update's recipients to the number queued and returns it.
 */
function profile_queue_update(int $updateId, ?array $audience = null): int
{
    $u = profile_update_get($updateId);
    if (!$u || $u['kind'] !== 'email') {
        return 0;
    }
    $exists = db()->prepare('SELECT 1 FROM profile_email_queue WHERE update_id = ? LIMIT 1');
    $exists->execute([$updateId]);
    if ($exists->fetch()) {
        return 0;
    }
    $insert = db()->prepare(
        'INSERT INTO profile_email_queue (update_id, profile_id, to_email, subject, body_text, body_html, status, attempts, created_at) VALUES (?, ?, ?, ?, ?, ?, \'queued\', 0, ?)'
    );
    $count = 0;
    $queue = static function (array $people) use ($u, $insert, $updateId, &$count): void {
        foreach ($people as $p) {
            [$email] = profile_validate_email((string) ($p['email'] ?? ''));
            if ($email === null || !isset($p['id'], $p['unsubscribe_token'])) {
                continue;
            }
            [$subject, $text, $html] = mail_build_update($u, $p);
            $insert->execute([$updateId, (int) $p['id'], $email, mb_substr($subject, 0, 190), $text, $html, profile_now()]);
            $count++;
        }
    };
    if ($audience !== null) {
        $queue($audience);
    } else {
        $last = 0;
        while ($people = profile_audience('email', (int) $u['topic'], $last, 500)) {
            $last = (int) end($people)['id'];
            $queue($people);
        }
    }
    db()->prepare('UPDATE profile_updates SET recipients = ? WHERE id = ?')->execute([$count, $updateId]);
    return $count;
}

/**
 * Checks an e-mail update, enforces the daily cap, logs it and queues one mail per person. Returns
 *   ['ok' => true, 'update_id' => id, 'recipients' => n]   (the queue is then sent with profile_process_queue)
 *   ['ok' => false, 'error' => message, 'errors' => field => message]   (nothing was logged or queued)
 */
function profile_email_start_update(string $title, string $body, string $linkType, string $linkValue, int $topic, ?int $createdBy): array
{
    $errors = profile_update_errors('email', $title, $body, $linkType, $linkValue, $topic);
    if ($errors) {
        return ['ok' => false, 'error' => reset($errors), 'errors' => $errors];
    }
    $cap = mail_cap_status();
    if ($cap['reached']) {
        return ['ok' => false, 'error' => 'The daily limit of ' . $cap['cap'] . ' email update' . ($cap['cap'] === 1 ? '' : 's') . ' has been reached. Please try again tomorrow, so people are not flooded.', 'errors' => []];
    }
    $count = profile_audience_counts($topic)['email'];
    if ($count < 1) {
        return ['ok' => false, 'error' => 'No confirmed email addresses have asked for this kind of update yet.', 'errors' => []];
    }
    [$type, $value] = profile_validate_link($linkType, $linkValue);
    $id = profile_update_create('email', $title, $body, $type, $value, $topic, $createdBy, $count);
    $queued = profile_queue_update($id);
    return ['ok' => true, 'update_id' => $id, 'recipients' => $queued];
}

/**
 * One test mail straight to an address (staff typed their own): not queued, not logged, not counted toward the daily cap, marked [Test]
 * and without an unsubscribe link. Returns ['ok' => bool, 'error' => message|null].
 */
function mail_updates_send_test(string $to, string $title, string $body, string $linkType, string $linkValue): array
{
    $errors = profile_update_errors('email', $title, $body, $linkType, $linkValue, 0);
    if ($errors) {
        return ['ok' => false, 'error' => reset($errors)];
    }
    [$email, $emailError] = profile_validate_email(trim($to));
    if ($email === null) {
        return ['ok' => false, 'error' => $emailError ?? 'Please enter an email address.'];
    }
    [$type, $value] = profile_validate_link($linkType, $linkValue);
    [$subject, $text, $html] = mail_build_update(['title' => $title, 'body' => $body, 'link_type' => $type, 'link_value' => $value], null, true);
    return mail_send($email, $subject, $text, $html)
        ? ['ok' => true, 'error' => null]
        : ['ok' => false, 'error' => 'The mail system did not accept the message.'];
}
