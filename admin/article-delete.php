<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'Article deleted.');
} else {
    flash('error', 'Invalid article.');
}

redirect(BASE_PATH . '/admin/articles.php');
