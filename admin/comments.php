<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && in_array($action, ['approve', 'spam', 'delete'], true)) {
        if ($action === 'delete') {
            db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
            flash('success', 'Comment deleted.');
        } else {
            $status = $action === 'approve' ? 'approved' : 'spam';
            db()->prepare('UPDATE comments SET status = ? WHERE id = ?')->execute([$status, $id]);
            flash('success', $action === 'approve' ? 'Comment approved.' : 'Comment marked as spam.');
        }
    }
    redirect(BASE_PATH . '/admin/comments.php?status=' . urlencode($_GET['status'] ?? 'pending'));
}

$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'spam', 'all'], true) ? $_GET['status'] : 'pending';

$where = $status === 'all' ? '1=1' : 'c.status = ?';
$params = $status === 'all' ? [] : [$status];

$stmt = db()->prepare(
    "SELECT c.*, a.title AS article_title, a.slug AS article_slug
     FROM comments c
     JOIN articles a ON a.id = c.article_id
     WHERE $where
     ORDER BY c.created_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$comments = $stmt->fetchAll();

$counts = db()->query(
    "SELECT status, COUNT(*) AS c FROM comments GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Comments';
$activeAdminNav = 'comments';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Comments</h2>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <a href="?status=pending" class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-outline' ?>">Pending (<?= (int) ($counts['pending'] ?? 0) ?>)</a>
        <a href="?status=approved" class="btn btn-sm <?= $status === 'approved' ? 'btn-primary' : 'btn-outline' ?>">Approved (<?= (int) ($counts['approved'] ?? 0) ?>)</a>
        <a href="?status=spam" class="btn btn-sm <?= $status === 'spam' ? 'btn-primary' : 'btn-outline' ?>">Spam (<?= (int) ($counts['spam'] ?? 0) ?>)</a>
        <a href="?status=all" class="btn btn-sm <?= $status === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
    </div>

    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr><th>Author</th><th>Comment</th><th>Article</th><th>Date</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$comments): ?>
            <tr class="empty-row"><td colspan="6">No comments here.</td></tr>
        <?php else: foreach ($comments as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= h($c['name']) ?></div>
                    <?php if ($c['email']): ?><div style="font-size:12px;color:var(--text-muted);"><?= h($c['email']) ?></div><?php endif; ?>
                </td>
                <td style="max-width:320px;"><?= h(mb_strimwidth($c['comment'], 0, 160, '…')) ?></td>
                <td>
                    <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($c['article_slug']) ?>#comments" target="_blank" style="color:var(--maroon);">
                        <?= h(mb_strimwidth($c['article_title'], 0, 40, '…')) ?>
                    </a>
                </td>
                <td><?= h(format_date($c['created_at'], 'M j, Y g:ia')) ?></td>
                <td><span class="status-pill status-<?= $c['status'] === 'approved' ? 'published' : ($c['status'] === 'pending' ? 'draft' : 'editor') ?>"><?= h($c['status']) ?></span></td>
                <td>
                    <div class="row-actions">
                        <?php if ($c['status'] !== 'approved'): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve" />
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                                <button type="submit" class="icon-btn" title="Approve"><i class="fas fa-check"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($c['status'] !== 'spam'): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="spam" />
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                                <button type="submit" class="icon-btn" title="Mark as spam"><i class="fas fa-ban"></i></button>
                            </form>
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
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
