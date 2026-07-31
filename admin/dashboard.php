<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$stats = db()->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'published') AS published,
        SUM(status = 'draft') AS draft,
        SUM(views) AS total_views
     FROM articles"
)->fetch();

$streamCount = db()->query("SELECT COUNT(*) AS c FROM streams WHERE is_active = 1")->fetch()['c'];
$userCount = db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
$pendingCommentCount = db()->query("SELECT COUNT(*) AS c FROM comments WHERE status = 'pending'")->fetch()['c'];

$recent = db()->query(
    "SELECT a.*, u.name AS author_name FROM articles a
     LEFT JOIN users u ON u.id = a.author_id
     ORDER BY a.created_at DESC LIMIT 6"
)->fetchAll();

$pageTitle = 'Dashboard';
$activeAdminNav = 'dashboard';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="icon"><i class="fas fa-newspaper"></i></div>
        <div>
            <div class="value"><?= (int) $stats['total'] ?></div>
            <div class="label">Total Articles</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background:rgba(46,125,50,0.1);color:#2e7d32;"><i class="fas fa-circle-check"></i></div>
        <div>
            <div class="value"><?= (int) $stats['published'] ?></div>
            <div class="label">Published</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background:rgba(201,168,76,0.15);color:#8a6d1f;"><i class="fas fa-pen"></i></div>
        <div>
            <div class="value"><?= (int) $stats['draft'] ?></div>
            <div class="label">Drafts</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-eye"></i></div>
        <div>
            <div class="value"><?= (int) $stats['total_views'] ?></div>
            <div class="label">Total Views</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-satellite-dish"></i></div>
        <div>
            <div class="value"><?= (int) $streamCount ?></div>
            <div class="label">Active Streams</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="value"><?= (int) $userCount ?></div>
            <div class="label">Admin Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background:rgba(201,168,76,0.15);color:#8a6d1f;"><i class="fas fa-comments"></i></div>
        <div>
            <div class="value"><?= (int) $pendingCommentCount ?></div>
            <div class="label">Pending Comments</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Recent Articles</h2>
        <a href="<?= h(BASE_PATH) ?>/admin/article-form.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Article</a>
    </div>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr><th></th><th>Title</th><th>Author</th><th>Status</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$recent): ?>
            <tr class="empty-row"><td colspan="6">No articles yet. Create your first one.</td></tr>
        <?php else: foreach ($recent as $a): ?>
            <tr>
                <td>
                    <div class="thumb">
                        <?php if ($a['featured_image']): ?><img src="<?= h(BASE_PATH . '/' . $a['featured_image']) ?>" alt="" /><?php endif; ?>
                    </div>
                </td>
                <td><?= h($a['title']) ?></td>
                <td><?= h($a['author_name'] ?? '—') ?></td>
                <td><span class="status-pill status-<?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
                <td><?= h(format_date($a['created_at'])) ?></td>
                <td><a href="<?= h(BASE_PATH) ?>/admin/article-form.php?id=<?= (int) $a['id'] ?>" class="icon-btn"><i class="fas fa-pen"></i></a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
