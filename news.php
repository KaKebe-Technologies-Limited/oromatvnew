<?php
require_once __DIR__ . '/includes/functions.php';

$activeNav = 'news';
$q = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$tagSlug = trim((string) ($_GET['tag'] ?? ''));

// A specific category ("news type") view is a curated top-6-by-popularity
// list rather than the full paginated latest-first feed.
$isCategoryView = $categorySlug !== '' && $q === '' && $tagSlug === '';
$perPage = $isCategoryView ? 6 : 9;
$page = $isCategoryView ? 1 : max(1, (int) ($_GET['page'] ?? 1));

$where = ["a.status = 'published'"];
$params = [];

if ($q !== '') {
    $where[] = '(a.title LIKE ? OR a.excerpt LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($categorySlug !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $categorySlug;
}

$joinTag = '';
if ($tagSlug !== '') {
    $joinTag = 'JOIN article_tags at ON at.article_id = a.id JOIN tags t ON t.id = at.tag_id';
    $where[] = 't.slug = ?';
    $params[] = $tagSlug;
}

$whereSql = implode(' AND ', $where);

if ($isCategoryView) {
    $totalPages = 1;
    $offset = 0;
} else {
    $countStmt = db()->prepare(
        "SELECT COUNT(DISTINCT a.id) AS total FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         $joinTag
         WHERE $whereSql"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
}

$orderBy = $isCategoryView ? 'a.views DESC, a.created_at DESC' : 'a.created_at DESC';

$sql = "SELECT DISTINCT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
        FROM articles a
        LEFT JOIN categories c ON c.id = a.category_id
        LEFT JOIN users u ON u.id = a.author_id
        $joinTag
        WHERE $whereSql
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$categories = db()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$activeCategoryName = null;
if ($isCategoryView) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $activeCategoryName = $cat['name'];
            break;
        }
    }
}

$pageTitle = 'News · ' . SITE_NAME;
$pageDescription = 'Latest news and updates from ' . SITE_NAME . '.';

function news_url(array $override = []): string
{
    $params = array_merge([
        'q' => $_GET['q'] ?? '',
        'category' => $_GET['category'] ?? '',
        'tag' => $_GET['tag'] ?? '',
        'page' => $_GET['page'] ?? '',
    ], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return BASE_PATH . '/news.php' . ($params ? '?' . http_build_query($params) : '');
}

require __DIR__ . '/includes/header.php';
?>

<section class="hero-banner hero-banner-compact">
    <div class="hero-banner-bg"></div>
    <div class="container hero-banner-content">
        <span class="badge" style="background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">Stories That Matter</span>
        <h1>News That Speaks<br>For Our Community.</h1>
        <p class="lead">Real people, real moments — the stories shaping the Oroma community at home and across the diaspora, reported with care.</p>
    </div>
</section>

<div class="container section">
    <div class="section-head">
        <?php if ($activeCategoryName): ?>
            <h2><?= h($activeCategoryName) ?> <span>Highlights</span></h2>
            <span class="view-all"><i class="fas fa-fire"></i> Top 6 most read</span>
        <?php else: ?>
            <h2>Latest <span>News</span></h2>
        <?php endif; ?>
    </div>

    <form class="filter-bar" method="get" action="<?= h(BASE_PATH) ?>/news.php">
        <?php if ($categorySlug !== ''): ?><input type="hidden" name="category" value="<?= h($categorySlug) ?>" /><?php endif; ?>
        <?php if ($tagSlug !== ''): ?><input type="hidden" name="tag" value="<?= h($tagSlug) ?>" /><?php endif; ?>
        <div class="search-box">
            <i class="fas fa-search" style="color:var(--text-muted);"></i>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search articles…" />
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div class="filter-bar">
        <a href="<?= h(news_url(['category' => ''])) ?>" class="filter-chip<?= $categorySlug === '' ? ' active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= h(news_url(['category' => $cat['slug'], 'page' => ''])) ?>"
               class="filter-chip<?= $categorySlug === $cat['slug'] ? ' active' : '' ?>"><?= h($cat['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$articles): ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <p>No articles found<?= $q !== '' ? ' for “' . h($q) . '”' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="news-grid">
            <?php foreach ($articles as $article): ?>
                <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($article['slug']) ?>" class="article-card">
                    <div class="thumb">
                        <?php if ($article['featured_image']): ?>
                            <img src="<?= h(BASE_PATH . '/' . $article['featured_image']) ?>" alt="<?= h($article['title']) ?>" loading="lazy" />
                        <?php else: ?>
                            <div class="thumb-fallback"></div>
                        <?php endif; ?>
                    </div>
                    <div class="body">
                        <?php if ($article['category_name']): ?>
                            <span class="badge badge-category"><?= h($article['category_name']) ?></span>
                        <?php endif; ?>
                        <h3><?= h($article['title']) ?></h3>
                        <p class="excerpt"><?= h($article['excerpt'] ?: make_excerpt($article['content'], 100)) ?></p>
                        <div class="article-meta">
                            <span><?= h($article['author_name'] ?? 'Oroma TV') ?></span>
                            <span class="sep">&middot;</span>
                            <span><?= h(time_ago($article['created_at'])) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= h(news_url(['page' => $page - 1])) ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= h(news_url(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= h(news_url(['page' => $page + 1])) ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
