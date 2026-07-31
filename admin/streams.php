<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $current = get_setting('stream_status', 'offline');
        set_setting('stream_status', $current === 'live' ? 'offline' : 'live');
        flash('success', 'Stream status updated.');
        redirect(BASE_PATH . '/admin/streams.php');
    }

    if ($action === 'save_stream') {
        $id = (int) ($_POST['id'] ?? 0);
        $type = in_array($_POST['type'] ?? '', ['youtube', 'radio'], true) ? $_POST['type'] : 'youtube';
        $name = trim((string) ($_POST['name'] ?? ''));
        $urlOrId = trim((string) ($_POST['url_or_id'] ?? ''));
        $icon = trim((string) ($_POST['icon'] ?? '')) ?: null;
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($name === '' || $urlOrId === '') {
            flash('error', 'Name and URL / Video ID are required.');
            redirect(BASE_PATH . '/admin/streams.php');
        }

        if ($isDefault) {
            db()->prepare('UPDATE streams SET is_default = 0 WHERE type = ?')->execute([$type]);
        }

        if ($id > 0) {
            $stmt = db()->prepare('UPDATE streams SET type=?, name=?, url_or_id=?, icon=?, is_default=? WHERE id=?');
            $stmt->execute([$type, $name, $urlOrId, $icon, $isDefault, $id]);
            flash('success', 'Stream updated.');
        } else {
            $maxOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) AS m FROM streams')->fetch()['m'];
            $stmt = db()->prepare('INSERT INTO streams (type, name, url_or_id, icon, is_default, sort_order) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$type, $name, $urlOrId, $icon, $isDefault, $maxOrder + 1]);
            flash('success', 'Stream added.');
        }
        redirect(BASE_PATH . '/admin/streams.php');
    }

    if ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE streams SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        redirect(BASE_PATH . '/admin/streams.php');
    }
}

$editStream = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM streams WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editStream = $stmt->fetch();
}

$streams = db()->query('SELECT * FROM streams ORDER BY type, sort_order, id')->fetchAll();
$streamStatus = get_setting('stream_status', 'offline');

$pageTitle = 'Streams';
$activeAdminNav = 'streams';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Live Status</h2>
    </div>
    <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">
        Controls the Live / Offline badge shown across the public site.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_status" />
        <?php if ($streamStatus === 'live'): ?>
            <span class="status-pill status-published" style="margin-right:12px;"><i class="fas fa-circle"></i> LIVE</span>
        <?php else: ?>
            <span class="status-pill status-editor" style="margin-right:12px;">OFFLINE</span>
        <?php endif; ?>
        <button type="submit" class="btn btn-outline btn-sm">
            Switch to <?= $streamStatus === 'live' ? 'Offline' : 'Live' ?>
        </button>
    </form>
</div>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:22px;align-items:start;">
    <div class="card">
        <div class="card-head"><h2><?= $editStream ? 'Edit Stream' : 'Add Stream' ?></h2></div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_stream" />
            <input type="hidden" name="id" value="<?= (int) ($editStream['id'] ?? 0) ?>" />

            <div class="form-group">
                <label for="type">Type</label>
                <select class="form-control" id="type" name="type">
                    <option value="youtube" <?= ($editStream['type'] ?? 'youtube') === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                    <option value="radio" <?= ($editStream['type'] ?? '') === 'radio' ? 'selected' : '' ?>>Radio (Radio Garden embed)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Name</label>
                <input class="form-control" type="text" id="name" name="name" required
                       value="<?= h($editStream['name'] ?? '') ?>" placeholder="e.g. Oroma TV" />
            </div>
            <div class="form-group">
                <label for="url_or_id">YouTube URL / Video ID, or Radio Garden embed URL</label>
                <input class="form-control" type="text" id="url_or_id" name="url_or_id" required
                       value="<?= h($editStream['url_or_id'] ?? '') ?>" placeholder="https://youtube.com/watch?v=… or https://radio.garden/embed/…" />
            </div>
            <div class="form-group">
                <label for="icon">Icon (Font Awesome class, optional)</label>
                <input class="form-control" type="text" id="icon" name="icon"
                       value="<?= h($editStream['icon'] ?? '') ?>" placeholder="fas fa-tv" />
            </div>
            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" name="is_default" value="1" <?= !empty($editStream['is_default']) ? 'checked' : '' ?> />
                    Default (loads first for this type)
                </label>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $editStream ? 'Update' : 'Add' ?> Stream</button>
                <?php if ($editStream): ?>
                    <a href="<?= h(BASE_PATH) ?>/admin/streams.php" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2>All Streams</h2></div>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Type</th><th>Name</th><th>URL / ID</th><th>Default</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php if (!$streams): ?>
                <tr class="empty-row"><td colspan="6">No streams configured yet — add one on the left.</td></tr>
            <?php else: foreach ($streams as $s): ?>
                <tr>
                    <td><i class="<?= $s['type'] === 'youtube' ? 'fab fa-youtube' : 'fas fa-broadcast-tower' ?>"></i> <?= h(ucfirst($s['type'])) ?></td>
                    <td><?= h($s['name']) ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($s['url_or_id']) ?></td>
                    <td><?= $s['is_default'] ? '<i class="fas fa-star" style="color:var(--gold);"></i>' : '' ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_active" />
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>" />
                            <button type="submit" class="status-pill <?= $s['is_active'] ? 'status-published' : 'status-editor' ?>" style="border:none;cursor:pointer;">
                                <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= h(BASE_PATH) ?>/admin/streams.php?edit=<?= (int) $s['id'] ?>" class="icon-btn"><i class="fas fa-pen"></i></a>
                            <form method="post" action="<?= h(BASE_PATH) ?>/admin/stream-delete.php" data-confirm="Delete stream “<?= h($s['name']) ?>”?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>" />
                                <button type="submit" class="icon-btn"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
