<?php
require_once __DIR__ . '/includes/functions.php';

$activeNav   = 'watch';
$bodyClass   = 'watch-page';
$pageTitle   = 'Watch Live · ' . SITE_NAME;
$pageDescription = 'Watch ' . SITE_NAME . ' live — free, anywhere in the world.';

$streamStatus = get_setting('stream_status', 'offline');

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

<button id="back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<script src="<?= h(BASE_PATH) ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
