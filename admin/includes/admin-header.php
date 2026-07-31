<?php
/**
 * Shared admin chrome. Requires require_login() already called by the page.
 * Pages set $pageTitle and $activeAdminNav before including this file.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$user = current_user();
$pageTitle = $pageTitle ?? 'Admin';
$activeAdminNav = $activeAdminNav ?? '';

function admin_nav_class(string $key, string $active): string
{
    return $key === $active ? 'active' : '';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($pageTitle) ?> · Admin · <?= h(SITE_NAME) ?></title>
    <link rel="icon" href="<?= h(BASE_PATH) ?>/img/logo.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= h(BASE_PATH) ?>/assets/css/admin.css" />
</head>
<body>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="brand">
        <img src="<?= h(BASE_PATH) ?>/img/logo.png" alt="<?= h(SITE_NAME) ?>" />
        <span>Oroma Admin</span>
    </div>
    <?php $pendingCommentCount = (int) db()->query("SELECT COUNT(*) AS c FROM comments WHERE status = 'pending'")->fetch()['c']; ?>
    <nav class="admin-nav">
        <a href="<?= h(BASE_PATH) ?>/admin/dashboard.php" class="<?= admin_nav_class('dashboard', $activeAdminNav) ?>"><i class="fas fa-gauge"></i> Dashboard</a>
        <a href="<?= h(BASE_PATH) ?>/admin/articles.php" class="<?= admin_nav_class('articles', $activeAdminNav) ?>"><i class="fas fa-newspaper"></i> Articles</a>
        <a href="<?= h(BASE_PATH) ?>/admin/comments.php" class="<?= admin_nav_class('comments', $activeAdminNav) ?>" style="justify-content:space-between;">
            <span><i class="fas fa-comments"></i> Comments</span>
            <?php if ($pendingCommentCount > 0): ?><span class="nav-badge"><?= $pendingCommentCount ?></span><?php endif; ?>
        </a>
        <a href="<?= h(BASE_PATH) ?>/admin/streams.php" class="<?= admin_nav_class('streams', $activeAdminNav) ?>"><i class="fas fa-satellite-dish"></i> Streams</a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= h(BASE_PATH) ?>/admin/users.php" class="<?= admin_nav_class('users', $activeAdminNav) ?>"><i class="fas fa-users"></i> Users</a>
        <?php endif; ?>
    </nav>
    <div class="foot">
        <a href="<?= h(BASE_PATH) ?>/index.php" target="_blank"><i class="fas fa-arrow-up-right-from-square"></i> View Site</a>
        <a href="<?= h(BASE_PATH) ?>/admin/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>

<div class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="menu-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1><?= h($pageTitle) ?></h1>
        </div>
        <div class="user-chip">
            <div class="avatar"><?= h(strtoupper(substr($user['name'], 0, 1))) ?></div>
            <div>
                <div style="font-weight:600;"><?= h($user['name']) ?></div>
                <div style="color:var(--text-muted);text-transform:capitalize;"><?= h($user['role']) ?></div>
            </div>
        </div>
    </div>

    <div class="admin-content">
        <?php $flashes = get_flashes(); ?>
        <?php if ($flashes): ?>
            <div class="flash-stack">
                <?php foreach ($flashes as $f): ?>
                    <div class="flash flash-<?= h($f['type']) ?>">
                        <i class="fas fa-<?= $f['type'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                        <?= h($f['message']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
