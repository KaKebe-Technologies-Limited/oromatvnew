<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/previous-shows-functions.php';
require_login();

$preview  = null;
$errors   = [];
$lastUrl  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? '';
    $url    = trim($_POST['youtube_url'] ?? '');
    $lastUrl = $url;

    if ($url === '') {
        $errors[] = 'Please paste a YouTube URL.';
    } else {
        $videoId = extract_youtube_id($url);
        if ($videoId === '') {
            $errors[] = 'That doesn\'t look like a valid YouTube URL.';
        } else {
            if ($action === 'preview') {
                // just fetch and show preview — don't save yet
                $meta = fetch_youtube_metadata($url);
                if ($meta) {
                    $preview = $meta;
                    $preview['guest_name'] = extract_guest_name($meta['title']);
                } else {
                    $errors[] = 'Could not fetch video data. Check the URL and try again.';
                }
            } else {
                // save
                try {
                    $newId = add_previous_show($url);
                    flash('success', 'Show added successfully.');
                    redirect(BASE_PATH . '/admin/previous-shows.php');
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

$pageTitle      = 'Add Previous Show';
$activeAdminNav = 'shows';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card" style="max-width:700px;">
    <div class="card-head">
        <h2>Add Previous Show</h2>
        <a href="<?= h(BASE_PATH) ?>/admin/previous-shows.php" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($errors): ?>
        <?php foreach ($errors as $e): ?>
            <div class="flash flash-error" style="margin-bottom:10px;">
                <i class="fas fa-circle-exclamation"></i> <?= h($e) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" id="addShowForm">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="youtube_url" style="font-size:15px;font-weight:700;">
                <i class="fab fa-youtube" style="color:#ff0000;"></i> YouTube URL
            </label>
            <input class="form-control" type="url" id="youtube_url" name="youtube_url"
                   value="<?= h($lastUrl) ?>"
                   placeholder="https://youtu.be/xxxx  or  https://www.youtube.com/watch?v=xxxx"
                   required style="font-size:14px;" />
            <div class="form-hint">
                Paste any YouTube link — youtu.be, youtube.com/watch, /live/, /shorts/ all supported.
                The system will automatically fetch the title, thumbnail, views and upload date.
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" name="action" value="preview" class="btn btn-outline">
                <i class="fas fa-eye"></i> Preview
            </button>
            <button type="submit" name="action" value="save" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Show
            </button>
        </div>
    </form>

    <?php if ($preview): ?>
    <div style="margin-top:28px;padding-top:24px;border-top:1px solid var(--border);">
        <h3 style="font-size:14px;font-weight:700;text-transform:uppercase;
                   letter-spacing:.6px;color:var(--text-muted);margin-bottom:16px;">
            Preview
        </h3>
        <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
            <img src="<?= h($preview['thumbnail_url']) ?>"
                 alt="<?= h($preview['title']) ?>"
                 style="width:200px;border-radius:8px;flex-shrink:0;"
                 onerror="this.style.display='none'" />
            <div>
                <div style="font-weight:700;font-size:15px;margin-bottom:8px;line-height:1.4;">
                    <?= h($preview['title']) ?>
                </div>
                <?php if ($preview['guest_name']): ?>
                <div style="color:var(--maroon);font-size:13px;margin-bottom:6px;">
                    <i class="fas fa-microphone"></i>
                    Guest: <strong><?= h($preview['guest_name']) ?></strong>
                </div>
                <?php endif; ?>
                <div style="font-size:13px;color:var(--text-muted);">
                    <i class="fab fa-youtube" style="color:#ff0000;"></i>
                    Video ID: <code><?= h($preview['video_id']) ?></code>
                </div>
                <?php if ($preview['views'] > 0): ?>
                <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">
                    <i class="fas fa-eye"></i> <?= number_format($preview['views']) ?> views
                </div>
                <?php endif; ?>
                <div style="margin-top:14px;">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="youtube_url" value="<?= h($lastUrl) ?>" />
                        <button type="submit" name="action" value="save" class="btn btn-primary btn-sm">
                            <i class="fas fa-check"></i> Confirm &amp; Save
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
