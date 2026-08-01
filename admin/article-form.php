<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;
if ($id > 0) {
    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category_name FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.id = ?"
    );
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('error', 'Article not found.');
        redirect(BASE_PATH . '/admin/articles.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $contentRaw = (string) ($_POST['content'] ?? '');
    $content = sanitize_html($contentRaw);

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if (trim(strip_tags($content)) === '' && !str_contains($content, '<img')) {
        $errors[] = 'Article content cannot be empty.';
    }

    if (!$errors) {
        $slugSource = trim((string) ($_POST['slug'] ?? '')) ?: $title;
        $slug = unique_slug($slugSource, 'articles', $id ?: null);

        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        if ($excerpt === '') {
            $excerpt = make_excerpt($content);
        }

        $categoryId = find_or_create_category((string) ($_POST['category'] ?? ''));
        $tagIds = find_or_create_tags((string) ($_POST['tags'] ?? ''));

        $metaTitle = trim((string) ($_POST['meta_title'] ?? '')) ?: null;
        $metaDescription = trim((string) ($_POST['meta_description'] ?? '')) ?: null;
        $status = in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : 'draft';
        $isFeatured  = isset($_POST['is_featured'])  ? 1 : 0;
        $isBreaking  = isset($_POST['is_breaking'])  ? 1 : 0;

        $featuredImage = $existing['featured_image'] ?? null;
        if (!empty($_FILES['featured_image_file']) && $_FILES['featured_image_file']['error'] === UPLOAD_ERR_OK) {
            $uploadError = null;
            $newImage = handle_image_upload($_FILES['featured_image_file'], $uploadError);
            if ($newImage) {
                $featuredImage = $newImage;
            } else {
                $errors[] = $uploadError;
            }
        } elseif (!empty($_POST['remove_featured_image'])) {
            $featuredImage = null;
        }

        $featuredImageCaption = trim((string) ($_POST['featured_image_caption'] ?? ''));
        $featuredImageCaption = ($featuredImageCaption !== '' && $featuredImage) ? $featuredImageCaption : null;

        if (!$errors) {
            $authorId = $existing['author_id'] ?? current_user()['id'];

            if ($existing) {
                $stmt = db()->prepare(
                    'UPDATE articles SET title=?, slug=?, excerpt=?, content=?, featured_image=?, featured_image_caption=?, category_id=?,
                     meta_title=?, meta_description=?, status=?, is_featured=?, is_breaking=? WHERE id=?'
                );
                $stmt->execute([$title, $slug, $excerpt, $content, $featuredImage, $featuredImageCaption, $categoryId,
                    $metaTitle, $metaDescription, $status, $isFeatured, $isBreaking, $id]);
                $articleId = $id;
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO articles (title, slug, excerpt, content, featured_image, featured_image_caption, category_id, author_id,
                     meta_title, meta_description, status, is_featured, is_breaking)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([$title, $slug, $excerpt, $content, $featuredImage, $featuredImageCaption, $categoryId, $authorId,
                    $metaTitle, $metaDescription, $status, $isFeatured, $isBreaking]);
                $articleId = (int) db()->lastInsertId();
            }

            db()->prepare('DELETE FROM article_tags WHERE article_id = ?')->execute([$articleId]);
            if ($tagIds) {
                $tagStmt = db()->prepare('INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)');
                foreach ($tagIds as $tagId) {
                    $tagStmt->execute([$articleId, $tagId]);
                }
            }

            flash('success', $existing ? 'Article updated.' : 'Article created.');

            if (($_POST['action'] ?? '') === 'preview') {
                redirect(BASE_PATH . '/article.php?slug=' . urlencode($slug));
            }
            redirect(BASE_PATH . '/admin/articles.php');
        }
    }

    // re-populate $existing with submitted values so the form redisplays what the user typed
    $existing = array_merge($existing ?? [], [
        'title' => $title,
        'slug' => $_POST['slug'] ?? '',
        'excerpt' => $_POST['excerpt'] ?? '',
        'content' => $contentRaw,
        'category_name' => $_POST['category'] ?? '',
        'meta_title' => $_POST['meta_title'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_breaking' => isset($_POST['is_breaking']) ? 1 : 0,
        'featured_image_caption' => $_POST['featured_image_caption'] ?? '',
        'id' => $id,
    ]);
}

$existingTags = '';
if ($id > 0) {
    $tagStmt = db()->prepare(
        'SELECT t.name FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name'
    );
    $tagStmt->execute([$id]);
    $existingTags = implode(', ', array_column($tagStmt->fetchAll(), 'name'));
} elseif (isset($_POST['tags'])) {
    $existingTags = $_POST['tags'];
}

$categories = db()->query('SELECT name FROM categories ORDER BY name ASC')->fetchAll();

$pageTitle = $existing ? 'Edit Article' : 'New Article';
$activeAdminNav = 'articles';
require __DIR__ . '/includes/admin-header.php';
?>

<script>
    window.OROMA_CSRF = <?= json_encode(csrf_token()) ?>;
    window.OROMA_UPLOAD_URL = <?= json_encode(BASE_PATH . '/admin/upload-image.php') ?>;
</script>

<?php if ($errors): ?>
    <div class="card" style="border-color:#c0392b;">
        <?php foreach ($errors as $err): ?>
            <div class="alert" style="margin-bottom:8px;"><?= h($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" action="<?= h(BASE_PATH) ?>/admin/article-form.php<?= $id ? '?id=' . $id : '' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>" />
    <input type="hidden" name="existing_featured_image" value="<?= h($existing['featured_image'] ?? '') ?>" />
    <input type="hidden" name="content" id="content" value="" />

    <div class="article-form-grid">
        <div>
            <div class="card">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input class="form-control" type="text" id="title" name="title" required value="<?= h($existing['title'] ?? '') ?>" />
                </div>
                <div class="form-group">
                    <label for="slug">Slug (URL)</label>
                    <input class="form-control" type="text" id="slug" name="slug" value="<?= h($existing['slug'] ?? '') ?>" placeholder="auto-generated from title" />
                    <div class="form-hint">Leave blank to auto-generate from the title.</div>
                </div>
                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <textarea class="form-control" id="excerpt" name="excerpt" rows="2" placeholder="Short summary shown on cards (auto-generated if left blank)"><?= h($existing['excerpt'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <div id="editor" style="background:#fff;color:#1a1a2e;border-radius:10px;min-height:320px;"><?= $existing['content'] ?? '' ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>SEO</h2></div>
                <div class="form-group">
                    <label for="meta_title">Meta Title</label>
                    <input class="form-control" type="text" id="meta_title" name="meta_title" value="<?= h($existing['meta_title'] ?? '') ?>" placeholder="Defaults to the article title" />
                </div>
                <div class="form-group">
                    <label for="meta_description">Meta Description</label>
                    <textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="500" placeholder="Defaults to the excerpt"><?= h($existing['meta_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-head"><h2>Publish</h2></div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="draft" <?= ($existing['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= ($existing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_featured" value="1" <?= !empty($existing['is_featured']) ? 'checked' : '' ?> />
                        Featured article (shows in hero section)
                    </label>
                </div>
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_breaking" value="1" <?= !empty($existing['is_breaking']) ? 'checked' : '' ?> />
                        Breaking news (shows in ticker)
                    </label>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <button type="submit" name="action" value="save" class="btn btn-primary btn-block"><i class="fas fa-check"></i> Save</button>
                    <button type="submit" name="action" value="preview" formtarget="_blank" class="btn btn-outline btn-block"><i class="fas fa-eye"></i> Save &amp; Preview</button>
                    <a href="<?= h(BASE_PATH) ?>/admin/articles.php" class="btn btn-outline btn-block">Cancel</a>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Featured Image</h2></div>
                <div class="image-preview" style="<?= empty($existing['featured_image']) ? 'display:none;' : '' ?>">
                    <img id="imagePreviewImg" src="<?= !empty($existing['featured_image']) ? h(BASE_PATH . '/' . $existing['featured_image']) : '' ?>" alt="" />
                </div>
                <input type="file" id="featured_image_file" name="featured_image_file" accept="image/*" class="form-control" />
                <?php if (!empty($existing['featured_image'])): ?>
                    <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;">
                        <input type="checkbox" name="remove_featured_image" value="1" /> Remove current image
                    </label>
                <?php endif; ?>
                <div class="form-group" style="margin-top:14px;margin-bottom:0;">
                    <label for="featured_image_caption">Image Caption</label>
                    <input class="form-control" type="text" id="featured_image_caption" name="featured_image_caption"
                           value="<?= h($existing['featured_image_caption'] ?? '') ?>" maxlength="300"
                           placeholder="e.g. Photo credit or description" />
                    <div class="form-hint">Shown under the photo on the published article.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Category &amp; Tags</h2></div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">— No category —</option>
                        <?php
                        $allCats = db()->query('SELECT name FROM categories WHERE is_active=1 ORDER BY display_order ASC, name ASC')->fetchAll();
                        foreach ($allCats as $c):
                            $sel = ($existing['category_name'] ?? '') === $c['name'] ? 'selected' : '';
                        ?>
                            <option value="<?= h($c['name']) ?>" <?= $sel ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tags">Tags</label>
                    <input class="form-control" type="text" id="tags" name="tags" value="<?= h($existingTags) ?>" placeholder="comma, separated, tags" />
                </div>
            </div>
        </div>
    </div>
</form>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
