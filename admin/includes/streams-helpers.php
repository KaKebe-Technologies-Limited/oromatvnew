<?php
/**
 * Small helpers shared by the Streams & Live admin page and its three tabs.
 */

/** Address of a tab of the Streams page, e.g. streams_url('channels', ['type' => 'radio']). */
function streams_url(string $tab = 'live', array $query = []): string
{
    return BASE_PATH . '/admin/streams.php?' . http_build_query(['tab' => $tab] + $query);
}

/**
 * A one-button POST form (CSRF included): "End now", "Delete", "Move up"… $label is trusted HTML (may hold an icon).
 * Options: class, confirm (a question shown before submitting), tab (where to land afterwards), title (tooltip), disabled.
 */
function action_form(string $action, array $fields, string $label, array $opts = []): string
{
    $class   = $opts['class'] ?? 'btn btn-outline btn-sm';
    $confirm = $opts['confirm'] ?? null;
    $tab     = $opts['tab'] ?? 'live';
    $title   = $opts['title'] ?? '';

    $html  = '<form method="post" action="' . h(streams_url($tab)) . '" style="display:inline"' . ($confirm ? ' data-confirm="' . h($confirm) . '"' : '') . '>';
    $html .= csrf_field() . '<input type="hidden" name="action" value="' . h($action) . '" />';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . h((string) $name) . '" value="' . h((string) $value) . '" />';
    }
    $html .= '<button type="submit" class="' . h($class) . '"' . ($title !== '' ? ' title="' . h($title) . '" aria-label="' . h($title) . '"' : '') . (!empty($opts['disabled']) ? ' disabled' : '') . '>' . $label . '</button></form>';
    return $html;
}

/** "Today", "Tomorrow", "Yesterday" or "Wed 14 Oct" — plus the 24-hour time. */
function when_parts(string $mysql): array
{
    $ts = strtotime($mysql);
    $day = date('Y-m-d', $ts);
    if ($day === date('Y-m-d')) {
        $label = 'Today';
    } elseif ($day === date('Y-m-d', strtotime('+1 day'))) {
        $label = 'Tomorrow';
    } elseif ($day === date('Y-m-d', strtotime('-1 day'))) {
        $label = 'Yesterday';
    } else {
        $label = date('D j M', $ts);
    }
    return ['day' => $label, 'time' => date('H:i', $ts)];
}

/** "Wed 14 Oct, 18:00" */
function fmt_when(?string $mysql): string
{
    return $mysql ? date('D j M, H:i', strtotime($mysql)) : '—';
}

/** "in 2h 15m", "in 3 days", "5 min ago" — relative to now. */
function until_text(string $mysql): string
{
    $diff = strtotime($mysql) - time();
    $abs = abs($diff);
    if ($abs < 60) {
        return $diff >= 0 ? 'in a moment' : 'just now';
    }
    if ($abs < 3600) {
        $text = floor($abs / 60) . ' min';
    } elseif ($abs < 86400) {
        $text = floor($abs / 3600) . 'h ' . floor(($abs % 3600) / 60) . 'm';
    } else {
        $days = (int) floor($abs / 86400);
        $text = $days . ($days === 1 ? ' day' : ' days');
    }
    return $diff >= 0 ? 'in ' . $text : $text . ' ago';
}

/** 5400 -> "1h 30m" */
function duration_text(int $seconds): string
{
    $m = (int) round($seconds / 60);
    if ($m < 60) {
        return $m . ' min';
    }
    return floor($m / 60) . 'h' . ($m % 60 ? ' ' . ($m % 60) . 'm' : '');
}

/** The "Runs for" choices: minutes => label ('open' = until the admin ends it). */
function duration_options(): array
{
    return [30 => '30 minutes', 60 => '1 hour', 90 => '1½ hours', 120 => '2 hours', 180 => '3 hours', 240 => '4 hours', 360 => '6 hours', 'open' => 'Until I end it'];
}

/** Moves a row one place up or down within a group, renumbering the whole group so ties and gaps never matter. */
function move_row(string $table, int $id, string $direction, string $where = '1=1', array $params = []): void
{
    $allowed = ['streams', 'offair_items']; // fixed internal names only
    if (!in_array($table, $allowed, true)) {
        return;
    }
    $stmt = db()->prepare("SELECT id FROM `$table` WHERE $where ORDER BY sort_order ASC, id ASC");
    $stmt->execute($params);
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    $at = array_search($id, $ids, true);
    if ($at === false) {
        return;
    }
    $swap = $direction === 'up' ? $at - 1 : $at + 1;
    if ($swap >= 0 && $swap < count($ids)) {
        [$ids[$at], $ids[$swap]] = [$ids[$swap], $ids[$at]];
    }
    $upd = db()->prepare("UPDATE `$table` SET sort_order = ? WHERE id = ?");
    foreach ($ids as $position => $rowId) {
        $upd->execute([$position + 1, $rowId]);
    }
}

/** A value to refill a form with: what was just posted (after a validation error), else the saved value, else a default. */
function old(string $key, $saved = '', $default = '')
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    return $saved !== '' && $saved !== null ? $saved : $default;
}

// ── sharing ────────────────────────────────────────────────────

/** The public address of a programme's watch page — always the real website, never a local address. */
function programme_public_url(array $p): string
{
    return rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php?p=' . (int) $p['id'];
}

/** The message people see when the programme is shared (wording depends on whether it is on air, coming up or finished). */
function programme_share_text(array $p): string
{
    $state = $p['state'] ?? live_program_state($p);
    $kind = ($p['kind'] ?? 'show') === 'movie' ? 'Movie premiere' : 'Programme';
    if ($state === 'live') {
        return '🔴 LIVE NOW on Oroma TV: ' . $p['title'] . ' — watch free:';
    }
    if ($state === 'upcoming') {
        $w = when_parts($p['starts_at']);
        return '📺 ' . $kind . ' on Oroma TV: ' . $p['title'] . ' — ' . $w['day'] . ' at ' . $w['time'] . ' (EAT). Watch free:';
    }
    return '📺 ' . $p['title'] . ' on Oroma TV — watch:';
}

/**
 * A "Share" drop-down: WhatsApp, Facebook, X, Telegram, e-mail and copy-link, with the message already written.
 * Works for any programme row, and for the on-air banner (pass $light = true for white-on-colour styling).
 */
function share_menu(array $p, bool $light = false): string
{
    $url = programme_public_url($p);
    $text = programme_share_text($p);
    $full = $text . ' ' . $url;
    $links = [
        ['fab fa-whatsapp', 'WhatsApp', 'https://wa.me/?text=' . rawurlencode($full)],
        ['fab fa-facebook-f', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url) . '&quote=' . rawurlencode($text)],
        ['fab fa-x-twitter', 'X (Twitter)', 'https://twitter.com/intent/tweet?text=' . rawurlencode($text) . '&url=' . rawurlencode($url)],
        ['fab fa-telegram', 'Telegram', 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($text)],
        ['fas fa-envelope', 'Email', 'mailto:?subject=' . rawurlencode($p['title']) . '&body=' . rawurlencode($full)],
    ];
    $html = '<details class="share-menu"><summary class="' . ($light ? 'btn btn-ghost-light btn-sm' : 'btn btn-outline btn-sm') . '"><i class="fas fa-share-nodes"></i> Share</summary><div class="share-pop">';
    foreach ($links as [$icon, $label, $href]) {
        $html .= '<a href="' . h($href) . '" target="_blank" rel="noopener noreferrer"><i class="' . $icon . '"></i> ' . h($label) . '</a>';
    }
    $html .= '<button type="button" data-copy="' . h($full) . '"><i class="fas fa-link"></i> Copy message &amp; link</button></div></details>';
    return $html;
}
