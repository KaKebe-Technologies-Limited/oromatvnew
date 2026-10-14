/**
 * Oroma TV · share buttons.
 *
 * Any element with [data-share] (plus data-title and data-url) opens a small menu: WhatsApp, Facebook, X, Telegram and
 * "Copy link". On phones that support it, the phone's own share sheet opens instead.
 * Buttons added later (the "coming up" list is built by script) work too, because the click is handled on the document.
 */
(function () {
    'use strict';

    var menu = null;

    function close() {
        if (menu) { menu.remove(); menu = null; }
    }

    function link(href, icon, label) {
        return '<a href="' + href + '" target="_blank" rel="noopener noreferrer"><i class="' + icon + '"></i> ' + label + '</a>';
    }

    function copy(url, done) {
        function fallback() {
            var t = document.createElement('textarea');
            t.value = url;
            t.style.position = 'fixed';
            t.style.opacity = '0';
            document.body.appendChild(t);
            t.select();
            try { document.execCommand('copy'); } catch (e) { /* nothing more to try */ }
            t.remove();
            done();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, fallback);
        else fallback();
    }

    function open(btn) {
        var title = btn.getAttribute('data-title') || document.title;
        var url = btn.getAttribute('data-url') || location.href;

        if (navigator.share && /Mobi|Android/i.test(navigator.userAgent)) {
            navigator.share({ title: title, text: title + ' — watch on Oroma TV', url: url }).catch(function () { /* the visitor closed the sheet */ });
            return;
        }

        close();
        var text = encodeURIComponent(title + ' — watch on Oroma TV');
        var u = encodeURIComponent(url);
        menu = document.createElement('div');
        menu.className = 'site-share-pop';
        menu.setAttribute('role', 'menu');
        menu.innerHTML =
            link('https://wa.me/?text=' + text + '%20' + u, 'fab fa-whatsapp', 'WhatsApp') +
            link('https://www.facebook.com/sharer/sharer.php?u=' + u, 'fab fa-facebook-f', 'Facebook') +
            link('https://twitter.com/intent/tweet?text=' + text + '&url=' + u, 'fab fa-x-twitter', 'X (Twitter)') +
            link('https://t.me/share/url?url=' + u + '&text=' + text, 'fab fa-telegram', 'Telegram') +
            '<button type="button" data-copy><i class="fas fa-link"></i> Copy link</button>';
        document.body.appendChild(menu);

        // Sit under the button, kept inside the screen.
        var r = btn.getBoundingClientRect();
        var w = menu.offsetWidth;
        var left = Math.min(Math.max(8, r.left + window.scrollX), window.scrollX + document.documentElement.clientWidth - w - 8);
        menu.style.left = left + 'px';
        menu.style.top = (r.bottom + window.scrollY + 8) + 'px';

        menu.querySelector('[data-copy]').addEventListener('click', function (e) {
            var b = e.currentTarget;
            copy(url, function () { b.innerHTML = '<i class="fas fa-check"></i> Link copied'; setTimeout(close, 1100); });
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-share]') : null;
        if (btn) { e.preventDefault(); e.stopPropagation(); open(btn); return; }
        if (menu && !menu.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
