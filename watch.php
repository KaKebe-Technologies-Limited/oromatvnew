<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/previous-shows-functions.php';

$activeNav   = 'watch';
$bodyClass   = 'watch-page';
$pageTitle   = 'Watch Live · ' . SITE_NAME;
$pageDescription = 'Watch ' . SITE_NAME . ' live — free, anywhere in the world.';

$streamStatus = get_setting('stream_status', 'offline');

// ── previous shows ───────────────────────────────────────────
$shows = get_previous_shows(50);

require __DIR__ . '/includes/header.php';
?>

<!-- ── LIVE PLAYER ── -->
<section class="container watch-player-section watch-player-section-top">
    <div class="player-card-full">
        <div class="dacast-frame">
            <div class="video-wrapper">
                <?php if ($streamStatus === 'live'): ?>
                    <span class="live-pulse-badge"><span class="dot"></span> LIVE</span>
                <?php endif; ?>
                <iframe id="fcdd965b-4244-ed60-8f9f-0a249792f9dc-live-fd9a7722-2b54-4def-b040-e1887cff2325"
                    src="https://iframe.dacast.com/live/fcdd965b-4244-ed60-8f9f-0a249792f9dc/fd9a7722-2b54-4def-b040-e1887cff2325"
                    allow="autoplay; encrypted-media" allowfullscreen
                    webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen
                    scrolling="no" title="Oroma TV Live Stream"></iframe>
            </div>
        </div>
    </div>
</section>

<!-- ── PREVIOUS SHOWS ── -->
<section class="container section" id="previous-shows">
    <div class="section-head reveal">
        <h2><i class="fas fa-film" style="color:var(--maroon)"></i> Previous <span>Shows</span></h2>
        <a href="<?= h(BASE_PATH) ?>/previous-shows.php" class="view-all">
            View All <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <?php if (!$shows): ?>
        <div class="empty-state">
            <i class="fas fa-video"></i>
            <p>No previous shows yet. Check back soon.</p>
        </div>
    <?php else: ?>
        <div class="shows-grid">
            <?php foreach ($shows as $i => $show): ?>
            <a href="<?= h(get_youtube_watch_url($show['video_id'])) ?>"
               target="_blank" rel="noopener noreferrer"
               class="show-card reveal reveal-delay-<?= min($i % 4 + 1, 4) ?>">

                <!-- thumbnail -->
                <div class="show-thumb">
                    <img
                        src="<?= h($show['thumbnail_url'] ?: get_youtube_thumbnail($show['video_id'])) ?>"
                        alt="<?= h($show['title']) ?>"
                        loading="lazy"
                        onerror="this.src='<?= h(BASE_PATH) ?>/assets/img/default-thumb.svg'" />
                    <div class="show-play-overlay">
                        <span class="play-btn-circle"><i class="fab fa-youtube"></i></span>
                    </div>
                    <?php if ($show['is_featured']): ?>
                        <span class="show-featured-badge"><i class="fas fa-star"></i> Featured</span>
                    <?php endif; ?>
                </div>

                <!-- body -->
                <div class="show-body">
                    <?php if ($show['guest_name']): ?>
                        <span class="show-guest">
                            <i class="fas fa-microphone"></i>
                            <?= h($show['guest_name']) ?>
                        </span>
                    <?php endif; ?>
                    <h3><?= h($show['title']) ?></h3>
                    <div class="show-meta">
                        <?php if ($show['upload_date']): ?>
                            <span><i class="fas fa-calendar"></i>
                                <?= date('M j, Y', strtotime($show['upload_date'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($show['views'] > 0): ?>
                            <span><i class="fas fa-eye"></i>
                                <?= number_format($show['views']) ?> views
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="show-watch-btn">
                        <i class="fab fa-youtube"></i> Watch on YouTube
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (count($shows) >= 8): ?>
        <div style="text-align:center;margin-top:36px;" class="reveal">
            <a href="<?= h(BASE_PATH) ?>/previous-shows.php" class="btn btn-outline-dark">
                View All Previous Shows <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
