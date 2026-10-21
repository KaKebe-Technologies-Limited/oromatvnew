<?php
/**
 * Import from a link: paste the address of a story on another website and get a draft here.
 *   - "Import it as it is": the text, the headline, the publish date and every photo (with its caption) are brought across,
 *     and the source is credited at the end. For your own stories and ones you have permission to republish.
 *   - "Rewrite with AI": the AI reports on the story in Oroma TV's voice (source credited at the top), and the story's photos
 *     are spread through the rewritten text.
 * The photos are downloaded into this site's uploads folder (not linked from the other site), the first/lead photo becomes the
 * cover, and the result is saved as a Draft for review in the normal editor.
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/openrouter-article-generator.php';
require_once __DIR__ . '/../includes/link-importer.php';

$errors = [];
$formUrl = '';
$formContext = '';
$formMode = 'import';
$formPhotos = true;
$formCategory = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    set_time_limit(120);

    $formUrl = trim((string) ($_POST['url'] ?? ''));
    $formContext = trim((string) ($_POST['context'] ?? ''));
    $formMode = ($_POST['mode'] ?? 'import') === 'ai' ? 'ai' : 'import';
    $formPhotos = isset($_POST['photos']);
    $formCategory = trim((string) ($_POST['category'] ?? ''));

    if ($formUrl === '' || !filter_var($formUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Please paste the full address of the story (starting with https://).';
    } elseif ($formMode === 'ai' && get_setting('openrouter_api_key', '') === '') {
        $errors[] = 'No OpenRouter API key configured yet, so the AI cannot rewrite stories. Add one under Settings, or choose "Import it as it is".';
    } else {
        $reader = new OromaTV_Link_Reader();
        $page = $reader->read($formUrl); // the story's text and photo list (needed by both modes)
        $title = '';
        $content = '';
        $excerpt = '';
        $featured = null;
        $photoCount = 0;
        $skipped = 0;
        $published = null;
        $note = '';

        if ($formMode === 'import') {
            if (!$page) {
                $errors[] = 'Could not read a story from that address. The page may block automated requests, need a login, or have too little text.';
            } else {
                $photos = $formPhotos ? $reader->importPhotos($page) : ['featured' => null, 'blocks' => array_values(array_filter($page['blocks'], fn($b) => $b['type'] !== 'img')), 'saved' => 0, 'failed' => 0];
                $featured = $photos['featured'];
                $photoCount = $photos['saved'];
                $skipped = $photos['failed'];
                $body = $reader->toHtml($photos['blocks']);

                $title = $page['title'];
                $source = h($page['source_name']);
                $when = $page['published'] ? ' on ' . date('j F Y', strtotime($page['published'])) : '';
                $credit = '<p><em>This story was first published by <a href="' . h($page['final_url']) . '" target="_blank" rel="noopener noreferrer">' . $source . '</a>' . $when . '.'
                    . ($photoCount > 0 ? ' Photos courtesy of ' . $source . '.' : '') . '</em></p>';
                $content = $body . $credit;
                $published = $page['published'];

                // The page's own description is often cut short ("…"), so the excerpt is the story's opening paragraph.
                foreach ($page['blocks'] as $b) {
                    if ($b['type'] === 'p') {
                        $excerpt = make_excerpt($b['html'], 200);
                        break;
                    }
                }
                $excerpt = $excerpt !== '' ? $excerpt : $page['description'];
            }
        } else {
            $generator = new OromaTV_AI_Content_Generator(get_setting('openrouter_api_key', ''), get_setting('openrouter_model', 'google/gemini-2.5-flash-lite'));
            $draft = $generator->generateDraft($formUrl, $formContext);
            if (!$draft['success']) {
                $errors[] = $draft['error'];
            } else {
                $title = $draft['headline'] !== '' ? $draft['headline'] : 'Untitled AI Draft';
                $content = $draft['content_html'];
                $excerpt = $draft['excerpt'];
                if ($page && $formPhotos) {
                    $photos = $reader->importPhotos($page);
                    $featured = $photos['featured'];
                    $photoCount = $photos['saved'];
                    $skipped = $photos['failed'];
                    $inBody = array_values(array_filter($photos['blocks'], fn($b) => $b['type'] === 'img'));
                    $content = OromaTV_Link_Reader::interleavePhotos($content, $inBody);
                }
                $published = $page['published'] ?? null;
                $note = $draft['image_idea'] !== '' && !$featured ? ' Suggested image: "' . $draft['image_idea'] . '".' : '';
            }
        }

        if (!$errors) {
            $content = sanitize_html($content);
            $slug = unique_slug($title, 'articles');
            $excerpt = $excerpt !== '' ? $excerpt : make_excerpt($content);
            $categoryId = $formCategory !== '' ? find_or_create_category($formCategory) : null;
            $caption = $featured && !empty($featured['caption']) ? mb_substr($featured['caption'], 0, 300) : null;
            $createdAt = $published ?? date('Y-m-d H:i:s');

            $stmt = db()->prepare(
                "INSERT INTO articles (title, slug, excerpt, content, featured_image, featured_image_caption, category_id, author_id, status, is_featured, is_breaking, body_font, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', 0, 0, 'inter', ?)"
            );
            $stmt->execute([$title, $slug, $excerpt, $content, $featured['path'] ?? null, $caption, $categoryId, current_user()['id'], $createdAt]);
            $newId = (int) db()->lastInsertId();

            $photoText = $photoCount > 0
                ? ' ' . $photoCount . ' photo' . ($photoCount === 1 ? '' : 's') . ' brought across' . ($featured ? ' (the first is the cover)' : '') . ($skipped > 0 ? ', ' . $skipped . ' skipped (too small or not available)' : '') . '.'
                : ($formPhotos ? ' No photos could be brought across.' : '');
            flash('success', ($formMode === 'import' ? 'Story imported from ' : 'AI draft created from ') . ($page['source_name'] ?? 'the link') . '.' . $photoText . ' Review it, pick a category, then publish when ready.' . $note);
            redirect(BASE_PATH . '/admin/article-form.php?id=' . $newId);
        }
    }
}

$cats = db()->query('SELECT name FROM categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC')->fetchAll();

$pageTitle = 'Import from a Link';
$activeAdminNav = 'ai-writer';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card" style="max-width:760px;">
    <div class="card-head">
        <h2><i class="fas fa-link"></i> Import a story from a link</h2>
    </div>

    <?php if ($errors): ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert" style="margin-bottom:12px;"><?= h($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin-bottom:22px;">
        Paste the address of a story on another website. You get a <strong>Draft</strong> here with the headline, the text and
        <strong>the photos</strong> — they are saved on this site and placed in the story, with their captions — and the source
        credited. Review it in the normal editor, add a category, and publish when you are happy with it.
    </p>

    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="url">Story address</label>
            <input class="form-control" type="url" id="url" name="url" required
                   placeholder="https://example.com/stories/..." value="<?= h($formUrl) ?>" />
        </div>

        <div class="form-group">
            <label>How should it be brought in?</label>
            <div class="mode-choice">
                <label class="mode-card">
                    <input type="radio" name="mode" value="import" <?= $formMode === 'import' ? 'checked' : '' ?> />
                    <span><strong>Import it as it is</strong>
                        Keeps the headline, text, links, publish date and captions exactly. Best for your own stories and ones you have permission to republish.</span>
                </label>
                <label class="mode-card">
                    <input type="radio" name="mode" value="ai" <?= $formMode === 'ai' ? 'checked' : '' ?> />
                    <span><strong>Rewrite with AI</strong>
                        The AI reports on the story in Oroma TV's own words and credits the source at the top. Best for other outlets' news.</span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="toggle-switch">
                <input type="checkbox" name="photos" value="1" <?= $formPhotos ? 'checked' : '' ?> />
                Bring the photos too (the first becomes the cover, the rest go in the story)
            </label>
            <div class="form-hint">Only bring photos you own or have permission to use. The source is credited under the story.</div>
        </div>

        <div class="form-group">
            <label for="category">Category (optional)</label>
            <select class="form-control" id="category" name="category" style="max-width:320px;">
                <option value="">— choose later —</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= h($c['name']) ?>" <?= $formCategory === $c['name'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="aiOnly" style="<?= $formMode === 'ai' ? '' : 'display:none;' ?>">
            <label for="context">Extra instructions for the AI (optional)</label>
            <textarea class="form-control" id="context" name="context" rows="3"
                      placeholder="e.g. Focus on the Uganda angle, keep it under 400 words…"><?= h($formContext) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary" id="genBtn">
            <i class="fas fa-file-import"></i> <span id="genLabel"><?= $formMode === 'ai' ? 'Generate Draft' : 'Import Story' ?></span>
        </button>
    </form>
</div>

<script>
(function () {
    var radios = document.querySelectorAll('input[name="mode"]');
    var aiOnly = document.getElementById('aiOnly');
    var label = document.getElementById('genLabel');
    function sync() {
        var ai = document.querySelector('input[name="mode"]:checked').value === 'ai';
        aiOnly.style.display = ai ? '' : 'none';
        label.textContent = ai ? 'Generate Draft' : 'Import Story';
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });

    document.getElementById('genBtn').addEventListener('click', function () {
        var btn = this;
        if (!btn.form.checkValidity()) return;
        requestAnimationFrame(function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working… reading the story and saving its photos can take up to a minute';
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
