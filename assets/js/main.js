(function () {
    'use strict';

    /* ── mobile nav ── */
    var toggle  = document.getElementById('navToggle');
    var nav     = document.getElementById('siteNav');
    var overlay = document.getElementById('navOverlay');

    function openNav() {
        if (!nav) return;
        nav.classList.add('open');
        if (overlay) overlay.classList.add('visible');
        if (toggle) { toggle.setAttribute('aria-expanded','true'); toggle.innerHTML='<i class="fas fa-times"></i>'; }
    }
    function closeNav() {
        if (!nav) return;
        nav.classList.remove('open');
        if (overlay) overlay.classList.remove('visible');
        if (toggle) { toggle.setAttribute('aria-expanded','false'); toggle.innerHTML='<i class="fas fa-bars"></i>'; }
    }
    if (toggle) toggle.addEventListener('click', function(){ nav && nav.classList.contains('open') ? closeNav() : openNav(); });
    if (overlay) overlay.addEventListener('click', closeNav);
    document.addEventListener('keydown', function(e){ if (e.key==='Escape') { closeNav(); closeSearch(); } });

    /* ── search overlay ── */
    var searchToggle  = document.getElementById('searchToggle');
    var searchOverlay = document.getElementById('searchOverlay');
    var searchInput   = document.getElementById('searchInput');
    var searchClose   = document.getElementById('searchClose');

    function openSearch() {
        if (!searchOverlay) return;
        closeNav();
        searchOverlay.classList.add('open');
        setTimeout(function () { if (searchInput) searchInput.focus(); }, 60);
    }
    function closeSearch() {
        if (!searchOverlay) return;
        searchOverlay.classList.remove('open');
    }
    if (searchToggle) searchToggle.addEventListener('click', openSearch);
    if (searchClose) searchClose.addEventListener('click', closeSearch);
    if (searchOverlay) {
        searchOverlay.addEventListener('click', function (e) {
            if (e.target === searchOverlay) closeSearch();
        });
    }

    /* ── stream tabs ── */
    var tabs  = document.querySelectorAll('.tab-btn[data-tab]');
    var panes = { youtube: document.getElementById('pane-youtube'), radio: document.getElementById('pane-radio') };
    tabs.forEach(function(btn){
        btn.addEventListener('click', function(){
            tabs.forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
            btn.classList.add('active'); btn.setAttribute('aria-selected','true');
            var t = btn.dataset.tab;
            Object.keys(panes).forEach(function(k){ if(panes[k]) panes[k].classList.toggle('active', k===t); });
        });
    });

    /* ── channel selector ── */
    var channelBtns = document.querySelectorAll('.channel-btn[data-embed]');
    var ytIframe    = document.getElementById('youtubePlayer');
    channelBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
            channelBtns.forEach(function(b){ b.classList.remove('active-channel'); });
            btn.classList.add('active-channel');
            if (btn.dataset.embed && ytIframe) ytIframe.src = btn.dataset.embed;
        });
    });

    /* ── reading progress ── */
    var prog = document.getElementById('reading-progress');
    if (prog) {
        document.addEventListener('scroll', function(){
            var scrolled = window.scrollY || document.documentElement.scrollTop;
            var total    = document.documentElement.scrollHeight - window.innerHeight;
            prog.style.width = (total > 0 ? Math.min(scrolled/total*100,100) : 0) + '%';
        }, {passive:true});
    }

    /* ── back to top ── */
    var btt = document.getElementById('back-to-top');
    if (btt) {
        window.addEventListener('scroll', function(){ btt.classList.toggle('visible', window.scrollY>500); }, {passive:true});
        btt.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });
    }

    /* ── scroll reveal ── */
    var revEls = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && revEls.length) {
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('visible'); io.unobserve(e.target); } });
        }, {threshold:0.1, rootMargin:'0px 0px -36px 0px'});
        revEls.forEach(function(el){ io.observe(el); });
    } else {
        revEls.forEach(function(el){ el.classList.add('visible'); });
    }

    /* ── copy link ── */
    document.querySelectorAll('.js-copy-link').forEach(function(btn){
        btn.addEventListener('click', function(){
            var url = this.dataset.url; if (!url) return;
            var icon = this.querySelector('i'); var txt = this.querySelector('.copy-text');
            navigator.clipboard.writeText(url).then(function(){
                if(icon) icon.className='fas fa-check';
                if(txt)  txt.textContent='Copied!';
                setTimeout(function(){ if(icon) icon.className='fas fa-link'; if(txt) txt.textContent='Copy link'; }, 1800);
            });
        });
    });

    /* ── snap: compose a shareable image (photo + logo + category + title) ── */
    document.querySelectorAll('.js-snap-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            var origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="snap-text">Generating…</span>';

            function restore() { btn.disabled = false; btn.innerHTML = origHtml; }

            buildSnapCard(btn.dataset.image, btn.dataset.logo, btn.dataset.title, btn.dataset.category, btn.dataset.date)
                .then(function (canvas) { return saveSnapCanvas(canvas); })
                .catch(function (err) {
                    console.error('Snap failed:', err);
                    alert('Could not create the snapshot. Please try again.');
                })
                .then(restore, restore);
        });
    });

    function loadImage(src, crossOrigin) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            if (crossOrigin) img.crossOrigin = 'anonymous';
            img.onload = function () { resolve(img); };
            img.onerror = function () { reject(new Error('Failed to load image: ' + src)); };
            img.src = src;
        });
    }

    /** Draw img into the given box, cover-fit (crop to fill, preserve aspect ratio). */
    function drawCover(ctx, img, x, y, w, h) {
        var ir = img.width / img.height, tr = w / h, sx, sy, sw, sh;
        if (ir > tr) { sh = img.height; sw = sh * tr; sx = (img.width - sw) / 2; sy = 0; }
        else { sw = img.width; sh = sw / tr; sx = 0; sy = (img.height - sh) / 2; }
        ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
    }

    /** Wraps text to maxWidth, draws up to maxLines (last one ellipsised), returns the y after the block. */
    function wrapText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
        var words = String(text || '').split(/\s+/).filter(Boolean);
        var lines = [];
        var line = '';
        for (var i = 0; i < words.length; i++) {
            var test = line ? line + ' ' + words[i] : words[i];
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = words[i];
            } else {
                line = test;
            }
        }
        if (line) lines.push(line);

        if (lines.length > maxLines) {
            lines = lines.slice(0, maxLines);
            var last = lines[maxLines - 1];
            while (ctx.measureText(last + '…').width > maxWidth && last.length > 1) {
                last = last.slice(0, -1);
            }
            lines[maxLines - 1] = last + '…';
        }

        lines.forEach(function (l, idx) { ctx.fillText(l, x, y + idx * lineHeight); });
        return y + lines.length * lineHeight;
    }

    function buildSnapCard(imageUrl, logoUrl, title, category, dateStr) {
        var W = 1080, H = 1350, PHOTO_H = 950, PAD = 60;

        return loadImage(imageUrl, true).then(function (photo) {
            var canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            var ctx = canvas.getContext('2d');

            drawCover(ctx, photo, 0, 0, W, PHOTO_H);

            var HEADING_FONT = '"Montserrat", -apple-system, "Segoe UI", sans-serif';

            return loadImage(logoUrl, true).catch(function () { return null; }).then(function (logo) {
                /* Wait for the Montserrat webfont so canvas text doesn't fall back silently. */
                var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
                return fontsReady.then(function () {
                    if (logo) {
                        var size = 96, topMargin = 56, rightMargin = 56;
                        ctx.save();
                        ctx.shadowColor = 'rgba(0,0,0,.45)';
                        ctx.shadowBlur = 14;
                        ctx.drawImage(logo, W - size - rightMargin, topMargin, size, size);
                        ctx.restore();
                    }

                    /* footer */
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, PHOTO_H, W, H - PHOTO_H);
                    ctx.fillStyle = '#800000';
                    ctx.fillRect(0, PHOTO_H, W, 6);

                    var y = PHOTO_H + 78;

                    /* "CATEGORY - OROMA NEWS", black, bold, caps */
                    var catLine = ((category || '').trim() ? category.trim().toUpperCase() + ' - ' : '') + 'OROMA NEWS';
                    ctx.fillStyle = '#000000';
                    ctx.font = '700 32px ' + HEADING_FONT;
                    ctx.textBaseline = 'alphabetic';
                    ctx.fillText(catLine, PAD, y);

                    y += 56;

                    /* title, in the site's maroon */
                    ctx.fillStyle = '#800000';
                    ctx.font = '800 46px ' + HEADING_FONT;
                    y = wrapText(ctx, title || '', PAD, y, W - PAD * 2, 56, 4);

                    /* publish date — important context for anyone sharing the snap */
                    if (dateStr) {
                        ctx.fillStyle = '#6b7280';
                        ctx.font = '600 28px ' + HEADING_FONT;
                        ctx.fillText(dateStr, PAD, y + 20);
                    }

                    return canvas;
                });
            });
        });
    }

    function saveSnapCanvas(canvas) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (!blob) { reject(new Error('Canvas produced no image data.')); return; }

                var filename = 'oroma-news-' + Date.now() + '.png';
                var file = (typeof File !== 'undefined') ? new File([blob], filename, { type: 'image/png' }) : null;

                /* Prefer the native share sheet (has a "Save Image"/"Save to Photos" option on
                   most mobile browsers) — falls back to a plain download otherwise. */
                if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
                    navigator.share({ files: [file], title: 'Oroma News' }).then(resolve).catch(function (err) {
                        if (err && err.name === 'AbortError') { resolve(); return; }
                        downloadBlob(blob, filename);
                        resolve();
                    });
                    return;
                }

                downloadBlob(blob, filename);
                resolve();
            }, 'image/png');
        });
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }

    /* ── sticky header shadow on scroll ── */
    var hdr = document.getElementById('siteHeader');
    if (hdr) {
        window.addEventListener('scroll', function(){
            hdr.style.boxShadow = window.scrollY > 10 ? '0 2px 16px rgba(0,0,0,.13)' : '';
        }, {passive:true});
    }

})();

/* ── newsletter submit (global) ── */
function handleNewsletter(e) {
    e.preventDefault();
    var btn = e.target.querySelector('button');
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Subscribed!';
    btn.disabled = true;
    setTimeout(function(){ btn.innerHTML = orig; btn.disabled = false; e.target.reset(); }, 3000);
}
