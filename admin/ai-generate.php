<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/openrouter-article-generator.php';

$errors = [];
$formUrl = '';
$formContext = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    set_time_limit(90);

    $formUrl = trim((string) ($_POST['url'] ?? ''));
    $formContext = trim((string) ($_POST['context'] ?? ''));

    $apiKey = get_setting('openrouter_api_key', '');
    $model  = get_setting('openrouter_model', 'google/gemini-2.5-flash-lite');
    if ($apiKey === '') {
        $errors[] = 'No OpenRouter API key configured yet. Add one under Settings first.';
    } elseif ($formUrl === '') {
        $errors[] = 'Please paste a source URL.';
    } else {
        $generator = new OromaTV_AI_Content_Generator($apiKey, $model);
        $draft = $generator->generateDraft($formUrl, $formContext);

        if (!$draft['success']) {
            $errors[] = $draft['error'];
        } else {
            $title = $draft['headline'] !== '' ? $draft['headline'] : 'Untitled AI Draft';
            $slug = unique_slug($title, 'articles');
            $content = sanitize_html($draft['content_html']);
            $excerpt = $draft['excerpt'] !== '' ? $draft['excerpt'] : make_excerpt($content);

            $stmt = db()->prepare(
                "INSERT INTO articles (title, slug, excerpt, content, category_id, author_id, status, is_featured, is_breaking, body_font)
                 VALUES (?, ?, ?, ?, NULL, ?, 'draft', 0, 0, 'inter')"
            );
            $stmt->execute([$title, $slug, $excerpt, $content, current_user()['id']]);
            $newId = (int) db()->lastInsertId();

            $imgHint = $draft['image_idea'] !== '' ? ' Suggested image: "' . $draft['image_idea'] . '".' : '';
            flash('success', 'AI draft created from ' . $draft['source_name'] . '. Review it, add a category and image, then publish when ready.' . $imgHint);
            redirect(BASE_PATH . '/admin/article-form.php?id=' . $newId);
        }
    }
}

$pageTitle = 'AI Article Writer';
$activeAdminNav = 'ai-writer';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card" style="max-width:680px;">
    <div class="card-head">
        <h2><i class="fas fa-wand-magic-sparkles"></i> AI Article Writer</h2>
    </div>

    <?php if ($errors): ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert" style="margin-bottom:12px;"><?= h($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin-bottom:22px;">
        Paste a link to a news story, tweet, or blog post. The AI drafts an Oroma TV piece that
        <strong>reports on</strong> it — the source is credited and linked automatically, both
        in the prompt and again in code — then hands you a <strong>Draft</strong> to review in the
        normal editor: add a category, attach an image, edit freely, and publish when you're happy with it.
    </p>

    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="url">Source URL</label>
            <input class="form-control" type="url" id="url" name="url" required
                   placeholder="https://example.com/article..." value="<?= h($formUrl) ?>" />
        </div>
        <div class="form-group">
            <label for="context">Extra instructions (optional)</label>
            <textarea class="form-control" id="context" name="context" rows="3"
                      placeholder="e.g. Focus on the Uganda angle, keep it under 400 words…"><?= h($formContext) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary" id="genBtn">
            <i class="fas fa-wand-magic-sparkles"></i> Generate Draft
        </button>
    </form>
</div>

<script>
document.getElementById('genBtn')?.addEventListener('click', function () {
    var btn = this;
    requestAnimationFrame(function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating… this can take up to a minute';
    });
});
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
