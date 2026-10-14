/**
 * Oroma TV · Previous Shows page: filter chips (All / Shows / Movies), search, and "starts in …" labels on premieres.
 */
(function () {
    'use strict';

    var grid = document.getElementById('psGrid');
    var rails = document.getElementById('psRails');
    var search = document.getElementById('psSearch');
    var chips = Array.prototype.slice.call(document.querySelectorAll('.ps-chip'));
    var filter = 'all';

    function apply() {
        if (!grid) return;
        var q = (search ? search.value : '').trim().toLowerCase();
        var shown = 0;
        Array.prototype.forEach.call(grid.children, function (card) {
            var okKind = filter === 'all' || card.getAttribute('data-kind') === filter;
            var okText = !q || (card.getAttribute('data-text') || '').indexOf(q) !== -1;
            card.hidden = !(okKind && okText);
            if (!card.hidden) shown++;
        });
        var filtering = filter !== 'all' || q !== '';
        if (rails) rails.hidden = filtering; // the rails are for browsing; a filter or a search shows one clear list
        var title = document.getElementById('psAllTitle');
        if (title) title.innerHTML = filter === 'movie' ? 'All <span>movies</span>' : (filter === 'show' ? 'All <span>shows</span>' : (q ? 'Search <span>results</span>' : 'All <span>episodes</span>'));
        var count = document.getElementById('psCount');
        if (count) count.textContent = shown + ' title' + (shown === 1 ? '' : 's');
        var none = document.getElementById('psNone');
        if (none) none.hidden = shown !== 0;
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            filter = chip.getAttribute('data-filter');
            chips.forEach(function (c) { c.classList.toggle('active', c === chip); });
            apply();
        });
    });
    if (search) search.addEventListener('input', apply);

    // "in 2 days", "in 3h 20m" on upcoming premieres (the time shown is the one in the visitor's own timezone).
    function relative(ms) {
        var m = Math.round(ms / 60000);
        if (m < 1) return 'starting now';
        if (m < 60) return 'in ' + m + ' min';
        var h = Math.floor(m / 60);
        if (h < 24) return 'in ' + h + 'h ' + (m % 60) + 'm';
        var d = Math.floor(h / 24);
        return 'in ' + d + ' day' + (d > 1 ? 's' : '');
    }
    var timers = Array.prototype.slice.call(document.querySelectorAll('[data-countdown]'));
    function paintTimers() {
        timers.forEach(function (n) {
            var at = Date.parse(n.getAttribute('data-countdown'));
            if (isNaN(at)) return;
            var d = new Date(at);
            var text = d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) + ' · ' +
                d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) + ' — ' + relative(at - Date.now());
            var icon = n.querySelector('i');
            n.innerHTML = (icon ? icon.outerHTML + ' ' : '') + text.replace(/&/g, '&amp;').replace(/</g, '&lt;');
        });
    }
    if (timers.length) { paintTimers(); setInterval(paintTimers, 30000); }
})();
