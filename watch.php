<?php
/**
 * Watch live — the synchronized stage (assets/js/live-sync.js does the work in the browser).
 *   watch.php          what is on air right now; switches by itself when the next programme starts
 *   watch.php?p=ID     a programme's own page — the share link: a countdown while it is upcoming, the stage once it is on air
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/live-functions.php';

$activeNav = 'watch';
$bodyClass = 'watch-page';

$live = resolve_live();
$program = $live['program'];
$next = $live['next'];

// A programme's own page (a shared link).
$requested = null;
$requestedState = null;
if (isset($_GET['p']) && (int) $_GET['p'] > 0) {
    $requested = get_live_program((int) $_GET['p']);
    $requestedState = $requested ? live_program_state($requested) : null;
}
$view = $requested && $requestedState === 'upcoming' ? 'premiere' : 'stage';

// What the first paint says, before the script takes over.
if ($live['source'] === 'program') {
    $first = ['title' => $program['title'], 'kicker' => live_kind_label($program['kind'] ?? 'show'), 'desc' => (string) $program['description'],
        'thumb' => live_program_thumbnail($program), 'live' => true, 'yt' => $program['provider'] === 'youtube' ? get_youtube_watch_url($program['video_id']) : '',
        'share' => rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php?p=' . (int) $program['id']];
} elseif ($live['source'] === 'main') {
    $main = main_channel(false);
    $first = ['title' => SITE_NAME . ' Live', 'kicker' => 'Live broadcast', 'desc' => '', 'thumb' => $main['video_id'] ? get_youtube_thumbnail($main['video_id']) : null,
        'live' => true, 'yt' => (string) $main['watch_url'], 'share' => rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php'];
} else {
    $first = ['title' => $live['playlist'] ? 'Playing from our library' : 'We are off air', 'kicker' => 'Library', 'desc' => '', 'thumb' => null, 'live' => false, 'yt' => '',
        'share' => rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php'];
}

if ($requested) {
    $kindLabel = live_kind_label($requested['kind'] ?? 'show');
    $pageTitle = $requested['title'] . ' · ' . $kindLabel . ' · ' . SITE_NAME;
    $pageDescription = $requestedState === 'upcoming'
        ? ($requested['description'] ?: $kindLabel . ' on ' . SITE_NAME . ' — ' . date('D j M Y, H:i', strtotime($requested['starts_at'])) . ' (East Africa Time). Free to watch.')
        : ($requested['description'] ?: 'Watch ' . $requested['title'] . ' on ' . SITE_NAME . '.');
    $pageImage = live_program_thumbnail($requested) ?: (SITE_URL . '/img/logo.png');
} else {
    $pageTitle = ($live['source'] === 'none' ? 'Watch Live' : 'Live: ' . $first['title']) . ' · ' . SITE_NAME;
    $pageDescription = 'Watch ' . SITE_NAME . ' live — free, anywhere in the world. Everyone watches the same programme at the same time.';
}

$extraFootHtml =
    '<script src="' . h(BASE_PATH) . '/assets/js/share.js?v=' . filemtime(__DIR__ . '/assets/js/share.js') . '"></script>' .
    '<script src="' . h(BASE_PATH) . ($view === 'premiere' ? '/assets/js/premiere.js?v=' . filemtime(__DIR__ . '/assets/js/premiere.js') : '/assets/js/live-sync.js?v=' . filemtime(__DIR__ . '/assets/js/live-sync.js')) . '"></script>';

require __DIR__ . '/includes/header.php';
$apiBase = h(BASE_PATH) . '/api/v1';
?>

<?php if ($view === 'premiere'):
    $r = $requested;
    $poster = live_program_thumbnail($r);
    $startTs = strtotime($r['starts_at']);
    $endIso = $r['ends_at'] ? date('c', strtotime($r['ends_at'])) : date('c', $startTs + 3600);
?>
<!-- ── PREMIERE PAGE (upcoming programme) ── -->
<section class="container lv-wrap">
    <div class="pm-hero" id="premiere" data-id="<?= (int) $r['id'] ?>" data-api="<?= $apiBase ?>"
         data-now="<?= h(date('c')) ?>" data-start="<?= h(date('c', $startTs)) ?>" data-end="<?= h($endIso) ?>"
         data-title="<?= h($r['title']) ?>" data-url="<?= h(rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php?p=' . (int) $r['id']) ?>"
         style="<?= $poster ? '--poster:url(\'' . h($poster) . '\')' : '' ?>">
        <div class="pm-poster"><?php if ($poster): ?><img src="<?= h($poster) ?>" alt="" /><?php else: ?><i class="fas fa-clapperboard"></i><?php endif; ?></div>
        <div class="pm-body">
            <span class="pm-kind"><i class="fas fa-<?= ($r['kind'] ?? 'show') === 'movie' ? 'clapperboard' : 'tower-broadcast' ?>"></i> <?= h(live_kind_label($r['kind'] ?? 'show')) ?></span>
            <h1><?= h($r['title']) ?></h1>
            <?php if ($r['description']): ?><p class="pm-desc"><?= h($r['description']) ?></p><?php endif; ?>
            <div class="pm-when"><i class="far fa-calendar"></i> <time datetime="<?= h(date('c', $startTs)) ?>" id="pmWhen"><?= h(date('l j F Y · H:i', $startTs)) ?> (East Africa Time)</time></div>

            <div class="pm-count" id="pmCount" aria-live="off">
                <div><b id="pmD">--</b><span>days</span></div>
                <div><b id="pmH">--</b><span>hours</span></div>
                <div><b id="pmM">--</b><span>minutes</span></div>
                <div><b id="pmS">--</b><span>seconds</span></div>
            </div>
            <p class="pm-hint" id="pmHint">Free to watch. When the countdown ends, this page starts the show by itself — everyone watches together.</p>

            <div class="lv-actions">
                <button type="button" class="lv-btn lv-btn-primary" id="pmCal"><i class="far fa-calendar-plus"></i> Add to calendar</button>
                <button type="button" class="lv-btn" data-share data-title="<?= h($r['title']) ?>" data-url="<?= h(rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php?p=' . (int) $r['id']) ?>"><i class="fas fa-share-nodes"></i> Share</button>
                <?php if ($radioOn): ?><button type="button" class="lv-btn" data-qfm data-api="<?= $apiBase ?>" data-label="Listen to Q FM"><i class="fas fa-radio"></i> <span class="qfm-btn-label">Listen to Q FM</span></button><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="lv-after">
        <a class="lv-link" href="<?= h(BASE_PATH) ?>/watch.php"><i class="fas fa-tower-broadcast"></i> <?= $live['source'] === 'none' ? 'See what is playing now' : 'Watch what is on air now' ?></a>
        <a class="lv-link" href="<?= h(BASE_PATH) ?>/previous-shows.php"><i class="fas fa-film"></i> Browse previous shows &amp; movies</a>
    </div>
</section>

<?php else: ?>
<!-- ── LIVE STAGE ── -->
<section class="container lv-wrap">
    <?php if ($requested && $requestedState === 'ended'): ?>
        <div class="lv-notice"><i class="fas fa-circle-info"></i> <b><?= h($requested['title']) ?></b> has finished. Here is what is on now — and you can find it again in <a href="<?= h(BASE_PATH) ?>/previous-shows.php">Previous Shows</a> if it was saved.</div>
    <?php endif; ?>

    <div class="lv-grid">
        <div class="lv-main" id="liveStage" data-api="<?= $apiBase ?>">
            <div class="lv-screen" id="lvScreen" data-kind="<?= $live['source'] === 'none' ? 'offair' : 'program' ?>"
                 style="<?= $first['thumb'] ? 'background-image:url(\'' . h($first['thumb']) . '\')' : '' ?>">
                <div class="lv-mount" id="lvMount"></div>
                <div class="lv-overlay show loading" id="lvOverlay"><div class="lv-spin"></div></div>
                <div class="lv-badges">
                    <span class="lv-badge <?= $first['live'] ? 'lv-badge-live' : 'lv-badge-off' ?>" id="lvBadge"><?= $first['live'] ? '<span class="dot"></span> LIVE' : '<i class="fas fa-film"></i> OFF AIR' ?></span>
                    <span class="lv-viewers" id="lvViewers" hidden></span>
                </div>
                <div class="lv-flash" id="lvFlash" role="status"></div>
                <div class="lv-controls">
                    <button type="button" class="lv-ctl" id="lvMute" aria-label="Turn the sound on"><i class="fas fa-volume-xmark"></i></button>
                    <button type="button" class="lv-ctl" id="lvFull" aria-label="Full screen"><i class="fas fa-expand"></i></button>
                </div>
            </div>

            <div class="lv-info">
                <div class="lv-kicker" id="lvKicker"><?php if ($first['live']): ?><span class="lv-red">ON AIR NOW</span> · <?php endif; ?><?= h($first['kicker']) ?></div>
                <h1 id="lvTitle"><?= h($first['title']) ?></h1>
                <p class="lv-desc" id="lvDesc" <?= $first['desc'] === '' ? 'hidden' : '' ?>><?= h($first['desc']) ?></p>
                <div class="lv-actions">
                    <button type="button" class="lv-btn lv-btn-primary" data-share data-title="<?= h($first['title']) ?>" data-url="<?= h($first['share']) ?>"><i class="fas fa-share-nodes"></i> Share</button>
                    <?php if ($radioOn): ?><button type="button" class="lv-btn" data-qfm data-api="<?= $apiBase ?>" data-label="Listen to Q FM"><i class="fas fa-radio"></i> <span class="qfm-btn-label">Listen to Q FM</span></button><?php endif; ?>
                    <a class="lv-btn" id="lvYt" target="_blank" rel="noopener noreferrer" href="<?= h($first['yt']) ?>" <?= $first['yt'] === '' ? 'hidden' : '' ?>><i class="fab fa-youtube"></i> Open on YouTube</a>
                </div>
                <div class="lv-sync" id="lvSync" hidden></div>
            </div>
            <noscript><p class="lv-notice">Turn on JavaScript to watch the live stage here, or open <a href="<?= h(main_channel(false)['watch_url'] ?: '#') ?>">the live stream</a>.</p></noscript>
        </div>

        <aside class="lv-side">
            <div class="lv-card lv-next" id="lvNextCard" <?= $next ? '' : 'hidden' ?> data-start="<?= $next ? h(date('c', strtotime($next['starts_at']))) : '' ?>">
                <div class="lv-next-thumb" id="lvNextThumb"></div>
                <div class="lv-next-body">
                    <span class="lv-card-label" id="lvNextLabel">Up next</span>
                    <b class="lv-next-count" id="lvNextCount">--:--</b>
                    <a class="lv-next-title" id="lvNextLink" href="<?= $next ? h(BASE_PATH . '/watch.php?p=' . (int) $next['id']) : '#' ?>"><span id="lvNextTitle"><?= $next ? h($next['title']) : '' ?></span></a>
                    <span class="lv-up-kind" id="lvNextKind"></span>
                </div>
            </div>

            <div class="lv-card" id="lvScheduleCard" hidden>
                <h2 class="lv-card-title"><i class="far fa-calendar-check"></i> Coming up</h2>
                <ul class="lv-ups" id="lvSchedule"></ul>
            </div>

            <div class="lv-card lv-more">
                <h2 class="lv-card-title"><i class="fas fa-film"></i> Catch up</h2>
                <p>Missed something? Every show and movie is in our library.</p>
                <a class="lv-btn lv-btn-primary" href="<?= h(BASE_PATH) ?>/previous-shows.php">Browse previous shows <i class="fas fa-arrow-right"></i></a>
            </div>
        </aside>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
