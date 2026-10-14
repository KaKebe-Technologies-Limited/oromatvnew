<?php
/**
 * Streams & Live → "Off-air Playlist" tab: the videos that play, one after another on a loop, whenever nothing is live.
 * Included by admin/streams.php.
 */
$items = $schemaReady ? get_offair_items(false) : [];
$activeCount = count(array_filter($items, fn($i) => $i['is_active']));
$offairOn = live_flag('offair_enabled', true);

// Previous shows that are not in the playlist yet — one click adds them.
$inPlaylist = array_column($items, 'video_id');
$suggestions = array_values(array_filter(get_previous_shows(60), fn($s) => !in_array($s['video_id'], $inPlaylist, true)));
$suggestions = array_slice($suggestions, 0, 8);
?>

<div class="two-col">
    <div class="stack">
        <!-- ───────── add by link ───────── -->
        <div class="card">
            <div class="card-head"><h2>Add a video</h2></div>
            <div class="hint-box">
                Paste a YouTube link and press Add. We read the title for you. These videos play in the app whenever Oroma TV is not live,
                and the moment a scheduled programme starts, the app switches to it.
            </div>
            <form method="post" action="<?= h(streams_url('playlist')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="offair_add" />
                <div class="form-group">
                    <label for="o_link">YouTube link</label>
                    <input class="form-control" type="text" id="o_link" name="link" required value="<?= h(old('link')) ?>" placeholder="https://youtu.be/…" />
                </div>
                <div class="form-group">
                    <label for="o_title">Title <span class="muted" style="font-weight:400;">(optional — filled in automatically)</span></label>
                    <input class="form-control" type="text" id="o_title" name="title" maxlength="255" value="<?= h(old('title')) ?>" />
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add to playlist</button>
            </form>
        </div>

        <!-- ───────── quick add ───────── -->
        <div class="card">
            <div class="card-head"><h2>Quick add from Previous Shows</h2></div>
            <?php if (!$suggestions): ?>
                <div class="empty-note">Every previous show is already in the playlist (or there are none yet).</div>
            <?php else: ?>
                <div class="prog-list">
                <?php foreach ($suggestions as $show): ?>
                    <div class="prog-row">
                        <img class="thumb-sm" src="<?= h($show['thumbnail_url'] ?: get_youtube_thumbnail($show['video_id'], 'mqdefault')) ?>" alt="" loading="lazy" />
                        <div class="prog-body"><div class="title" title="<?= h($show['title']) ?>"><?= h($show['title']) ?></div></div>
                        <?= action_form('offair_add_show', ['show_id' => $show['id']], '<i class="fas fa-plus"></i> Add', ['class' => 'btn btn-outline btn-sm', 'tab' => 'playlist']) ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ───────── the playlist ───────── -->
    <div class="card">
        <div class="card-head"><h2>Playlist</h2><span class="muted" style="font-size:13px;"><?= $activeCount ?> of <?= count($items) ?> active · plays in this order, on a loop</span></div>

        <?php if (!$offairOn): ?>
            <div class="alert" style="margin-bottom:14px;">The off-air playlist is switched off, so the app is not playing it. Turn it back on under <a href="<?= h(streams_url('live')) ?>" style="text-decoration:underline;">Live Control → Automation</a>.</div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="empty-note"><i class="fas fa-list-ul" style="font-size:26px;display:block;margin-bottom:8px;opacity:.5;"></i>The playlist is empty. Add a video on the left — or a few, so the app always has something to play.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>#</th><th></th><th>Video</th><th>Order</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $item): ?>
                <tr style="<?= $item['is_active'] ? '' : 'opacity:.55;' ?>">
                    <td style="font-weight:700;color:var(--text-muted);"><?= $index + 1 ?></td>
                    <td><img class="thumb-sm" src="<?= h($item['thumbnail_url'] ?: get_youtube_thumbnail($item['video_id'], 'mqdefault')) ?>" alt="" loading="lazy" /></td>
                    <td>
                        <div style="font-weight:700;max-width:340px;"><?= h($item['title']) ?></div>
                        <a class="link-ellipsis" href="<?= h(get_youtube_watch_url($item['video_id'])) ?>" target="_blank" rel="noopener noreferrer" style="font-size:12.5px;">youtube.com/watch?v=<?= h($item['video_id']) ?></a>
                    </td>
                    <td>
                        <div class="order-btns">
                            <?= action_form('offair_move', ['id' => $item['id'], 'direction' => 'up'], '<i class="fas fa-chevron-up"></i>', ['class' => '', 'tab' => 'playlist', 'title' => 'Move up', 'disabled' => $index === 0]) ?>
                            <?= action_form('offair_move', ['id' => $item['id'], 'direction' => 'down'], '<i class="fas fa-chevron-down"></i>', ['class' => '', 'tab' => 'playlist', 'title' => 'Move down', 'disabled' => $index === count($items) - 1]) ?>
                        </div>
                    </td>
                    <td><?= action_form('offair_toggle', ['id' => $item['id']], $item['is_active'] ? 'Playing' : 'Paused', ['class' => 'status-pill ' . ($item['is_active'] ? 'status-published' : 'status-editor')  . ' pill-btn', 'tab' => 'playlist', 'title' => $item['is_active'] ? 'In the rotation — click to pause it' : 'Not in the rotation — click to include it']) ?></td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="icon-btn" title="Preview" data-preview="<?= h(youtube_embed_url($item['video_id'], true)) ?>" data-title="<?= h($item['title']) ?>"><i class="fas fa-eye"></i></button>
                            <?= action_form('offair_delete', ['id' => $item['id']], '<i class="fas fa-trash"></i>', ['class' => 'icon-btn', 'tab' => 'playlist', 'title' => 'Remove', 'confirm' => 'Remove “' . $item['title'] . '” from the playlist?']) ?>
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
