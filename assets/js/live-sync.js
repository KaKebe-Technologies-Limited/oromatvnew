/**
 * Oroma TV · the synchronized live stage on watch.php.
 *
 * What it does
 *   - Asks /api/v1/live what is on air and builds the player for it.
 *   - A recorded programme ("premiere") is played from the server's clock: everyone, on the website and in the app,
 *     is at the same second, like TV. Someone who opens the page late joins mid-programme.
 *   - Watches the clock: when the next programme's start time arrives (or the current one finishes) the stage switches by
 *     itself, with no reload. If nothing is scheduled the off-air playlist plays, so the screen is never empty.
 *   - Counts the viewer (a heartbeat every 30 s, anonymous) and shows the "coming up" list with a live countdown.
 *
 * The clock: the server's time is read from every answer, so a wrong clock on the visitor's computer changes nothing.
 */
(function () {
    'use strict';

    var root = document.getElementById('liveStage');
    if (!root) return;

    var API = root.getAttribute('data-api') || '/api/v1';
    var screen = document.getElementById('lvScreen');
    var mount = document.getElementById('lvMount');
    var overlay = document.getElementById('lvOverlay');
    var $ = function (id) { return document.getElementById(id); };

    var skew = 0;               // server time minus this computer's time, in ms
    var snap = null;            // the last answer from /live
    var stage = null;           // the stage being shown (snap.stage, or an off-air stand-in)
    var stageKey = null;
    var player = null;          // YT.Player, when the stage is a YouTube video
    var playerReady = false;
    var wantMuted = true;       // browsers only allow sound after a tap
    var radioOn = false;
    var driftTimer = null;
    var switchTimer = null;
    var viewerId = null;
    var shieldUntil = 0;        // YouTube flashes its title and logo for a moment after play starts; the stage stays covered until then

    function now() { return Date.now() + skew; }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function clock(ms) {
        var s = Math.max(0, Math.floor(ms / 1000));
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), r = s % 60;
        return h > 0 ? h + ':' + pad(m) + ':' + pad(r) : pad(m) + ':' + pad(r);
    }
    function words(ms) {
        var m = Math.round(ms / 60000);
        if (m < 1) return 'less than a minute';
        if (m < 60) return m + ' min';
        var h = Math.floor(m / 60), r = m % 60;
        if (h < 24) return h + 'h' + (r ? ' ' + r + 'm' : '');
        var d = Math.floor(h / 24);
        return d + ' day' + (d > 1 ? 's' : '');
    }
    function when(iso) {
        var d = new Date(iso), t = new Date();
        var day = d.toDateString() === t.toDateString() ? 'Today'
            : (d.toDateString() === new Date(t.getTime() + 86400000).toDateString() ? 'Tomorrow'
                : d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }));
        return day + ' · ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    }
    var KINDS = { show: 'Programme', movie: 'Movie premiere', event: 'Special event' };

    /* ── talking to the server ────────────────────────────────── */

    function getLive() {
        var t0 = Date.now();
        return fetch(API + '/live', { headers: { Accept: 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('live ' + r.status)); })
            .then(function (j) {
                var t1 = Date.now();
                var data = j.data;
                var serverMs = Date.parse(data.server_time);
                if (!isNaN(serverMs)) skew = serverMs - (t0 + t1) / 2; // assume the answer was made halfway through the round trip
                return data;
            });
    }

    function ping() {
        if (document.hidden || !snap) return;
        if (!viewerId) {
            try { viewerId = localStorage.getItem('oroma_viewer'); } catch (e) { /* private mode */ }
            if (!viewerId) {
                viewerId = 'web-' + Array.apply(null, Array(20)).map(function () {
                    return 'abcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(Math.random() * 36));
                }).join('');
                try { localStorage.setItem('oroma_viewer', viewerId); } catch (e) { /* private mode */ }
            }
        }
        fetch(API + '/live/ping', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ viewer_id: viewerId, platform: 'web' })
        }).then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.data) showViewers(j.data.watching); })
            .catch(function () { /* a missed heartbeat is harmless */ });
    }

    /* ── player ───────────────────────────────────────────────── */

    var ytLoading = null;
    function loadYouTube() {
        if (window.YT && window.YT.Player) return Promise.resolve();
        if (ytLoading) return ytLoading;
        ytLoading = new Promise(function (resolve, reject) {
            var prev = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () { if (prev) prev(); resolve(); };
            var s = document.createElement('script');
            s.src = 'https://www.youtube.com/iframe_api';
            s.onerror = function () { ytLoading = null; reject(new Error('YouTube did not load')); };
            document.head.appendChild(s);
        });
        return ytLoading;
    }

    function destroyPlayer() {
        clearInterval(driftTimer);
        playerReady = false;
        if (player && player.destroy) { try { player.destroy(); } catch (e) { /* already gone */ } }
        player = null;
        mount.innerHTML = '';
    }

    /** Where, in seconds, a recorded programme should be right now. */
    function expectedPosition(st) {
        var start = Date.parse(st.starts_at);
        return isNaN(start) ? 0 : Math.max(0, (now() - start) / 1000);
    }

    function applyMute() {
        if (!player || !playerReady) return;
        var mute = wantMuted || radioOn;
        try { if (mute) player.mute(); else { player.unMute(); player.setVolume(100); } } catch (e) { /* not ready */ }
        var btn = $('lvMute');
        if (btn) {
            btn.innerHTML = '<i class="fas ' + (mute ? 'fa-volume-xmark' : 'fa-volume-high') + '"></i>';
            btn.setAttribute('aria-label', mute ? 'Turn the sound on' : 'Mute');
        }
        if (Date.now() < shieldUntil) return;
        var playing = player.getPlayerState && player.getPlayerState() === 1;
        if (mute && !radioOn && playing) showOverlay('unmute');
        else if (overlay.classList.contains('unmute')) showOverlay(null);
    }

    function showOverlay(kind, extra) {
        overlay.style.backgroundImage = '';
        if (!kind) { overlay.className = 'lv-overlay'; overlay.innerHTML = ''; return; }
        overlay.className = 'lv-overlay show ' + kind;
        if (kind === 'loading') {
            if (screen.style.backgroundImage) overlay.style.backgroundImage = 'linear-gradient(rgba(0,0,0,.6), rgba(0,0,0,.6)), ' + screen.style.backgroundImage;
            overlay.innerHTML = '<div class="lv-spin"></div>';
        } else if (kind === 'unmute') {
            overlay.innerHTML = '<button type="button" class="lv-tap" id="lvTap"><i class="fas fa-volume-high"></i> Tap for sound</button>';
            $('lvTap').addEventListener('click', function () { wantMuted = false; applyMute(); });
        } else if (kind === 'play') {
            overlay.innerHTML = '<button type="button" class="lv-tap lv-tap-big" id="lvTap"><i class="fas fa-play"></i> Tap to watch</button>';
            $('lvTap').addEventListener('click', function () {
                wantMuted = false;
                if (player && player.playVideo) {
                    if (stage && stage.mode === 'premiere' && stage.kind === 'program') { try { player.seekTo(expectedPosition(stage), true); } catch (e) { /* ignore */ } }
                    player.playVideo();
                }
                applyMute();
            });
        } else if (kind === 'message') {
            overlay.innerHTML = extra || '';
        }
    }

    function buildYouTube(st, opts) {
        showOverlay('loading');
        shieldUntil = 0;
        var holder = document.createElement('div');
        mount.appendChild(holder);
        var vars = { autoplay: 1, controls: 0, disablekb: 1, rel: 0, modestbranding: 1, playsinline: 1, iv_load_policy: 3, fs: 0, mute: 1, origin: location.origin };
        var cfg = { width: '100%', height: '100%', playerVars: vars, events: {} };
        if (opts.playlist) { // the first video plays, then the rest; loop=1 starts the whole list again
            cfg.videoId = opts.playlist[0];
            vars.playlist = opts.playlist.length > 1 ? opts.playlist.slice(1).join(',') : opts.playlist[0];
            vars.loop = 1;
        } else {
            cfg.videoId = st.video_id;
        }
        if (opts.start > 0) vars.start = Math.floor(opts.start);

        cfg.events.onReady = function (e) {
            playerReady = true;
            applyMute(); // start silent: browsers refuse autoplay with sound
            e.target.playVideo();
            keepInSync();
        };
        cfg.events.onAutoplayBlocked = function () { showOverlay('play'); };
        cfg.events.onStateChange = function (e) {
            var S = window.YT.PlayerState;
            if (e.data === S.PLAYING) {
                if (!shieldUntil) {
                    shieldUntil = Date.now() + 3200;
                    setTimeout(function () { if (stageKey === opts.key) { showOverlay(null); applyMute(); } }, 3250);
                } else if (Date.now() >= shieldUntil) {
                    showOverlay(null);
                }
                applyMute();
            }
            else if (e.data === S.BUFFERING) { /* the picture catches up by itself */ }
            else if (e.data === S.PAUSED && !document.hidden) { setTimeout(function () { if (player && player.getPlayerState && player.getPlayerState() === 2) player.playVideo(); }, 600); }
            else if (e.data === S.ENDED && !opts.playlist) { onVideoEnded(); }
        };
        cfg.events.onError = function () {
            showOverlay('message', '<div class="lv-note"><i class="fas fa-triangle-exclamation"></i> This video cannot be played here.' +
                (st.watch_url ? ' <a href="' + esc(st.watch_url) + '" target="_blank" rel="noopener">Watch on YouTube</a>' : '') + '</div>');
        };

        loadYouTube().then(function () {
            if (stageKey !== opts.key) return; // the stage changed while the API was loading
            player = new window.YT.Player(holder, cfg);
        }).catch(function () {
            showOverlay('message', '<div class="lv-note"><i class="fas fa-triangle-exclamation"></i> The player could not load. Check your connection and refresh.</div>');
        });
    }

    /** A recorded programme: pull anyone who drifted (a sleeping tab, a slow connection) back to the shared moment. */
    function keepInSync() {
        clearInterval(driftTimer);
        if (!stage || stage.kind !== 'program' || stage.mode !== 'premiere') return;
        driftTimer = setInterval(function () {
            if (!player || !playerReady || document.hidden || !player.getCurrentTime) return;
            var state = player.getPlayerState();
            if (state !== 1) return;
            var want = expectedPosition(stage);
            var dur = player.getDuration ? player.getDuration() : 0;
            if (dur && want >= dur - 1) return; // the recording has ended; onVideoEnded handles it
            if (Math.abs(player.getCurrentTime() - want) > 4) player.seekTo(want, true);
        }, 5000);
    }

    function onVideoEnded() {
        showOverlay('message', '<div class="lv-note"><i class="fas fa-flag-checkered"></i> That was the end of the programme.' +
            (snap && snap.next ? ' Next: <b>' + esc(snap.next.title) + '</b>' : ' More shows are in <a href="previous-shows.php">Previous Shows</a>.') + '</div>');
        refresh();
    }

    function buildEmbed(st) {
        var f = document.createElement('iframe');
        f.src = st.embed_url;
        f.allow = 'autoplay; encrypted-media; fullscreen';
        f.setAttribute('allowfullscreen', '');
        f.setAttribute('scrolling', 'no');
        f.title = st.title;
        mount.appendChild(f);
        showOverlay(null);
        screen.classList.add('lv-embed');
    }

    /* ── showing a stage ──────────────────────────────────────── */

    function offAirStage(s) {
        var items = (s.playlist || []).filter(function (i) { return i.video_id; });
        return {
            kind: 'offair', id: 'offair', mode: 'live', programme_kind: 'show', provider: 'youtube',
            title: items.length ? 'Playing from our library' : 'We are off air',
            description: items.length
                ? 'Nothing is scheduled at this moment, so enjoy a selection from our previous shows' + (s.next ? ' until ' + s.next.title + ' starts.' : '.')
                : 'We will be back soon.',
            video_id: items.length ? items[0].video_id : null,
            thumbnail: items.length ? items[0].thumbnail : null,
            watch_url: null, share_url: location.origin + location.pathname, starts_at: null, effective_end: null,
            playlist_ids: items.map(function (i) { return i.video_id; })
        };
    }

    function shuffled(list) {
        var a = list.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1)), t = a[i];
            a[i] = a[j];
            a[j] = t;
        }
        return a;
    }

    function renderStage(s) {
        var st = s.stage || offAirStage(s);
        var key = st.id + (st.kind === 'offair' ? ':' + (st.playlist_ids || []).join(',') : '');
        var changed = key !== stageKey;
        stage = st;

        paintInfo(st);
        if (!changed) return;

        stageKey = key;
        destroyPlayer();
        screen.classList.remove('lv-embed');
        screen.setAttribute('data-kind', st.kind);
        screen.style.backgroundImage = st.thumbnail ? 'url("' + st.thumbnail + '")' : '';

        if (st.kind === 'offair') {
            if (st.playlist_ids.length) buildYouTube(st, { key: key, playlist: s.shuffle ? shuffled(st.playlist_ids) : st.playlist_ids });
            else showOverlay('message', '<div class="lv-note"><i class="fas fa-tower-broadcast"></i> We are off air right now. Check the schedule for our next show.</div>');
        } else if (st.provider === 'youtube') {
            buildYouTube(st, { key: key, start: st.mode === 'premiere' ? expectedPosition(st) : 0 });
        } else {
            buildEmbed(st);
        }
        if (changed && stage.kind === 'program' && root.getAttribute('data-ready') === '1') flash('Now on air: ' + st.title);
        root.setAttribute('data-ready', '1');
    }

    function paintInfo(st) {
        var live = st.kind !== 'offair';
        var badge = $('lvBadge');
        badge.className = 'lv-badge ' + (live ? 'lv-badge-live' : 'lv-badge-off');
        badge.innerHTML = live ? '<span class="dot"></span> LIVE' : '<i class="fas fa-film"></i> OFF AIR';

        var kind = st.kind === 'program' ? KINDS[st.programme_kind] || 'Programme' : (st.kind === 'main' ? 'Live broadcast' : 'Library');
        var how = st.kind === 'program' && st.mode === 'premiere' ? ' · everyone watches together' : '';
        $('lvKicker').innerHTML = (live ? '<span class="lv-red">ON AIR NOW</span> · ' : '') + esc(kind) + esc(how);
        $('lvTitle').textContent = st.title;
        var desc = $('lvDesc');
        desc.textContent = st.description || '';
        desc.hidden = !st.description;

        var share = document.querySelector('.lv-info [data-share]');
        if (share) { share.setAttribute('data-title', st.title); share.setAttribute('data-url', st.share_url || location.href); }
        var yt = $('lvYt');
        if (yt) { yt.hidden = !st.watch_url; if (st.watch_url) yt.href = st.watch_url; }

        var sync = $('lvSync');
        if (st.kind === 'program' && st.mode === 'premiere') {
            var began = Date.parse(st.starts_at);
            sync.innerHTML = '<i class="fas fa-arrows-rotate"></i> In sync with everyone on the website and the app — started ' + esc(words(now() - began)) + ' ago.';
            sync.hidden = false;
        } else if (st.kind === 'offair') {
            sync.innerHTML = '<i class="fas fa-circle-info"></i> This plays on its own until the next programme begins — you do not need to refresh.';
            sync.hidden = false;
        } else {
            sync.hidden = true;
        }
    }

    function flash(text) {
        var f = $('lvFlash');
        if (!f) return;
        f.textContent = text;
        f.classList.add('show');
        setTimeout(function () { f.classList.remove('show'); }, 5000);
    }

    function showViewers(n) {
        var v = $('lvViewers');
        if (!v) return;
        if (n >= 2) { v.hidden = false; v.innerHTML = '<i class="fas fa-eye"></i> ' + n + ' watching'; } else { v.hidden = true; }
    }

    /* ── the schedule on the side ─────────────────────────────── */

    function renderSchedule(s) {
        var list = $('lvSchedule');
        var items = s.schedule || [];
        $('lvScheduleCard').hidden = !items.length;
        list.innerHTML = items.map(function (p) {
            return '<li class="lv-up kind-' + esc(p.kind) + '">' +
                '<a class="lv-up-link" href="watch.php?p=' + p.id + '">' +
                    '<span class="lv-up-time">' + esc(when(p.starts_at)) + '</span>' +
                    '<span class="lv-up-title">' + esc(p.title) + '</span>' +
                    '<span class="lv-up-kind">' + esc(p.kind_label || KINDS[p.kind] || '') + '</span>' +
                '</a>' +
                '<button type="button" class="lv-up-share" data-share data-title="' + esc(p.title) + '" data-url="' + esc(p.share_url) + '" aria-label="Share ' + esc(p.title) + '"><i class="fas fa-share-nodes"></i></button>' +
            '</li>';
        }).join('');

        var next = s.next;
        var card = $('lvNextCard');
        card.hidden = !next;
        if (next) {
            card.setAttribute('data-start', next.starts_at);
            $('lvNextTitle').textContent = next.title;
            $('lvNextKind').textContent = next.kind_label || KINDS[next.kind] || '';
            var thumb = $('lvNextThumb');
            thumb.style.backgroundImage = next.thumbnail ? 'url("' + next.thumbnail + '")' : '';
            $('lvNextLink').href = 'watch.php?p=' + next.id;
        }
    }

    function tick() {
        var card = $('lvNextCard');
        if (card && !card.hidden) {
            var start = Date.parse(card.getAttribute('data-start'));
            var left = start - now();
            $('lvNextCount').textContent = left > 0 ? (left >= 86400000 ? words(left) : clock(left)) : 'Starting…';
            $('lvNextLabel').textContent = left > 0 && left < 86400000 ? 'Starts in' : (left > 0 ? 'Starts in about' : 'Going live');
        }
        var shown = $('lvSync');
        if (shown && stage && stage.kind === 'program' && stage.mode === 'premiere' && !shown.hidden) {
            shown.innerHTML = '<i class="fas fa-arrows-rotate"></i> In sync with everyone on the website and the app — started ' + esc(words(now() - Date.parse(stage.starts_at))) + ' ago.';
        }
    }

    /* ── keeping up with the clock ────────────────────────────── */

    function planSwitch() {
        clearTimeout(switchTimer);
        if (!snap) return;
        var marks = [];
        if (snap.stage && snap.stage.effective_end) marks.push(Date.parse(snap.stage.effective_end));
        if (snap.next && snap.next.starts_at) marks.push(Date.parse(snap.next.starts_at));
        marks = marks.filter(function (m) { return !isNaN(m) && m > now() - 1000; });
        if (!marks.length) return;
        var wait = Math.min.apply(null, marks) - now() + 400;
        // setTimeout cannot wait longer than ~24 days; the regular refresh covers anything further away anyway.
        switchTimer = setTimeout(function () { refresh(); setTimeout(refresh, 1800); }, Math.max(300, Math.min(wait, 2000000000)));
    }

    function refresh() {
        return getLive().then(function (data) {
            snap = data;
            renderStage(data);
            renderSchedule(data);
            showViewers(data.watching);
            planSwitch();
        }).catch(function () { /* offline for a moment: keep what is on screen and try again at the next refresh */ });
    }

    /* ── buttons ──────────────────────────────────────────────── */

    var muteBtn = $('lvMute');
    if (muteBtn) muteBtn.addEventListener('click', function () {
        if (wantMuted || radioOn) { // turn the video's sound on (this stops the radio, so the two never talk over each other)
            if (radioOn && window.OromaRadio) window.OromaRadio.stop();
            wantMuted = false;
        } else {
            wantMuted = true;
        }
        applyMute();
    });
    var fsBtn = $('lvFull');
    if (fsBtn) fsBtn.addEventListener('click', function () {
        var d = document;
        if (d.fullscreenElement || d.webkitFullscreenElement) { (d.exitFullscreen || d.webkitExitFullscreen).call(d); return; }
        var req = screen.requestFullscreen || screen.webkitRequestFullscreen;
        if (req) req.call(screen);
    });
    window.addEventListener('oroma:radio', function (e) { radioOn = !!(e.detail && e.detail.playing); applyMute(); });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) return;
        refresh();
        if (player && playerReady) {
            if (player.getPlayerState() !== 1) player.playVideo();
            if (stage && stage.kind === 'program' && stage.mode === 'premiere') player.seekTo(expectedPosition(stage), true);
        }
    });

    /* ── go ───────────────────────────────────────────────────── */

    showOverlay('loading');
    refresh().then(function () { ping(); });
    setInterval(refresh, 20000);
    setInterval(ping, 30000);
    setInterval(tick, 1000);
})();
