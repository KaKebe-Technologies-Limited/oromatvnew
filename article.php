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
    $showViews = get_setting('show_article_views', '1');
    if ($showViews !== '0') {
        db()->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([$article['id']]);
    }
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
$pageCanonical = SITE_URL . '/article.php?slug=' . urlencode($article['slug']);
$pageType = 'article';

// ── OG image: featured image → first img in content → logo fallback ──
if ($article['featured_image']) {
    $pageImage = SITE_URL . img_url($article['featured_image']);
} else {
    // Try to extract the first <img src="..."> from article content
    $firstImg = null;
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $article['content'], $imgMatch)) {
        $src = $imgMatch[1];
        if (!preg_match('#^https?://#i', $src)) {
            $src = SITE_URL . img_url($src);
        }
        $firstImg = $src;
    }
    $pageImage = $firstImg ?? (SITE_URL . '/img/logo.png');
}

$showViews   = get_setting('show_article_views', '1') !== '0';
$articleFont = $article['body_font'] ?? get_setting('article_font', 'inter');

// Strip excessive blank lines pasted from AI/Word docs
$cleanContent = $article['content'];
// Remove empty <p> tags (whitespace, &nbsp;, <br> only)
$cleanContent = preg_replace('/<p[^>]*>(\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $cleanContent);
// Collapse 3+ consecutive <br> into one line break
$cleanContent = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br />', $cleanContent);
// Remove runs of 3+ consecutive closing+opening p tags (over-spaced paragraphs)
$cleanContent = preg_replace('/(<\/p>\s*<p[^>]*>\s*){2,}/i', '</p><p>', $cleanContent);

// Fix image paths: rewrite any /uploads/ or /oromatvnew/uploads/ etc. to use BASE_PATH
// so images work correctly on both local and live server
$cleanContent = normalize_content_image_urls($cleanContent);

// Preload Lora font in <head> if needed
$extraHeadHtml = '';
if ($articleFont === 'lora') {
    $extraHeadHtml = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" />';
}

require __DIR__ . '/includes/header.php';

/** Compact sidebar list item: small thumb + category label + title + meta. */
function render_side_article(array $a): void
{
    ?>
    <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($a['slug']) ?>" class="side-article">
        <div class="side-thumb">
            <img src="<?= h(article_thumb_src($a['featured_image'], (int) $a['id'], 160, 160)) ?>" alt="<?= h($a['title']) ?>" loading="lazy" />
            <?= render_thumb_logo() ?>
        </div>
        <div class="side-article-body">
            <?php if (!empty($a['category_name'])): ?><span class="side-cat"><?= h($a['category_name']) ?></span><?php endif; ?>
            <h4><?= h($a['title']) ?></h4>
            <div class="article-meta"><span><?= h(time_ago($a['created_at'])) ?></span></div>
        </div>
    </a>
    <?php
}

/** Large sidebar feature card: image, with title + meta in a faded dark panel below it. */
function render_side_feature_card(array $a): void
{
    ?>
    <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($a['slug']) ?>" class="side-feature-card">
        <div class="thumb">
            <img src="<?= h(article_thumb_src($a['featured_image'], (int) $a['id'], 360, 220)) ?>" alt="<?= h($a['title']) ?>" loading="lazy" />
            <?= render_thumb_logo() ?>
            <?php if (!empty($a['category_name'])): ?><span class="cat-badge"><?= h($a['category_name']) ?></span><?php endif; ?>
        </div>
        <span class="cap-title"><?= h($a['title']) ?></span>
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
                <?php $featSrc = article_thumb_src($article['featured_image'], $article['id'], 1200, 675); ?>
                <img src="<?= h($featSrc) ?>" alt="<?= h($article['title']) ?>" />
                <?= render_thumb_logo() ?>
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
                <button type="button" class="copy-link-btn js-copy-link" data-url="<?= h($pageCanonical) ?>" title="Copy link to this article">
                    <i class="fas fa-link"></i> <span class="copy-text">Copy link</span>
                </button>
                <button type="button" class="copy-link-btn js-snap-btn"
                        data-image="<?= h($pageImage) ?>"
                        data-logo="<?= h(BASE_PATH) ?>/assets/img/oroma_circle_logo.svg"
                        data-title="<?= h($article['title']) ?>"
                        data-category="<?= h($article['category_name'] ?? '') ?>"
                        data-date="<?= h(format_date($article['created_at'])) ?>"
                        title="Save a shareable snapshot of this article">
                    <i class="fas fa-camera"></i> <span class="snap-text">Snap</span>
                </button>
            </div>
            <div class="article-meta-line article-views-line">
                <?php if ($showViews): ?>
                    <i class="fas fa-eye"></i> <?= (int) $article['views'] ?> Views
                    <span class="sep">&middot;</span>
                <?php endif; ?>
                <a href="#comments"><i class="fas fa-comment"></i> <?= (int) $commentCount ?> Comment<?= $commentCount !== 1 ? 's' : '' ?></a>
                <span class="sep">&middot;</span>
                <span><i class="fas fa-clock"></i> <?= reading_time($article['content']) ?> min read</span>
            </div>

            <div class="article-body article-font-<?= h($articleFont) ?>"><?= $cleanContent ?></div>

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
                <button class="share-btn js-copy-link" type="button" data-url="<?= h($pageCanonical) ?>" title="Copy link"><i class="fas fa-link"></i></button>
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
                                    <div class="comment-actions">
                                        <button type="button" class="comment-report-btn" data-comment-id="<?= (int) $c['id'] ?>" aria-expanded="false"><i class="fas fa-flag"></i> Report</button>
                                        <span class="comment-report-menu" hidden>
                                            <button type="button" data-reason="spam">Spam</button>
                                            <button type="button" data-reason="abusive">Abusive or hateful</button>
                                            <button type="button" data-reason="misleading">Misleading</button>
                                            <button type="button" data-reason="other">Other</button>
                                        </span>
                                        <span class="comment-report-done" role="status" hidden></span>
                                    </div>
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
                    <label class="comment-terms"><input type="checkbox" name="accept_terms" value="1" required /> <span>I agree to the <a href="<?= h(BASE_PATH) ?>/terms.php" target="_blank" rel="noopener">Terms of Use</a>. We have no tolerance for objectionable content or abusive users.</span></label>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Comment</button>
                    <p class="form-hint">Comments are checked automatically, and some are reviewed by our team before they appear. Your email address is optional and never shown; see our <a href="<?= h(BASE_PATH) ?>/privacy.php" target="_blank" rel="noopener">Privacy Policy</a>. Use <strong>Report</strong> on any comment you find abusive. We aim to review reports within 24 hours.</p>
                </form>
                <script>
                (function () {
                    // Report a comment: choose a reason, send it to the API, say thank you. Nothing about the reporter is shown to anyone.
                    var api = <?= json_encode(rtrim(BASE_PATH, '/') . '/api/v1') ?>;
                    document.querySelectorAll('.comment-actions').forEach(function (box) {
                        var btn = box.querySelector('.comment-report-btn'), menu = box.querySelector('.comment-report-menu'), done = box.querySelector('.comment-report-done');
                        btn.addEventListener('click', function () {
                            var open = menu.hidden;
                            menu.hidden = !open;
                            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                        });
                        menu.querySelectorAll('button').forEach(function (b) {
                            b.addEventListener('click', function () {
                                menu.hidden = true;
                                btn.hidden = true;
                                fetch(api + '/comments/' + btn.getAttribute('data-comment-id') + '/report', {
                                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ reason: b.getAttribute('data-reason'), platform: 'web' })
                                }).then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
                                  .then(function (res) {
                                      var gone = res.s === 404 && res.j && res.j.code === 'comment_gone';
                                      done.textContent = gone ? 'This comment is no longer available.' : ((res.j && res.j.message) || (res.s < 400 ? 'Thank you. We aim to review reports within 24 hours.' : 'Could not send your report. Please try again.'));
                                      done.hidden = false;
                                      if (res.j && res.j.data && res.j.data.hidden) { box.closest('.comment-item').style.opacity = '.45'; }
                                      if (res.s >= 400 && !gone) { btn.hidden = false; }
                                  })
                                  .catch(function () { done.textContent = 'Could not send your report. Please try again, or email <?= h(SITE_EMAIL) ?>.'; done.hidden = false; btn.hidden = false; });
                            });
                        });
                    });
                })();
                </script>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
