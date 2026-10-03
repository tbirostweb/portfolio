<?php
// Jeton anti-spam du formulaire de contact : un HMAC signé sur l'horodatage
// du rendu de la page. send_mail.php vérifie la signature et le délai
// minimum écoulé avant d'accepter le message (voir plus bas dans ce fichier).
require __DIR__ . '/vendor/autoload.php';
try {
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    // Pas de .env : normal en prod, les variables viennent de l'environnement.
}
$contactFormTs    = time();
$contactFormToken = hash_hmac('sha256', (string) $contactFormTs, $_ENV['CONTACT_FORM_SECRET'] ?? '');

// Cache-busting : ajoute ?v=<empreinte du contenu> aux fichiers statiques.
// Le .htaccess met ces URL versionnées en cache 1 an : dès qu'un fichier
// change, son empreinte (donc son URL) change et le navigateur le recharge.
function asset(string $path): string {
    $file = __DIR__ . '/' . $path;
    return is_file($file) ? $path . '?v=' . substr(md5_file($file), 0, 8) : $path;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portfolio · Théo Birost </title>
<meta name="description" content="Portfolio de Théo Birost, développeur web full-stack à Troyes (BUT MMI). Projets réels en Vue, JavaScript, PHP et WordPress — du design au déploiement. Disponible en CDI et freelance.">
<link rel="canonical" href="https://portfolio.theo-birost.fr/">
<meta property="og:title" content="Théo Birost — Développeur web full-stack">
<meta property="og:description" content="Portfolio — projets web réels, du design au déploiement. Disponible CDI & freelance.">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:url" content="https://portfolio.theo-birost.fr/">
<meta property="og:image" content="https://portfolio.theo-birost.fr/og-image.png">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Théo Birost — Développeur web full-stack">
<meta name="twitter:description" content="Portfolio — projets web réels, du design au déploiement.">
<meta name="twitter:image" content="https://portfolio.theo-birost.fr/og-image.png">
<link rel="icon" href="<?= asset('img/TB.png') ?>" type="image/png">
<!-- Polices auto-hébergées (RGPD : aucune requête vers Google) -->
<link rel="preload" href="fonts/ibmplexsans-400-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="fonts/ibmplexsanscondensed-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('fonts/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('output.css') ?>">
<link rel="stylesheet" href="<?= asset('studio.css') ?>">
<!-- Animations : GSAP auto-hébergé (CSP 'self'), voir js/motion/ -->
<link rel="stylesheet" href="<?= asset('motion.css') ?>">
<script src="<?= asset('js/motion/init.js') ?>"></script>
<script defer src="<?= asset('js/lib/gsap.min.js') ?>"></script>
<script defer src="<?= asset('js/lib/ScrollTrigger.min.js') ?>"></script>
<script defer src="<?= asset('js/lib/SplitText.min.js') ?>"></script>
<script defer src="<?= asset('js/motion/core.js') ?>"></script>
<script defer src="<?= asset('js/motion/site.js') ?>"></script>
</head>
<body>

<header class="nav">
  <div class="wrap nav__in">
    <a href="#top" class="brand"><b></b>Théo Birost</a>
    <nav class="nav__links" id="menu">
      <a href="#projets">Projets</a>
      <a href="#competences">Compétences</a>
      <a href="#parcours">Parcours</a>
      <a href="#prestations">Prestations</a>
      <a href="#contact" class="btn btn-accent">Me contacter</a>
    </nav>
    <button class="nav__toggle" id="toggle" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button>
  </div>
</header>

<main id="top">

<!-- HERO -->
<section class="hero">
  <div class="wrap hero__grid">
    <div>
      <span class="chip"><b></b>Disponible · CDI &amp; freelance · Remote</span>
      <h1>Développeur web <span class="accent">full-stack</span>.</h1>
      <p class="hero__sub">Moi c'est Théo, basé à Troyes, diplômé d'un BUT MMI. J'aime prendre un projet depuis la première ébauche jusqu'à sa mise en ligne, en soignant autant le code que ce que voit l'utilisateur. Envie de voir ce que ça donne ?</p>
      <div class="hero__cta">
        <a href="#projets" class="btn btn-accent btn-lg">Voir mes projets
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        <a href="#contact" class="btn btn-ghost btn-lg">Me contacter</a>
        <a href="<?= asset('pdf/CV.pdf') ?>" class="tlink" download>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
          Mon CV (PDF)</a>
      </div>
    </div>
    <aside class="hero__card">
      <!-- Remplace le bloc .portrait par : <div class="portrait"><img src="ta-photo.jpg" alt="Théo Birost"></div> -->
      <div class="portrait">
        <span class="portrait__mono">TB</span>
        <div class="portrait__badge"><span>Théo Birost</span><span class="dot">● Troyes, FR</span></div>
      </div>
    </aside>
  </div>
</section>

<!-- FACTS -->
<div class="facts">
  <div class="wrap facts__grid">
    <div class="fcell"><span class="n">01</span><div class="t">Full-stack — front &amp; back</div></div>
    <div class="fcell"><span class="n">02</span><div class="t">8 projets réalisés</div></div>
    <div class="fcell"><span class="n">03</span><div class="t">BUT MMI · Bac+3</div></div>
    <div class="fcell"><span class="n">04</span><div class="t">Dispo — CDI &amp; freelance</div></div>
  </div>
</div>

<!-- PROJETS -->
<section class="section" id="projets">
  <div class="wrap">
    <div class="sec-head">
      <span class="eyebrow">Projets</span>
      <h2 class="h2 reveal">Ce que j'ai fait récemment.</h2>
      <p class="lead reveal">Quelques projets concrets, du site client livré en prod à l'appli perso pour apprendre.</p>
    </div>
    <div class="projects">

      <article class="project reveal">
        <div class="shot">
          <div class="shot__bar"><i></i><i></i><i></i><span class="shot__url">maisondubonheurstesavine.fr</span></div>
          <div class="shot__img"><img src="<?= asset('img/maisondubonheur.webp') ?>" alt="Maison du Bonheur — site de réservation" width="1400" height="875" loading="lazy" decoding="async"></div>
        </div>
        <div>
          <span class="project__k">Projet client · Stage</span>
          <h3>Maison du Bonheur</h3>
          <span class="project__ctx">Site de réservation · Deux logements touristiques</span>
          <p>Le site vitrine de deux appartements meublés à Troyes et Sainte-Savine. J'ai mis en avant les logements, les avis des voyageurs et l'accueil de l'hôtesse, avec un parcours clair qui mène jusqu'à la réservation. Un site rapide et soigné, pensé pour donner envie de poser ses valises.</p>
          <p class="project__role"><b>Mon rôle</b>Seul développeur, de la conception à la mise en ligne, pendant mon stage à la SCI Birost.</p>
          <div class="tags"><span class="tag">Vue 3</span><span class="tag">Vite</span><span class="tag">JavaScript</span><span class="tag">SEO</span></div>
          <div class="project__links">
            <a class="plink" href="https://maisondubonheurstesavine.fr/" target="_blank" rel="noopener">Voir le site
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
          </div>
        </div>
      </article>

      <article class="project reveal">
        <div class="shot">
          <div class="shot__bar"><i></i><i></i><i></i><span class="shot__url">hydrogenbusinessforclimate.com</span></div>
          <div class="shot__img"> <img src="<?= asset('img/hydrogen_website.webp') ?>" alt="Forum Hydrogen" width="1400" height="804" loading="lazy" decoding="async">
            </div>
        </div>
        <div>
          <span class="project__k">Projet client · Stage</span>
          <h3>Forum Hydrogen Business for Climate</h3>
          <span class="project__ctx">Événement B2B international · Refonte complète</span>
          <p>Le site de ce forum international dédié à l'hydrogène avait besoin d'un vrai coup de neuf. Je me suis occupé de la bascule vers une version bilingue FR/EN, j'ai créé des blocs sur-mesure pour présenter intervenants, exposants et presse, et j'ai chassé les lenteurs sur un contenu pourtant dense.</p>
          <p class="project__role"><b>Mon rôle</b>Seul développeur sur le projet, de l'intégration au déploiement, pendant mon stage chez AER BFC.</p>
          <div class="tags"><span class="tag">WordPress</span><span class="tag">ACF</span><span class="tag">CPT UI</span><span class="tag">TranslatePress</span><span class="tag">PHP</span><span class="tag">SEO</span></div>
          <div class="project__links">
            <a class="plink" href="https://www.hydrogenbusinessforclimate.com" target="_blank" rel="noopener">Voir le site
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
          </div>
        </div>
      </article>

      <article class="project reveal">
        <div class="shot">
          <div class="shot__bar"><i></i><i></i><i></i><span class="shot__url">generique.theo-birost.fr</span></div>
          <div class="shot__img"><img src="<?= asset('img/generique.webp') ?>" alt="Générique — index de cinéma" width="1400" height="875" loading="lazy" decoding="async"></div>
        </div>
        <div>
          <span class="project__k">Projet perso · Application web</span>
          <h3>Générique</h3>
          <span class="project__ctx">Index de cinéma · Recherche &amp; fiches détaillées</span>
          <p>Une application pour explorer un index de films, d'interprètes et de réalisateurs : recherche instantanée, fiches détaillées et données à jour. Une base full-stack complète, du front jusqu'à la logique serveur.</p>
          <p class="project__role"><b>Mon rôle</b>Conception et développement complet, du front à l'API.</p>
          <div class="tags"><span class="tag">Vue 3</span><span class="tag">Vite</span><span class="tag">JavaScript</span><span class="tag">API</span></div>
          <div class="project__links">
            <a class="plink" href="https://generique.theo-birost.fr" target="_blank" rel="noopener">Voir le projet
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
          </div>
        </div>
      </article>

      <article class="project reveal">
        <div class="shot">
          <div class="shot__bar"><i></i><i></i><i></i><span class="shot__url">clicker — jeu</span></div>
          <div class="shot__img">
          <img src="<?= asset('img/clicker_img.webp') ?>" alt="Jeu du clicker" width="1400" height="741" loading="lazy" decoding="async">
          </div>
        </div>
        <div>
          <span class="project__k">Projet perso · Solo</span>
          <h3>Jeu du clicker</h3>
          <span class="project__ctx">Jeu de clic old-school · Système de progression</span>
          <p>Un petit jeu de clic old-school : on accumule des points, on débloque des améliorations et on grimpe au classement.</p>
          <p class="project__role"><b>Mon rôle</b>Conception et développement complet, du front au back.</p>
          <div class="tags"><span class="tag">HTML</span><span class="tag">Tailwind</span><span class="tag">PHP</span><span class="tag">Docker</span></div>
          <div class="project__links">
            <a class="plink" href="https://portfolio.theo-birost.fr/clicker/" target="_blank" rel="noopener">Voir le projet
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
          </div>
        </div>
      </article>

    </div>

    <div class="subhead reveal">Autres projets — perso</div>
    <div class="mini-grid reveal">
      <article class="mini">
        <span class="mini__k">Défi perso · Intégration</span>
        <h3>One Page Challenge</h3>
        <p>Un défi que je me lance : concevoir et coder une landing page complète en une heure, from scratch. Quatre pages à ce jour — e-commerce, voyage, tech et SaaS.</p>
        <div class="tags"><span class="tag">Vue 3</span><span class="tag">Vite</span><span class="tag">Tailwind</span><span class="tag">HTML</span></div>
        <a class="plink" href="https://onepage.birostweb.fr" target="_blank" rel="noopener">Voir le projet <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
      </article>
      <article class="mini">
        <span class="mini__k">Jeu web · Solo</span>
        <h3>Empire Culturel</h3>
        <p>Jeu incrémental de collection de monuments : farm, boosters, arbre technologique et classement. Parties sauvegardées en local.</p>
        <div class="tags"><span class="tag">Vue 3</span><span class="tag">Tailwind</span><span class="tag">JavaScript</span></div>
        <a class="plink" href="https://carte.theo-birost.fr" target="_blank" rel="noopener">Voir le projet <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
      </article>
      <article class="mini">
        <span class="mini__k">Projet perso · Solo</span>
        <h3>Sidequest</h3>
        <p>Une collection de mini-jeux à découvrir dans le navigateur : dactylographie, lancer de dés, morpion et Snake.</p>
        <div class="tags"><span class="tag">Mini-jeux</span><span class="tag">Jeu web</span></div>
        <a class="plink" href="https://jeu.birostweb.fr/" target="_blank" rel="noopener">Voir le projet <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
      </article>
      <article class="mini">
        <span class="mini__k">Projet perso · Solo</span>
        <h3>Portfolio template</h3>
        <p>Un template de portfolio en Tailwind et PHP, multilingue FR/EN, pensé pour être facile à reprendre et à adapter.</p>
        <div class="tags"><span class="tag">Tailwind</span><span class="tag">HTML</span><span class="tag">JavaScript</span><span class="tag">PHP</span></div>
        <a class="plink" href="http://mmi23f02.mmi-troyes.fr/portfolio-v3/" target="_blank" rel="noopener">Voir le projet <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
      </article>
    </div>
  </div>
</section>

<!-- COMPETENCES -->
<section class="section" id="competences" style="background:#DEDACD">
  <div class="wrap">
    <div class="sec-head">
      <span class="eyebrow">Compétences</span>
      <h2 class="h2 reveal">Ma stack technique.</h2>
      <p class="lead reveal">Les outils et langages que j'utilise au quotidien, du front au déploiement.</p>
    </div>
    <div class="skills reveal">
      <div class="skillrow"><h3>Front-end</h3><div class="skilltags"><span class="tag">HTML</span><span class="tag">CSS</span><span class="tag">SCSS</span><span class="tag">JavaScript</span><span class="tag">Vue.js</span><span class="tag">Bootstrap</span><span class="tag">Tailwind</span><span class="tag">SEO</span></div></div>
      <div class="skillrow"><h3>Back-end</h3><div class="skilltags"><span class="tag">PHP</span><span class="tag">Symfony</span><span class="tag">Node.js</span><span class="tag">MySQL</span><span class="tag">MongoDB</span><span class="tag">GraphQL</span><span class="tag">JSON</span></div></div>
      <div class="skillrow"><h3>Outils</h3><div class="skilltags"><span class="tag">Docker</span><span class="tag">VS Code</span><span class="tag">PhpStorm</span><span class="tag">WebStorm</span><span class="tag">GitHub</span><span class="tag">GitHub Actions</span><span class="tag">WordPress</span><span class="tag">Blender</span><span class="tag">Unity</span></div></div>
      <div class="skillrow"><h3>UI/UX &amp; Design</h3><div class="skilltags"><span class="tag">Figma</span><span class="tag">Canva</span><span class="tag">Illustrator</span><span class="tag">Photoshop</span><span class="tag">Dimension</span></div></div>
      <div class="skillrow"><h3>IA</h3><div class="skilltags"><span class="tag">Claude</span><span class="tag">ChatGPT</span><span class="tag">Gemini</span><span class="tag">Perplexity</span></div></div>
    </div>
  </div>
</section>

<!-- PARCOURS -->
<section class="section" id="parcours">
  <div class="wrap">
    <div class="sec-head">
      <span class="eyebrow">Parcours</span>
      <h2 class="h2 reveal">Mon parcours.</h2>
    </div>
    <p class="about reveal">Je suis développeur <b>full-stack</b>, curieux et plutôt exigeant avec moi-même. Ce qui me plaît, c'est de suivre un projet du premier croquis jusqu'à sa mise en ligne, en soignant autant le <b>code</b> que ce que voit l'utilisateur final. Je cherche aujourd'hui une équipe où continuer à apprendre, en <b>CDI</b> comme en <b>freelance</b>.</p>
    <div class="tl reveal">
      <div class="tlitem">
        <span class="tldate">22 juin – 15 août 2026 · 8 semaines</span>
        <h3>Développement web et communication digitale — Stage</h3>
        <p class="tlrole">SCI BIROST</p>
        <p>Mission : création d'un site de réservation pour deux logements touristiques, gestion et développement de leur communication digitale, et participation à la promotion publicitaire des hébergements.</p>
      </div>
      <div class="tlitem">
        <span class="tldate">20 avril – 19 juin 2026 · 9 semaines</span>
        <h3>Développeur web — Stage</h3>
        <p class="tlrole">AER BFC · Agence économique régionale de Bourgogne-Franche-Comté · Dijon</p>
        <p>Contribution à un projet de refonte de site internet. Travail sur le site du Forum Hydrogen Business for Climate : intégration WordPress, développements PHP, version multilingue et optimisation des performances.</p>
      </div>
      <div class="tlitem">
        <span class="tldate">2023 – 2026</span>
        <h3>BUT MMI — Bac+3</h3>
        <p class="tlrole">Métiers du Multimédia et de l'Internet</p>
        <p>Formation full-stack complète : développement web front &amp; back, design d'interface, gestion de projet et culture numérique.</p>
      </div>
    </div>
  </div>
</section>

<!-- SITE PRO / PRESTATIONS -->
<section class="section" id="prestations" style="background:#DEDACD">
  <div class="wrap">
    <div style="display:flex;flex-wrap:wrap;gap:clamp(20px,4vw,40px);align-items:center;justify-content:space-between">
      <div style="flex:1 1 320px">
        <span class="eyebrow">Prestations</span>
        <h2 class="h2" style="margin-top:14px">Un projet web pour votre activité&nbsp;?</h2>
        <p class="lead">Sites vitrines, boutiques et applications sur-mesure — de la conception au déploiement. Mes offres, mes tarifs et un formulaire de devis sont sur mon site professionnel.</p>
      </div>
      <a href="https://birostweb.fr" target="_blank" rel="noopener" class="btn btn-accent btn-lg" style="white-space:nowrap">Voir mes prestations
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></a>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="section section--dark cta" id="contact">
  <div class="wrap">
    <div class="cta__grid">
      <div>
        <span class="eyebrow" style="margin-bottom:16px">Contact</span>
        <h2 class="h2">Travaillons ensemble.</h2>
        <p class="lead">Une offre en CDI, une mission freelance, ou juste envie de discuter ? Je réponds vite.</p>
        <div class="manifest">
          <div class="mrow"><span class="mk">Mail</span><span class="mv"><a href="mailto:contact@theo-birost.fr">contact@theo-birost.fr</a></span></div>
          <div class="mrow"><span class="mk">GitHub</span><span class="mv"><a href="https://github.com/theobirost" target="_blank" rel="noopener">github.com/theobirost</a></span></div>
          <div class="mrow"><span class="mk">LinkedIn</span><span class="mv"><a href="https://www.linkedin.com/in/theobirost" target="_blank" rel="noopener">linkedin.com/in/theobirost</a></span></div>
          <div class="mrow"><span class="mk">Lieu</span><span class="mv">Troyes (10) · France · Remote</span></div>
        </div>
      </div>
      <form class="form" id="cform" method="post" action="send_mail.php">
        <div class="field"><label for="name">Nom</label><input id="name" name="name" type="text" autocomplete="name" required maxlength="100"></div>
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="254"></div>
        <div class="field"><label for="message">Votre message</label><textarea id="message" name="message" required maxlength="5000"></textarea></div>
        <input type="hidden" name="ts" value="<?= htmlspecialchars((string) $contactFormTs, ENT_QUOTES) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($contactFormToken, ENT_QUOTES) ?>">
        <div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">
          <label for="website">Laisser ce champ vide</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-accent btn-lg" style="justify-content:center">Envoyer
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
      </form>
    </div>
  </div>
</section>
</main>

<footer class="footer">
  <div class="wrap">
    <div class="footer__in">
      <span class="footer__b">Théo Birost — Développeur web full-stack</span>
      <nav class="footer__l"><a href="#projets">Projets</a><a href="#competences">Compétences</a><a href="#parcours">Parcours</a><a href="#contact">Contact</a><a href="#top">↑ Haut</a></nav>
    </div>
    <div class="footer__meta"><span>© 2026 Théo Birost · Troyes, France</span><span>Développeur web full-stack</span></div>
  </div>
</footer>

<script>
// --- Script pour le menu de navigation mobile (burger menu) ---
(function() {
  var toggleBtn = document.getElementById('toggle'); // Le bouton burger
  var menu = document.getElementById('menu'); // Le menu de navigation

  // Au clic sur le bouton
  toggleBtn.addEventListener('click', function() {
    // On ajoute/supprime la classe 'open' sur le menu pour l'afficher/cacher
    var isOpen = menu.classList.toggle('open');
    // On met à jour l'attribut 'aria expanded' pour l'accessibilité
    toggleBtn.setAttribute('aria-expanded', isOpen);
  });

  // Pour fermer le menu quand on clique sur un lien (sur mobile).
  menu.querySelectorAll('a').forEach(function(link) {
    link.addEventListener('click', function() {
      menu.classList.remove('open');
      toggleBtn.setAttribute('aria-expanded', false);
    });
  });
})();

// --- Script pour le formulaire de contact ---
(function() {
  var form = document.getElementById('cform');
  form.addEventListener('submit', function(event) {
    event.preventDefault();
    var formData = new FormData(form);

    fetch('send_mail.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.text())
    .then(text => {
      alert(text);
      form.reset();
    })
    .catch(function() {
      alert("Une erreur est survenue. Merci de réessayer.");
    });
  });
})();

// --- Script pour l'animation d'apparition au défilement ---
(function() {
  // Si l'utilisateur préfère des animations réduites, on ne fait rien.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  // On crée un "observateur" qui va surveiller quand les éléments entrent dans la fenêtre
  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      // Si l'élément est visible...
      if (entry.isIntersecting) {
        // ... on lui ajoute la classe 'in' pour déclencher l'animation CSS
        entry.target.classList.add('in');
        // Et on arrête de l'observer pour ne pas répéter l'animation
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 }); // L'animation se déclenche quand 12% de l'élément est visible

  // On demande à l'observateur de surveiller tous les éléments avec la classe '.reveal'
  document.querySelectorAll('.reveal').forEach(function(element) {
    observer.observe(element);
  });
})();
</script>
</body>
</html>