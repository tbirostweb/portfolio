/* Animations portfolio.theo-birost.fr — plus démonstratives que birostweb :
   texte découpé, timeline qui se trace, parallaxes légères, curseur sur
   les projets. Défilement natif, rien n'est épinglé.
   Utilitaires : js/motion/core.js */
Motion.run(function (m) {
  var gsap = m.gsap;

  /* ---------- Barre de progression de lecture ---------- */
  var bar = document.createElement('div');
  bar.className = 'm-progress';
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);
  m.onCleanup(function () { bar.remove(); });
  gsap.fromTo(bar, { scaleX: 0 }, { scaleX: 1, ease: 'none',
    scrollTrigger: { trigger: document.documentElement, start: 'top top', end: 'bottom bottom', scrub: .3 } });

  /* ---------- Hero ---------- */
  var hero = document.querySelector('.hero');
  if (hero) {
    var tl = gsap.timeline({ delay: .1 });
    m.add(tl, m.fadeUp('.hero .chip', { trigger: false, y: 12, duration: .6 }), 0);
    m.add(tl, m.words('.hero h1', { trigger: false, chars: true, yPercent: 115, rotate: 8, stagger: .028, duration: 1.1 }), .1);
    m.add(tl, m.words('.hero__sub', { trigger: false, aria: 'none', yPercent: 100, stagger: .012, duration: .8 }), .55);
    m.add(tl, m.fadeUp('.hero__cta > *', { trigger: false, y: 18, stagger: .08, duration: .8, ease: 'back.out(1.6)' }), .9);

    var card = hero.querySelector('.hero__card');
    if (card) {
      m.track([card]);
      gsap.set(card, { autoAlpha: 1 });
      tl.fromTo(card.querySelector('.portrait'), { clipPath: 'inset(100% 0% 0% 0%)' },
        { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.3, ease: 'power4.inOut', clearProps: 'clipPath' }, .15);
      m.add(tl, m.words(card.querySelector('.portrait__mono'), { trigger: false, chars: true, mask: 'chars', aria: 'none',
        yPercent: 100, stagger: .1, duration: 1, ease: 'power4.out' }), .75);
      tl.from(card.querySelector('.portrait__badge'), { autoAlpha: 0, y: 16, duration: .7, ease: 'power3.out',
        clearProps: 'transform,opacity,visibility' }, 1.05);
    }
  }

  /* ---------- Chiffres clés ---------- */
  // Même déclencheur pour tout le bandeau : aucun chiffre ne s'affiche à « 0 » en attendant.
  var facts = document.querySelector('.facts');
  m.batch('.fcell', { y: 20, stagger: .1, start: 'top 95%' });
  m.roll('.fcell .n', { trigger: facts, start: 'top 95%' });
  m.countUp(m.$$('.fcell .t').filter(function (el) { return /^\d/.test(el.textContent); }),
    { trigger: facts, start: 'top 95%', duration: 1.4, delay: .2 });

  /* ---------- En-têtes de section ---------- */
  m.$$('.sec-head').forEach(function (h) {
    m.sectionHead(h, { title: { stagger: .05, rotate: 5, duration: 1 } });
  });

  /* ---------- Projets : carte qui monte, capture qui se dévoile ---------- */
  m.$$('.project').forEach(function (p) {
    var ptl = gsap.timeline({ scrollTrigger: { trigger: p, start: 'top 85%', once: true } });
    m.add(ptl, m.fadeUp(p, { trigger: false, y: 80, scale: .96, duration: 1.1 }), 0);
    var shot = p.querySelector('.shot');
    if (shot) ptl.fromTo(shot, { clipPath: 'inset(0% 0% 100% 0%)' },
      { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.2, ease: 'power4.inOut', clearProps: 'clipPath' }, .15);
    m.add(ptl, m.fadeUp(p.querySelectorAll(':scope > div:last-child > *'), { trigger: false, y: 20, stagger: .06, duration: .7 }), .35);
  });
  m.fadeUp('.subhead', { x: -16, y: 0, duration: .8 });
  m.batch('.mini', { y: 40, scale: .97, stagger: .1, duration: .8 });

  /* ---------- Stack technique : ligne par ligne, tags en cascade ---------- */
  m.$$('.skillrow').forEach(function (row) {
    var rtl = gsap.timeline({ scrollTrigger: { trigger: row, start: 'top 88%', once: true } });
    m.add(rtl, m.fadeUp(row.querySelector('h3'), { trigger: false, x: -24, y: 0, duration: .7 }), 0);
    m.add(rtl, m.fadeUp(row.querySelectorAll('.tag'), { trigger: false, y: 12, scale: .9, stagger: .035, duration: .5, ease: 'back.out(2)' }), .1);
  });

  /* ---------- Parcours : texte qui s'allume au scroll ---------- */
  var about = document.querySelector('.about');
  if (about && window.SplitText) {
    m.track([about]);
    var aboutSplit = new SplitText(about, { type: 'words', aria: 'none', wordsClass: 'aw' });
    m.onCleanup(function () { aboutSplit.revert(); });
    gsap.fromTo(aboutSplit.words, { opacity: .2 }, { opacity: 1, stagger: .1, ease: 'none',
      scrollTrigger: { trigger: about, start: 'top 80%', end: 'bottom 55%', scrub: true } });
  }

  /* ---------- Timeline : la ligne se trace, les étapes s'allument ---------- */
  var tlEl = document.querySelector('.tl');
  if (tlEl) {
    m.track([tlEl]);
    gsap.fromTo(tlEl, { '--tl': 0 }, { '--tl': 1, ease: 'none',
      scrollTrigger: { trigger: tlEl, start: 'top 70%', end: 'bottom 60%', scrub: .4 } });
    m.$$('.tlitem', tlEl).forEach(function (it) {
      var itl = gsap.timeline({ scrollTrigger: { trigger: it, start: 'top 78%', once: true } });
      itl.fromTo(it, { '--dot': 0 }, { '--dot': 1, duration: .6, ease: 'back.out(3)' }, 0);
      m.add(itl, m.fadeUp(it.querySelectorAll(':scope > *'), { trigger: false, x: 30, y: 0, stagger: .07, duration: .7 }), .05);
    });
  }

  /* ---------- Prestations ---------- */
  var pre = document.getElementById('prestations');
  if (pre) {
    var prtl = gsap.timeline({ scrollTrigger: { trigger: pre, start: 'top 75%', once: true } });
    m.add(prtl, m.fadeUp(pre.querySelector('.eyebrow'), { trigger: false, x: -8, y: 0, duration: .6 }), 0);
    m.add(prtl, m.words(pre.querySelector('.h2'), { trigger: false, stagger: .05, rotate: 5 }), .1);
    m.add(prtl, m.fadeUp(pre.querySelectorAll('.lead, .btn'), { trigger: false, y: 16, stagger: .12 }), .35);
  }

  /* ---------- Contact : la section s'ouvre, puis son contenu ---------- */
  var cta = document.querySelector('.cta');
  if (cta) {
    gsap.fromTo(cta, { clipPath: 'inset(0% 3% 0% 3% round 3px)' }, { clipPath: 'inset(0% 0% 0% 0% round 0px)', ease: 'none',
      scrollTrigger: { trigger: cta, start: 'top bottom', end: 'top 35%', scrub: true } });
    var col = cta.querySelector('.cta__grid > div');
    var ctl = gsap.timeline({ scrollTrigger: { trigger: cta, start: 'top 70%', once: true } });
    if (col) {
      m.add(ctl, m.fadeUp(col.querySelector('.eyebrow'), { trigger: false, x: -8, y: 0, duration: .6 }), 0);
      m.add(ctl, m.words(col.querySelector('.h2'), { trigger: false, chars: true, stagger: .02, rotate: 6 }), .1);
      m.add(ctl, m.fadeUp(col.querySelectorAll(':scope > .lead'), { trigger: false, y: 14 }), .4);
    }
    m.add(ctl, m.fadeUp(cta.querySelector('.form'), { trigger: false, y: 40, duration: 1 }), .25);
    m.batch('.mrow', { x: -12, y: 0, stagger: .07, duration: .6 });
  }
}, {

  /* ---------- Grand écran : parallaxes légères ---------- */
  desktop: function (m) {
    var gsap = m.gsap;
    gsap.to('.hero__card', { y: 70, ease: 'none',
      scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
    m.$$('.project').forEach(function (p) {
      var img = p.querySelector('.shot__img img');
      if (!img) return;
      gsap.set(img, { scale: 1.08 });
      gsap.fromTo(img, { yPercent: -3 }, { yPercent: 3, ease: 'none',
        scrollTrigger: { trigger: p, start: 'top bottom', end: 'bottom top', scrub: true } });
    });
  },

  /* ---------- Souris : magnétisme, tilt du portrait, curseur projets ---------- */
  pointer: function (m) {
    var gsap = m.gsap, root = document.documentElement;

    m.magnetic('.hero__cta .btn, #prestations .btn, .cta .btn-accent', .25);

    // Portrait : légère inclinaison 3D qui suit la souris.
    var card = document.querySelector('.hero__card');
    var portrait = card && card.querySelector('.portrait');
    if (portrait) {
      var mono = portrait.querySelector('.portrait__mono');
      gsap.set(portrait, { transformPerspective: 900 });
      var rx = gsap.quickTo(portrait, 'rotationX', { duration: .6, ease: 'power3.out' });
      var ry = gsap.quickTo(portrait, 'rotationY', { duration: .6, ease: 'power3.out' });
      var mx = mono && gsap.quickTo(mono, 'x', { duration: .8, ease: 'power3.out' });
      var my = mono && gsap.quickTo(mono, 'y', { duration: .8, ease: 'power3.out' });
      m.on(card, 'pointermove', function (e) {
        var r = card.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
        ry(px * 8); rx(-py * 8);
        if (mono) { mx(px * 18); my(py * 18); }
      });
      m.on(card, 'pointerleave', function () { rx(0); ry(0); if (mono) { mx(0); my(0); } });
      m.onCleanup(function () { gsap.set(mono ? [portrait, mono] : portrait, { clearProps: 'transform' }); });
    }

    // Curseur « Voir le projet » au survol des captures (clic = ouvre le projet).
    var shots = m.$$('.project .shot');
    if (!shots.length) return;
    var cursor = document.createElement('div');
    cursor.className = 'm-cursor';
    cursor.setAttribute('aria-hidden', 'true');
    cursor.innerHTML = 'Voir le projet <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg>';
    document.body.appendChild(cursor);
    root.classList.add('m-cursor-on');
    gsap.set(cursor, { xPercent: -50, yPercent: -50, scale: 0, autoAlpha: 0 });
    var cx = gsap.quickTo(cursor, 'x', { duration: .35, ease: 'power3.out' });
    var cy = gsap.quickTo(cursor, 'y', { duration: .35, ease: 'power3.out' });
    m.on(window, 'pointermove', function (e) { cx(e.clientX); cy(e.clientY); }, { passive: true });

    shots.forEach(function (shot) {
      var project = shot.closest('.project');
      var link = project && project.querySelector('.plink');
      var img = shot.querySelector('.shot__img img');
      m.on(shot, 'pointerenter', function (e) {
        gsap.set(cursor, { x: e.clientX, y: e.clientY });
        gsap.to(cursor, { scale: 1, autoAlpha: 1, duration: .35, ease: 'back.out(2)', overwrite: 'auto' });
        if (img) gsap.to(img, { scale: 1.14, duration: .9, ease: 'power3.out', overwrite: 'auto' });
      });
      m.on(shot, 'pointerleave', function () {
        gsap.to(cursor, { scale: 0, autoAlpha: 0, duration: .25, ease: 'power2.in', overwrite: 'auto' });
        if (img) gsap.to(img, { scale: window.matchMedia('(min-width: 921px)').matches ? 1.08 : 1, duration: .9, ease: 'power3.out', overwrite: 'auto' });
      });
      if (link) m.on(shot, 'click', function () { window.open(link.href, '_blank', 'noopener'); });
    });

    m.onCleanup(function () {
      cursor.remove();
      root.classList.remove('m-cursor-on');
    });
  }
});
