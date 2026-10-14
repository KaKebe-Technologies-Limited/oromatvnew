<?php
/**
 * Previous Shows — the library: a hero, what is on air / premiering, movies, featured shows and everything else,
 * with filters and search (assets/js/shows.js). Each card opens its own page, show.php.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/previous-shows-functions.php';
require_once __DIR__ . '/includes/live-functions.php';

$activeNav       = 'shows';
$pageTitle       = 'Previous Shows & Movies · ' . SITE_NAME;
$pageDescription = 'Watch every past episode, interview, special broadcast and movie from Oroma TV — free, any time.';

ensure_live_schema(); // makes sure the "kind" (show / movie) column exists before the shows are read

// The same video added twice in the admin should still appear once here (the first one in the admin's order wins).
$shows = [];
$seenVideos = [];
foreach (get_previous_shows(200) as $s) {
    if (isset($seenVideos[$s['video_id']])) {
        continue;
    }
    $seenVideos[$s['video_id']] = true;
    $shows[] = $s;
}
$hero = null;
foreach ($shows as $s) {
    if ($s['is_featured']) { $hero = $s; break; }
}
$hero = $hero ?? ($shows[0] ?? null);

$movies   = array_values(array_filter($shows, fn($s) => ($s['kind'] ?? 'show') === 'movie'));
$featured = array_values(array_filter($shows, fn($s) => $s['is_featured'] && (!$hero || $s['id'] !== $hero['id'])));
$movieCount = count($movies);

$onAir = resolve_live();
$premieres = [];
try {
    $premieres = get_upcoming_programs(24 * 30, 10);
} catch (Throwable $e) {
    error_log('[previous-shows] premieres: ' . $e->getMessage());
}

$extraFootHtml =
    '<script src="' . h(BASE_PATH) . '/assets/js/share.js?v=' . filemtime(__DIR__ . '/assets/js/share.js') . '"></script>' .
    '<script src="' . h(BASE_PATH) . '/assets/js/shows.js?v=' . filemtime(__DIR__ . '/assets/js/shows.js') . '"></script>';

/** One show card (the same everywhere on this page). */
function ps_card(array $s, string $class = ''): void
{
    $kind = $s['kind'] ?? 'show';
    $url = BASE_PATH . '/show.php?id=' . (int) $s['id'];
    $thumb = $s['thumbnail_url'] ?: get_youtube_thumbnail($s['video_id']);
    $text = strtolower($s['title'] . ' ' . ($s['guest_name'] ?? '') . ' ' . ($kind === 'movie' ? 'movie' : 'show'));
    ?>
    <a href="<?= h($url) ?>" class="ps-card <?= h($class) ?>" data-kind="<?= h($kind) ?>" data-text="<?= h($text) ?>">
        <div class="ps-thumb">
            <img src="<?= h($thumb) ?>" alt="" loading="lazy" onerror="this.src='<?= h(BASE_PATH) ?>/assets/img/default-thumb.svg'" />
            <span class="ps-play"><i class="fas fa-play"></i></span>
            <?php if ($kind === 'movie'): ?><span class="ps-tag ps-tag-movie"><i class="fas fa-clapperboard"></i> Movie</span>
            <?php elseif ($s['is_featured']): ?><span class="ps-tag ps-tag-star"><i class="fas fa-star"></i> Featured</span><?php endif; ?>
        </div>
        <div class="ps-body">
            <?php if (!empty($s['guest_name'])): ?><span class="ps-guest"><i class="fas fa-microphone"></i> <?= h($s['guest_name']) ?></span><?php endif; ?>
            <h3><?= h($s['title']) ?></h3>
            <div class="ps-meta">
                <?php if ($s['upload_date']): ?><span><i class="far fa-calendar"></i> <?= h(date('M j, Y', strtotime($s['upload_date']))) ?></span><?php endif; ?>
                <?php if ($s['views'] > 0): ?><span><i class="far fa-eye"></i> <?= h(number_format($s['views'])) ?></span><?php endif; ?>
            </div>
        </div>
    </a>
    <?php
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($hero):
    $heroThumb = 'https://i.ytimg.com/vi/' . rawurlencode($hero['video_id']) . '/maxresdefault.jpg';
    $heroFallback = $hero['thumbnail_url'] ?: get_youtube_thumbnail($hero['video_id']);
?>
<!-- ── HERO ── -->
<section class="ps-hero">
    <img class="ps-hero-img" src="<?= h($heroThumb) ?>" alt="" onerror="this.onerror=null;this.src='<?= h($heroFallback) ?>'" />
    <div class="ps-hero-shade"></div>
    <div class="container ps-hero-inner">
        <div class="ps-hero-copy">
            <span class="ps-eyebrow"><i class="fas fa-<?= ($hero['kind'] ?? 'show') === 'movie' ? 'clapperboard' : 'star' ?>"></i> <?= ($hero['kind'] ?? 'show') === 'movie' ? 'Featured movie' : 'Featured episode' ?></span>
            <h1><?= h($hero['title']) ?></h1>
            <?php if (!empty($hero['guest_name'])): ?><p class="ps-hero-guest"><i class="fas fa-microphone"></i> With <?= h($hero['guest_name']) ?></p><?php endif; ?>
            <div class="ps-hero-actions">
                <a class="ps-btn ps-btn-primary" href="<?= h(BASE_PATH) ?>/show.php?id=<?= (int) $hero['id'] ?>"><i class="fas fa-play"></i> Watch now</a>
                <button type="button" class="ps-btn ps-btn-ghost" data-share data-title="<?= h($hero['title']) ?>" data-url="<?= h(rtrim(OROMA_PUBLIC_URL, '/') . '/show.php?id=' . (int) $hero['id']) ?>"><i class="fas fa-share-nodes"></i> Share</button>
            </div>
        </div>

        <?php if ($onAir['source'] !== 'none'):
            $nowTitle = $onAir['source'] === 'program' ? $onAir['program']['title'] : SITE_NAME . ' Live'; ?>
            <a class="ps-live-card" href="<?= h(BASE_PATH) ?>/watch.php">
                <span class="ps-live-pill"><span class="dot"></span> ON AIR NOW</span>
                <b><?= h($nowTitle) ?></b>
                <span class="ps-live-go">Join everyone watching <i class="fas fa-arrow-right"></i></span>
            </a>
        <?php elseif ($premieres): $p0 = $premieres[0]; ?>
            <a class="ps-live-card" href="<?= h(BASE_PATH) ?>/watch.php?p=<?= (int) $p0['id'] ?>">
                <span class="ps-live-pill ps-live-soon"><i class="far fa-clock"></i> NEXT PREMIERE</span>
                <b><?= h($p0['title']) ?></b>
                <span class="ps-live-go" data-countdown="<?= h(date('c', strtotime($p0['starts_at']))) ?>"><?= h(date('D j M · H:i', strtotime($p0['starts_at']))) ?></span>
            </a>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<section class="ps-hero ps-hero-empty">
    <div class="container ps-hero-inner"><div class="ps-hero-copy"><h1>Previous Shows</h1><p class="ps-hero-guest">Our library is being filled. Check back soon.</p></div></div>
</section>
<?php endif; ?>

<div class="container ps-wrap">

    <?php if ($shows): ?>
    <!-- ── FILTERS ── -->
    <div class="ps-toolbar" id="psToolbar">
        <div class="ps-chips" role="tablist" aria-label="Filter">
            <button type="button" class="ps-chip active" data-filter="all">All <span><?= count($shows) ?></span></button>
            <button type="button" class="ps-chip" data-filter="show">Shows <span><?= count($shows) - $movieCount ?></span></button>
            <?php if ($movieCount): ?><button type="button" class="ps-chip" data-filter="movie"><i class="fas fa-clapperboard"></i> Movies <span><?= $movieCount ?></span></button><?php endif; ?>
        </div>
        <label class="ps-search"><i class="fas fa-magnifying-glass"></i>
            <input type="search" id="psSearch" placeholder="Search shows, guests, movies…" autocomplete="off" aria-label="Search shows" />
        </label>
    </div>
    <?php endif; ?>

    <div id="psRails">
        <?php if ($premieres): ?>
        <!-- ── PREMIERES ── -->
        <section class="ps-section">
            <div class="ps-head"><h2><i class="fas fa-bolt"></i> Premieres &amp; <span>coming soon</span></h2><a href="<?= h(BASE_PATH) ?>/watch.php">Watch live <i class="fas fa-arrow-right"></i></a></div>
            <div class="ps-rail">
                <?php foreach ($premieres as $p): $thumb = live_program_thumbnail($p); ?>
                <a href="<?= h(BASE_PATH) ?>/watch.php?p=<?= (int) $p['id'] ?>" class="ps-prem">
                    <div class="ps-thumb">
                        <?php if ($thumb): ?><img src="<?= h($thumb) ?>" alt="" loading="lazy" /><?php endif; ?>
                        <span class="ps-tag <?= ($p['kind'] ?? 'show') === 'movie' ? 'ps-tag-movie' : 'ps-tag-soon' ?>"><i class="fas fa-<?= ($p['kind'] ?? 'show') === 'movie' ? 'clapperboard' : 'bolt' ?>"></i> <?= h(live_kind_label($p['kind'] ?? 'show')) ?></span>
                    </div>
                    <div class="ps-body">
                        <span class="ps-when" data-countdown="<?= h(date('c', strtotime($p['starts_at']))) ?>"><i class="far fa-clock"></i> <?= h(date('D j M · H:i', strtotime($p['starts_at']))) ?></span>
                        <h3><?= h($p['title']) ?></h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($movies): ?>
        <!-- ── MOVIES ── -->
        <section class="ps-section">
            <div class="ps-head"><h2><i class="fas fa-clapperboard"></i> Movie <span>night</span></h2></div>
            <div class="ps-rail">
                <?php foreach ($movies as $m) { ps_card($m, 'ps-card-rail'); } ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($featured): ?>
        <!-- ── FEATURED ── -->
        <section class="ps-section">
            <div class="ps-head"><h2><i class="fas fa-star"></i> Featured <span>episodes</span></h2></div>
            <div class="ps-rail">
                <?php foreach (array_slice($featured, 0, 10) as $f) { ps_card($f, 'ps-card-rail'); } ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <!-- ── EVERYTHING ── -->
    <section class="ps-section">
        <div class="ps-head"><h2 id="psAllTitle">All <span>episodes</span></h2><span class="ps-count" id="psCount"><?= count($shows) ?> title<?= count($shows) !== 1 ? 's' : '' ?></span></div>
        <?php if (!$shows): ?>
            <div class="empty-state"><i class="fas fa-video"></i><p>No previous shows yet. Check back soon.</p></div>
        <?php else: ?>
            <div class="ps-grid" id="psGrid">
                <?php foreach ($shows as $s) { ps_card($s); } ?>
            </div>
            <div class="empty-state" id="psNone" hidden><i class="fas fa-magnifying-glass"></i><p>Nothing matches that. Try another word.</p></div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
