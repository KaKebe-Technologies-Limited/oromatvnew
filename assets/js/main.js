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
    document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeNav(); });

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
