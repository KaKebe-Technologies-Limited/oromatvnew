<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/previous-shows-functions.php';
require_login();

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM previous_shows WHERE id=?');
$stmt->execute([$id]);
$show = $stmt->fetch();
if (!$show) {
    flash('error', 'Show not found.');
    redirect(BASE_PATH . '/admin/previous-shows.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? 'save';

    if ($action === 'refresh') {
        $ok = refresh_youtube_metadata($id);
        flash($ok ? 'success' : 'error', $ok ? 'Metadata refreshed from YouTube.' : 'Could not refresh metadata.');
        redirect(BASE_PATH . '/admin/previous-shows-edit.php?id=' . $id);
    }

    // save
    $guestName    = trim($_POST['guest_name']    ?? '');
    $isFeatured   = isset($_POST['is_featured'])  ? 1 : 0;
    $displayOrder = (int)($_POST['display_order'] ?? 0);
    $description  = trim($_POST['description']   ?? '');

    db()->prepare(
        'UPDATE previous_shows SET guest_name=?, is_featured=?, display_order=?, description=? WHERE id=?'
    )->execute([
        $guestName ?: null,
        $isFeatured,
        $displayOrder,
        $description ?: null,
        $id,
    ]);
    flash('success', 'Show updated.');
    redirect(BASE_PATH . '/admin/previous-shows.php');
}

$pageTitle      = 'Edit Show';
$activeAdminNav = 'shows';
require __DIR__ . '/includes/admin-header.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;max-width:900px;">

    <!-- preview column -->
    <div class="card">
        <div class="card-head"><h2>YouTube Preview</h2></div>

        <img src="<?= h($show['thumbnail_url'] ?: 'https://i.ytimg.com/vi/' . $show['video_id'] . '/hqdefault.jpg') ?>"
             alt="<?= h($show['title']) ?>"
             style="width:100%;border-radius:8px;margin-bottom:14px;"
             onerror="this.src='<?= h(BASE_PATH) ?>/assets/img/default-thumb.svg'" />

        <div style="font-weight:700;font-size:14px;line-height:1.4;margin-bottom:10px;">
            <?= h($show['title']) ?>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:flex;flex-direction:column;gap:5px;">
            <span><i class="fab fa-youtube" style="color:#ff0000;"></i>
                <a href="https://www.youtube.com/watch?v=<?= h($show['video_id']) ?>"
                   target="_blank" style="color:var(--maroon);">
                    youtu.be/<?= h($show['video_id']) ?>
                </a>
            </span>
            <?php if ($show['views'] > 0): ?>
            <span><i class="fas fa-eye"></i> <?= number_format($show['views']) ?> views</span>
            <?php endif; ?>
            <?php if ($show['upload_date']): ?>
            <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($show['upload_date'])) ?></span>
            <?php endif; ?>
            <?php if ($show['last_fetched']): ?>
            <span style="font-size:11.5px;margin-top:4px;color:var(--text-muted);">
                Last fetched: <?= date('M j, Y H:i', strtotime($show['last_fetched'])) ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- refresh -->
        <form method="post" style="margin-top:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>" />
            <button type="submit" name="action" value="refresh" class="btn btn-outline btn-sm" style="width:100%;">
                <i class="fas fa-rotate"></i> Refresh from YouTube
            </button>
        </form>
    </div>

    <!-- edit column -->
    <div class="card">
        <div class="card-head">
            <h2>Edit Details</h2>
            <a href="<?= h(BASE_PATH) ?>/admin/previous-shows.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>" />
            <input type="hidden" name="action" value="save" />

            <div class="form-group">
                <label for="guest_name">Guest Name</label>
                <input class="form-control" type="text" id="guest_name" name="guest_name"
                       value="<?= h($show['guest_name'] ?? '') ?>"
                       placeholder="e.g. Kimberly Jael Aremo Acio" />
                <div class="form-hint">Auto-extracted from the title — override here if needed.</div>
            </div>

            <div class="form-group">
                <label for="description">Description (optional)</label>
                <textarea class="form-control" id="description" name="description"
                          rows="3"><?= h($show['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="display_order">Display Order</label>
                <input class="form-control" type="number" id="display_order" name="display_order"
                       value="<?= (int)$show['display_order'] ?>" min="0" style="max-width:120px;" />
                <div class="form-hint">Lower number = appears first.</div>
            </div>

            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" name="is_featured" value="1"
                           <?= $show['is_featured'] ? 'checked' : '' ?> />
                    Featured episode (shown first with gold star badge)
                </label>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Save Changes
                </button>
                <a href="<?= h(BASE_PATH) ?>/admin/previous-shows.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
