<?php
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email, role, avatar FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: false;
    }
    return $user ?: null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect(BASE_PATH . '/admin/login.php');
    }
}

function require_role(string $role): void
{
    require_login();
    $user = current_user();
    if ($user['role'] !== $role) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
