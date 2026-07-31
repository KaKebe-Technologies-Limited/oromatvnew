(function () {
    'use strict';

    // ---------- mobile nav ----------
    var toggle = document.getElementById('navToggle');
    var nav = document.getElementById('siteNav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            nav.classList.toggle('open');
        });
    }

    // ---------- stream tabs ----------
    var tabs = document.querySelectorAll('.tab-btn[data-tab]');
    var panes = {
        youtube: document.getElementById('pane-youtube'),
        radio: document.getElementById('pane-radio')
    };

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabs.forEach(function (b) {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');

            var target = btn.dataset.tab;
            Object.keys(panes).forEach(function (key) {
                if (panes[key]) {
                    panes[key].classList.toggle('active', key === target);
                }
            });

            if (target === 'youtube') {
                var iframe = document.getElementById('youtubePlayer');
                if (iframe) {
                    iframe.src = iframe.src;
                }
            }
        });
    });

    // ---------- channel selector ----------
    var channelBtns = document.querySelectorAll('.channel-btn[data-embed]');
    var ytIframe = document.getElementById('youtubePlayer');

    channelBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            channelBtns.forEach(function (b) {
                b.classList.remove('active-channel');
            });
            btn.classList.add('active-channel');

            var embedUrl = btn.dataset.embed;
            if (embedUrl && ytIframe) {
                ytIframe.src = embedUrl;
            }
        });
    });
})();
