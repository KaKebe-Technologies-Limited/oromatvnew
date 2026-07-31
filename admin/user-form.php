<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('error', 'User not found.');
        redirect(BASE_PATH . '/admin/users.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'editor'], true) ? $_POST['role'] : 'editor';

    if ($name === '' || $email === '') {
        $errors[] = 'Name and email are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!$existing && $password === '') {
        $errors[] = 'Password is required for a new user.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        $dupStmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $dupStmt->execute([$email, $id ?: 0]);
        if ($dupStmt->fetch()) {
            $errors[] = 'That email is already in use.';
        }
    }

    if ($existing && $existing['role'] === 'admin' && $role === 'editor') {
        $adminCount = (int) db()->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch()['c'];
        if ($adminCount <= 1) {
            $errors[] = 'Cannot demote the last remaining admin.';
        }
    }

    if (!$errors) {
        if ($existing) {
            if ($password !== '') {
                $stmt = db()->prepare('UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?');
                $stmt->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = db()->prepare('UPDATE users SET name=?, email=?, role=? WHERE id=?');
                $stmt->execute([$name, $email, $role, $id]);
            }
            flash('success', 'User updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('success', 'User created.');
        }
        redirect(BASE_PATH . '/admin/users.php');
    }

    $existing = array_merge($existing ?? [], ['name' => $name, 'email' => $email, 'role' => $role]);
}

$pageTitle = $existing ? 'Edit User' : 'New User';
$activeAdminNav = 'users';
require __DIR__ . '/includes/admin-header.php';
?>

<?php if ($errors): ?>
    <div class="card" style="border-color:#c0392b;">
        <?php foreach ($errors as $err): ?>
            <div class="alert" style="margin-bottom:8px;"><?= h($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
    <div class="card-head"><h2><?= $existing ? 'Edit User' : 'New User' ?></h2></div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>" />

        <div class="form-group">
            <label for="name">Name</label>
            <input class="form-control" type="text" id="name" name="name" required value="<?= h($existing['name'] ?? '') ?>" />
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" required value="<?= h($existing['email'] ?? '') ?>" />
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" placeholder="<?= $existing ? 'Leave blank to keep current password' : '' ?>" />
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select class="form-control" id="role" name="role">
                <option value="editor" <?= ($existing['role'] ?? 'editor') === 'editor' ? 'selected' : '' ?>>Editor</option>
                <option value="admin" <?= ($existing['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <div class="form-hint">Admins can manage users; editors can manage articles &amp; streams only.</div>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary"><?= $existing ? 'Update' : 'Create' ?> User</button>
            <a href="<?= h(BASE_PATH) ?>/admin/users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
