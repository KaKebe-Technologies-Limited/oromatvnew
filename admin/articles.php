<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = 'a.title LIKE ?';
    $params[] = '%' . $q . '%';
}
if (in_array($status, ['draft', 'published'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) AS c FROM articles a WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT a.*, c.name AS category_name, u.name AS author_name
        FROM articles a
        LEFT JOIN categories c ON c.id = a.category_id
        LEFT JOIN users u ON u.id = a.author_id
        WHERE $whereSql
        ORDER BY a.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$pageTitle = 'Articles';
$activeAdminNav = 'articles';
require __DIR__ . '/includes/admin-header.php';

function articles_url(array $override = []): string
{
    $params = array_merge([
        'q' => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? '',
        'page' => $_GET['page'] ?? '',
    ], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return BASE_PATH . '/admin/articles.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="card">
    <div class="card-head">
        <h2>All Articles (<?= $total ?>)</h2>
        <a href="<?= h(BASE_PATH) ?>/admin/article-form.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Article</a>
    </div>

    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
        <input class="form-control" style="max-width:260px;" type="text" name="q" placeholder="Search title…" value="<?= h($q) ?>" />
        <select class="form-control" style="max-width:180px;" name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    </form>

    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr><th></th><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$articles): ?>
            <tr class="empty-row"><td colspan="8">No articles found.</td></tr>
        <?php else: foreach ($articles as $a): ?>
            <tr>
                <td>
                    <div class="thumb">
                        <?php if ($a['featured_image']): ?><img src="<?= h(BASE_PATH . '/' . $a['featured_image']) ?>" alt="" /><?php endif; ?>
                    </div>
                </td>
                <td>
                    <?= h($a['title']) ?>
                    <?php if ($a['is_featured']): ?><i class="fas fa-star" style="color:var(--gold);margin-left:4px;" title="Featured"></i><?php endif; ?>
                </td>
                <td><?= h($a['category_name'] ?? '—') ?></td>
                <td><?= h($a['author_name'] ?? '—') ?></td>
                <td><span class="status-pill status-<?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
                <td><?= (int) $a['views'] ?></td>
                <td><?= h(format_date($a['created_at'])) ?></td>
                <td>
                    <div class="row-actions">
                        <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($a['slug']) ?>" target="_blank" class="icon-btn" title="Preview"><i class="fas fa-eye"></i></a>
                        <a href="<?= h(BASE_PATH) ?>/admin/article-form.php?id=<?= (int) $a['id'] ?>" class="icon-btn" title="Edit"><i class="fas fa-pen"></i></a>
                        <form method="post" action="<?= h(BASE_PATH) ?>/admin/article-delete.php" data-confirm="Delete “<?= h($a['title']) ?>”? This cannot be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>" />
                            <button type="submit" class="icon-btn" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top:20px;display:flex;gap:8px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= h(articles_url(['page' => $i])) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
