<?php
/**
 * Streams & Live → "Channels" tab: the radio stations and extra video channels viewers can pick on the app's Live tab.
 * Included by admin/streams.php.
 */
$typeFilter = in_array($_GET['type'] ?? '', ['youtube', 'radio'], true) ? $_GET['type'] : '';
$allStreams = db()->query('SELECT * FROM streams ORDER BY type ASC, sort_order ASC, id ASC')->fetchAll();

$byType = ['youtube' => 0, 'radio' => 0];
foreach ($allStreams as $s) {
    $byType[$s['type']] = ($byType[$s['type']] ?? 0) + 1;
}
$streams = array_values(array_filter($allStreams, fn($s) => $typeFilter === '' || $s['type'] === $typeFilter));

// Position of each channel inside its own type (decides which up/down arrows work).
$position = [];
$seen = ['youtube' => 0, 'radio' => 0];
foreach ($allStreams as $s) {
    $position[$s['id']] = ++$seen[$s['type']];
}

$editStream = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM streams WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editStream = $stmt->fetch() ?: null;
}
$formType = old('type', $editStream['type'] ?? 'youtube');

// Q FM — listen live. "Check now" asks the station fresh; otherwise show the last answer without touching the network.
require_once __DIR__ . '/../../includes/radio-functions.php';
$radioLink = radio_source_link();
$radioInfo = !empty($_GET['check']) ? radio_resolve(true) : radio_cached();
$radioChecked = !empty($_GET['check']);
?>

<div class="two-col">
  <div class="stack">
    <!-- ───────── add / edit ───────── -->
    <div class="card" id="channelForm">
        <div class="card-head"><h2><?= $editStream ? 'Edit channel' : 'Add a channel' ?></h2></div>
        <div class="hint-box">
            Channels appear on the app’s <strong>Live</strong> tab: radio stations under “Radio”, videos under “More videos”.
            Viewers tap one to play it instead of the main broadcast.
        </div>
        <form method="post" action="<?= h(streams_url('channels')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_stream" />
            <input type="hidden" name="id" value="<?= (int) ($editStream['id'] ?? 0) ?>" />

            <div class="form-group">
                <label for="c_type">Type</label>
                <select class="form-control" id="c_type" name="type">
                    <option value="youtube" <?= $formType === 'youtube' ? 'selected' : '' ?>>Video (YouTube)</option>
                    <option value="radio" <?= $formType === 'radio' ? 'selected' : '' ?>>Radio (player or stream link)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="c_name">Name</label>
                <input class="form-control" type="text" id="c_name" name="name" required maxlength="100" value="<?= h(old('name', $editStream['name'] ?? '')) ?>" placeholder="e.g. Radio QFM 94.3" />
            </div>
            <div class="form-group">
                <label for="c_link">Link</label>
                <input class="form-control" type="text" id="c_link" name="url_or_id" required value="<?= h(old('url_or_id', $editStream['url_or_id'] ?? '')) ?>" placeholder="https://youtu.be/…  or  https://…/embed" />
                <div class="form-hint">YouTube video link for videos. For radio: the station’s player (embed) link, a direct stream link, or a pasted <code>&lt;iframe&gt;</code> embed code.</div>
            </div>
            <div class="form-group">
                <label for="c_icon">Icon <span class="muted" style="font-weight:400;">(optional, Font Awesome class)</span></label>
                <input class="form-control" type="text" id="c_icon" name="icon" maxlength="50" value="<?= h(old('icon', $editStream['icon'] ?? '')) ?>" placeholder="fas fa-broadcast-tower" />
            </div>
            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" name="is_default" value="1" <?= old('is_default', $editStream['is_default'] ?? '') ? 'checked' : '' ?> />
                    Make this the default <?= $formType === 'radio' ? 'radio station' : 'video channel' ?>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-<?= $editStream ? 'floppy-disk' : 'plus' ?>"></i> <?= $editStream ? 'Save changes' : 'Add channel' ?></button>
                <?php if ($editStream): ?><a href="<?= h(streams_url('channels')) ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ───────── Q FM listen live ───────── -->
    <div class="card" id="radioCard">
        <div class="card-head"><h2><i class="fas fa-headphones" style="color:var(--maroon);"></i> Q FM — listen live</h2></div>
        <div class="hint-box">
            Puts a small <strong>Listen to Q FM</strong> button in the app and on the website. Paste the station’s
            <strong>Radio Garden</strong> link (open the station on radio.garden and copy the address) — the stream and the station’s details are then read live from it.
            A radio server player link or a direct stream address works too.
        </div>
        <form method="post" action="<?= h(streams_url('channels')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="radio_save" />
            <div class="form-group">
                <label for="r_url">Radio Garden link</label>
                <input class="form-control" type="text" id="r_url" name="qfm_radio_url" value="<?= h(old('qfm_radio_url', (string) get_setting('qfm_radio_url', ''))) ?>" placeholder="https://radio.garden/listen/…" />
                <div class="form-hint">Leave empty to use the first <em>Radio</em> channel below.</div>
            </div>
            <div class="form-group">
                <label for="r_name">Button name <span class="muted" style="font-weight:400;">(optional)</span></label>
                <input class="form-control" type="text" id="r_name" name="qfm_radio_name" maxlength="60" value="<?= h(old('qfm_radio_name', (string) get_setting('qfm_radio_name', ''))) ?>" placeholder="Q FM 94.3" />
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button>
                <a class="btn btn-outline" href="<?= h(streams_url('channels', ['check' => 1])) ?>#radioCard"><i class="fas fa-rotate"></i> Check now</a>
            </div>
        </form>

        <div class="radio-result">
            <?php if (!$radioInfo): ?>
                <span class="muted"><?= $radioLink === '' ? 'No radio is set up yet.' : 'Not checked yet — press “Check now”.' ?></span>
            <?php elseif (empty($radioInfo['enabled']) || empty($radioInfo['stream_url'])): ?>
                <span class="bad"><i class="fas fa-circle-xmark"></i> Could not find a stream.</span> <?= h($radioInfo['error'] ?? '') ?>
            <?php else: ?>
                <span class="ok"><i class="fas fa-circle-check"></i> Ready</span> · <strong><?= h($radioInfo['name']) ?></strong><?= $radioInfo['place'] ? ' · ' . h($radioInfo['place']) : '' ?><?= $radioInfo['frequency'] ? ' · ' . h($radioInfo['frequency']) : '' ?>
                <?php if (!empty($radioInfo['now_playing'])): ?><br />Now playing: <strong><?= h(trim(($radioInfo['now_playing']['artist'] ?? '') . ' — ' . ($radioInfo['now_playing']['title'] ?? ''), ' —')) ?></strong><?php endif; ?>
                <?php if ($radioInfo['listeners'] !== null): ?><br />Listening on the station now: <?= (int) $radioInfo['listeners'] ?><?php endif; ?>
                <br />Source: <?= h(['radiogarden' => 'Radio Garden', 'azuracast' => 'the station’s own radio server', 'direct' => 'a direct stream'][$radioInfo['source']] ?? $radioInfo['source']) ?>
                <br />Stream: <code><?= h($radioInfo['stream_url']) ?></code>
                <?php if (!empty($radioInfo['error'])): ?><br /><span class="muted"><i class="fas fa-circle-info"></i> <?= h($radioInfo['error']) ?></span><?php endif; ?>
                <div style="margin-top:10px;"><audio controls preload="none" src="<?= h($radioInfo['stream_url']) ?>" style="width:100%;"></audio></div>
                <?php if (isset($radioInfo['_age'])): ?><div class="muted" style="font-size:12px;margin-top:6px;">Last checked <?= (int) $radioInfo['_age'] ?> s ago<?= $radioChecked ? '' : ' — press “Check now” for fresh details' ?>.</div><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
  </div>

    <!-- ───────── list ───────── -->
    <div class="card">
        <div class="card-head"><h2>All channels</h2><span class="muted" style="font-size:13px;"><?= count($streams) ?> shown</span></div>

        <div class="chip-row">
            <a class="chip <?= $typeFilter === '' ? 'active' : '' ?>" href="<?= h(streams_url('channels')) ?>">All (<?= count($allStreams) ?>)</a>
            <a class="chip <?= $typeFilter === 'youtube' ? 'active' : '' ?>" href="<?= h(streams_url('channels', ['type' => 'youtube'])) ?>"><i class="fab fa-youtube"></i> Videos (<?= $byType['youtube'] ?>)</a>
            <a class="chip <?= $typeFilter === 'radio' ? 'active' : '' ?>" href="<?= h(streams_url('channels', ['type' => 'radio'])) ?>"><i class="fas fa-broadcast-tower"></i> Radio (<?= $byType['radio'] ?>)</a>
        </div>

        <?php if (!$streams): ?>
            <div class="empty-note"><i class="fas fa-satellite-dish" style="font-size:26px;display:block;margin-bottom:8px;opacity:.5;"></i>No channels here yet — add one on the left.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th></th><th>Channel</th><th>Order</th><th>Default</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($streams as $s):
                $isVideo = $s['type'] === 'youtube';
                $videoId = $isVideo ? youtube_video_id($s['url_or_id']) : '';
                $preview = $isVideo ? youtube_embed_url($s['url_or_id'], true) : $s['url_or_id'];
                $first = $position[$s['id']] === 1;
                $last = $position[$s['id']] === $byType[$s['type']];
                $keep = ['type_filter' => $typeFilter];
            ?>
                <tr style="<?= $s['is_active'] ? '' : 'opacity:.55;' ?>">
                    <td>
                        <?php if ($isVideo && $videoId): ?>
                            <img class="thumb-sm" src="<?= h(get_youtube_thumbnail($videoId, 'mqdefault')) ?>" alt="" loading="lazy" />
                        <?php else: ?>
                            <div class="thumb-icon"><i class="<?= h($s['icon'] ?: 'fas fa-broadcast-tower') ?>"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:700;"><?= h($s['name']) ?></div>
                        <div class="muted" style="font-size:12.5px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <span class="badge-src"><i class="<?= $isVideo ? 'fab fa-youtube' : 'fas fa-broadcast-tower' ?>"></i> <?= $isVideo ? 'Video' : 'Radio' ?></span>
                            <a class="link-ellipsis" href="<?= h($isVideo ? get_youtube_watch_url($videoId) : $s['url_or_id']) ?>" target="_blank" rel="noopener noreferrer" title="<?= h($s['url_or_id']) ?>"><?= h(preg_replace('~^https?://(www\.)?~', '', $s['url_or_id'])) ?></a>
                        </div>
                    </td>
                    <td>
                        <div class="order-btns">
                            <?= action_form('stream_move', ['id' => $s['id'], 'direction' => 'up'] + $keep, '<i class="fas fa-chevron-up"></i>', ['class' => '', 'tab' => 'channels', 'title' => 'Move up', 'disabled' => $first]) ?>
                            <?= action_form('stream_move', ['id' => $s['id'], 'direction' => 'down'] + $keep, '<i class="fas fa-chevron-down"></i>', ['class' => '', 'tab' => 'channels', 'title' => 'Move down', 'disabled' => $last]) ?>
                        </div>
                    </td>
                    <td><?= action_form('stream_default', ['id' => $s['id']] + $keep, '<i class="fas fa-star"></i>', ['class' => 'star-btn' . ($s['is_default'] ? ' on' : ''), 'tab' => 'channels', 'title' => $s['is_default'] ? 'Default — click to clear' : 'Make default']) ?></td>
                    <td><?= action_form('toggle_active', ['id' => $s['id']] + $keep, $s['is_active'] ? 'Active' : 'Hidden', ['class' => 'status-pill ' . ($s['is_active'] ? 'status-published' : 'status-editor')  . ' pill-btn', 'tab' => 'channels', 'title' => $s['is_active'] ? 'Visible in the app — click to hide' : 'Hidden — click to show']) ?></td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="icon-btn" title="Preview" data-preview="<?= h($preview) ?>" data-title="<?= h($s['name']) ?>"><i class="fas fa-eye"></i></button>
                            <a class="icon-btn" title="Edit" href="<?= h(streams_url('channels', ['edit' => $s['id']])) ?>#channelForm"><i class="fas fa-pen"></i></a>
                            <form method="post" action="<?= h(BASE_PATH) ?>/admin/stream-delete.php" data-confirm="Delete channel “<?= h($s['name']) ?>”?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>" />
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
</div>
