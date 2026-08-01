<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ── handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $catId  = (int)($_POST['cat_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $icon   = trim($_POST['icon'] ?? 'fa-folder');
        $color  = trim($_POST['color'] ?? '#800000');
        $order  = (int)($_POST['display_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $desc   = trim($_POST['description'] ?? '');

        if ($name === '') { flash('error', 'Category name is required.'); redirect(BASE_PATH.'/admin/categories.php'); }

        $slug = unique_slug($name, 'categories', $catId ?: null);

        if ($catId > 0) {
            db()->prepare('UPDATE categories SET name=?,slug=?,icon=?,color=?,display_order=?,is_active=?,description=? WHERE id=?')
               ->execute([$name,$slug,$icon,$color,$order,$active,$desc,$catId]);
            flash('success', 'Category updated.');
        } else {
            db()->prepare('INSERT INTO categories (name,slug,icon,color,display_order,is_active,description) VALUES (?,?,?,?,?,?,?)')
               ->execute([$name,$slug,$icon,$color,$order,$active,$desc]);
            flash('success', 'Category created.');
        }

    } elseif ($action === 'delete') {
        $catId = (int)($_POST['cat_id'] ?? 0);
        if ($catId > 0) {
            db()->prepare('UPDATE articles SET category_id=NULL WHERE category_id=?')->execute([$catId]);
            db()->prepare('DELETE FROM categories WHERE id=?')->execute([$catId]);
            flash('success', 'Category deleted.');
        }

    } elseif ($action === 'toggle') {
        $catId = (int)($_POST['cat_id'] ?? 0);
        db()->prepare('UPDATE categories SET is_active = NOT is_active WHERE id=?')->execute([$catId]);
        flash('success', 'Category visibility toggled.');
    }

    redirect(BASE_PATH . '/admin/categories.php');
}

// ── load data ───────────────────────────────────────────────
$categories = get_categories_with_counts();

// edit mode
$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($categories as $c) { if ((int)$c['id'] === $editId) { $editing = $c; break; } }
}

$pageTitle       = 'Categories';
$activeAdminNav  = 'categories';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Categories (<?= count($categories) ?>)</h2>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('catFormCard').style.display='block';this.style.display='none';">
            <i class="fas fa-plus"></i> New Category
        </button>
    </div>

    <!-- add / edit form -->
    <div id="catFormCard" class="card" style="background:var(--bg);<?= $editing ? '' : 'display:none;' ?> margin-bottom:24px;">
        <div class="card-head">
            <h2><?= $editing ? 'Edit: '.h($editing['name']) : 'Add New Category' ?></h2>
            <?php if (!$editing): ?>
            <button class="btn btn-outline btn-sm" onclick="this.closest('#catFormCard').style.display='none';">
                <i class="fas fa-times"></i> Cancel
            </button>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= h(BASE_PATH) ?>/admin/categories.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save" />
            <input type="hidden" name="cat_id" value="<?= (int)($editing['id'] ?? 0) ?>" />

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label>Name *</label>
                    <input class="form-control" type="text" name="name" required value="<?= h($editing['name'] ?? '') ?>" placeholder="e.g. Politics" />
                </div>
                <div class="form-group">
                    <label>Font Awesome Icon class</label>
                    <input class="form-control" type="text" name="icon" value="<?= h($editing['icon'] ?? 'fa-folder') ?>" placeholder="e.g. fa-landmark" />
                    <div class="form-hint">Use any <a href="https://fontawesome.com/icons" target="_blank">Font Awesome</a> class (fas prefix is added automatically).</div>
                </div>
                <div class="form-group">
                    <label>Display Order</label>
                    <input class="form-control" type="number" name="display_order" value="<?= (int)($editing['display_order'] ?? 0) ?>" min="0" />
                </div>
                <div class="form-group">
                    <label>Accent Color</label>
                    <input class="form-control" type="color" name="color" value="<?= h($editing['color'] ?? '#800000') ?>" style="height:42px;padding:4px 8px;" />
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Description (optional)</label>
                    <textarea class="form-control" name="description" rows="2" placeholder="Short description shown in category pages"><?= h($editing['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" value="1" <?= !isset($editing) || $editing['is_active'] ? 'checked' : '' ?> />
                        Active (visible in nav & filters)
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> <?= $editing ? 'Update' : 'Create' ?> Category</button>
                <a href="<?= h(BASE_PATH) ?>/admin/categories.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

    <!-- categories table -->
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Order</th><th>Icon</th><th>Name</th><th>Slug</th>
                <th>Articles</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$categories): ?>
            <tr class="empty-row"><td colspan="7">No categories yet.</td></tr>
        <?php else: foreach ($categories as $cat): ?>
            <tr>
                <td><?= (int)$cat['display_order'] ?></td>
                <td><i class="fas <?= h($cat['icon'] ?: 'fa-folder') ?>" style="color:var(--maroon);font-size:16px;"></i></td>
                <td><strong><?= h($cat['name']) ?></strong></td>
                <td><code style="font-size:12px;"><?= h($cat['slug']) ?></code></td>
                <td><?= (int)$cat['article_count'] ?></td>
                <td>
                    <span class="status-pill <?= $cat['is_active'] ? 'status-published' : 'status-draft' ?>">
                        <?= $cat['is_active'] ? 'Active' : 'Hidden' ?>
                    </span>
                </td>
                <td>
                    <div class="row-actions">
                        <a href="<?= h(BASE_PATH) ?>/admin/categories.php?edit=<?= (int)$cat['id'] ?>" class="icon-btn" title="Edit"><i class="fas fa-pen"></i></a>
                        <form method="post" action="<?= h(BASE_PATH) ?>/admin/categories.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle" />
                            <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>" />
                            <button type="submit" class="icon-btn" title="<?= $cat['is_active'] ? 'Hide' : 'Show' ?>">
                                <i class="fas fa-<?= $cat['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                            </button>
                        </form>
                        <form method="post" action="<?= h(BASE_PATH) ?>/admin/categories.php"
                              data-confirm="Delete '<?= h($cat['name']) ?>'? Articles in this category will become uncategorised.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>" />
                            <button type="submit" class="icon-btn" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
