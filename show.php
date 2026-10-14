<?php
/**
 * One previous show or movie: the player, details, share buttons and more to watch.
 * (Reached from previous-shows.php; the share link for a saved show is show.php?id=ID.)
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/previous-shows-functions.php';
require_once __DIR__ . '/includes/live-functions.php';

$activeNav = 'shows';
ensure_live_schema();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM previous_shows WHERE id = ? AND is_active = 1');
$stmt->execute([$id]);
$show = $stmt->fetch();
if (!$show) {
    flash('error', 'That show could not be found.');
    redirect(BASE_PATH . '/previous-shows.php');
}

$kind = $show['kind'] ?? 'show';
$kindWord = $kind === 'movie' ? 'Movie' : 'Show';
$shareUrl = rtrim(OROMA_PUBLIC_URL, '/') . '/show.php?id=' . (int) $show['id'];
$thumb = $show['thumbnail_url'] ?: get_youtube_thumbnail($show['video_id']);

$pageTitle       = $show['title'] . ' · ' . SITE_NAME;
$pageDescription = $show['description'] ?: ($show['guest_name'] ? 'Watch ' . $show['guest_name'] . ' on ' . SITE_NAME . ' — ' . $show['title'] : 'Watch ' . $show['title'] . ' on ' . SITE_NAME . ' — free, any time.');
$pageImage       = $thumb;
$pageType        = 'video.other';

// More to watch: the same kind first, never the same video.
$related = [];
$seen = [$show['video_id'] => true];
$more = db()->prepare("SELECT * FROM previous_shows WHERE is_active = 1 AND id <> ? ORDER BY (kind = ?) DESC, is_featured DESC, display_order ASC, created_at DESC LIMIT 40");
$more->execute([$id, $kind]);
foreach ($more->fetchAll() as $r) {
    if (isset($seen[$r['video_id']])) {
        continue;
    }
    $seen[$r['video_id']] = true;
    $related[] = $r;
    if (count($related) === 8) {
        break;
    }
}

$onAir = resolve_live();
$extraFootHtml = '<script src="' . h(BASE_PATH) . '/assets/js/share.js?v=' . filemtime(__DIR__ . '/assets/js/share.js') . '"></script>';

require __DIR__ . '/includes/header.php';
?>

<section class="container lv-wrap">
    <nav class="sh-crumbs" aria-label="Breadcrumb">
        <a href="<?= h(BASE_PATH) ?>/previous-shows.php"><i class="fas fa-arrow-left"></i> Previous Shows</a>
    </nav>

    <div class="lv-grid">
        <div class="lv-main">
            <div class="lv-screen lv-screen-plain">
                <iframe src="https://www.youtube.com/embed/<?= h(rawurlencode($show['video_id'])) ?>?rel=0&modestbranding=1&playsinline=1"
                        title="<?= h($show['title']) ?>" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
            </div>

            <div class="lv-info">
                <div class="lv-kicker"><?php if ($kind === 'movie'): ?><i class="fas fa-clapperboard"></i> Movie<?php else: ?><i class="fas fa-film"></i> Previous show<?php endif; ?>
                    <?php if ($show['upload_date']): ?> · <?= h(date('j F Y', strtotime($show['upload_date']))) ?><?php endif; ?>
                    <?php if ($show['views'] > 0): ?> · <?= h(number_format($show['views'])) ?> views<?php endif; ?></div>
                <h1><?= h($show['title']) ?></h1>
                <?php if (!empty($show['guest_name'])): ?><p class="lv-desc"><i class="fas fa-microphone"></i> With <b><?= h($show['guest_name']) ?></b></p><?php endif; ?>
                <?php if (!empty($show['description'])): ?><p class="lv-desc"><?= nl2br(h($show['description'])) ?></p><?php endif; ?>
                <div class="lv-actions">
                    <button type="button" class="lv-btn lv-btn-primary" data-share data-title="<?= h($show['title']) ?>" data-url="<?= h($shareUrl) ?>"><i class="fas fa-share-nodes"></i> Share</button>
                    <a class="lv-btn" target="_blank" rel="noopener noreferrer" href="<?= h(get_youtube_watch_url($show['video_id'])) ?>"><i class="fab fa-youtube"></i> Open on YouTube</a>
                    <?php if ($radioOn): ?><button type="button" class="lv-btn" data-qfm data-api="<?= h(BASE_PATH) ?>/api/v1" data-label="Listen to Q FM"><i class="fas fa-radio"></i> <span class="qfm-btn-label">Listen to Q FM</span></button><?php endif; ?>
                </div>
            </div>
        </div>

        <aside class="lv-side">
            <?php if ($onAir['source'] !== 'none'): ?>
            <a class="lv-card lv-onair" href="<?= h(BASE_PATH) ?>/watch.php">
                <span class="lv-card-label"><span class="dot"></span> On air now</span>
                <b><?= h($onAir['source'] === 'program' ? $onAir['program']['title'] : SITE_NAME . ' Live') ?></b>
                <span class="lv-onair-go">Join everyone watching <i class="fas fa-arrow-right"></i></span>
            </a>
            <?php endif; ?>

            <?php if ($related): ?>
            <div class="lv-card">
                <h2 class="lv-card-title"><i class="fas fa-circle-play"></i> More to watch</h2>
                <ul class="sh-related">
                    <?php foreach ($related as $r): ?>
                    <li><a href="<?= h(BASE_PATH) ?>/show.php?id=<?= (int) $r['id'] ?>">
                        <span class="sh-r-thumb"><img src="<?= h($r['thumbnail_url'] ?: get_youtube_thumbnail($r['video_id'])) ?>" alt="" loading="lazy" /></span>
                        <span class="sh-r-text"><b><?= h($r['title']) ?></b>
                            <small><?= ($r['kind'] ?? 'show') === 'movie' ? '🎬 Movie' : 'Show' ?><?= $r['upload_date'] ? ' · ' . h(date('M j, Y', strtotime($r['upload_date']))) : '' ?></small></span>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
