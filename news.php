<?php
require_once __DIR__ . '/includes/functions.php';

$activeNav    = 'news';
$q            = trim((string)($_GET['q']        ?? ''));
$categorySlug = trim((string)($_GET['category'] ?? ''));
$tagSlug      = trim((string)($_GET['tag']      ?? ''));

$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = ["a.status = 'published'"];
$params = [];

if ($q !== '') {
    $where[]  = '(a.title LIKE ? OR a.excerpt LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($categorySlug !== '') {
    $where[]  = 'c.slug = ?';
    $params[] = $categorySlug;
}

$joinTag = '';
if ($tagSlug !== '') {
    $joinTag  = 'JOIN article_tags at ON at.article_id = a.id JOIN tags t ON t.id = at.tag_id';
    $where[]  = 't.slug = ?';
    $params[] = $tagSlug;
}

$whereSql  = implode(' AND ', $where);
$countStmt = db()->prepare(
    "SELECT COUNT(DISTINCT a.id) AS total FROM articles a
     LEFT JOIN categories c ON c.id = a.category_id
     $joinTag WHERE $whereSql"
);
$countStmt->execute($params);
$total      = (int)$countStmt->fetch()['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "SELECT DISTINCT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
        FROM articles a
        LEFT JOIN categories c ON c.id = a.category_id
        LEFT JOIN users u ON u.id = a.author_id
        $joinTag
        WHERE $whereSql
        ORDER BY a.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt     = db()->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$categories        = get_active_categories();
$activeCategoryName = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) { $activeCategoryName = $cat['name']; break; }
}

$pageTitle       = ($activeCategoryName ? $activeCategoryName . ' · ' : '') . 'News · ' . SITE_NAME;
$pageDescription = 'Latest news and updates from ' . SITE_NAME . '.';

function news_url(array $override = []): string
{
    $p = array_merge([
        'q'        => $_GET['q']        ?? '',
        'category' => $_GET['category'] ?? '',
        'tag'      => $_GET['tag']      ?? '',
        'page'     => $_GET['page']     ?? '',
    ], $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return BASE_PATH . '/news.php' . ($p ? '?' . http_build_query($p) : '');
}

require __DIR__ . '/includes/header.php';
?>

<!-- hero banner -->
<section class="hero-banner hero-banner-compact">
    <div class="hero-banner-bg"></div>
    <div class="container">
        <?php if ($activeCategoryName): ?>
            <span class="cat-badge" style="margin-bottom:16px;display:inline-block;"><?= h($activeCategoryName) ?></span>
            <h1><?= h($activeCategoryName) ?></h1>
            <p class="lead">Explore the latest <?= h($activeCategoryName) ?> stories from across the Oroma community.</p>
        <?php else: ?>
            <h1>News &amp; Stories</h1>
            <p class="lead">Real people, real moments — the stories shaping the Oroma community, reported with care.</p>
        <?php endif; ?>
    </div>
</section>

<div class="container section">

    <!-- search + filter -->
    <form class="filter-bar" method="get" action="<?= h(BASE_PATH) ?>/news.php" style="margin-bottom:16px;">
        <?php if ($tagSlug !== ''): ?>
            <input type="hidden" name="tag" value="<?= h($tagSlug) ?>" />
        <?php endif; ?>
        <div class="search-box">
            <i class="fas fa-search" style="color:var(--gray);"></i>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search articles…" />
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($q !== ''): ?>
            <a href="<?= h(BASE_PATH) ?>/news.php" class="btn btn-sm" style="border:1.5px solid var(--border);">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </form>

    <!-- category pills -->
    <div class="cat-filter-bar" style="margin-bottom:28px;">
        <a href="<?= h(BASE_PATH) ?>/news.php" class="cat-pill<?= $categorySlug === '' ? ' active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= h(news_url(['category' => $cat['slug'], 'page' => ''])) ?>"
               class="cat-pill<?= $categorySlug === $cat['slug'] ? ' active' : '' ?>">
                <i class="fas <?= h($cat['icon'] ?: 'fa-folder') ?>"></i>
                <?= h($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- section head -->
    <div class="section-head reveal">
        <?php if ($activeCategoryName): ?>
            <h2><?= h($activeCategoryName) ?> <span>Stories</span></h2>
        <?php elseif ($q !== ''): ?>
            <h2>Results for <span>"<?= h($q) ?>"</span></h2>
        <?php else: ?>
            <h2>Latest <span>News</span></h2>
        <?php endif; ?>
        <span style="font-size:13px;color:var(--gray);"><?= $total ?> article<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- grid -->
    <?php if (!$articles): ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <p>No articles found<?= $q !== '' ? ' for "' . h($q) . '"' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="news-grid-4">
            <?php foreach ($articles as $i => $a): ?>
                <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($a['slug']) ?>"
                   class="news-card reveal reveal-delay-<?= min($i % 4 + 1, 4) ?>">
                    <div class="thumb">
                        <?php $src = $a['featured_image']
                            ? BASE_PATH . '/' . $a['featured_image']
                            : placeholder_image($a['id'], 480, 300); ?>
                        <img src="<?= h($src) ?>" alt="<?= h($a['title']) ?>" loading="lazy" />
                        <?= render_thumb_logo() ?>
                        <?php if ($a['category_name']): ?>
                            <span class="cat-badge cat-badge-sm"><?= h($a['category_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="content">
                        <h3><?= h($a['title']) ?></h3>
                        <p class="excerpt"><?= h($a['excerpt'] ?: make_excerpt($a['content'], 100)) ?></p>
                        <div class="card-meta">
                            <span class="author"><?= h($a['author_name'] ?? 'Oroma TV') ?></span>
                            <span class="sep">&middot;</span>
                            <span><?= h(time_ago($a['created_at'])) ?></span>
                            <span class="sep">&middot;</span>
                            <span><?= reading_time($a['content']) ?> min</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= h(news_url(['page' => $page - 1])) ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                if ($start > 1): ?><a href="<?= h(news_url(['page' => 1])) ?>">1</a><?php if ($start > 2): ?><span style="padding:0 4px;">…</span><?php endif; endif;
                for ($i = $start; $i <= $end; $i++):
                    if ($i === $page): ?><span class="current"><?= $i ?></span><?php
                    else: ?><a href="<?= h(news_url(['page' => $i])) ?>"><?= $i ?></a><?php
                    endif;
                endfor;
                if ($end < $totalPages): if ($end < $totalPages - 1): ?><span style="padding:0 4px;">…</span><?php endif; ?><a href="<?= h(news_url(['page' => $totalPages])) ?>"><?= $totalPages ?></a><?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= h(news_url(['page' => $page + 1])) ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
