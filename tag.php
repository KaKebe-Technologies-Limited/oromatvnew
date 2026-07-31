<?php
require_once __DIR__ . '/includes/functions.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
redirect(BASE_PATH . '/news.php' . ($slug !== '' ? '?tag=' . urlencode($slug) : ''));
