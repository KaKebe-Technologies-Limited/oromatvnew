<?php
/**
 * Comments: moderate what readers post. Tabs: Reported (needs a decision within 24 hours) · Pending (held by the filter, a ban or
 * automatic hiding) · Approved · Spam (hidden) · All · Rules (extra blocked words, allowed link domains, banned commenters).
 * The rules behind this are in includes/comment-moderation.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comment-moderation.php';
require_login();

ensure_comment_schema(true); // this page looks at the real tables (and retries a failed setup at once)
$ready = comment_schema_ready();
$isAdmin = (current_user()['role'] ?? '') === 'admin';

$allowedTabs = ['reported', 'pending', 'approved', 'spam', 'all', 'rules'];
$reportedCount = $ready ? reported_comment_count() : 0;
$requested = (string) ($_GET['status'] ?? '');
$status = in_array($requested, $allowedTabs, true) ? $requested : ($reportedCount > 0 ? 'reported' : 'pending');
if (!$ready && in_array($status, ['reported', 'rules'], true)) {
    $status = 'pending';
}
if ($status === 'rules' && !$isAdmin) {
    $status = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'approve') {
        // 'reviewed' tells the automatic hiding to leave this comment alone from now on.
        if ($ready) {
            db()->prepare("UPDATE comments SET status = 'approved', hold_reason = 'reviewed' WHERE id = ?")->execute([$id]);
        } else {
            db()->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")->execute([$id]);
        }
        comment_resolve_reports($id);
        flash('success', 'Comment approved.');
    } elseif ($id > 0 && $action === 'spam') {
        db()->prepare("UPDATE comments SET status = 'spam' WHERE id = ?")->execute([$id]);
        comment_resolve_reports($id);
        flash('success', 'Comment hidden.');
    } elseif ($id > 0 && $action === 'delete') {
        if ($ready) {
            db()->prepare('DELETE FROM comment_reports WHERE comment_id = ?')->execute([$id]);
        }
        db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
        flash('success', 'Comment deleted.');
    } elseif ($id > 0 && ($action === 'ban' || ($action === 'ban_permanent' && $isAdmin)) && $ready) {
        // Editors and admins can ban for COMMENT_BAN_DAYS (90 days); only an admin can make a ban permanent or remove one.
        $permanent = $action === 'ban_permanent';
        $hidden = comment_ban_from_comment($id, (int) (current_user()['id'] ?? 0) ?: null, $permanent);
        flash('success', 'Commenter banned ' . ($permanent ? 'permanently' : 'for ' . COMMENT_BAN_DAYS . ' days') . '. ' . $hidden . ' comment' . ($hidden === 1 ? '' : 's') . ' hidden. Future comments from them are held for review.');
    } elseif ($action === 'save_rules' && $isAdmin && $ready) {
        set_setting('comment_blocklist', implode("\n", ugc_lines((string) ($_POST['blocklist'] ?? ''))));
        set_setting('comment_allowed_domains', implode("\n", ugc_lines((string) ($_POST['domains'] ?? ''))));
        flash('success', 'Comment rules saved.');
    } elseif ($id > 0 && $action === 'unban' && $isAdmin && $ready) {
        db()->prepare('DELETE FROM comment_bans WHERE id = ?')->execute([$id]);
        flash('success', 'Ban removed.');
    }
    redirect(BASE_PATH . '/admin/comments.php?status=' . urlencode($status));
}

// What each tab shows.
$comments = [];
if ($status !== 'rules') {
    $cols = $ready ? 'c.hold_reason, c.device_hash' : 'NULL AS hold_reason, NULL AS device_hash';
    if ($status === 'reported') {
        // Overdue (open for more than 24 hours) first, then the ones reported from the most different networks, then oldest first:
        // one source filing many reports cannot push reports from several readers out of the first 200.
        $stmt = db()->query(
            "SELECT c.*, a.title AS article_title, a.slug AS article_slug, x.report_count, x.networks, x.reasons, x.first_reported, x.last_reported
             FROM (SELECT comment_id, COUNT(*) AS report_count, COUNT(DISTINCT ip_hash) AS networks,
                          GROUP_CONCAT(reason ORDER BY created_at SEPARATOR ',') AS reasons,
                          MIN(created_at) AS first_reported, MAX(created_at) AS last_reported
                   FROM comment_reports WHERE resolved_at IS NULL GROUP BY comment_id) x
             JOIN comments c ON c.id = x.comment_id JOIN articles a ON a.id = c.article_id
             ORDER BY (x.first_reported < '" . date('Y-m-d H:i:s', time() - 86400) . "') DESC, x.networks DESC, x.first_reported ASC LIMIT 200"
        );
        $comments = $stmt->fetchAll();
    } else {
        $where = $status === 'all' ? '1=1' : 'c.status = ?';
        $stmt = db()->prepare(
            "SELECT c.*, $cols, a.title AS article_title, a.slug AS article_slug
             FROM comments c JOIN articles a ON a.id = c.article_id
             WHERE $where ORDER BY c.created_at DESC LIMIT 200"
        );
        $stmt->execute($status === 'all' ? [] : [$status]);
        $comments = $stmt->fetchAll();
    }
}

// DbStatement::fetchAll() has no fetch mode, so build the status => count map from the rows.
$counts = array_column(db()->query('SELECT status, COUNT(*) AS c FROM comments GROUP BY status')->fetchAll(), 'c', 'status');

// Active bans, loaded once, so each row can show a "Banned" tag.
$bans = [];
if ($ready) {
    $bs = db()->prepare('SELECT * FROM comment_bans WHERE expires_at IS NULL OR expires_at > ? ORDER BY created_at DESC');
    $bs->execute([date('Y-m-d H:i:s')]);
    $bans = $bs->fetchAll();
}
$banned = function (array $c) use ($bans): bool {
    foreach ($bans as $b) {
        if (($b['kind'] === 'name' && $b['value'] === ugc_name_key((string) $c['name']))
            || ($b['kind'] === 'email' && $c['email'] && $b['value'] === ugc_email_key((string) $c['email']))
            || ($b['kind'] === 'device' && !empty($c['device_hash']) && $b['value'] === $c['device_hash'])) {
            return true;
        }
    }
    return false;
};

/** "held: link", "auto-hidden by reports"… for the tag next to a comment's status. */
$holdLabel = function (?string $reason): string {
    if ($reason === null || $reason === '') {
        return '';
    }
    if ($reason === 'reports') return 'auto-hidden by reports';
    if ($reason === 'banned') return 'held: banned commenter';
    if ($reason === 'reviewed') return 'approved by a moderator';
    return 'held: ' . str_replace('filter:', 'filter · ', $reason);
};

$oldestOpen = null;
if ($status === 'reported' && $comments) {
    // The list is not in date order (see its ORDER BY), so ask for the oldest open report directly.
    $oldestOpen = db()->query('SELECT MIN(created_at) AS t FROM comment_reports WHERE resolved_at IS NULL')->fetch()['t'] ?? null;
}

$pageTitle = 'Comments';
$activeAdminNav = 'comments';
require __DIR__ . '/includes/admin-header.php';

$tab = fn(string $key, string $label) => '<a href="?status=' . $key . '" class="btn btn-sm ' . ($status === $key ? 'btn-primary' : 'btn-outline') . '">' . $label . '</a>';
?>

<div class="card">
    <div class="card-head">
        <h2>Comments</h2>
    </div>

    <?php if (!$ready): ?>
        <div class="alert" style="margin-bottom:16px;">Comment moderation tables are missing. Run <code>database/comment-moderation.sql</code> in phpMyAdmin (readers can still comment, but Report and bans will not work until then).</div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <?php if ($ready): ?><?= $tab('reported', 'Reported (' . $reportedCount . ')') ?><?php endif; ?>
        <?= $tab('pending', 'Pending (' . (int) ($counts['pending'] ?? 0) . ')') ?>
        <?= $tab('approved', 'Approved (' . (int) ($counts['approved'] ?? 0) . ')') ?>
        <?= $tab('spam', 'Hidden (' . (int) ($counts['spam'] ?? 0) . ')') ?>
        <?= $tab('all', 'All') ?>
        <?php if ($ready && $isAdmin): ?><?= $tab('rules', 'Rules') ?><?php endif; ?>
    </div>

<?php if ($status === 'rules'): ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_rules" />
        <div class="form-group">
            <label for="blocklist">Extra blocked words and phrases</label>
            <textarea class="form-control" id="blocklist" name="blocklist" rows="6" maxlength="10000"><?= h((string) get_setting('comment_blocklist', '')) ?></textarea>
            <div class="form-hint">One per line. A comment containing one is <strong>held for review</strong>, never deleted. End a word with <code>*</code> to also match longer words (e.g. <code>idiot*</code>). Lines starting with <code>#</code> are notes. Add Acholi, Luo or Swahili words here; the built-in list is English only.</div>
        </div>
        <div class="form-group">
            <label for="domains">Extra allowed link domains</label>
            <textarea class="form-control" id="domains" name="domains" rows="3" maxlength="10000"><?= h((string) get_setting('comment_allowed_domains', '')) ?></textarea>
            <div class="form-hint">One per line, e.g. <code>monitor.co.ug</code>. Comments with any other link are held for review. Always allowed: oromatv.com, youtube.com, youtu.be, facebook.com, x.com, twitter.com, instagram.com, wikipedia.org.</div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save rules</button>
    </form>

    <h3 style="margin:30px 0 10px;font-size:16px;">Banned commenters</h3>
    <?php if (!$bans): ?>
        <p style="color:var(--text-muted);font-size:14px;">No active bans. Use the <i class="fas fa-user-slash"></i> button next to a comment to ban a commenter (their name, e-mail and device) for <?= (int) COMMENT_BAN_DAYS ?> days, or <i class="fas fa-user-lock"></i> to ban permanently. Their future comments are held for review.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Type</th><th>Value</th><th>Note</th><th>Added</th><th>Expires</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($bans as $b): ?>
                <tr>
                    <td><?= h($b['kind']) ?></td>
                    <td><?= h($b['kind'] === 'device' ? substr($b['value'], 0, 8) . '…' : $b['value']) ?></td>
                    <td><?= h((string) $b['note']) ?></td>
                    <td><?= h(format_date($b['created_at'], 'M j, Y')) ?></td>
                    <td><?= $b['expires_at'] ? h(format_date($b['expires_at'], 'M j, Y')) : 'never' ?></td>
                    <td>
                        <form method="post" data-confirm="Remove this ban?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unban" />
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>" />
                            <button type="submit" class="icon-btn" title="Remove ban"><i class="fas fa-xmark"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <?php
    // One-off check for the owner on the live site: which address does the server really see for a visitor? (This request only; nothing is stored.)
    $seen = [
        'REMOTE_ADDR'      => $_SERVER['REMOTE_ADDR'] ?? '',
        'X-Forwarded-For'  => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        'CF-Connecting-IP' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        'True-Client-IP'   => $_SERVER['HTTP_TRUE_CLIENT_IP'] ?? '',
    ];
    ?>
    <h3 style="margin:30px 0 10px;font-size:16px;">Visitor address check</h3>
    <p style="color:var(--text-muted);font-size:13.5px;margin-bottom:12px;">
        Rate limits, the "3 different networks" auto-hide and device bans tell visitors apart by <code>REMOTE_ADDR</code> only (forwarded headers can be forged, so they are never trusted).
        Open this page once on the live site from your phone on mobile data: if <code>REMOTE_ADDR</code> is your own public address nothing needs doing; if it is the same for other devices, or looks like the host's CDN while a forwarded header shows your real address, tell the developer, because many readers would then share one limit.
    </p>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Seen on this request</th><th>Value</th></tr></thead>
        <tbody>
        <?php foreach ($seen as $label => $value): ?>
            <tr><td><code><?= h($label) ?></code></td><td><?= $value !== '' ? h($value) : '<span style="color:var(--text-muted);">not sent</span>' ?></td></tr>
        <?php endforeach; ?>
            <tr><td>Counted as one visitor</td><td><?= h(ugc_client_ip()) ?> <span style="color:var(--text-muted);">(IPv6 is counted by its /64 prefix)</span></td></tr>
        </tbody>
    </table>
    </div>

<?php else: ?>

    <?php if ($status === 'reported'): ?>
        <p style="color:var(--text-muted);font-size:13.5px;margin-bottom:16px;">
            <strong>We aim to review reports within 24 hours</strong> (our Terms and the app say so to readers and the app stores).
            <?php if ($oldestOpen): ?>Oldest open report: <strong><?= h(time_ago($oldestOpen)) ?></strong>.<?php endif; ?>
            <?php if ($reportedCount > count($comments)): ?>Showing the first <?= (int) count($comments) ?> of <?= (int) $reportedCount ?> (overdue and widely reported first).<?php endif; ?>
            <i class="fas fa-check"></i> keeps the comment and closes its reports · <i class="fas fa-ban"></i> hides it · <i class="fas fa-user-slash"></i> bans the commenter.
        </p>
    <?php endif; ?>

    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr><th>Author</th><th>Comment</th><th>Article</th><?php if ($status === 'reported'): ?><th>Reports</th><?php endif; ?><th><?= $status === 'reported' ? 'First reported' : 'Date' ?></th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$comments): ?>
            <tr class="empty-row"><td colspan="7">No comments here.</td></tr>
        <?php else: foreach ($comments as $c):
            $isBanned = $banned($c);
            $over24 = $status === 'reported' && strtotime($c['first_reported']) < time() - 86400;
        ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= h($c['name']) ?> <?php if ($isBanned): ?><span class="status-pill status-editor" title="Future comments from this person are held">Banned</span><?php endif; ?></div>
                    <?php if ($c['email']): ?><div style="font-size:12px;color:var(--text-muted);"><?= h($c['email']) ?></div><?php endif; ?>
                </td>
                <td style="max-width:340px;"><?= h(mb_strimwidth($c['comment'], 0, $status === 'reported' ? 400 : 160, '…')) ?></td>
                <td>
                    <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($c['article_slug']) ?>#comments" target="_blank" style="color:var(--maroon);">
                        <?= h(mb_strimwidth($c['article_title'], 0, 40, '…')) ?>
                    </a>
                </td>
                <?php if ($status === 'reported'):
                    $reasons = array_count_values(array_filter(explode(',', (string) $c['reasons'])));
                ?>
                    <td>
                        <strong><?= (int) $c['report_count'] ?></strong>
                        <div style="font-size:12px;color:var(--text-muted);" title="Reports from several different networks count for more than many from one."><?= (int) $c['networks'] ?> network<?= (int) $c['networks'] === 1 ? '' : 's' ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?php foreach ($reasons as $r => $n): ?><?= (int) $n ?> × <?= h($r) ?><br /><?php endforeach; ?></div>
                    </td>
                    <td style="<?= $over24 ? 'color:#c0392b;font-weight:700;' : '' ?>"><?= h(time_ago($c['first_reported'])) ?><?= $over24 ? '<div style="font-size:11px;">over 24 h</div>' : '' ?></td>
                <?php else: ?>
                    <td><?= h(format_date($c['created_at'], 'M j, Y g:ia')) ?></td>
                <?php endif; ?>
                <td>
                    <span class="status-pill status-<?= $c['status'] === 'approved' ? 'published' : ($c['status'] === 'pending' ? 'draft' : 'editor') ?>"><?= h($c['status'] === 'spam' ? 'hidden' : $c['status']) ?></span>
                    <?php if ($holdLabel($c['hold_reason'] ?? null)): ?><div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;"><?= h($holdLabel($c['hold_reason'])) ?></div><?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <?php if ($c['status'] !== 'approved' || $status === 'reported'): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve" />
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                                <button type="submit" class="icon-btn" title="<?= $status === 'reported' ? 'Keep it and close the reports' : 'Approve' ?>"><i class="fas fa-check"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($c['status'] !== 'spam'): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="spam" />
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                                <button type="submit" class="icon-btn" title="Hide this comment"><i class="fas fa-ban"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($ready): ?>
                            <form method="post" data-confirm="Ban this commenter for <?= (int) COMMENT_BAN_DAYS ?> days? Future comments from this name, e-mail and device will be held for review, and their other comments (same app install, e-mail or name) hidden.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="ban" />
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                                <button type="submit" class="icon-btn" title="Ban commenter for <?= (int) COMMENT_BAN_DAYS ?> days"><i class="fas fa-user-slash"></i></button>
                            </form>
                            <?php if ($isAdmin): ?>
                                <form method="post" data-confirm="Ban this commenter PERMANENTLY? Their name and e-mail stay banned until you remove the ban under Rules.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="ban_permanent" />
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                                    <button type="submit" class="icon-btn" title="Ban commenter permanently"><i class="fas fa-user-lock"></i></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <form method="post" data-confirm="Delete this comment permanently?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                            <button type="submit" class="icon-btn" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
