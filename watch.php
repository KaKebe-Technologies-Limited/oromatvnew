<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/previous-shows-functions.php';

$activeNav   = 'watch';
$bodyClass   = 'watch-page';
$pageTitle   = 'Watch Live · ' . SITE_NAME;
$pageDescription = 'Watch ' . SITE_NAME . ' live TV and radio streams — free, anywhere in the world.';

// ── streams ──────────────────────────────────────────────────
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

// ── previous shows ───────────────────────────────────────────
$shows = get_previous_shows(50);

require __DIR__ . '/includes/header.php';
?>

<!-- ── PAGE HERO ── -->
<div class="watch-page-hero">
    <div class="container">
        <div class="watch-hero-text">
            <?php if ($streamStatus === 'live'): ?>
                <span class="live-dot-badge"><span class="dot"></span> Live Now</span>
            <?php endif; ?>
            <h1><i class="fas fa-broadcast-tower"></i> Watch &amp; Listen Live</h1>
            <p>Oroma TV and Oroma Radio — streaming free, anywhere in the world.</p>
        </div>
    </div>
</div>

<!-- ── LIVE PLAYER ── -->
<section class="container watch-player-section">
    <div class="player-card-full">

        <div class="player-card-header">
            <div class="player-card-title">
                <h2>Now Streaming</h2>
                <p>Tune in live — TV &amp; Radio</p>
            </div>
            <?php if ($streamStatus === 'live'): ?>
                <span class="badge badge-live"><span class="dot"></span> Live</span>
            <?php else: ?>
                <span class="badge badge-offline"><span class="dot"></span> Offline</span>
            <?php endif; ?>
        </div>

        <!-- tabs -->
        <div class="tabs" role="tablist">
            <button class="tab-btn active" data-tab="youtube" role="tab" aria-selected="true">
                <i class="fab fa-youtube"></i> Live TV
            </button>
            <button class="tab-btn" data-tab="radio" role="tab" aria-selected="false">
                <i class="fas fa-broadcast-tower"></i> Radio
            </button>
        </div>

        <!-- YouTube pane -->
        <div class="tab-pane active" id="pane-youtube" role="tabpanel">
            <div class="video-wrapper">
                <?php if ($defaultYoutube): ?>
                    <iframe id="youtubePlayer"
                        src="<?= h(youtube_embed_url($defaultYoutube['url_or_id'])) ?>"
                        allow="autoplay; encrypted-media; picture-in-picture"
                        allowfullscreen title="Oroma TV Live Stream"></iframe>
                <?php else: ?>
                    <div class="empty-stream">
                        <i class="fas fa-tv"></i>
                        <p>No live stream is configured yet.</p>
                        <p style="font-size:13px;opacity:.7;">Check back soon or set one up in the Admin Panel.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($youtubeStreams) > 1): ?>
            <div class="channel-selector">
                <?php foreach ($youtubeStreams as $i => $s): ?>
                    <button class="channel-btn<?= $i === 0 ? ' active-channel' : '' ?>"
                        data-embed="<?= h(youtube_embed_url($s['url_or_id'])) ?>">
                        <i class="<?= h($s['icon'] ?: 'fas fa-tv') ?>"></i>
                        <?= h($s['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Radio pane -->
        <div class="tab-pane" id="pane-radio" role="tabpanel">
            <div class="radio-wrapper">
                <?php if ($radioStream): ?>
                    <iframe src="<?= h($radioStream['url_or_id']) ?>"
                        allow="autoplay" loading="lazy" title="Oroma Radio"></iframe>
                <?php else: ?>
                    <div class="empty-stream" style="height:220px;">
                        <i class="fas fa-headphones"></i>
                        <p>No radio stream configured yet.</p>
                    </div>
                <?php endif; ?>
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
