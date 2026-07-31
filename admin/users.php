<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $me = current_user();

    if ($id === $me['id']) {
        flash('error', 'You cannot delete your own account.');
    } else {
        $adminCount = (int) db()->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch()['c'];
        $target = db()->prepare('SELECT role FROM users WHERE id = ?');
        $target->execute([$id]);
        $target = $target->fetch();

        if ($target && $target['role'] === 'admin' && $adminCount <= 1) {
            flash('error', 'Cannot delete the last remaining admin.');
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'User deleted.');
        }
    }
    redirect(BASE_PATH . '/admin/users.php');
}

$users = db()->query(
    "SELECT u.*, (SELECT COUNT(*) FROM articles a WHERE a.author_id = u.id) AS article_count
     FROM users u ORDER BY u.created_at ASC"
)->fetchAll();

$pageTitle = 'Users';
$activeAdminNav = 'users';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Admin &amp; Author Accounts</h2>
        <a href="<?= h(BASE_PATH) ?>/admin/user-form.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New User</a>
    </div>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Articles</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= h($u['name']) ?></td>
                <td><?= h($u['email']) ?></td>
                <td><span class="status-pill status-<?= $u['role'] === 'admin' ? 'admin' : 'editor' ?>"><?= h($u['role']) ?></span></td>
                <td><?= (int) $u['article_count'] ?></td>
                <td><?= h(format_date($u['created_at'])) ?></td>
                <td>
                    <div class="row-actions">
                        <a href="<?= h(BASE_PATH) ?>/admin/user-form.php?id=<?= (int) $u['id'] ?>" class="icon-btn"><i class="fas fa-pen"></i></a>
                        <form method="post" data-confirm="Delete user “<?= h($u['name']) ?>”?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>" />
                            <button type="submit" class="icon-btn"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
