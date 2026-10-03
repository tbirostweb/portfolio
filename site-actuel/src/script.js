// --- Script pour le menu de navigation mobile (burger menu) ---
(function() {
  const toggleBtn = document.getElementById('toggle');
  const menu = document.getElementById('menu');

  if (toggleBtn && menu) {
    toggleBtn.addEventListener('click', function() {
      const isOpen = menu.classList.toggle('open');
      toggleBtn.setAttribute('aria-expanded', isOpen);
    });

    menu.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function() {
        menu.classList.remove('open');
        toggleBtn.setAttribute('aria-expanded', false);
      });
    });
  }
})();

// --- Script pour l'animation d'apparition au défilement ---
(function() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('in');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.reveal').forEach(function(element) {
    observer.observe(element);
  });
})();