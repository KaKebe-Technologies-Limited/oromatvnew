<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/previous-shows-functions.php';
require_login();

// handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        delete_previous_show($id);
        flash('success', 'Show deleted.');
    } elseif ($action === 'toggle_featured' && $id) {
        toggle_featured_show($id);
        flash('success', 'Featured status updated.');
    } elseif ($action === 'refresh' && $id) {
        $ok = refresh_youtube_metadata($id);
        flash($ok ? 'success' : 'error', $ok ? 'Metadata refreshed.' : 'Could not fetch metadata.');
    } elseif ($action === 'reorder' && $id) {
        update_show_order($id, (int)($_POST['display_order'] ?? 0));
        flash('success', 'Order updated.');
    }
    redirect(BASE_PATH . '/admin/previous-shows.php');
}

$shows = db()->query(
    "SELECT * FROM previous_shows ORDER BY display_order ASC, created_at DESC"
)->fetchAll();

$pageTitle      = 'Previous Shows';
$activeAdminNav = 'shows';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Previous Shows (<?= count($shows) ?>)</h2>
        <a href="<?= h(BASE_PATH) ?>/admin/previous-shows-add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Show
        </a>
    </div>

    <?php if (!$shows): ?>
        <div style="text-align:center;padding:48px 20px;color:var(--text-muted);">
            <i class="fas fa-video" style="font-size:36px;color:var(--gold);display:block;margin-bottom:12px;"></i>
            <p>No shows yet. <a href="<?= h(BASE_PATH) ?>/admin/previous-shows-add.php">Add the first one →</a></p>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:80px;">Thumb</th>
                <th>Title / Guest</th>
                <th>Views</th>
                <th>Date</th>
                <th>Featured</th>
                <th style="width:90px;">Order</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($shows as $s): ?>
        <tr>
            <td>
                <div class="thumb" style="width:80px;height:50px;border-radius:6px;overflow:hidden;background:#0f172a;">
                    <img src="<?= h($s['thumbnail_url'] ?: 'https://i.ytimg.com/vi/' . $s['video_id'] . '/mqdefault.jpg') ?>"
                         alt="" style="width:100%;height:100%;object-fit:cover;" />
                </div>
            </td>
            <td>
                <div style="font-weight:600;font-size:13.5px;max-width:340px;white-space:normal;line-height:1.35;">
                    <?= h($s['title']) ?>
                </div>
                <?php if ($s['guest_name']): ?>
                    <div style="color:var(--maroon);font-size:12px;margin-top:3px;">
                        <i class="fas fa-microphone"></i> <?= h($s['guest_name']) ?>
                    </div>
                <?php endif; ?>
                <div style="margin-top:4px;">
                    <a href="https://www.youtube.com/watch?v=<?= h($s['video_id']) ?>"
                       target="_blank" style="font-size:11.5px;color:var(--text-muted);">
                        <i class="fab fa-youtube" style="color:#ff0000;"></i>
                        youtu.be/<?= h($s['video_id']) ?>
                    </a>
                </div>
            </td>
            <td><?= $s['views'] > 0 ? number_format($s['views']) : '—' ?></td>
            <td><?= $s['upload_date'] ? date('M j, Y', strtotime($s['upload_date'])) : '—' ?></td>
            <td>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_featured" />
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                    <button type="submit" class="icon-btn" title="Toggle Featured"
                            style="color:<?= $s['is_featured'] ? 'var(--gold)' : 'var(--text-muted)' ?>;">
                        <i class="fas fa-star"></i>
                    </button>
                </form>
            </td>
            <td>
                <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reorder" />
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                    <input type="number" name="display_order" value="<?= (int)$s['display_order'] ?>"
                           class="form-control" style="width:60px;padding:5px 8px;font-size:13px;" min="0" />
                    <button type="submit" class="icon-btn" title="Save order"><i class="fas fa-check"></i></button>
                </form>
            </td>
            <td>
                <div class="row-actions">
                    <a href="<?= h(BASE_PATH) ?>/admin/previous-shows-edit.php?id=<?= (int)$s['id'] ?>"
                       class="icon-btn" title="Edit"><i class="fas fa-pen"></i></a>
                    <!-- refresh metadata -->
                    <form method="post" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="refresh" />
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                        <button type="submit" class="icon-btn" title="Refresh metadata from YouTube">
                            <i class="fas fa-rotate"></i>
                        </button>
                    </form>
                    <!-- delete -->
                    <form method="post" data-confirm="Delete '<?= h($s['title']) ?>'?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                        <button type="submit" class="icon-btn" title="Delete"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
