<?php
require_once __DIR__ . '/includes/functions.php';

$activeNav = 'home';
$pageTitle = SITE_NAME . ' · Live Stream & News';

$youtubeStreams = db()->query(
    "SELECT * FROM streams WHERE type = 'youtube' AND is_active = 1 ORDER BY is_default DESC, sort_order ASC, id ASC"
)->fetchAll();

$radioStream = db()->query(
    "SELECT * FROM streams WHERE type = 'radio' AND is_active = 1 ORDER BY is_default DESC, sort_order ASC, id ASC LIMIT 1"
)->fetch();

$defaultYoutube = $youtubeStreams[0] ?? null;

$featured = db()->query(
    "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
     FROM articles a
     LEFT JOIN categories c ON c.id = a.category_id
     LEFT JOIN users u ON u.id = a.author_id
     WHERE a.status = 'published' AND a.is_featured = 1
     ORDER BY a.created_at DESC LIMIT 1"
)->fetch();

$excludeId = $featured['id'] ?? 0;
$latestStmt = db()->prepare(
    "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
     FROM articles a
     LEFT JOIN categories c ON c.id = a.category_id
     LEFT JOIN users u ON u.id = a.author_id
     WHERE a.status = 'published' AND a.id != ?
     ORDER BY a.created_at DESC LIMIT 6"
);
$latestStmt->execute([$excludeId]);
$latest = $latestStmt->fetchAll();

if (!$featured && $latest) {
    $featured = array_shift($latest);
}

$trending = get_trending_articles(6, $featured['id'] ?? null);

require __DIR__ . '/includes/header.php';
?>

<section class="hero-banner">
    <div class="hero-banner-bg"></div>
    <div class="container hero-banner-content">
        <?php if ($streamStatus === 'live'): ?>
            <span class="badge badge-live"><span class="dot"></span> Live Now</span>
        <?php else: ?>
            <span class="badge" style="background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">Oromo News &amp; Live Media</span>
        <?php endif; ?>
        <h1>Your Voice.<br>Your Community.<br>Always On.</h1>
        <p class="lead">Live TV, radio, and the stories that matter to the Oromo community — streaming free, anywhere in the world.</p>
        <div class="hero-cta-row">
            <a href="#watch" class="btn btn-gold btn-lg"><i class="fas fa-play"></i> Watch Live</a>
            <a href="<?= h(BASE_PATH) ?>/news.php" class="btn btn-outline-light btn-lg"><i class="fas fa-newspaper"></i> Read Latest News</a>
        </div>
    </div>
</section>

<div class="container section" style="padding-top:56px;">
    <div class="section-head">
        <h2>Latest <span>News</span></h2>
        <a href="<?= h(BASE_PATH) ?>/news.php" class="view-all">View all <i class="fas fa-arrow-right"></i></a>
    </div>

    <?php if (!$featured && !$latest): ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <p>No articles published yet. Check back soon.</p>
        </div>
    <?php else: ?>
        <?php if ($featured): ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($featured['slug']) ?>" class="featured-article">
                <div class="thumb">
                    <?php if ($featured['featured_image']): ?>
                        <img src="<?= h(BASE_PATH . '/' . $featured['featured_image']) ?>" alt="<?= h($featured['title']) ?>" loading="lazy" />
                    <?php else: ?>
                        <div class="thumb-fallback"></div>
                    <?php endif; ?>
                </div>
                <div class="body">
                    <?php if ($featured['category_name']): ?>
                        <span class="badge badge-category"><?= h($featured['category_name']) ?></span>
                    <?php endif; ?>
                    <h2><?= h($featured['title']) ?></h2>
                    <p><?= h($featured['excerpt'] ?: make_excerpt($featured['content'])) ?></p>
                    <div class="article-meta">
                        <span><i class="fas fa-user"></i> <?= h($featured['author_name'] ?? 'Oroma TV') ?></span>
                        <span class="sep">&middot;</span>
                        <span><?= h(time_ago($featured['created_at'])) ?></span>
                    </div>
                </div>
            </a>
        <?php endif; ?>

        <?php if ($latest): ?>
            <div class="news-grid">
                <?php foreach ($latest as $article): ?>
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
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($trending): ?>
<div class="container section" style="padding-top:8px;padding-bottom:8px;">
    <div class="section-head">
        <h2><i class="fas fa-fire" style="color:var(--maroon);"></i> Trending <span>Now</span></h2>
    </div>
    <div class="trending-strip">
        <?php foreach ($trending as $i => $t): ?>
            <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($t['slug']) ?>" class="trending-card">
                <span class="trending-rank"><?= $i + 1 ?></span>
                <div class="thumb">
                    <?php if ($t['featured_image']): ?>
                        <img src="<?= h(BASE_PATH . '/' . $t['featured_image']) ?>" alt="<?= h($t['title']) ?>" loading="lazy" />
                    <?php else: ?>
                        <div class="thumb-fallback"></div>
                    <?php endif; ?>
                </div>
                <div class="body">
                    <?php if ($t['category_name']): ?>
                        <span class="badge badge-category"><?= h($t['category_name']) ?></span>
                    <?php endif; ?>
                    <h3><?= h($t['title']) ?></h3>
                    <div class="article-meta"><span><i class="fas fa-eye"></i> <?= (int) $t['views'] ?> views</span></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<section class="watch-section" id="watch">
    <div class="watch-banner-bg"></div>
    <div class="container">
        <div class="watch-section-head">
            <h2>Watch &amp; Listen <span>Live</span></h2>
            <p>Tune in to Oroma TV and Oroma Radio, streaming straight from the source.</p>
        </div>

        <div class="player-card">
            <div class="player-header">
                <div>
                    <h3 style="font-size:17px;font-weight:700;">Now Streaming</h3>
                    <p style="color:var(--text-muted);font-size:13px;margin-top:2px;">Stream · Watch · Connect</p>
                </div>
                <?php if ($streamStatus === 'live'): ?>
                    <span class="badge badge-live"><span class="dot"></span> Live Now</span>
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
                            allow="autoplay; encrypted-media"
                            allowfullscreen></iframe>
                    <?php else: ?>
                        <div class="empty-stream">
                            <i class="fas fa-tv"></i>
                            <div>No live stream configured yet.</div>
                            <div style="font-size:12px;opacity:0.7;">Check back soon, or set one up in the Admin Panel.</div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($youtubeStreams) > 0): ?>
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
                        <div class="empty-stream" style="background:var(--navy);height:100%;border-radius:var(--radius-md);">
                            <i class="fas fa-headphones"></i>
                            <div>No radio stream configured yet.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
