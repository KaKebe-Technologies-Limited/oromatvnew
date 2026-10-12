<?php
require_once __DIR__ . '/includes/functions.php';

$activeNav  = 'home';
$bodyClass  = 'is-home';
$pageTitle  = SITE_NAME . ' · Live Stream & News';

// ── data ──────────────────────────────────────────────────────
$categories = get_active_categories();

// 3 for the hero slideshow + 3 for the hero side cards + 4 for the quick-read strip
$featured = get_featured_articles(10);
// Only articles actually marked "Featured" should be off-limits to Latest News below —
// the fallback padding just below borrows latest articles to keep the hero full, and
// those should still be free to also appear in Latest News (otherwise, on a site with
// few articles, the hero fallback can swallow everything and leave Latest News empty).
$realFeaturedIds = array_map('intval', array_column($featured, 'id'));
// Fallback: pad with latest published so the hero is never left empty
if (count($featured) < 10) {
    $featIds  = array_column($featured, 'id');
    $placeholders = count($featIds) ? implode(',', array_fill(0, count($featIds), '?')) : '0';
    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
         FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u ON u.id = a.author_id
         WHERE a.status='published' AND a.id NOT IN ($placeholders)
         ORDER BY a.created_at DESC LIMIT " . (10 - count($featured))
    );
    $stmt->execute($featIds ?: []);
    $featured = array_merge($featured, $stmt->fetchAll());
}

// Active category filter for the Latest News grid
$filterCatSlug = trim((string)($_GET['cat'] ?? ''));
$filterCatId   = null;
if ($filterCatSlug) {
    foreach ($categories as $c) {
        if ($c['slug'] === $filterCatSlug) { $filterCatId = (int)$c['id']; break; }
    }
}

// Only the genuinely-featured articles are excluded from Latest News below —
// hero fallback padding (see above) stays eligible to show there too.
$shownIds = $realFeaturedIds;

$latestArticles = get_latest_articles(8, $filterCatId, 0, $filterCatId ? [] : $shownIds);
$trending       = get_trending_articles(6);

// A compact list of further headlines — never repeats what's already on screen.
$moreHeadlines = get_latest_articles(
    6, null, 0,
    array_unique(array_merge($shownIds, array_column($latestArticles, 'id')))
);

$heroSlides   = array_slice($featured, 0, 3);
$heroSide     = array_slice($featured, 3, 3);
$quickStories = array_slice($featured, 6, 4);

$streamStatus = get_setting('stream_status', 'offline');
$isLive       = $streamStatus === 'live';

require __DIR__ . '/includes/header.php';
?>

<?php /* ── QUICK-READ STRIP ── */ if ($quickStories): ?>
<section class="quick-strip" aria-label="Quick reads">
    <div class="container-wide">
        <div class="quick-strip-inner">
            <?php foreach ($quickStories as $q): ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($q['slug']) ?>" class="quick-item"
               style="<?= h(category_style($q['category_slug'] ?? null)) ?>">
                <span class="quick-thumb">
                    <img src="<?= h(article_thumb_src($q['featured_image'], (int) $q['id'], 160, 160)) ?>"
                         alt="" loading="lazy" />
                </span>
                <span class="quick-body">
                    <?php if (!empty($q['category_name'])): ?><span class="quick-cat"><?= h($q['category_name']) ?></span><?php endif; ?>
                    <span class="quick-title"><?= h($q['title']) ?></span>
                    <span class="quick-meta"><?= reading_time($q['content']) ?> min read · <?= h(time_ago($q['created_at'])) ?></span>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php /* ── HERO ── big rotating story + three compact cards ── */ if ($heroSlides): ?>
<section class="hero-section container-wide">
    <div class="hero-grid<?= $heroSide ? '' : ' hero-grid--solo' ?>">

        <div class="hero-main">
            <div class="hero-slideshow" id="heroSlideshow">
                <?php foreach ($heroSlides as $i => $slide): ?>
                <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($slide['slug']) ?>"
                   class="hero-slide<?= $i === 0 ? ' active' : '' ?>"
                   style="<?= h(category_style($slide['category_slug'] ?? null)) ?>">
                    <img src="<?= h(article_thumb_src($slide['featured_image'], (int) $slide['id'], 1000, 620)) ?>"
                         alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" />
                    <?= render_thumb_logo() ?>
                    <div class="hero-overlay">
                        <?php if ($slide['category_name']): ?>
                            <span class="cat-badge"><?= h($slide['category_name']) ?></span>
                        <?php endif; ?>
                        <h2><?= h($slide['title']) ?></h2>
                        <p class="hero-excerpt"><?= h($slide['excerpt'] ?: make_excerpt($slide['content'], 150)) ?></p>
                        <div class="hero-meta">
                            <span class="hero-avatar"><?= h(comment_initial($slide['author_name'] ?? 'Oroma')) ?></span>
                            <span class="hero-author">By <?= h($slide['author_name'] ?? 'Oroma TV') ?></span>
                            <span class="hero-meta-item"><i class="far fa-calendar"></i> <?= h(format_date($slide['created_at'])) ?></span>
                            <span class="hero-meta-item"><i class="far fa-clock"></i> <?= reading_time($slide['content']) ?> min read</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>

                <?php if (count($heroSlides) > 1): ?>
                <div class="hero-slide-dots">
                    <?php foreach ($heroSlides as $i => $slide): ?>
                        <button type="button" class="hero-slide-dot<?= $i === 0 ? ' active' : '' ?>"
                                data-slide-index="<?= $i ?>" aria-label="Show story <?= $i + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($heroSide): ?>
        <div class="hero-side">
            <?php foreach ($heroSide as $hs): ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($hs['slug']) ?>" class="side-story"
               style="<?= h(category_style($hs['category_slug'] ?? null)) ?>">
                <span class="side-story-thumb">
                    <img src="<?= h(article_thumb_src($hs['featured_image'], (int) $hs['id'], 320, 240)) ?>"
                         alt="" loading="lazy" />
                </span>
                <span class="side-story-body">
                    <?php if ($hs['category_name']): ?><span class="chip"><?= h($hs['category_name']) ?></span><?php endif; ?>
                    <span class="side-story-title"><?= h($hs['title']) ?></span>
                    <span class="side-story-meta"><span class="by">By <b><?= h($hs['author_name'] ?? 'Oroma TV') ?></b></span> <span class="dot-sep"></span> <?= h(format_date($hs['created_at'])) ?></span>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>
<?php endif; ?>

<?php /* ── LIVE PROMO BANNER ── */ ?>
<section class="container section-tight">
    <div class="promo-banner reveal">
        <div class="promo-copy">
            <span class="promo-eyebrow<?= $isLive ? ' is-live' : '' ?>">
                <span class="dot"></span> <?= $isLive ? 'Live now on Oroma TV' : 'Oroma TV Live' ?>
            </span>
            <h2>Watch Oroma TV live, wherever you are</h2>
            <p>Stream our broadcast in real time, and catch up on every previous show whenever you like.</p>
        </div>
        <div class="promo-actions">
            <a href="<?= h(BASE_PATH) ?>/watch.php" class="btn btn-light"><i class="fas fa-play"></i> <?= $isLive ? 'Watch now' : 'Watch live' ?></a>
            <a href="<?= h(BASE_PATH) ?>/previous-shows.php" class="btn btn-ghost">Previous shows</a>
        </div>
    </div>
</section>

<?php /* ── LATEST NEWS ── */ ?>
<section class="container section" id="latestNews">
    <div class="section-head reveal">
        <h2>Latest <span>News</span></h2>
        <a href="<?= h(BASE_PATH) ?>/news.php" class="view-all">View all <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="cat-filter-bar" id="catFilterBar" role="navigation" aria-label="Filter by category">
        <a href="<?= h(BASE_PATH) ?>/index.php#latestNews"
           class="cat-pill<?= $filterCatSlug === '' ? ' active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= h(BASE_PATH) ?>/index.php?cat=<?= urlencode($cat['slug']) ?>#latestNews"
               class="cat-pill<?= $filterCatSlug === $cat['slug'] ? ' active' : '' ?>">
                <i class="fas <?= h($cat['icon'] ?: 'fa-folder') ?>"></i>
                <?= h($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="news-grid-4" id="newsGrid">
        <?php if (!$latestArticles): ?>
            <div class="empty-state" style="grid-column:1/-1;">
                <i class="fas fa-newspaper"></i>
                <p>No articles in this category yet. Check back soon.</p>
            </div>
        <?php else: foreach ($latestArticles as $i => $a): render_story_card($a, $i); endforeach; endif; ?>
    </div>

    <div class="section-cta reveal">
        <a href="<?= h(BASE_PATH) ?>/news.php<?= $filterCatSlug ? '?category=' . urlencode($filterCatSlug) : '' ?>"
           class="btn btn-outline-dark">
            View all stories <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>

<?php /* ── TRENDING + STAY CONNECTED ── */ if ($trending): $trendTop = $trending[0]; $trendList = array_slice($trending, 1, 4); ?>
<section class="container section" style="padding-top:0;">
    <div class="trending-layout">
        <div>
            <div class="section-head reveal">
                <h2>Trending <span>Now</span></h2>
                <a href="<?= h(BASE_PATH) ?>/news.php" class="view-all">View all <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="trending-main">
                <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($trendTop['slug']) ?>"
                   class="feature-card reveal" style="<?= h(category_style($trendTop['category_slug'] ?? null)) ?>">
                    <img src="<?= h(article_thumb_src($trendTop['featured_image'], (int) $trendTop['id'], 640, 520)) ?>"
                         alt="" loading="lazy" />
                    <?= render_thumb_logo() ?>
                    <span class="feature-overlay">
                        <span class="cat-badge"><i class="fas fa-fire"></i> Most read</span>
                        <span class="feature-title"><?= h($trendTop['title']) ?></span>
                        <span class="feature-meta"><i class="far fa-eye"></i> <?= number_format((int) $trendTop['views']) ?> views</span>
                    </span>
                </a>

                <ol class="rank-list">
                    <?php foreach ($trendList as $n => $t): ?>
                    <li class="reveal reveal-delay-<?= min($n + 1, 4) ?>">
                        <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($t['slug']) ?>" class="rank-item"
                           style="<?= h(category_style($t['category_slug'] ?? null)) ?>">
                            <span class="rank-num"><?= $n + 2 ?></span>
                            <span class="rank-thumb">
                                <img src="<?= h(article_thumb_src($t['featured_image'], (int) $t['id'], 160, 130)) ?>" alt="" loading="lazy" />
                            </span>
                            <span class="rank-body">
                                <?php if (!empty($t['category_name'])): ?><span class="story-cat"><?= h($t['category_name']) ?></span><?php endif; ?>
                                <span class="rank-title"><?= h($t['title']) ?></span>
                                <span class="rank-meta"><?= number_format((int) $t['views']) ?> views</span>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>

        <aside>
            <div class="section-head reveal">
                <h2>Stay <span>Connected</span></h2>
            </div>
            <div class="social-tiles">
                <?php foreach (social_links() as $key => $s): ?>
                <a href="<?= h($s['url']) ?>" class="social-tile tile-<?= h($key) ?> reveal" target="_blank" rel="noopener noreferrer">
                    <i class="<?= h($s['icon']) ?>"></i>
                    <span><strong><?= h($s['label']) ?></strong><small>Follow Oroma TV</small></span>
                    <i class="fas fa-arrow-up-right-from-square go"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</section>
<?php endif; ?>

<?php /* ── MORE HEADLINES ── */ if ($moreHeadlines): ?>
<section class="container section" style="padding-top:0;">
    <div class="section-head reveal">
        <h2>More <span>Headlines</span></h2>
        <a href="<?= h(BASE_PATH) ?>/news.php" class="view-all">View all <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="headline-list reveal">
        <?php foreach ($moreHeadlines as $m): ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($m['slug']) ?>" class="headline-row"
               style="<?= h(category_style($m['category_slug'] ?? null)) ?>">
                <span class="headline-thumb">
                    <img src="<?= h(article_thumb_src($m['featured_image'], (int) $m['id'], 160, 160)) ?>" alt="" loading="lazy" />
                </span>
                <span class="headline-body">
                    <?php if ($m['category_name']): ?><span class="story-cat"><?= h($m['category_name']) ?></span><?php endif; ?>
                    <h4><?= h($m['title']) ?></h4>
                    <span class="rank-meta"><?= h(time_ago($m['created_at'])) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
