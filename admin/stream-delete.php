<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    db()->prepare('DELETE FROM streams WHERE id = ?')->execute([$id]);
    flash('success', 'Stream deleted.');
}

redirect(BASE_PATH . '/admin/streams.php');
