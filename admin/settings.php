<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $showViews   = isset($_POST['show_article_views']) ? '1' : '0';
    $articleFont = in_array($_POST['article_font'] ?? '', ['inter','lora'], true)
                   ? $_POST['article_font'] : 'inter';
    $streamStatus = in_array($_POST['stream_status'] ?? '', ['live','offline'], true)
                    ? $_POST['stream_status'] : 'offline';

    set_setting('show_article_views', $showViews);
    set_setting('article_font',       $articleFont);
    set_setting('stream_status',      $streamStatus);

    flash('success', 'Settings saved.');
    redirect(BASE_PATH . '/admin/settings.php');
}

$showViews    = get_setting('show_article_views', '1');
$articleFont  = get_setting('article_font', 'inter');
$streamStatus = get_setting('stream_status', 'offline');

$pageTitle      = 'Site Settings';
$activeAdminNav = 'settings';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="card" style="max-width:640px;">
    <div class="card-head"><h2>Site Settings</h2></div>

    <form method="post">
        <?= csrf_field() ?>

        <!-- ── Article Display ── -->
        <div style="margin-bottom:28px;">
            <h3 style="font-size:13px;font-weight:800;text-transform:uppercase;
                       letter-spacing:.7px;color:var(--text-muted);margin-bottom:16px;
                       border-bottom:1px solid var(--border);padding-bottom:10px;">
                Article Display
            </h3>

            <div class="form-group">
                <label class="toggle-switch" style="font-size:14px;font-weight:600;">
                    <input type="checkbox" name="show_article_views" value="1"
                           <?= $showViews !== '0' ? 'checked' : '' ?> />
                    Show view count on articles
                </label>
                <div class="form-hint" style="margin-top:6px;">
                    When disabled, the "X Views" line is hidden from all published articles.
                    View counts still increment internally.
                </div>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label for="article_font" style="font-weight:600;">Default Article Body Font</label>
                <select class="form-control" id="article_font" name="article_font" style="max-width:320px;">
                    <option value="inter" <?= $articleFont === 'inter' ? 'selected' : '' ?>>
                        Inter — Clean &amp; modern sans-serif
                    </option>
                    <option value="lora" <?= $articleFont === 'lora' ? 'selected' : '' ?>>
                        Lora — Elegant serif for long reads
                    </option>
                </select>
                <div class="form-hint">
                    Site-wide default. Individual articles can override this in their publish settings.
                </div>
            </div>
        </div>

        <!-- ── Stream Status ── -->
        <div style="margin-bottom:28px;">
            <h3 style="font-size:13px;font-weight:800;text-transform:uppercase;
                       letter-spacing:.7px;color:var(--text-muted);margin-bottom:16px;
                       border-bottom:1px solid var(--border);padding-bottom:10px;">
                Live Stream
            </h3>

            <div class="form-group">
                <label for="stream_status" style="font-weight:600;">Stream Status</label>
                <select class="form-control" id="stream_status" name="stream_status" style="max-width:200px;">
                    <option value="offline" <?= $streamStatus === 'offline' ? 'selected' : '' ?>>Offline</option>
                    <option value="live"    <?= $streamStatus === 'live'    ? 'selected' : '' ?>>Live Now</option>
                </select>
                <div class="form-hint">
                    Controls the "Live Now" badge in the nav and hero sections.
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-check"></i> Save Settings
        </button>
    </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
