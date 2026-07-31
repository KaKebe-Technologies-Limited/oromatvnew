<?php
/**
 * Shared public-site header.
 * Pages may set before including this file:
 *   $pageTitle, $pageDescription, $pageImage, $pageCanonical, $pageType ('website'|'article')
 */
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? SITE_NAME . ' · Live Stream & News';
$pageDescription = $pageDescription ?? 'Watch ' . SITE_NAME . ' live, listen to the radio, and read the latest news — ' . SITE_TAGLINE . '.';
$pageImage = $pageImage ?? (SITE_URL . '/img/logo.png');
$pageCanonical = $pageCanonical ?? (SITE_URL . ($_SERVER['REQUEST_URI'] ?? ''));
$pageType = $pageType ?? 'website';

$streamStatus = get_setting('stream_status', 'offline');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>" />
    <link rel="canonical" href="<?= h($pageCanonical) ?>" />
    <meta name="theme-color" content="#800000" />

    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>" />
    <meta property="og:type" content="<?= h($pageType) ?>" />
    <meta property="og:title" content="<?= h($pageTitle) ?>" />
    <meta property="og:description" content="<?= h($pageDescription) ?>" />
    <meta property="og:image" content="<?= h($pageImage) ?>" />
    <meta property="og:url" content="<?= h($pageCanonical) ?>" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= h($pageTitle) ?>" />
    <meta name="twitter:description" content="<?= h($pageDescription) ?>" />
    <meta name="twitter:image" content="<?= h($pageImage) ?>" />

    <link rel="icon" href="<?= h(BASE_PATH) ?>/img/logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= h(BASE_PATH) ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" />
</head>
<body class="<?= h($bodyClass ?? '') ?>">

<?php if (($bodyClass ?? '') === 'is-home'): ?>
<div class="home-motion-bg" aria-hidden="true">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
    <span class="blob blob-3"></span>
</div>
<?php endif; ?>

<header class="site-header">
    <div class="inner">
        <a href="<?= h(BASE_PATH) ?>/index.php" class="brand">
            <img src="<?= h(BASE_PATH) ?>/img/logo.png" alt="<?= h(SITE_NAME) ?>" />
        </a>

        <nav class="site-nav" id="siteNav">
            <a href="<?= h(BASE_PATH) ?>/news.php"<?= ($activeNav ?? '') === 'news' ? ' class="active"' : '' ?>>News</a>
            <a href="<?= h(BASE_PATH) ?>/index.php"<?= ($activeNav ?? '') === 'home' ? ' class="active"' : '' ?>>Home</a>
            <a href="<?= h(BASE_PATH) ?>/index.php#watch" class="nav-live-btn <?= $streamStatus === 'live' ? 'is-live' : '' ?>">
                <span class="dot"></span> <?= $streamStatus === 'live' ? 'Live Now' : 'Watch' ?>
            </a>
        </nav>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
    </div>
</header>

<?php $flashes = get_flashes(); ?>
<?php if ($flashes): ?>
    <div class="container flash-stack" style="margin-top:20px;">
        <?php foreach ($flashes as $f): ?>
            <div class="flash flash-<?= h($f['type']) ?>">
                <i class="fas fa-<?= $f['type'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                <?= h($f['message']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
