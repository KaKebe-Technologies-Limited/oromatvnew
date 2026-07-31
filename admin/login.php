<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect(BASE_PATH . '/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (attempt_login($email, $password)) {
        redirect(BASE_PATH . '/admin/dashboard.php');
    } else {
        $error = 'Invalid email or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Login · <?= h(SITE_NAME) ?></title>
    <link rel="icon" href="<?= h(BASE_PATH) ?>/img/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= h(BASE_PATH) ?>/assets/css/admin.css" />
</head>
<body>
<div class="login-screen">
    <div class="login-card">
        <img src="<?= h(BASE_PATH) ?>/img/logo.png" alt="<?= h(SITE_NAME) ?>" />
        <h1>Admin Login</h1>
        <p class="sub">Sign in to manage <?= h(SITE_NAME) ?></p>

        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>" />
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" required />
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
    </div>
</div>
</body>
</html>
