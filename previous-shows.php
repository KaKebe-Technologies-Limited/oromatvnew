<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/previous-shows-functions.php';

$activeNav       = 'shows';
$pageTitle       = 'Previous Shows · ' . SITE_NAME;
$pageDescription = 'Browse all previous episodes of Oroma TV — past shows, interviews and special broadcasts.';

$shows = get_previous_shows(200);

require __DIR__ . '/includes/header.php';
?>

<!-- hero -->
<section class="hero-banner hero-banner-compact">
    <div class="hero-banner-bg"></div>
    <div class="container">
        <span class="cat-badge" style="margin-bottom:14px;display:inline-block;">
            <i class="fas fa-film"></i> On Demand
        </span>
        <h1>Previous Shows</h1>
        <p class="lead">Browse every episode — past broadcasts, interviews and special features from Oroma TV.</p>
    </div>
</section>

<div class="container section">

    <div class="section-head reveal">
        <h2>All <span>Episodes</span></h2>
        <span style="font-size:13px;color:var(--gray);">
            <?= count($shows) ?> episode<?= count($shows) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (!$shows): ?>
        <div class="empty-state">
            <i class="fas fa-video"></i>
            <p>No previous shows yet. Check back soon.</p>
        </div>
    <?php else: ?>
        <!-- featured row first -->
        <?php $featured = array_filter($shows, fn($s) => $s['is_featured']); ?>
        <?php if ($featured): ?>
        <div class="section-head reveal" style="margin-bottom:16px;">
            <h2 style="font-size:18px;"><i class="fas fa-star" style="color:var(--gold)"></i> Featured <span>Episodes</span></h2>
        </div>
        <div class="shows-grid shows-grid-featured">
            <?php foreach (array_slice(array_values($featured), 0, 4) as $i => $show): ?>
            <a href="<?= h(get_youtube_watch_url($show['video_id'])) ?>"
               target="_blank" rel="noopener noreferrer"
               class="show-card reveal reveal-delay-<?= min($i + 1, 4) ?>">
                <div class="show-thumb">
                    <img src="<?= h($show['thumbnail_url'] ?: get_youtube_thumbnail($show['video_id'])) ?>"
                         alt="<?= h($show['title']) ?>" loading="lazy"
                         onerror="this.src='<?= h(BASE_PATH) ?>/assets/img/default-thumb.svg'" />
                    <div class="show-play-overlay"><span class="play-btn-circle"><i class="fab fa-youtube"></i></span></div>
                    <span class="show-featured-badge"><i class="fas fa-star"></i> Featured</span>
                </div>
                <div class="show-body">
                    <?php if ($show['guest_name']): ?><span class="show-guest"><i class="fas fa-microphone"></i> <?= h($show['guest_name']) ?></span><?php endif; ?>
                    <h3><?= h($show['title']) ?></h3>
                    <div class="show-meta">
                        <?php if ($show['upload_date']): ?><span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($show['upload_date'])) ?></span><?php endif; ?>
                        <?php if ($show['views'] > 0): ?><span><i class="fas fa-eye"></i> <?= number_format($show['views']) ?> views</span><?php endif; ?>
                    </div>
                    <span class="show-watch-btn"><i class="fab fa-youtube"></i> Watch on YouTube</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="section-head reveal" style="margin-top:40px;margin-bottom:16px;">
            <h2 style="font-size:18px;">All <span>Episodes</span></h2>
        </div>
        <?php endif; ?>

        <div class="shows-grid">
            <?php foreach ($shows as $i => $show): ?>
            <a href="<?= h(get_youtube_watch_url($show['video_id'])) ?>"
               target="_blank" rel="noopener noreferrer"
               class="show-card reveal reveal-delay-<?= min($i % 4 + 1, 4) ?>">
                <div class="show-thumb">
                    <img src="<?= h($show['thumbnail_url'] ?: get_youtube_thumbnail($show['video_id'])) ?>"
                         alt="<?= h($show['title']) ?>" loading="lazy"
                         onerror="this.src='<?= h(BASE_PATH) ?>/assets/img/default-thumb.svg'" />
                    <div class="show-play-overlay">
                        <span class="play-btn-circle"><i class="fab fa-youtube"></i></span>
                    </div>
                    <?php if ($show['is_featured']): ?>
                        <span class="show-featured-badge"><i class="fas fa-star"></i> Featured</span>
                    <?php endif; ?>
                </div>
                <div class="show-body">
                    <?php if ($show['guest_name']): ?>
                        <span class="show-guest"><i class="fas fa-microphone"></i> <?= h($show['guest_name']) ?></span>
                    <?php endif; ?>
                    <h3><?= h($show['title']) ?></h3>
                    <div class="show-meta">
                        <?php if ($show['upload_date']): ?>
                            <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($show['upload_date'])) ?></span>
                        <?php endif; ?>
                        <?php if ($show['views'] > 0): ?>
                            <span><i class="fas fa-eye"></i> <?= number_format($show['views']) ?> views</span>
                        <?php endif; ?>
                    </div>
                    <span class="show-watch-btn"><i class="fab fa-youtube"></i> Watch on YouTube</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
