<?php
require_once __DIR__ . '/includes/functions.php';

$activeNav  = 'home';
$bodyClass  = 'is-home';
$pageTitle  = SITE_NAME . ' · Live Stream & News';

// ── data ──────────────────────────────────────────────────────
$categories    = get_active_categories();
$breakingNews  = get_breaking_news(12);

// 3 for the hero (main + 2 side cards) + up to 4 more for the "Highlighted Stories" strip below it
$featured = get_featured_articles(7);
// Fallback: pad with latest published so the hero and the highlights strip are never left empty
if (count($featured) < 7) {
    $featIds  = array_column($featured, 'id');
    $placeholders = count($featIds) ? implode(',', array_fill(0, count($featIds), '?')) : '0';
    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
         FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u ON u.id = a.author_id
         WHERE a.status='published' AND a.id NOT IN ($placeholders)
         ORDER BY a.created_at DESC LIMIT " . (7 - count($featured))
    );
    $stmt->execute($featIds ?: []);
    $featured = array_merge($featured, $stmt->fetchAll());
}

// Active category filter (AJAX or page load)
$filterCatSlug = trim((string)($_GET['cat'] ?? ''));
$filterCatId   = null;
if ($filterCatSlug) {
    foreach ($categories as $c) {
        if ($c['slug'] === $filterCatSlug) { $filterCatId = (int)$c['id']; break; }
    }
}

$latestArticles = get_latest_articles(8, $filterCatId);
$trending       = get_trending_articles(6);

// Streams
$youtubeStreams = db()->query(
    "SELECT * FROM streams WHERE type='youtube' AND is_active=1
     ORDER BY is_default DESC, sort_order ASC"
)->fetchAll();
$radioStream = db()->query(
    "SELECT * FROM streams WHERE type='radio' AND is_active=1
     ORDER BY is_default DESC LIMIT 1"
)->fetch();
$defaultYoutube = $youtubeStreams[0] ?? null;
$streamStatus   = get_setting('stream_status', 'offline');

require __DIR__ . '/includes/header.php';
?>

<?php /* ── NEWS TICKER ── */ if ($breakingNews): ?>
<div class="news-ticker-bar" aria-label="Latest news headlines">
    <div class="ticker-track-wrap">
        <div class="ticker-scroll">
            <?php foreach (array_merge($breakingNews, $breakingNews) as $bn): ?>
                <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($bn['slug']) ?>">
                    <?= h($bn['title']) ?>
                </a>
                <span class="ticker-dot">◆</span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php /* ── HERO SECTION ── */
$hero1 = $featured[0] ?? null;
$hero2 = $featured[1] ?? null;
$hero3 = $featured[2] ?? null;
$highlighted = array_slice($featured, 3, 4);
?>
<?php if ($hero1): ?>
<section class="hero-section container-wide">
    <div class="hero-grid">

        <?php /* Main hero card */ ?>
        <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($hero1['slug']) ?>"
           class="hero-main reveal">
            <?php $img1 = article_thumb_src($hero1['featured_image'], $hero1['id'], 900, 560); ?>
            <img src="<?= h($img1) ?>" alt="<?= h($hero1['title']) ?>" loading="eager" />
            <?= render_thumb_logo() ?>
            <div class="hero-overlay">
                <?php if ($hero1['category_name']): ?>
                    <span class="cat-badge"><?= h($hero1['category_name']) ?></span>
                <?php endif; ?>
                <h2><?= h($hero1['title']) ?></h2>
            </div>
        </a>

        <?php /* Side cards */ ?>
        <div class="hero-side">
            <?php foreach ([$hero2, $hero3] as $hs): if (!$hs) continue; ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($hs['slug']) ?>"
               class="hero-side-card reveal">
                <?php $hsImg = article_thumb_src($hs['featured_image'], $hs['id'], 480, 300); ?>
                <div class="thumb">
                    <img src="<?= h($hsImg) ?>" alt="<?= h($hs['title']) ?>" loading="lazy" />
                    <?= render_thumb_logo() ?>
                </div>
                <div class="body">
                    <?php if ($hs['category_name']): ?>
                        <span class="cat-badge cat-badge-sm"><?= h($hs['category_name']) ?></span>
                    <?php endif; ?>
                    <h3><?= h($hs['title']) ?></h3>
                    <p class="excerpt"><?= h($hs['excerpt'] ?: make_excerpt($hs['content'], 80)) ?></p>
                    <div class="card-meta">
                        <span><?= h(time_ago($hs['created_at'])) ?></span>
                        <span class="sep">·</span>
                        <span><?= reading_time($hs['content']) ?> min</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

    </div>
</section>
<?php endif; ?>

<?php /* ── HIGHLIGHTED STORIES (extra featured picks from admin, below the hero) ── */ ?>
<?php if ($highlighted): ?>
<section class="container-wide highlighted-section">
    <div class="highlighted-list">
        <?php foreach ($highlighted as $h): ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($h['slug']) ?>" class="highlighted-item reveal">
                <div class="highlighted-thumb">
                    <?php $hImg = article_thumb_src($h['featured_image'], $h['id'], 200, 200); ?>
                    <img src="<?= h($hImg) ?>" alt="<?= h($h['title']) ?>" loading="lazy" />
                    <?= render_thumb_logo() ?>
                </div>
                <div class="highlighted-body">
                    <?php if ($h['category_name']): ?><span class="side-cat"><?= h($h['category_name']) ?></span><?php endif; ?>
                    <h4><?= h($h['title']) ?></h4>
                    <div class="article-meta"><span><?= h(time_ago($h['created_at'])) ?></span></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php /* ── CATEGORY QUICK FILTER ── */ ?>
<div class="container">
    <div class="cat-filter-bar reveal" id="catFilterBar">
        <a href="<?= h(BASE_PATH) ?>/index.php"
           class="cat-pill<?= $filterCatSlug === '' ? ' active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= h(BASE_PATH) ?>/index.php?cat=<?= urlencode($cat['slug']) ?>"
               class="cat-pill<?= $filterCatSlug === $cat['slug'] ? ' active' : '' ?>">
                <i class="fas <?= h($cat['icon'] ?: 'fa-folder') ?>"></i>
                <?= h($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php /* ── LATEST NEWS GRID ── */ ?>
<section class="container section" id="latestNews">
    <div class="section-head reveal">
        <h2>Latest <span>News</span></h2>
        <a href="<?= h(BASE_PATH) ?>/news.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="news-grid-4" id="newsGrid">
        <?php if (!$latestArticles): ?>
            <div class="empty-state" style="grid-column:1/-1;">
                <i class="fas fa-newspaper"></i>
                <p>No articles published yet. Check back soon.</p>
            </div>
        <?php else: foreach ($latestArticles as $i => $a): ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($a['slug']) ?>"
               class="news-card reveal reveal-delay-<?= min($i % 4 + 1, 4) ?>">
                <div class="thumb">
                    <?php $aImg = article_thumb_src($a['featured_image'], $a['id'], 480, 300); ?>
                    <img src="<?= h($aImg) ?>" alt="<?= h($a['title']) ?>" loading="lazy" />
                    <?= render_thumb_logo() ?>
                    <?php if ($a['category_name']): ?>
                        <span class="cat-badge cat-badge-sm"><?= h($a['category_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="content">
                    <h3><?= h($a['title']) ?></h3>
                    <p class="excerpt"><?= h($a['excerpt'] ?: make_excerpt($a['content'], 90)) ?></p>
                    <div class="card-meta">
                        <span class="author"><?= h($a['author_name'] ?? 'Oroma TV') ?></span>
                        <span class="sep">·</span>
                        <span><?= h(time_ago($a['created_at'])) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>

    <div style="text-align:center;margin-top:36px;" class="reveal">
        <a href="<?= h(BASE_PATH) ?>/news.php<?= $filterCatSlug ? '?category=' . urlencode($filterCatSlug) : '' ?>"
           class="btn btn-outline-dark">
            Load More Stories <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>

<?php /* ── TRENDING NOW ── */ ?>
<?php if ($trending): ?>
<section class="container section" style="padding-top:0;">
    <div class="section-head reveal">
        <h2><i class="fas fa-fire" style="color:var(--maroon)"></i> Trending <span>Now</span></h2>
        <a href="<?= h(BASE_PATH) ?>/news.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="trending-strip">
        <?php foreach ($trending as $i => $t): ?>
        <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($t['slug']) ?>"
           class="trending-card reveal">
            <div class="thumb">
                <?php $tImg = article_thumb_src($t['featured_image'], $t['id'], 360, 220); ?>
                <img src="<?= h($tImg) ?>" alt="<?= h($t['title']) ?>" loading="lazy" />
                <?= render_thumb_logo() ?>
                <span class="trending-rank <?= $i === 0 ? 'top-rank' : '' ?>"><?= $i + 1 ?></span>
            </div>
            <div class="body">
                <?php if ($t['category_name']): ?>
                    <span class="cat-badge cat-badge-sm"><?= h($t['category_name']) ?></span>
                <?php endif; ?>
                <h3><?= h($t['title']) ?></h3>
                <div class="card-meta">
                    <i class="fas fa-eye"></i> <?= number_format((int)$t['views']) ?> views
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php /* ── LIVE STREAM SECTION ── */ ?>
<section class="watch-section" id="watch">
    <div class="watch-banner-bg"></div>
    <div class="container">
        <div class="watch-section-head reveal">
            <?php if ($streamStatus === 'live'): ?>
                <span class="live-dot-badge"><span class="dot"></span> Live Now</span>
            <?php endif; ?>
            <h2>Watch &amp; Listen <span>Live</span></h2>
            <p>Stream Oroma TV and Oroma Radio — free, anywhere in the world.</p>
        </div>

        <div class="player-card reveal">
            <div class="player-header">
                <div>
                    <h3>Now Streaming</h3>
                    <p>Stream · Watch · Connect</p>
                </div>
                <?php if ($streamStatus === 'live'): ?>
                    <span class="badge badge-live"><span class="dot"></span> Live</span>
                <?php else: ?>
                    <span class="badge badge-offline"><span class="dot"></span> Offline</span>
                <?php endif; ?>
            </div>

            <div class="tabs" role="tablist">
                <button class="tab-btn active" data-tab="youtube" role="tab" aria-selected="true">
                    <i class="fab fa-youtube"></i> TV
                </button>
                <button class="tab-btn" data-tab="radio" role="tab" aria-selected="false">
                    <i class="fas fa-broadcast-tower"></i> Radio
                </button>
            </div>

            <div class="tab-pane active" id="pane-youtube" role="tabpanel">
                <div class="video-wrapper">
                    <?php if ($defaultYoutube): ?>
                        <iframe id="youtubePlayer"
                            src="<?= h(youtube_embed_url($defaultYoutube['url_or_id'])) ?>"
                            allow="autoplay; encrypted-media" allowfullscreen></iframe>
                    <?php else: ?>
                        <div class="empty-stream">
                            <i class="fas fa-tv"></i>
                            <div>No live stream configured.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (count($youtubeStreams) > 1): ?>
                    <div class="channel-selector">
                        <?php foreach ($youtubeStreams as $i => $s): ?>
                            <button class="channel-btn<?= $i === 0 ? ' active-channel' : '' ?>"
                                data-embed="<?= h(youtube_embed_url($s['url_or_id'])) ?>">
                                <i class="<?= h($s['icon'] ?: 'fas fa-tv') ?>"></i> <?= h($s['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane" id="pane-radio" role="tabpanel">
                <div class="radio-wrapper">
                    <?php if ($radioStream): ?>
                        <iframe src="<?= h($radioStream['url_or_id']) ?>" allow="autoplay" loading="lazy"></iframe>
                    <?php else: ?>
                        <div class="empty-stream" style="height:200px;">
                            <i class="fas fa-headphones"></i>
                            <div>No radio stream configured.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php /* ── NEWSLETTER ── */ ?>
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-box reveal">
            <div class="newsletter-text">
                <i class="fas fa-envelope-open-text"></i>
                <div>
                    <h3>Stay Informed</h3>
                    <p>Get the latest Oroma TV news delivered to your inbox.</p>
                </div>
            </div>
            <form class="newsletter-form" onsubmit="return false;">
                <input type="email" placeholder="Your email address" aria-label="Email address" />
                <button type="submit" class="btn btn-gold">Subscribe <i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
