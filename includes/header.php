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
$navCategories   = db()->query("SELECT name, slug, icon FROM categories WHERE is_active=1 ORDER BY display_order ASC, name ASC LIMIT 10")->fetchAll();
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800;900&family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="<?= h(BASE_PATH) ?>/assets/css/style.css?v=2.1.1" />
    <?= $extraHeadHtml ?? '' ?>
</head>
<body class="<?= h($bodyClass ?? '') ?>">

<div id="reading-progress" aria-hidden="true"></div>
<div class="nav-overlay" id="navOverlay"></div>

<!-- ── SITE HEADER ── -->
<header class="site-header" id="siteHeader">
    <div class="inner">
        <!-- Search -->
        <button class="nav-search-btn" id="searchToggle" aria-label="Search" title="Search">
            <i class="fas fa-search"></i>
        </button>

        <!-- Logo -->
        <a href="<?= h(BASE_PATH) ?>/index.php" class="brand">
            <img src="<?= h(BASE_PATH) ?>/img/logo.png" alt="<?= h(SITE_NAME) ?>" />
        </a>

        <!-- Nav -->
        <nav class="site-nav" id="siteNav" aria-label="Main navigation">
            <a href="<?= h(BASE_PATH) ?>/index.php"  <?= ($activeNav ?? '') === 'home'  ? 'class="active"' : '' ?>>Home</a>
            <a href="<?= h(BASE_PATH) ?>/news.php"   <?= ($activeNav ?? '') === 'news'  ? 'class="active"' : '' ?>>News</a>
            <a href="<?= h(BASE_PATH) ?>/watch.php"  <?= ($activeNav ?? '') === 'watch' ? 'class="active"' : '' ?>>Watch Live</a>
            <a href="<?= h(BASE_PATH) ?>/previous-shows.php" <?= ($activeNav ?? '') === 'shows' ? 'class="active"' : '' ?>>Previous Shows</a>
            <a href="<?= h(BASE_PATH) ?>/contact.php" <?= ($activeNav ?? '') === 'contact' ? 'class="active"' : '' ?>>Contact</a>
            <?php foreach (array_slice($navCategories, 0, 4) as $nc): ?>
                <a href="<?= h(BASE_PATH) ?>/news.php?category=<?= urlencode($nc['slug']) ?>"
                   <?= ($activeNav ?? '') === $nc['slug'] ? 'class="active"' : '' ?>>
                    <?= h($nc['name']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Right controls -->
        <div class="header-right">
            <a href="<?= h(BASE_PATH) ?>/index.php#watch"
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
