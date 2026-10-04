// Formulaire de contact : envoi fetch, un seul envoi à la fois, message conservé en cas d'erreur.
(function () {
  var form = document.getElementById('cform');
  if (!form) { return; }
  var status = document.getElementById('cform-status');
  var button = form.querySelector('button[type="submit"]');
  var busy = false;

  function show(text) { if (status) { status.textContent = text; } }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (busy) { return; }
    busy = true;
    if (button) { button.disabled = true; }
    show('Envoi en cours…');

    fetch('send_mail.php', { method: 'POST', body: new FormData(form) })
      .then(function (response) {
        return response.text().then(function (text) { return { ok: response.ok, text: text }; });
      })
      .then(function (r) {
        show(r.text);
        if (r.ok) { form.reset(); }
      })
      .catch(function () {
        show('Une erreur est survenue. Merci de réessayer.');
      })
      .then(function () {
        busy = false;
        if (button) { button.disabled = false; }
      });
  });
})();
