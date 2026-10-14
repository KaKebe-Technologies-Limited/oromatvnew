/**
 * Oroma TV · "Listen to Q FM" — the small button in the top bar and the player bar it opens.
 *
 * Any element with [data-qfm] toggles the radio. The first press asks /api/v1/radio for the station's live stream
 * (and what is playing), then a slim player bar slides up at the bottom of the page. While it plays the bar keeps
 * "now playing" fresh, and it tells the page (an "oroma:radio" event) so a video on the page can be muted — the
 * live picture keeps running, so nobody falls out of sync, and the sound comes back when the radio stops.
 *
 * The audio stops when the visitor opens another page — a normal limit of a website, not of this player.
 */
(function () {
    'use strict';

    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-qfm]'));
    if (!buttons.length) return;

    var api = buttons[0].getAttribute('data-api') || '/api/v1';
    var audio = null;
    var bar = null;
    var info = null;        // the last answer from /api/v1/radio
    var state = 'idle';     // idle · loading · playing · paused · error
    var poll = null;

    function el(tag, cls, html) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (html != null) n.innerHTML = html;
        return n;
    }

    function buildBar() {
        bar = el('div', 'qfm-bar');
        bar.setAttribute('role', 'region');
        bar.setAttribute('aria-label', 'Q FM radio player');
        bar.innerHTML =
            '<div class="qfm-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></div>' +
            '<div class="qfm-text">' +
                '<div class="qfm-name"><span class="qfm-live">LIVE</span> <b data-r="name">Q FM</b></div>' +
                '<div class="qfm-now" data-r="now" aria-live="polite">Connecting…</div>' +
            '</div>' +
            '<button type="button" class="qfm-play" data-r="play" aria-label="Pause"><i class="fas fa-pause"></i></button>' +
            '<button type="button" class="qfm-close" data-r="close" aria-label="Close the radio"><i class="fas fa-xmark"></i></button>';
        document.body.appendChild(bar);
        bar.querySelector('[data-r="play"]').addEventListener('click', function () {
            if (state === 'playing') pause(); else start();
        });
        bar.querySelector('[data-r="close"]').addEventListener('click', stop);
        requestAnimationFrame(function () { bar.classList.add('open'); });
    }

    function setState(next, message) {
        state = next;
        if (bar) {
            bar.setAttribute('data-state', next);
            var play = bar.querySelector('[data-r="play"]');
            var icon = next === 'playing' ? 'fa-pause' : (next === 'loading' ? 'fa-spinner fa-spin' : (next === 'error' ? 'fa-rotate-right' : 'fa-play'));
            play.innerHTML = '<i class="fas ' + icon + '"></i>';
            play.setAttribute('aria-label', next === 'playing' ? 'Pause' : (next === 'error' ? 'Try again' : 'Play'));
            if (message) bar.querySelector('[data-r="now"]').textContent = message;
        }
        buttons.forEach(function (b) {
            b.classList.toggle('is-playing', next === 'playing' || next === 'loading');
            b.setAttribute('aria-pressed', next === 'playing' ? 'true' : 'false');
            var label = b.querySelector('.qfm-btn-label');
            if (label) label.textContent = next === 'playing' || next === 'loading' ? 'Q FM playing' : (b.getAttribute('data-label') || 'Listen to Q FM');
        });
        window.dispatchEvent(new CustomEvent('oroma:radio', { detail: { playing: next === 'playing' || next === 'loading' } }));
    }

    function nowText() {
        if (!info) return 'Live radio';
        var np = info.now_playing;
        if (np && (np.title || np.artist)) {
            return (np.artist ? np.artist + ' — ' : '') + (np.title || '');
        }
        var bits = [];
        if (info.frequency) bits.push(info.frequency);
        if (info.place) bits.push(info.place);
        if (typeof info.listeners === 'number' && info.listeners > 0) bits.push(info.listeners + ' listening');
        return bits.length ? bits.join(' · ') : 'Live radio';
    }

    function paint() {
        if (!bar || !info) return;
        bar.querySelector('[data-r="name"]').textContent = info.name || 'Q FM';
        if (state === 'playing' || state === 'paused') bar.querySelector('[data-r="now"]').textContent = nowText();
        if ('mediaSession' in navigator && window.MediaMetadata) {
            var np = info.now_playing || {};
            navigator.mediaSession.metadata = new MediaMetadata({
                title: np.title || (info.name || 'Q FM'),
                artist: np.artist || 'Oroma TV · live radio',
                artwork: np.art ? [{ src: np.art }] : []
            });
        }
    }

    function fetchInfo() {
        return fetch(api + '/radio', { headers: { Accept: 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('radio ' + r.status)); })
            .then(function (j) { info = j.data; paint(); return info; });
    }

    function start() {
        if (!bar) buildBar();
        setState('loading', 'Connecting…');
        var ready = info ? Promise.resolve(info) : fetchInfo();
        ready.then(function (data) {
            if (!data || !data.stream_url) throw new Error('no stream');
            if (!audio) {
                audio = new Audio();
                audio.preload = 'none';
                audio.addEventListener('playing', function () { setState('playing'); paint(); });
                audio.addEventListener('waiting', function () { if (state === 'playing') setState('loading', 'Buffering…'); });
                audio.addEventListener('error', function () { setState('error', 'The station is not reachable right now. Tap to try again.'); });
            }
            // A live stream must always join "now", so reload it instead of resuming old buffered sound.
            audio.src = data.stream_url + (data.stream_url.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now();
            audio.load();
            return audio.play();
        }).then(function () {
            clearInterval(poll);
            poll = setInterval(function () { fetchInfo().catch(function () {}); }, 20000);
        }).catch(function (err) {
            setState('error', err && err.name === 'NotAllowedError' ? 'Tap play to start the radio.' : 'The station is not reachable right now. Tap to try again.');
        });
    }

    function pause() {
        if (audio) { audio.pause(); audio.removeAttribute('src'); audio.load(); }
        setState('paused', 'Paused');
        clearInterval(poll);
    }

    function stop() {
        pause();
        setState('idle');
        if (bar) {
            bar.classList.remove('open');
            var gone = bar;
            bar = null;
            setTimeout(function () { gone.remove(); }, 300);
        }
    }

    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            if (state === 'playing' || state === 'loading') { stop(); } else { start(); }
        });
    });

    if ('mediaSession' in navigator) {
        navigator.mediaSession.setActionHandler('play', function () { start(); });
        navigator.mediaSession.setActionHandler('pause', function () { pause(); });
        navigator.mediaSession.setActionHandler('stop', function () { stop(); });
    }

    window.OromaRadio = { start: start, stop: stop, isPlaying: function () { return state === 'playing' || state === 'loading'; } };
})();
