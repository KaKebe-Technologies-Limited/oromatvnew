<?php
/**
 * Streams & Live — the control centre for everything that plays on Oroma TV.
 *
 *   Live Control      what is on air right now, scheduled live programmes, and the automation switches
 *   Channels          the radio stations and extra video channels viewers can pick (shown on the Live tab of the app)
 *   Off-air Playlist  videos that play on a loop whenever nothing is live
 *
 * The tab screens live in admin/includes/streams-{live,channels,playlist}.php; the buttons' logic is in streams-actions.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/live-functions.php';
require_login();
require_once __DIR__ . '/includes/streams-helpers.php';
require_once __DIR__ . '/includes/streams-actions.php';

$tab = (string) ($_GET['tab'] ?? 'live');
if (!in_array($tab, ['live', 'channels', 'playlist'], true)) {
    $tab = 'live';
}
$schemaReady = live_schema_ready();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $needsLiveTables = !in_array($_POST['action'] ?? '', ['save_stream', 'toggle_active', 'stream_default', 'stream_move', 'toggle_status'], true);
    if ($needsLiveTables && !$schemaReady) {
        flash('error', 'The live schedule tables are missing and could not be created automatically. Import database/live-schedule.sql in phpMyAdmin, then try again.');
        redirect(streams_url($tab));
    }
    $result = streams_handle_post(); // redirects when it succeeds; returns the form mistakes otherwise
    $tab = $result['tab'];
    $errors = $result['errors'];
}

$counts = [
    'channels' => (int) db()->query('SELECT COUNT(*) AS c FROM streams')->fetch()['c'],
    'playlist' => $schemaReady ? (int) db()->query('SELECT COUNT(*) AS c FROM offair_items')->fetch()['c'] : 0,
    'upcoming' => $schemaReady ? (int) db()->query("SELECT COUNT(*) AS c FROM live_programs WHERE starts_at > '" . date('Y-m-d H:i:s') . "' AND ended_at IS NULL")->fetch()['c'] : 0,
];

$pageTitle = 'Streams & Live';
$activeAdminNav = 'streams';
require __DIR__ . '/includes/admin-header.php';
?>

<?php if (!$schemaReady): ?>
    <div class="alert" style="margin-bottom:18px;">
        <strong>The live schedule tables are not set up yet.</strong>
        They are normally created automatically; this database user is not allowed to. Import
        <code>database/live-schedule.sql</code> in phpMyAdmin and reload this page. Channels still work meanwhile.
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert" style="margin-bottom:18px;">
        <?php foreach ($errors as $error): ?><div><i class="fas fa-circle-exclamation"></i> <?= h($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="tabs-bar" role="tablist">
    <a class="tab-link <?= $tab === 'live' ? 'active' : '' ?>" href="<?= h(streams_url('live')) ?>"><i class="fas fa-tower-broadcast"></i> Live Control
        <?php if ($counts['upcoming']): ?><span class="count"><?= $counts['upcoming'] ?> upcoming</span><?php endif; ?></a>
    <a class="tab-link <?= $tab === 'channels' ? 'active' : '' ?>" href="<?= h(streams_url('channels')) ?>"><i class="fas fa-satellite-dish"></i> Channels <span class="count"><?= $counts['channels'] ?></span></a>
    <a class="tab-link <?= $tab === 'playlist' ? 'active' : '' ?>" href="<?= h(streams_url('playlist')) ?>"><i class="fas fa-list-ul"></i> Off-air Playlist <span class="count"><?= $counts['playlist'] ?></span></a>
</div>

<?php require __DIR__ . '/includes/streams-' . $tab . '.php'; ?>

<!-- Preview window: any button with data-preview="<player address>" opens it. -->
<div class="preview-backdrop" id="previewBackdrop" aria-hidden="true">
    <div class="preview-box" role="dialog" aria-modal="true" aria-label="Preview">
        <div class="bar"><span id="previewTitle">Preview</span><button type="button" id="previewClose" aria-label="Close preview"><i class="fas fa-xmark"></i></button></div>
        <div class="frame" id="previewFrame"></div>
    </div>
</div>
<script>
(function () {
    var backdrop = document.getElementById('previewBackdrop');
    var frame = document.getElementById('previewFrame');
    var title = document.getElementById('previewTitle');
    function close() { backdrop.classList.remove('open'); frame.innerHTML = ''; }
    document.addEventListener('click', function (e) {
        var open = e.target.closest('[data-preview]');
        if (open) {
            e.preventDefault();
            title.textContent = open.getAttribute('data-title') || 'Preview';
            frame.innerHTML = '';
            var iframe = document.createElement('iframe');
            iframe.src = open.getAttribute('data-preview');
            iframe.allow = 'autoplay; encrypted-media; fullscreen';
            iframe.setAttribute('allowfullscreen', '');
            frame.appendChild(iframe);
            backdrop.classList.add('open');
            return;
        }
        if (e.target === backdrop || e.target.closest('#previewClose')) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
