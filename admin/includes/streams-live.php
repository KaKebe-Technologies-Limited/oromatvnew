<?php
/**
 * Streams & Live → "Live Control" tab: what is on air and who is watching, the 48-hour schedule with its gaps, the
 * scheduling form (programmes, movie premieres, repeats), the programme list, and the automation switches.
 * Included by admin/streams.php (which has already checked the login and run any button that was pressed).
 */
$resolved = resolve_live();
$source = $resolved['source'];
$program = $resolved['program'];
$next = $resolved['next'];
$main = main_channel(true);
$lists = $schemaReady ? list_live_programs() : ['live' => [], 'upcoming' => [], 'recent' => []];
$overview = $schemaReady ? schedule_overview(48) : ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d H:i:s', time() + 172800), 'blocks' => [], 'gaps' => []];

$scheduleOn = live_flag('live_schedule_enabled', true);
$offairOn = live_flag('offair_enabled', true);
$shuffleOn = live_flag('offair_shuffle', false);
$useShows = live_flag('offair_use_shows', true);
$autoArchive = live_flag('live_auto_archive', true);
$playlistActive = $schemaReady ? (int) db()->query('SELECT COUNT(*) AS c FROM offair_items WHERE is_active = 1')->fetch()['c'] : 0;
$showsAvailable = (int) db()->query("SELECT COUNT(*) AS c FROM previous_shows WHERE is_active = 1 AND video_id <> ''")->fetch()['c'];
$activeChannels = (int) db()->query('SELECT COUNT(*) AS c FROM streams WHERE is_active = 1')->fetch()['c'];
$counts = live_viewer_counts();
$scope = live_scope($resolved);

// What fills the screen when nothing is scheduled — and whether that is "nothing at all".
if (!$offairOn) {
    $fillWith = ['none', 'an empty “not live” screen (the off-air playlist is switched off)'];
} elseif ($playlistActive) {
    $fillWith = ['playlist', 'the off-air playlist (' . $playlistActive . ' video' . ($playlistActive === 1 ? '' : 's') . ')'];
} elseif ($useShows && $showsAvailable) {
    $fillWith = ['shows', 'previous shows (the off-air playlist is empty)'];
} else {
    $fillWith = ['none', 'an EMPTY screen — add videos to the off-air playlist'];
}

$editing = null;
if ($schemaReady && !empty($_GET['edit'])) {
    $editing = get_live_program((int) $_GET['edit']);
}

// "Runs for" preselection: the saved length if it is one of the presets, else "keep the current length".
$durationValue = (string) ($_GET['len'] ?? '60');
if ($editing) {
    $durationValue = 'open';
    if ($editing['ends_at']) {
        $minutes = (int) round((strtotime($editing['ends_at']) - strtotime($editing['starts_at'])) / 60);
        $durationValue = array_key_exists($minutes, duration_options()) ? (string) $minutes : 'keep';
    }
}
$durationValue = (string) old('duration', $durationValue);
$defaultStart = !empty($_GET['start']) && DateTime::createFromFormat('Y-m-d\TH:i', (string) $_GET['start'])
    ? (string) $_GET['start']
    : date('Y-m-d\TH:i', (int) (ceil(time() / 900) * 900)); // the next quarter hour
$startValue = old('starts_at', $editing ? date('Y-m-d\TH:i', strtotime($editing['starts_at'])) : $defaultStart);
$startMode = old('start_mode', 'schedule');
$formKind = old('kind', $editing['kind'] ?? 'show');
$formMode = old('mode', $editing['mode'] ?? 'auto');
$formRepeat = old('repeat', 'none');
$mainPreview = $main['embed_url'];

// Timeline geometry.
$tFrom = strtotime($overview['from']);
$tTotal = max(1, strtotime($overview['to']) - $tFrom);
$pct = fn(int $ts) => max(0, min(100, ($ts - $tFrom) / $tTotal * 100));
$kindColour = ['show' => '#800000', 'movie' => '#b8860b', 'event' => '#2b3a67'];
?>

<!-- ───────── status banner ───────── -->
<?php if ($source === 'program'): ?>
    <div class="status-hero is-live">
        <div>
            <span class="eyebrow"><span class="dot"></span> On air now · <?= h(live_kind_label($program['kind'])) ?></span>
            <h2><?= h($program['title']) ?></h2>
            <p>Started <?= h(until_text($program['starts_at'])) ?> (<?= h(fmt_when($program['starts_at'])) ?>) ·
                <?= $program['ends_at'] ? 'ends ' . h(fmt_when($program['ends_at'])) . ' (' . h(until_text($program['ends_at'])) . ')' : 'runs until you end it' ?>.
                <?= $program['mode'] === 'premiere'
                    ? 'Everyone watches this <strong>in sync</strong>: whoever opens the app or the website now joins at ' . h(gmdate('i:s', live_program_offset($program))) . ' into it.'
                    : 'This is a real-time stream, so everyone is at the live moment.' ?></p>
        </div>
        <div class="hero-actions">
            <?= share_menu($program + ['state' => 'live'], true) ?>
            <button type="button" class="btn btn-ghost-light btn-sm" data-preview="<?= h(live_program_embed_url($program, true, live_program_offset($program))) ?>" data-title="<?= h($program['title']) ?>"><i class="fas fa-eye"></i> Preview</button>
            <?= action_form('program_end', ['id' => $program['id']], '<i class="fas fa-stop"></i> End now', ['class' => 'btn btn-light btn-sm', 'confirm' => 'End “' . $program['title'] . '” now? Viewers will go back to the normal screen.']) ?>
        </div>
    </div>
<?php elseif ($source === 'main'): ?>
    <div class="status-hero is-main">
        <div>
            <span class="eyebrow"><span class="dot"></span> On air — main channel</span>
            <h2>The main channel is switched on</h2>
            <p>Viewers see the main channel (<?= $main['provider'] === 'youtube' ? 'YouTube' : 'embedded player' ?>). Scheduled programmes still take over when their time comes.
                While this switch is on, the off-air playlist does not play — switch it off to let the playlist run between programmes.</p>
        </div>
        <div class="hero-actions">
            <button type="button" class="btn btn-ghost-light btn-sm" data-preview="<?= h($mainPreview) ?>" data-title="Main channel"><i class="fas fa-eye"></i> Preview</button>
            <?= action_form('toggle_status', [], '<i class="fas fa-power-off"></i> Switch off', ['class' => 'btn btn-light btn-sm']) ?>
        </div>
    </div>
<?php else: ?>
    <div class="status-hero is-off">
        <div>
            <span class="eyebrow"><span class="dot"></span> Off air</span>
            <h2><?= $next ? 'Next live: ' . h($next['title']) : 'Nothing is live right now' ?></h2>
            <p>
                <?php if ($next): $w = when_parts($next['starts_at']); ?>
                    Goes on air by itself <strong><?= h(strtolower($w['day'])) ?> at <?= h($w['time']) ?></strong> (<?= h(until_text($next['starts_at'])) ?>).
                <?php else: ?>
                    Schedule a programme below and it will go on air automatically at its start time.
                <?php endif; ?>
                <?php if (!$scheduleOn): ?><br /><strong>Scheduling is paused</strong> (see Automation below), so nothing will start by itself.<?php endif; ?>
                <br />Meanwhile viewers see <?= h($fillWith[1]) ?>.
            </p>
        </div>
        <div class="hero-actions">
            <?php if ($next): ?>
                <?= share_menu($next + ['state' => 'upcoming'], true) ?>
                <?= action_form('program_golive', ['id' => $next['id']], '<i class="fas fa-bolt"></i> Go live now', ['class' => 'btn btn-light btn-sm', 'confirm' => 'Put “' . $next['title'] . '” on air right now?']) ?>
            <?php endif; ?>
            <?= action_form('toggle_status', [], '<i class="fas fa-power-off"></i> Main channel on', ['class' => 'btn btn-ghost-light btn-sm', 'title' => 'Put the main channel on air without scheduling anything']) ?>
        </div>
    </div>
<?php endif; ?>

<!-- ───────── viewers + quick numbers ───────── -->
<div class="mini-stats">
    <div class="mini-stat viewers">
        <div class="v"><span class="pulse-dot"></span> <span id="vNow"><?= $counts['now'] ?></span></div>
        <div class="l">Watching live now · <span id="vSplit"><?= $counts['app'] ?> app · <?= $counts['web'] ?> website</span></div>
        <div class="l" id="vExtra" style="margin-top:4px;"><?= $program ? 'Peak ' . (int) $program['peak_viewers'] . ' · ' . live_unique_viewers($scope) . ' different devices' : ($counts['offair'] ? $counts['offair'] . ' on the off-air playlist' : 'Counts devices that checked in the last minute') ?></div>
    </div>
    <div class="mini-stat"><div class="v"><?= count($lists['upcoming']) ?></div><div class="l">Upcoming programmes</div></div>
    <div class="mini-stat"><div class="v" style="font-size:15px;line-height:1.3;"><?= $fillWith[0] === 'none' ? '<span style="color:#c0392b;">Empty!</span>' : ($fillWith[0] === 'shows' ? 'Previous shows' : 'Playlist') ?></div><div class="l">Fills time between programmes</div></div>
    <div class="mini-stat"><div class="v" style="font-size:16px;"><?= h(date('D j M, H:i')) ?></div><div class="l">Site time now (<?= h(date_default_timezone_get()) ?>)</div></div>
</div>

<!-- ───────── the next 48 hours ───────── -->
<div class="card" style="margin-bottom:22px;">
    <div class="card-head"><h2>Schedule — the next 48 hours</h2>
        <span class="muted" style="font-size:13px;"><?= count($overview['gaps']) ? '<i class="fas fa-triangle-exclamation" style="color:#c0872b;"></i> ' . count($overview['gaps']) . ' gap' . (count($overview['gaps']) === 1 ? '' : 's') . ' to fill' : '<i class="fas fa-circle-check" style="color:#2e7d32;"></i> No gaps — always something on' ?></span>
    </div>
    <div class="timeline" aria-label="Schedule for the next 48 hours">
        <div class="tl-ticks">
            <?php for ($t = (int) (ceil($tFrom / 21600) * 21600); $t < $tFrom + $tTotal; $t += 21600): ?>
                <span style="left:<?= round($pct($t), 2) ?>%;"><?= (int) date('G', $t) === 0 ? h(date('D j', $t)) : h(date('H:i', $t)) ?></span>
            <?php endfor; ?>
        </div>
        <div class="tl-bar">
            <?php foreach ($overview['gaps'] as $g): ?>
                <div class="tl-gap" style="left:<?= round($pct(strtotime($g['from'])), 2) ?>%;width:<?= round($pct(strtotime($g['to'])) - $pct(strtotime($g['from'])), 2) ?>%;" title="Gap: <?= h(fmt_when($g['from'])) ?> – <?= h(date('H:i', strtotime($g['to']))) ?> (<?= h(duration_text($g['minutes'] * 60)) ?>)"></div>
            <?php endforeach; ?>
            <?php foreach ($overview['blocks'] as $b):
                $left = $pct(max(strtotime($b['starts_at']), $tFrom));
                $right = $pct(strtotime($b['planned_end'])); ?>
                <div class="tl-block <?= $b['state'] === 'live' ? 'is-live' : '' ?>" style="left:<?= round($left, 2) ?>%;width:<?= round(max(0.6, $right - $left), 2) ?>%;background:<?= $kindColour[$b['kind']] ?? '#800000' ?>;"
                     title="<?= h($b['title']) ?> · <?= h(fmt_when($b['starts_at'])) ?> – <?= h(date('H:i', strtotime($b['planned_end']))) ?>"><span><?= h($b['title']) ?></span></div>
            <?php endforeach; ?>
            <div class="tl-now" style="left:0;"><span>now</span></div>
        </div>
        <div class="tl-legend">
            <span><i style="background:#800000"></i> Programme</span><span><i style="background:#b8860b"></i> Movie premiere</span><span><i style="background:#2b3a67"></i> Special event</span><span><i class="hatch"></i> Gap (<?= h($fillWith[1]) ?>)</span>
        </div>
    </div>
    <?php if ($overview['gaps']): ?>
        <div class="gap-list">
            <?php foreach (array_slice($overview['gaps'], 0, 6) as $g):
                $fillStart = date('Y-m-d\TH:i', (int) (ceil(max(strtotime($g['from']), time()) / 300) * 300));
                $fillLen = $g['minutes'] >= 1440 ? 60 : max(30, min(360, (int) (round($g['minutes'] / 30) * 30)));
                ?>
                <div class="gap-row">
                    <i class="fas fa-<?= $fillWith[0] === 'none' ? 'triangle-exclamation' : 'hourglass-half' ?>" style="color:<?= $fillWith[0] === 'none' ? '#c0392b' : '#c0872b' ?>;"></i>
                    <div style="flex:1;min-width:0;">
                        <strong><?= h(fmt_when($g['from'])) ?> → <?= h(date('Y-m-d', strtotime($g['to'])) === date('Y-m-d', strtotime($g['from'])) ? date('H:i', strtotime($g['to'])) : fmt_when($g['to'])) ?></strong> <span class="muted">· <?= h(duration_text($g['minutes'] * 60)) ?> with nothing scheduled</span>
                        <div class="muted" style="font-size:12.5px;">Viewers will see <?= h($fillWith[1]) ?>.</div>
                    </div>
                    <a class="btn btn-outline btn-sm" href="<?= h(streams_url('live', ['start' => $fillStart, 'len' => $fillLen])) ?>#programForm"><i class="fas fa-plus"></i> Fill this gap</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="two-col">
    <!-- ───────── schedule form ───────── -->
    <div class="card" id="programForm">
        <div class="card-head"><h2><?= $editing ? 'Edit programme' : 'Schedule a programme or premiere' ?></h2></div>
        <div class="hint-box">
            Paste the stream link and choose when it starts. At that moment it goes on air in the app and on the website —
            nobody needs to press anything. Times are <strong><?= h(date_default_timezone_get()) ?></strong> time.
        </div>
        <form method="post" action="<?= h(streams_url('live')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="program_save" />
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>" />

            <div class="form-group">
                <label for="p_kind">What is it?</label>
                <select class="form-control" id="p_kind" name="kind">
                    <option value="show" <?= $formKind === 'show' ? 'selected' : '' ?>>Programme / show</option>
                    <option value="movie" <?= $formKind === 'movie' ? 'selected' : '' ?>>🎬 Movie premiere</option>
                    <option value="event" <?= $formKind === 'event' ? 'selected' : '' ?>>Special event</option>
                </select>
            </div>
            <div class="form-group">
                <label for="p_title">Title</label>
                <input class="form-control" type="text" id="p_title" name="title" required maxlength="200" value="<?= h(old('title', $editing['title'] ?? '')) ?>" placeholder="e.g. Yite Twolo Morning Talk-back" />
            </div>
            <div class="form-group">
                <label for="p_link">Stream link</label>
                <input class="form-control" type="text" id="p_link" name="stream_link" required value="<?= h(old('stream_link', $editing['stream_url'] ?? '')) ?>" placeholder="https://youtu.be/…  or  https://youtube.com/watch?v=…" />
                <div class="form-hint">A YouTube video link, an <code>https://</code> player link, or a pasted <code>&lt;iframe&gt;</code> embed code.</div>
            </div>
            <div class="form-group">
                <label for="p_mode">How should it play?</label>
                <select class="form-control" id="p_mode" name="mode">
                    <option value="auto" <?= $formMode === 'auto' ? 'selected' : '' ?>>Auto-detect (recommended)</option>
                    <option value="premiere" <?= $formMode === 'premiere' ? 'selected' : '' ?>>Pre-recorded — everyone watches in sync, from the start</option>
                    <option value="live" <?= $formMode === 'live' ? 'selected' : '' ?>>Real-time live stream</option>
                </select>
                <div class="form-hint"><strong>In sync</strong> means the programme runs on the clock like TV: if it started 20 minutes ago, someone who opens the app now joins at minute 20. We check YouTube to tell recordings from live streams.</div>
            </div>
            <div class="form-group">
                <label for="p_desc">Short description <span class="muted" style="font-weight:400;">(optional — shown on premiere cards)</span></label>
                <input class="form-control" type="text" id="p_desc" name="description" maxlength="500" value="<?= h(old('description', $editing['description'] ?? '')) ?>" placeholder="Who is on, what it is about" />
            </div>
            <div class="form-group" id="posterGroup">
                <label for="p_poster">Poster image link <span class="muted" style="font-weight:400;">(optional — defaults to the video's own picture)</span></label>
                <input class="form-control" type="text" id="p_poster" name="poster_url" maxlength="500" value="<?= h(old('poster_url', $editing['poster_url'] ?? '')) ?>" placeholder="https://…/poster.jpg" />
            </div>

            <div class="form-group">
                <label>When does it start?</label>
                <label class="toggle-switch" style="margin-right:18px;"><input type="radio" name="start_mode" value="schedule" <?= $startMode !== 'now' ? 'checked' : '' ?> /> At a set time</label>
                <label class="toggle-switch"><input type="radio" name="start_mode" value="now" <?= $startMode === 'now' ? 'checked' : '' ?> /> Go live right now</label>
            </div>
            <div class="form-row">
                <div class="form-group" id="startAtGroup">
                    <label for="p_start">Start time</label>
                    <input class="form-control" type="datetime-local" id="p_start" name="starts_at" value="<?= h($startValue) ?>" />
                </div>
                <div class="form-group">
                    <label for="p_len">Runs for</label>
                    <select class="form-control" id="p_len" name="duration">
                        <?php foreach (duration_options() as $value => $label): ?>
                            <option value="<?= h((string) $value) ?>" <?= (string) $value === $durationValue ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                        <?php if ($editing && $editing['ends_at']): ?>
                            <option value="keep" <?= $durationValue === 'keep' ? 'selected' : '' ?>>Keep current length</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <?php if (!$editing): ?>
            <div class="form-group">
                <label for="p_repeat">Repeat</label>
                <select class="form-control" id="p_repeat" name="repeat">
                    <option value="none" <?= $formRepeat === 'none' ? 'selected' : '' ?>>Does not repeat</option>
                    <option value="daily" <?= $formRepeat === 'daily' ? 'selected' : '' ?>>Every day (next 60 days)</option>
                    <option value="weekdays" <?= $formRepeat === 'weekdays' ? 'selected' : '' ?>>Weekdays, Monday to Friday (next 8 weeks)</option>
                    <option value="weekly" <?= $formRepeat === 'weekly' ? 'selected' : '' ?>>Every week, same day and time (next 26 weeks)</option>
                </select>
                <div class="form-hint">Repeats create all the showings at once, so the schedule is already full. You can delete the whole series later.</div>
            </div>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-check"></i> <?= $editing ? 'Save changes' : 'Save to schedule' ?></button>
                <?php if ($editing): ?><a href="<?= h(streams_url('live')) ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ───────── programme lists ───────── -->
    <div class="card">
        <div class="card-head"><h2>Programmes</h2><span class="muted" style="font-size:13px;"><?= count($lists['upcoming']) + count($lists['live']) ?> scheduled or on air</span></div>

        <?php
        $renderRow = function (array $p) use ($resolved): string {
            $state = $p['state'] ?? live_program_state($p, $resolved['now']);
            $w = when_parts($p['starts_at']);
            $end = $p['ends_at'] ? $p['ends_at'] : null;
            $thumb = live_program_thumbnail($p);
            ob_start(); ?>
            <div class="prog-row">
                <?php if ($thumb): ?><img class="thumb-sm" src="<?= h($thumb) ?>" alt="" loading="lazy" style="width:92px;height:56px;" /><?php endif; ?>
                <div class="prog-when"><div class="d"><?= h($w['day']) ?></div><div class="t"><?= h($w['time']) ?></div></div>
                <div class="prog-body">
                    <div class="title" title="<?= h($p['title']) ?>"><?= h($p['title']) ?></div>
                    <div class="meta">
                        <span class="status-pill pill-<?= h($state) ?>"><?= $state === 'live' ? '<i class="fas fa-circle"></i> On air' : ($state === 'upcoming' ? 'Starts ' . h(until_text($p['starts_at'])) : 'Ended') ?></span>
                        <?php if ($p['kind'] !== 'show'): ?><span class="badge-src"><?= $p['kind'] === 'movie' ? '🎬 Movie' : '★ Event' ?></span><?php endif; ?>
                        <span class="badge-src" title="<?= $p['mode'] === 'premiere' ? 'Everyone watches in sync from the clock' : 'Real-time stream' ?>"><i class="fas fa-<?= $p['mode'] === 'premiere' ? 'clock-rotate-left' : 'tower-broadcast' ?>"></i> <?= $p['mode'] === 'premiere' ? 'In sync' : 'Live stream' ?></span>
                        <?php if ($p['series_id']): ?><span class="badge-src" title="Part of a repeating series"><i class="fas fa-repeat"></i> Repeats</span><?php endif; ?>
                        <span><?= $end ? 'until ' . h(date('H:i', strtotime($end))) . ' (' . h(duration_text(strtotime($end) - strtotime($p['starts_at']))) . ')' : 'until ended' ?></span>
                        <?php if ($state !== 'upcoming' && (int) $p['peak_viewers'] > 0): ?><span><i class="fas fa-eye"></i> peak <?= (int) $p['peak_viewers'] ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="prog-actions">
                    <?php if ($state !== 'ended'): ?><?= share_menu($p + ['state' => $state]) ?><?php endif; ?>
                    <button type="button" class="icon-btn" title="Preview" data-preview="<?= h(live_program_embed_url($p, true)) ?>" data-title="<?= h($p['title']) ?>"><i class="fas fa-eye"></i></button>
                    <?php if ($state === 'live'): ?>
                        <?= action_form('program_end', ['id' => $p['id']], '<i class="fas fa-stop"></i> End', ['class' => 'btn btn-outline btn-sm', 'confirm' => 'End “' . $p['title'] . '” now?']) ?>
                    <?php else: ?>
                        <?= action_form('program_golive', ['id' => $p['id']], '<i class="fas fa-bolt"></i> ' . ($state === 'upcoming' ? 'Go live now' : 'Run again'), ['class' => 'btn btn-outline btn-sm', 'confirm' => 'Put “' . $p['title'] . '” on air right now?']) ?>
                    <?php endif; ?>
                    <?php if ($end): ?><a class="icon-btn" title="Schedule something right after this" href="<?= h(streams_url('live', ['start' => date('Y-m-d\TH:i', strtotime($end))])) ?>#programForm"><i class="fas fa-forward-step"></i></a><?php endif; ?>
                    <a class="icon-btn" title="Edit" href="<?= h(streams_url('live', ['edit' => $p['id']])) ?>#programForm"><i class="fas fa-pen"></i></a>
                    <?php if ($p['series_id']): ?>
                        <?= action_form('program_delete_series', ['id' => $p['id']], '<i class="fas fa-layer-group"></i>', ['class' => 'icon-btn', 'title' => 'Delete all upcoming showings of this series', 'confirm' => 'Delete ALL upcoming showings of “' . $p['title'] . '”?']) ?>
                    <?php endif; ?>
                    <?= action_form('program_delete', ['id' => $p['id']], '<i class="fas fa-trash"></i>', ['class' => 'icon-btn', 'title' => 'Delete', 'confirm' => 'Delete “' . $p['title'] . '”? This cannot be undone.']) ?>
                </div>
            </div>
            <?php return ob_get_clean();
        };
        ?>

        <?php if (!$lists['live'] && !$lists['upcoming'] && !$lists['recent']): ?>
            <div class="empty-note"><i class="fas fa-calendar-plus" style="font-size:26px;display:block;margin-bottom:8px;opacity:.5;"></i>No programmes yet. Schedule your first one on the left.</div>
        <?php endif; ?>

        <?php if ($lists['live']): ?>
            <div class="group-label"><i class="fas fa-circle" style="color:#2e7d32;font-size:8px;"></i> On air</div>
            <div class="prog-list"><?php foreach ($lists['live'] as $p) { echo $renderRow($p); } ?></div>
        <?php endif; ?>
        <?php if ($lists['upcoming']): ?>
            <div class="group-label" style="margin-top:14px;">Upcoming (<?= count($lists['upcoming']) ?>)</div>
            <div class="prog-list"><?php foreach (array_slice($lists['upcoming'], 0, 12) as $p) { echo $renderRow($p); } ?></div>
            <?php if (count($lists['upcoming']) > 12): ?><div class="empty-note" style="padding:10px;">…and <?= count($lists['upcoming']) - 12 ?> more later in the schedule.</div><?php endif; ?>
        <?php endif; ?>
        <?php if ($lists['recent']): ?>
            <div class="group-label" style="margin-top:14px;">Recent</div>
            <div class="prog-list"><?php foreach ($lists['recent'] as $p) { echo $renderRow($p); } ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- ───────── automation + main channel ───────── -->
<div class="card" style="margin-top:22px;max-width:980px;">
    <div class="card-head"><h2>Automation &amp; main channel</h2></div>
    <form method="post" action="<?= h(streams_url('live')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="live_settings" />

        <div class="switch-row">
            <div><div class="t">Go live automatically</div><div class="d">Scheduled programmes go on air at their start time and end at their end time. Turn this off to pause the schedule without deleting anything.</div></div>
            <input type="checkbox" name="live_schedule_enabled" value="1" <?= $scheduleOn ? 'checked' : '' ?> aria-label="Go live automatically" />
        </div>
        <div class="switch-row">
            <div><div class="t">Play the off-air playlist when nothing is live</div><div class="d">Between programmes the app and the website play your playlist videos, one after another on a loop, instead of an empty screen.</div></div>
            <input type="checkbox" name="offair_enabled" value="1" <?= $offairOn ? 'checked' : '' ?> aria-label="Play the off-air playlist" />
        </div>
        <div class="switch-row">
            <div><div class="t">If the playlist is empty, play previous shows</div><div class="d">A safety net so the screen is never blank: with no playlist videos, viewers get your Previous Shows instead.</div></div>
            <input type="checkbox" name="offair_use_shows" value="1" <?= $useShows ? 'checked' : '' ?> aria-label="Use previous shows when the playlist is empty" />
        </div>
        <div class="switch-row">
            <div><div class="t">Shuffle the off-air playlist</div><div class="d">Start from a random video each time instead of always from the top.</div></div>
            <input type="checkbox" name="offair_shuffle" value="1" <?= $shuffleOn ? 'checked' : '' ?> aria-label="Shuffle the off-air playlist" />
        </div>
        <div class="switch-row">
            <div><div class="t">Keep finished programmes in Previous Shows</div><div class="d">When a YouTube programme ends it is added to Previous Shows (movie premieres go under “Movies”), so people can watch it later.</div></div>
            <input type="checkbox" name="live_auto_archive" value="1" <?= $autoArchive ? 'checked' : '' ?> aria-label="Keep finished programmes in Previous Shows" />
        </div>

        <div class="form-group" style="margin-top:18px;">
            <label for="main_url">Main channel link <span class="muted" style="font-weight:400;">(used by the “Main channel on” switch)</span></label>
            <input class="form-control" type="text" id="main_url" name="main_url" value="<?= h(old('main_url', $main['custom'] ? $main['stream_url'] : '')) ?>" placeholder="Leave empty to use the default set in config.php" />
            <div class="form-hint">
                Currently using: <strong><?= $main['custom'] ? 'the link above' : 'the default player' ?></strong> (<?= $main['provider'] === 'youtube' ? 'YouTube' : 'embedded player' ?>).
                <a href="#" data-preview="<?= h($mainPreview) ?>" data-title="Main channel" style="color:var(--maroon);text-decoration:underline;">Preview it</a>.
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save settings</button>
    </form>
</div>

<script>
(function () {
    // The start-time box only matters for "At a set time".
    var radios = document.querySelectorAll('input[name="start_mode"]');
    var group = document.getElementById('startAtGroup');
    function sync() {
        var now = document.querySelector('input[name="start_mode"]:checked');
        if (group) group.style.opacity = now && now.value === 'now' ? '.4' : '1';
        var input = document.getElementById('p_start');
        if (input) input.disabled = !!(now && now.value === 'now');
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();

    // "Watching now" updates by itself every 15 seconds.
    var base = <?= json_encode(BASE_PATH . '/admin/live-stats.php') ?>;
    function refresh() {
        fetch(base, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                var set = function (id, text) { var el = document.getElementById(id); if (el) el.textContent = text; };
                set('vNow', d.now);
                set('vSplit', d.app + ' app · ' + d.web + ' website');
                if (d.peak !== null && d.unique !== null) set('vExtra', 'Peak ' + d.peak + ' · ' + d.unique + ' different devices');
                else if (d.offair) set('vExtra', d.offair + ' on the off-air playlist');
            })
            .catch(function () {});
    }
    setInterval(refresh, 15000);
})();

// Share menus: "Copy message & link", and close other open menus when one opens.
document.addEventListener('click', function (e) {
    var copy = e.target.closest('[data-copy]');
    if (copy) {
        var text = copy.getAttribute('data-copy');
        var done = function () { copy.innerHTML = '<i class="fas fa-check"></i> Copied!'; setTimeout(function () { copy.innerHTML = '<i class="fas fa-link"></i> Copy message &amp; link'; }, 1800); };
        if (navigator.clipboard) navigator.clipboard.writeText(text).then(done); else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
        return;
    }
    document.querySelectorAll('details.share-menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
});
</script>
