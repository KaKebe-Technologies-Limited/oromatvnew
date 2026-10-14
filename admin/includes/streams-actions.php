<?php
/**
 * Everything the Streams & Live page can DO (every button and form on its three tabs).
 * streams_handle_post() runs one action. On success it sets a flash message and redirects (so a refresh never repeats
 * it). When a form has mistakes it returns ['tab' => ..., 'errors' => [...]] so the page can show them with the
 * visitor's text still in the form.
 */

function streams_fail(string $tab, array $errors): array
{
    return ['tab' => $tab, 'errors' => $errors];
}

function streams_done(string $tab, string $message, array $query = []): void
{
    flash('success', $message);
    redirect(streams_url($tab, $query));
}

function streams_handle_post(): array
{
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $now = date('Y-m-d H:i:s');

    switch ($action) {

        // ───────────────── Live control ─────────────────

        case 'toggle_status': // the manual "main channel" switch
            $on = get_setting('stream_status', 'offline') !== 'live';
            set_setting('stream_status', $on ? 'live' : 'offline');
            streams_done('live', $on ? 'Main channel is now on air.' : 'Main channel switched off.');

        case 'program_save': {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $link = parse_stream_link((string) ($_POST['stream_link'] ?? ''));
            $startNow = ($_POST['start_mode'] ?? 'schedule') === 'now';
            $kind = in_array($_POST['kind'] ?? '', ['show', 'movie', 'event'], true) ? $_POST['kind'] : 'show';
            $modeChoice = in_array($_POST['mode'] ?? '', ['auto', 'premiere', 'live'], true) ? $_POST['mode'] : 'auto';
            $repeat = in_array($_POST['repeat'] ?? '', ['daily', 'weekdays', 'weekly'], true) ? $_POST['repeat'] : 'none';
            $poster = trim((string) ($_POST['poster_url'] ?? ''));
            $errors = [];

            if ($title === '' || mb_strlen($title) > 200) {
                $errors[] = 'Give the programme a title (up to 200 characters).';
            }
            if (mb_strlen($description) > 500) {
                $errors[] = 'The description can be at most 500 characters.';
            }
            if (!$link) {
                $errors[] = 'That stream link could not be used. Paste a YouTube link (youtu.be/… or youtube.com/watch?v=…), an https:// embed link, or an <iframe> embed code.';
            }
            if ($poster !== '' && (!preg_match('~^https://[^\s]+$~i', $poster) || mb_strlen($poster) > 500 || !filter_var($poster, FILTER_VALIDATE_URL))) {
                $errors[] = 'The poster must be an https:// link to an image (or leave it empty to use the video\'s own picture).';
            }

            $starts = $now;
            if (!$startNow) {
                $parsed = DateTime::createFromFormat('Y-m-d\TH:i', (string) ($_POST['starts_at'] ?? ''));
                if (!$parsed) {
                    $errors[] = 'Choose when the programme should start.';
                } else {
                    $starts = $parsed->format('Y-m-d H:i:00');
                }
            }

            // How long it runs: minutes, "open" (until ended by hand) or "keep" (the length it already had).
            $ends = null;
            $length = (string) ($_POST['duration'] ?? '60');
            if ($length === 'keep' && $id > 0) {
                $old = db()->prepare('SELECT starts_at, ends_at FROM live_programs WHERE id = ?');
                $old->execute([$id]);
                $row = $old->fetch();
                $kept = $row && $row['ends_at'] ? max(300, strtotime($row['ends_at']) - strtotime($row['starts_at'])) : null;
                $ends = $kept ? date('Y-m-d H:i:s', strtotime($starts) + $kept) : null;
            } elseif ($length !== 'open' && ctype_digit($length) && (int) $length >= 5 && (int) $length <= 1440) {
                $ends = date('Y-m-d H:i:s', strtotime($starts) + (int) $length * 60);
            }

            $effectiveEnd = $ends ?? date('Y-m-d H:i:s', strtotime($starts . ' +' . LIVE_MAX_HOURS . ' hours'));
            if (!$errors && $effectiveEnd <= $now) {
                $errors[] = 'That time has already passed. Pick a start time that is now or later (or choose “Go live right now”).';
            }
            if (!$errors && $repeat !== 'none' && !$ends) {
                $errors[] = 'A repeating programme needs a length (choose how long it runs instead of “Until I end it”).';
            }
            if ($errors) {
                return streams_fail('live', $errors);
            }

            // Play mode: "premiere" = a recording everyone watches in sync from the server clock; "live" = a real-time stream.
            // "Auto" asks YouTube; anything that is not a YouTube video cannot be synchronised, so it is treated as live.
            $mode = $link['provider'] !== 'youtube' ? 'live' : ($modeChoice === 'auto' ? detect_youtube_mode($link['video_id']) : $modeChoice);

            $seriesId = null;
            if ($id > 0) {
                $keep = db()->prepare('SELECT series_id FROM live_programs WHERE id = ?');
                $keep->execute([$id]);
                $seriesId = ($keep->fetch()['series_id'] ?? null) ?: null;
                db()->prepare(
                    'UPDATE live_programs SET title=?, description=?, provider=?, stream_url=?, video_id=?, starts_at=?, ends_at=?, ended_at=NULL, mode=?, kind=?, poster_url=? WHERE id=?'
                )->execute([$title, $description ?: null, $link['provider'], $link['stream_url'], $link['video_id'], $starts, $ends, $mode, $kind, $poster ?: null, $id]);
                $savedId = $id;
                $occurrences = 1;
            } else {
                db()->prepare(
                    'INSERT INTO live_programs (title, description, provider, stream_url, video_id, starts_at, ends_at, created_by, created_at, mode, kind, poster_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$title, $description ?: null, $link['provider'], $link['stream_url'], $link['video_id'], $starts, $ends, current_user()['id'] ?? null, $now, $mode, $kind, $poster ?: null]);
                $savedId = (int) db()->lastInsertId();
                $occurrences = 1;
                if ($repeat !== 'none') {
                    $occurrences = create_programme_series(get_live_program($savedId), $repeat);
                }
            }

            // Tell the admin if this clashes with something already scheduled (the one that started most recently wins on air).
            $clashes = find_overlapping_programs($starts, $effectiveEnd, $savedId, $seriesId ?? $savedId);
            if ($clashes) {
                $names = implode(', ', array_map(fn($c) => '“' . $c['title'] . '” (' . fmt_when($c['starts_at']) . ')', array_slice($clashes, 0, 3)));
                flash('error', 'Heads up: this overlaps with ' . $names . '. While both are scheduled, the one that started most recently is the one on air.');
            }

            $modeNote = $mode === 'premiere' ? ' Everyone will watch it in sync, from the start, as if it were on TV.' : '';
            $message = $starts <= $now
                ? '“' . $title . '” is on air now.' . $modeNote
                : '“' . $title . '” is scheduled for ' . fmt_when($starts) . ' — it will go on air by itself.' . $modeNote;
            if ($occurrences > 1) {
                $last = db()->prepare('SELECT MAX(starts_at) AS d FROM live_programs WHERE series_id = ?');
                $last->execute([$savedId]);
                $message .= ' It repeats ' . $repeat . ': ' . $occurrences . ' showings through ' . date('D j M', strtotime($last->fetch()['d'])) . '.';
            }
            streams_done('live', $message);
        }

        case 'program_golive': {
            $p = get_live_program($id);
            if ($p) {
                $length = $p['ends_at'] ? strtotime($p['ends_at']) - strtotime($p['starts_at']) : 0;
                $ends = $length > 0 ? date('Y-m-d H:i:s', time() + $length) : null;
                db()->prepare('UPDATE live_programs SET starts_at = ?, ends_at = ?, ended_at = NULL WHERE id = ?')->execute([$now, $ends, $id]);
                streams_done('live', '“' . $p['title'] . '” is on air now.');
            }
            streams_done('live', 'That programme no longer exists.');
        }

        case 'program_end':
            db()->prepare('UPDATE live_programs SET ended_at = ? WHERE id = ?')->execute([$now, $id]);
            streams_done('live', 'The programme has ended and the site is back to normal.');

        case 'program_delete':
            db()->prepare('DELETE FROM live_programs WHERE id = ?')->execute([$id]);
            streams_done('live', 'Programme deleted.');

        case 'program_delete_series': {
            $p = get_live_program($id);
            if ($p && $p['series_id']) {
                $stmt = db()->prepare('DELETE FROM live_programs WHERE series_id = ? AND starts_at > ?');
                $stmt->execute([$p['series_id'], $now]);
                streams_done('live', 'All upcoming showings of “' . $p['title'] . '” were deleted.');
            }
            streams_done('live', 'That programme is not part of a series.');
        }

        case 'live_settings': {
            $mainRaw = trim((string) ($_POST['main_url'] ?? ''));
            $main = $mainRaw !== '' ? parse_stream_link($mainRaw) : null;
            if ($mainRaw !== '' && !$main) {
                return streams_fail('live', ['The main channel link could not be used. Paste a YouTube link, an https:// embed link, or an <iframe> embed code — or leave it empty to use the default.']);
            }
            foreach (['live_schedule_enabled', 'offair_enabled', 'offair_shuffle', 'offair_use_shows', 'live_auto_archive'] as $flag) {
                set_setting($flag, isset($_POST[$flag]) ? '1' : '0');
            }
            set_setting('live_main_url', $main ? $main['stream_url'] : '');
            streams_done('live', 'Automation settings saved.');
        }

        // ───────────────── Channels (radio + extra videos) ─────────────────

        case 'radio_save': {
            $raw = trim((string) ($_POST['qfm_radio_url'] ?? ''));
            $name = trim((string) ($_POST['qfm_radio_name'] ?? ''));
            require_once __DIR__ . '/../../includes/radio-functions.php';
            if ($raw !== '' && !radio_parse_source($raw)) {
                return streams_fail('channels', ['That radio link could not be used. Paste a Radio Garden link (https://radio.garden/listen/…), the station\'s player link, or a direct https:// stream address.']);
            }
            if (mb_strlen($name) > 60) {
                return streams_fail('channels', ['The radio name can be at most 60 characters.']);
            }
            set_setting('qfm_radio_url', $raw);
            set_setting('qfm_radio_name', $name);
            streams_done('channels', 'Radio settings saved.', ['check' => 1]);
        }

        case 'save_stream': {
            $type = in_array($_POST['type'] ?? '', ['youtube', 'radio'], true) ? $_POST['type'] : 'youtube';
            $name = trim((string) ($_POST['name'] ?? ''));
            $link = parse_stream_link((string) ($_POST['url_or_id'] ?? ''));
            $icon = trim((string) ($_POST['icon'] ?? ''));
            $isDefault = isset($_POST['is_default']) ? 1 : 0;
            $errors = [];

            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = 'Give the channel a name (up to 100 characters).';
            }
            if (!$link) {
                $errors[] = 'That link could not be used. Paste a YouTube link, an https:// address, or an <iframe> embed code.';
            } elseif ($type === 'youtube' && $link['provider'] !== 'youtube') {
                $errors[] = 'A video channel needs a YouTube link. For a radio player choose the Radio type.';
            }
            if ($icon !== '' && !preg_match('/^[a-z0-9 \-]{1,50}$/i', $icon)) {
                $errors[] = 'The icon must be a Font Awesome class such as “fas fa-tv”.';
            }
            if ($errors) {
                return streams_fail('channels', $errors);
            }

            if ($isDefault) {
                db()->prepare('UPDATE streams SET is_default = 0 WHERE type = ?')->execute([$type]);
            }
            $source = $link['stream_url'];
            if ($id > 0) {
                db()->prepare('UPDATE streams SET type=?, name=?, url_or_id=?, icon=?, is_default=? WHERE id=?')
                    ->execute([$type, $name, $source, $icon ?: null, $isDefault, $id]);
                streams_done('channels', 'Channel updated.');
            }
            $max = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) AS m FROM streams')->fetch()['m'];
            db()->prepare('INSERT INTO streams (type, name, url_or_id, icon, is_default, sort_order) VALUES (?,?,?,?,?,?)')
                ->execute([$type, $name, $source, $icon ?: null, $isDefault, $max + 1]);
            streams_done('channels', 'Channel added.');
        }

        case 'toggle_active':
            db()->prepare('UPDATE streams SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            streams_done('channels', 'Channel visibility updated.', array_filter(['type' => $_POST['type_filter'] ?? '']));

        case 'stream_default': {
            $stmt = db()->prepare('SELECT type, is_default FROM streams WHERE id = ?');
            $stmt->execute([$id]);
            if ($row = $stmt->fetch()) {
                db()->prepare('UPDATE streams SET is_default = 0 WHERE type = ?')->execute([$row['type']]);
                if (!$row['is_default']) {
                    db()->prepare('UPDATE streams SET is_default = 1 WHERE id = ?')->execute([$id]);
                }
            }
            streams_done('channels', 'Default channel updated.', array_filter(['type' => $_POST['type_filter'] ?? '']));
        }

        case 'stream_move': {
            $stmt = db()->prepare('SELECT type FROM streams WHERE id = ?');
            $stmt->execute([$id]);
            if ($row = $stmt->fetch()) {
                move_row('streams', $id, ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down', 'type = ?', [$row['type']]);
            }
            redirect(streams_url('channels', array_filter(['type' => $_POST['type_filter'] ?? ''])));
        }

        // ───────────────── Off-air playlist ─────────────────

        case 'offair_add': {
            $link = parse_stream_link((string) ($_POST['link'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            if (!$link || $link['provider'] !== 'youtube') {
                return streams_fail('playlist', ['Paste a YouTube video link (youtu.be/… or youtube.com/watch?v=…).']);
            }
            $meta = $title === '' ? fetch_youtube_metadata($link['stream_url']) : null;
            $title = $title !== '' ? $title : ($meta['title'] ?? '');
            if ($title === '') {
                return streams_fail('playlist', ['We could not read that video\'s title automatically. Type a title for it and add it again.']);
            }
            streams_done('playlist', offair_insert($title, $link['video_id'], $link['stream_url']));
        }

        case 'offair_add_show': {
            $stmt = db()->prepare('SELECT * FROM previous_shows WHERE id = ?');
            $stmt->execute([(int) ($_POST['show_id'] ?? 0)]);
            if ($show = $stmt->fetch()) {
                streams_done('playlist', offair_insert($show['title'], $show['video_id'], get_youtube_watch_url($show['video_id']), $show['thumbnail_url']));
            }
            streams_done('playlist', 'That show no longer exists.');
        }

        case 'offair_toggle':
            db()->prepare('UPDATE offair_items SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            streams_done('playlist', 'Playlist item updated.');

        case 'offair_move':
            move_row('offair_items', $id, ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down');
            redirect(streams_url('playlist'));

        case 'offair_delete':
            db()->prepare('DELETE FROM offair_items WHERE id = ?')->execute([$id]);
            streams_done('playlist', 'Removed from the playlist.');
    }

    redirect(streams_url('live'));
}

/** Adds a video to the end of the off-air playlist (skipping duplicates) and returns the message to show. */
function offair_insert(string $title, string $videoId, string $sourceUrl, ?string $thumbnail = null): string
{
    $exists = db()->prepare('SELECT id FROM offair_items WHERE video_id = ? LIMIT 1');
    $exists->execute([$videoId]);
    if ($exists->fetch()) {
        return '“' . $title . '” is already in the playlist.';
    }
    $max = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) AS m FROM offair_items')->fetch()['m'];
    db()->prepare('INSERT INTO offair_items (title, video_id, source_url, thumbnail_url, sort_order, is_active, created_at) VALUES (?,?,?,?,?,1,?)')
        ->execute([mb_substr($title, 0, 255), $videoId, $sourceUrl, $thumbnail ?: get_youtube_thumbnail($videoId), $max + 1, date('Y-m-d H:i:s')]);
    return '“' . $title . '” added to the off-air playlist.';
}
