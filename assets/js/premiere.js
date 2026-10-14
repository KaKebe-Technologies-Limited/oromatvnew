/**
 * Oroma TV · the countdown on a programme's own page (watch.php?p=ID while it has not started).
 *
 * Counts down to the start time using the server's clock, shows the time in the visitor's own timezone, offers an
 * "Add to calendar" file, and — the moment the programme goes on air — reloads the page so the show begins by itself.
 */
(function () {
    'use strict';

    var hero = document.getElementById('premiere');
    if (!hero) return;

    var API = hero.getAttribute('data-api') || '/api/v1';
    var id = hero.getAttribute('data-id');
    var start = Date.parse(hero.getAttribute('data-start'));
    var end = Date.parse(hero.getAttribute('data-end'));
    var skew = Date.parse(hero.getAttribute('data-now')) - Date.now(); // server time minus this computer's time
    var title = hero.getAttribute('data-title');
    var url = hero.getAttribute('data-url');
    var checking = false;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function set(elId, text) { var n = document.getElementById(elId); if (n) n.textContent = text; }

    // The visitor's own timezone, so "20:00" is never a guess.
    var when = document.getElementById('pmWhen');
    if (when && window.Intl) {
        try {
            when.textContent = new Date(start).toLocaleString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short' });
        } catch (e) { /* keep the server's wording */ }
    }

    function wentLive() {
        if (checking) return;
        checking = true;
        // Wait until the server agrees it is on air (its clock decides), then load the stage.
        (function ask() {
            fetch(API + '/programmes/' + id, { cache: 'no-store', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    if (j && j.data && j.data.on_air) { location.reload(); } else { setTimeout(ask, 1500); }
                })
                .catch(function () { setTimeout(ask, 3000); });
        })();
    }

    function tick() {
        var left = start - (Date.now() + skew);
        if (left <= 0) {
            ['pmD', 'pmH', 'pmM', 'pmS'].forEach(function (k) { set(k, '00'); });
            set('pmHint', 'Starting now…');
            wentLive();
            return;
        }
        var s = Math.floor(left / 1000);
        set('pmD', pad(Math.floor(s / 86400)));
        set('pmH', pad(Math.floor((s % 86400) / 3600)));
        set('pmM', pad(Math.floor((s % 3600) / 60)));
        set('pmS', pad(s % 60));
    }
    tick();
    setInterval(tick, 1000);

    // Add to calendar: a small .ics file every calendar app understands.
    function ics(ms) {
        var d = new Date(ms);
        return d.getUTCFullYear() + pad(d.getUTCMonth() + 1) + pad(d.getUTCDate()) + 'T' + pad(d.getUTCHours()) + pad(d.getUTCMinutes()) + pad(d.getUTCSeconds()) + 'Z';
    }
    function clean(text) { return String(text).replace(/[\\;,]/g, function (c) { return '\\' + c; }).replace(/\r?\n/g, '\\n'); }
    var cal = document.getElementById('pmCal');
    if (cal) cal.addEventListener('click', function () {
        var lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Oroma TV//Live//EN', 'BEGIN:VEVENT',
            'UID:programme-' + id + '@oromatv', 'DTSTAMP:' + ics(Date.now()), 'DTSTART:' + ics(start), 'DTEND:' + ics(isNaN(end) ? start + 3600000 : end),
            'SUMMARY:' + clean(title + ' — Oroma TV'), 'DESCRIPTION:' + clean('Watch free: ' + url), 'URL:' + url,
            'BEGIN:VALARM', 'TRIGGER:-PT10M', 'ACTION:DISPLAY', 'DESCRIPTION:' + clean(title + ' starts in 10 minutes'), 'END:VALARM',
            'END:VEVENT', 'END:VCALENDAR'
        ];
        var blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'oroma-tv-' + id + '.ics';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        cal.innerHTML = '<i class="fas fa-check"></i> Added — check your calendar';
    });
})();
