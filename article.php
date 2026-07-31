<?php
require_once __DIR__ . '/includes/auth.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="container section"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Article not found.</p></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = db()->prepare(
    "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
     FROM articles a
     LEFT JOIN categories c ON c.id = a.category_id
     LEFT JOIN users u ON u.id = a.author_id
     WHERE a.slug = ? LIMIT 1"
);
$stmt->execute([$slug]);
$article = $stmt->fetch();

$canView = $article && ($article['status'] === 'published' || is_logged_in());

if (!$canView) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="container section"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Article not found.</p></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($article['status'] === 'published') {
    db()->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([$article['id']]);
}

$tagStmt = db()->prepare(
    'SELECT t.name, t.slug FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name'
);
$tagStmt->execute([$article['id']]);
$tags = $tagStmt->fetchAll();

$trending = get_trending_articles(7, $article['id']);
$trendingCards = array_slice($trending, 0, 2);
$mostRead = array_slice($trending, 2, 5);
$recommended = get_recommended_articles($article, 5);
$sidebarCategories = db()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();

$comments = get_approved_comments($article['id']);
$commentCount = count($comments);

$prevStmt = db()->prepare(
    "SELECT title, slug FROM articles WHERE status = 'published' AND created_at < ? ORDER BY created_at DESC LIMIT 1"
);
$prevStmt->execute([$article['created_at']]);
$prevArticle = $prevStmt->fetch();

$nextStmt = db()->prepare(
    "SELECT title, slug FROM articles WHERE status = 'published' AND created_at > ? ORDER BY created_at ASC LIMIT 1"
);
$nextStmt->execute([$article['created_at']]);
$nextArticle = $nextStmt->fetch();

$activeNav = 'news';
$pageTitle = ($article['meta_title'] ?: $article['title']) . ' · ' . SITE_NAME;
$pageDescription = $article['meta_description'] ?: ($article['excerpt'] ?: make_excerpt($article['content']));
$pageImage = $article['featured_image'] ? (SITE_URL . '/' . $article['featured_image']) : (SITE_URL . '/img/logo.png');
$pageCanonical = SITE_URL . '/article.php?slug=' . urlencode($article['slug']);
$pageType = 'article';

require __DIR__ . '/includes/header.php';

/** Compact sidebar list item: small thumb + category label + title + meta. */
function render_side_article(array $a): void
{
    ?>
    <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($a['slug']) ?>" class="side-article">
        <div class="side-thumb">
            <?php if (!empty($a['featured_image'])): ?>
                <img src="<?= h(BASE_PATH . '/' . $a['featured_image']) ?>" alt="<?= h($a['title']) ?>" loading="lazy" />
            <?php else: ?>
                <div class="thumb-fallback"></div>
            <?php endif; ?>
        </div>
        <div class="side-article-body">
            <?php if (!empty($a['category_name'])): ?><span class="side-cat"><?= h($a['category_name']) ?></span><?php endif; ?>
            <h4><?= h($a['title']) ?></h4>
            <div class="article-meta"><span><?= h(time_ago($a['created_at'])) ?></span></div>
        </div>
    </a>
    <?php
}

/** Large sidebar feature card: image with category badge + title overlaid. */
function render_side_feature_card(array $a): void
{
    ?>
    <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($a['slug']) ?>" class="side-feature-card">
        <div class="thumb">
            <?php if (!empty($a['featured_image'])): ?>
                <img src="<?= h(BASE_PATH . '/' . $a['featured_image']) ?>" alt="<?= h($a['title']) ?>" loading="lazy" />
            <?php else: ?>
                <div class="thumb-fallback"></div>
            <?php endif; ?>
            <?php if (!empty($a['category_name'])): ?><span class="cat-badge"><?= h($a['category_name']) ?></span><?php endif; ?>
            <span class="cap-title"><?= h($a['title']) ?></span>
        </div>
        <div class="cap-meta"><?= h(time_ago($a['created_at'])) ?> <?php if (!empty($a['author_name'])): ?>/ <?= h($a['author_name']) ?><?php endif; ?></div>
    </a>
    <?php
}
?>

<div class="container section">
    <div class="article-layout">
        <div class="article-main">
            <?php if ($article['status'] === 'draft'): ?>
                <span class="badge" style="background:rgba(201,168,76,0.15);color:var(--gold);margin-bottom:16px;"><i class="fas fa-eye"></i> Draft preview — not publicly visible</span>
            <?php endif; ?>

            <div class="article-cover">
                <?php if ($article['featured_image']): ?>
                    <img src="<?= h(BASE_PATH . '/' . $article['featured_image']) ?>" alt="<?= h($article['title']) ?>" />
                <?php else: ?>
                    <div class="thumb-fallback"></div>
                <?php endif; ?>
                <?php if (!empty($article['featured_image_caption'])): ?>
                    <figcaption class="cover-caption"><?= h($article['featured_image_caption']) ?></figcaption>
                <?php endif; ?>
            </div>

            <?php if ($article['category_name']): ?>
                <a href="<?= h(BASE_PATH) ?>/category.php?slug=<?= urlencode($article['category_slug']) ?>" class="category-label"><?= h($article['category_name']) ?></a>
            <?php endif; ?>

            <h1 class="article-title"><?= h($article['title']) ?></h1>

            <div class="article-meta-line">
                <?= h(time_ago($article['created_at'])) ?> <span class="sep">/</span> <?= h($article['author_name'] ?? 'Oroma TV') ?>
            </div>
            <div class="article-meta-line article-views-line">
                <i class="fas fa-eye"></i> <?= (int) $article['views'] ?> Views
                <span class="sep">&middot;</span>
                <a href="#comments"><i class="fas fa-comment"></i> <?= (int) $commentCount ?> Comments</a>
            </div>

            <div class="article-body"><?= $article['content'] ?></div>

            <?php if ($tags): ?>
                <div class="article-tags">
                    <?php foreach ($tags as $tag): ?>
                        <a href="<?= h(BASE_PATH) ?>/tag.php?slug=<?= urlencode($tag['slug']) ?>" class="tag-chip">#<?= h($tag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="share-row">
                <span class="label">Share this:</span>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" title="Share on Facebook"
                   href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageCanonical) ?>"><i class="fab fa-facebook-f"></i></a>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" title="Share on X"
                   href="https://twitter.com/intent/tweet?url=<?= urlencode($pageCanonical) ?>&text=<?= urlencode($article['title']) ?>"><i class="fab fa-twitter"></i></a>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" title="Share on WhatsApp"
                   href="https://wa.me/?text=<?= urlencode($article['title'] . ' ' . $pageCanonical) ?>"><i class="fab fa-whatsapp"></i></a>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" title="Share on Telegram"
                   href="https://t.me/share/url?url=<?= urlencode($pageCanonical) ?>&text=<?= urlencode($article['title']) ?>"><i class="fab fa-telegram-plane"></i></a>
                <a class="share-btn" title="Share by email"
                   href="mailto:?subject=<?= urlencode($article['title']) ?>&body=<?= urlencode($pageCanonical) ?>"><i class="fas fa-envelope"></i></a>
                <button class="share-btn" type="button" id="copyLinkBtn" data-url="<?= h($pageCanonical) ?>" title="Copy link"><i class="fas fa-link"></i></button>
            </div>

            <?php if ($prevArticle || $nextArticle): ?>
                <div class="article-pager">
                    <?php if ($prevArticle): ?>
                        <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($prevArticle['slug']) ?>" class="pager-link pager-prev">
                            <span class="pager-label"><i class="fas fa-arrow-left"></i> Previous</span>
                            <span class="pager-title"><?= h($prevArticle['title']) ?></span>
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if ($nextArticle): ?>
                        <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($nextArticle['slug']) ?>" class="pager-link pager-next">
                            <span class="pager-label">Next <i class="fas fa-arrow-right"></i></span>
                            <span class="pager-title"><?= h($nextArticle['title']) ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="comments-section" id="comments">
                <h2 class="comments-heading"><i class="fas fa-comments"></i> <?= (int) $commentCount ?> Comment<?= $commentCount === 1 ? '' : 's' ?></h2>

                <?php if ($comments): ?>
                    <ul class="comment-list">
                        <?php foreach ($comments as $c): ?>
                            <li class="comment-item">
                                <div class="comment-avatar"><?= h(comment_initial($c['name'])) ?></div>
                                <div class="comment-body">
                                    <div class="comment-meta">
                                        <span class="comment-author"><?= h($c['name']) ?></span>
                                        <span class="sep">&middot;</span>
                                        <span><?= h(time_ago($c['created_at'])) ?></span>
                                    </div>
                                    <p class="comment-text"><?= nl2br(h($c['comment'])) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="comments-empty">Be the first to share your thoughts on this story.</p>
                <?php endif; ?>

                <form method="post" action="<?= h(BASE_PATH) ?>/comment-submit.php" class="comment-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>" />
                    <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true" />

                    <h3>Leave a Comment</h3>
                    <div class="comment-form-row">
                        <input class="form-control" type="text" name="name" placeholder="Your name" maxlength="100" required />
                        <input class="form-control" type="email" name="email" placeholder="Email (optional, not published)" maxlength="150" />
                    </div>
                    <textarea class="form-control" name="comment" rows="4" placeholder="Join the conversation…" maxlength="2000" required></textarea>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Comment</button>
                    <p class="form-hint">Comments are reviewed before they appear publicly.</p>
                </form>
            </div>
        </div>

        <aside class="article-sidebar">
            <?php if ($trendingCards): ?>
                <div class="sidebar-widget">
                    <h3><i class="fas fa-fire"></i> Trending Now</h3>
                    <?php foreach ($trendingCards as $a): render_side_feature_card($a); endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($mostRead): ?>
                <div class="sidebar-widget">
                    <h3><i class="fas fa-chart-line"></i> Most Read</h3>
                    <div class="side-article-list">
                        <?php foreach ($mostRead as $a): render_side_article($a); endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($recommended): ?>
                <div class="sidebar-widget">
                    <h3><i class="fas fa-thumbs-up"></i> Recommended For You</h3>
                    <div class="side-article-list">
                        <?php foreach ($recommended as $a): render_side_article($a); endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($sidebarCategories): ?>
                <div class="sidebar-widget">
                    <h3><i class="fas fa-layer-group"></i> Categories</h3>
                    <div class="sidebar-categories">
                        <?php foreach ($sidebarCategories as $cat): ?>
                            <a href="<?= h(BASE_PATH) ?>/category.php?slug=<?= urlencode($cat['slug']) ?>" class="filter-chip"><?= h($cat['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script>
document.getElementById('copyLinkBtn')?.addEventListener('click', function () {
    navigator.clipboard.writeText(this.dataset.url).then(() => {
        var icon = this.querySelector('i');
        icon.className = 'fas fa-check';
        setTimeout(() => { icon.className = 'fas fa-link'; }, 1500);
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
