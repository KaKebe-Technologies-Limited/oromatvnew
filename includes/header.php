<?php
/**
 * Shared public-site header.
 */
require_once __DIR__ . '/functions.php';

$pageTitle       = $pageTitle       ?? SITE_NAME . ' · Live Stream & News';
$pageDescription = $pageDescription ?? 'Watch ' . SITE_NAME . ' live, listen to the radio, and read the latest news.';
$pageImage       = $pageImage       ?? (SITE_URL . '/img/logo.png');
$pageCanonical   = $pageCanonical   ?? (SITE_URL . ($_SERVER['REQUEST_URI'] ?? ''));
$pageType        = $pageType        ?? 'website';
$streamStatus    = get_setting('stream_status', 'offline');
$navCategories   = get_active_categories();

// Top bar: breaking headlines when there are any, otherwise the latest stories.
$topbarLabel = 'Breaking';
$topbarItems = get_breaking_news(6);
if (!$topbarItems) {
    $topbarLabel = 'Latest';
    $topbarItems = get_latest_articles(6);
}
$categorySlugs = array_column($navCategories, 'slug');
$inCategoryNav = in_array($activeNav ?? '', $categorySlugs, true) && ($activeNav ?? '') !== 'news';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>" />
    <link rel="canonical" href="<?= h($pageCanonical) ?>" />
    <meta name="theme-color" content="#800000" />
    <meta property="og:site_name"   content="<?= h(SITE_NAME) ?>" />
    <meta property="og:type"        content="<?= h($pageType) ?>" />
    <meta property="og:title"       content="<?= h($pageTitle) ?>" />
    <meta property="og:description" content="<?= h($pageDescription) ?>" />
    <meta property="og:image"       content="<?= h($pageImage) ?>" />
    <meta property="og:url"         content="<?= h($pageCanonical) ?>" />
    <meta name="twitter:card"        content="summary_large_image" />
    <meta name="twitter:title"       content="<?= h($pageTitle) ?>" />
    <meta name="twitter:description" content="<?= h($pageDescription) ?>" />
    <meta name="twitter:image"       content="<?= h($pageImage) ?>" />
    <link rel="icon" href="<?= h(BASE_PATH) ?>/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="<?= h(BASE_PATH) ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" />
    <?= $extraHeadHtml ?? '' ?>
</head>
<body class="<?= h($bodyClass ?? '') ?>">

<div id="reading-progress" aria-hidden="true"></div>
<div class="nav-overlay" id="navOverlay"></div>

<!-- ── SEARCH OVERLAY ── -->
<div class="search-overlay" id="searchOverlay">
    <div class="search-modal">
        <button type="button" class="search-close" id="searchClose" aria-label="Close search">
            <i class="fas fa-times"></i>
        </button>
        <form action="<?= h(BASE_PATH) ?>/news.php" method="get" class="search-form">
            <i class="fas fa-search"></i>
            <input type="text" name="q" id="searchInput" placeholder="Search news…" autocomplete="off" />
            <button type="submit" aria-label="Search"><i class="fas fa-arrow-right"></i></button>
        </form>
    </div>
</div>

<!-- ── TOP BAR ── -->
<div class="topbar">
    <div class="topbar-inner">
        <?php if ($topbarItems): ?>
        <div class="topbar-trending">
            <span class="topbar-label"><i class="fas fa-bolt"></i> <?= h($topbarLabel) ?></span>
            <div class="topbar-headlines" id="topbarHeadlines" aria-live="off">
                <?php foreach ($topbarItems as $i => $t): ?>
                    <a href="<?= h(BASE_PATH) ?>/article.php?slug=<?= urlencode($t['slug']) ?>"
                       class="<?= $i === 0 ? 'active' : '' ?>"><?= h($t['title']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="topbar-trending"></div>
        <?php endif; ?>

        <div class="topbar-right">
            <span class="topbar-date"><i class="far fa-calendar"></i> <?= h(date('M j, Y')) ?></span>
            <span class="topbar-sep" aria-hidden="true"></span>
            <span class="topbar-follow">Follow us</span>
            <?php foreach (social_links() as $s): ?>
                <a href="<?= h($s['url']) ?>" class="topbar-social" target="_blank" rel="noopener noreferrer"
                   aria-label="<?= h($s['label']) ?>"><i class="<?= h($s['icon']) ?>"></i></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── SITE HEADER ── -->
<header class="site-header" id="siteHeader">
    <div class="inner">
        <!-- Logo -->
        <a href="<?= h(BASE_PATH) ?>/index.php" class="brand" aria-label="<?= h(SITE_NAME) ?> — home">
            <img src="<?= h(BASE_PATH) ?>/img/logo-trim.png" alt="<?= h(SITE_NAME) ?>" />
        </a>

        <!-- Nav -->
        <nav class="site-nav" id="siteNav" aria-label="Main navigation">
            <a href="<?= h(BASE_PATH) ?>/index.php"  <?= ($activeNav ?? '') === 'home'  ? 'class="active"' : '' ?>>Home</a>
            <a href="<?= h(BASE_PATH) ?>/news.php"   <?= ($activeNav ?? '') === 'news'  ? 'class="active"' : '' ?>>News</a>
            <a href="<?= h(BASE_PATH) ?>/watch.php"  <?= ($activeNav ?? '') === 'watch' ? 'class="active"' : '' ?>>Watch Live</a>
            <a href="<?= h(BASE_PATH) ?>/previous-shows.php" <?= ($activeNav ?? '') === 'shows' ? 'class="active"' : '' ?>>Previous Shows</a>

            <?php if ($navCategories): ?>
            <div class="nav-dd<?= $inCategoryNav ? ' has-active' : '' ?>">
                <button type="button" class="nav-dd-toggle" aria-haspopup="true" aria-expanded="false">
                    Categories <i class="fas fa-chevron-down"></i>
                </button>
                <div class="nav-dd-menu">
                    <?php foreach ($navCategories as $nc): ?>
                        <a href="<?= h(BASE_PATH) ?>/news.php?category=<?= urlencode($nc['slug']) ?>"
                           style="<?= h(category_style($nc['slug'])) ?>"
                           <?= ($activeNav ?? '') === $nc['slug'] && $nc['slug'] !== 'news' ? 'class="active"' : '' ?>>
                            <i class="fas <?= h($nc['icon'] ?: 'fa-folder') ?>"></i>
                            <?= h($nc['name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?= h(BASE_PATH) ?>/news.php" class="nav-dd-all">All stories <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <a href="<?= h(BASE_PATH) ?>/contact.php" <?= ($activeNav ?? '') === 'contact' ? 'class="active"' : '' ?>>Contact</a>
        </nav>

        <!-- Right controls -->
        <div class="header-right">
            <button class="nav-search-btn" id="searchToggle" aria-label="Search" title="Search">
                <i class="fas fa-search"></i>
            </button>
            <a href="<?= h(BASE_PATH) ?>/watch.php"
               class="btn-watch-live <?= $streamStatus === 'live' ? 'is-live' : '' ?>">
                <span class="dot"></span>
                <span class="btn-label"><?= $streamStatus === 'live' ? 'Live Now' : 'Watch Live' ?></span>
            </a>
            <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<?php $flashes = get_flashes(); if ($flashes): ?>
<div class="container flash-stack" style="margin-top:16px;">
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= h($f['type']) ?>">
            <i class="fas fa-<?= $f['type'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
            <?= h($f['message']) ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
