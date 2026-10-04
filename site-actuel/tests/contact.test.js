// node tests/contact.test.js : logique de js/contact.js avec DOM/fetch factices.
const fs = require('fs'), path = require('path'), assert = require('assert');
const code = fs.readFileSync(path.join(__dirname, '../src/js/contact.js'), 'utf8');

async function scenario(fetchImpl) {
  let handler, resets = 0, fetches = 0;
  const status = { textContent: '' }, button = { disabled: false };
  const form = {
    addEventListener: (_e, h) => { handler = h; },
    querySelector: () => button,
    reset: () => { resets++; },
  };
  const document = { getElementById: (id) => (id === 'cform' ? form : status) };
  global.FormData = function () {};
  const fetch = (...a) => { fetches++; return fetchImpl(...a); };
  new Function('document', 'fetch', code)(document, fetch);
  const ev = { preventDefault() {} };
  handler(ev); handler(ev); // double clic
  await new Promise((r) => setTimeout(r, 20));
  return { resets, fetches, status: status.textContent, disabled: button.disabled };
}
const resp = (ok, text) => () => Promise.resolve({ ok, text: () => Promise.resolve(text) });

(async () => {
  let r = await scenario(resp(false, 'Trop de tentatives'));
  assert.deepStrictEqual([r.fetches, r.resets, r.status, r.disabled], [1, 0, 'Trop de tentatives', false], '429');
  r = await scenario(resp(false, 'Erreur serveur'));
  assert.strictEqual(r.resets, 0, '500 : message conservé'); assert.strictEqual(r.fetches, 1);
  r = await scenario(resp(true, 'Merci'));
  assert.deepStrictEqual([r.fetches, r.resets, r.status], [1, 1, 'Merci'], 'succès : reset');
  r = await scenario(() => Promise.reject(new Error('net')));
  assert.strictEqual(r.resets, 0); assert.ok(/erreur/i.test(r.status)); assert.strictEqual(r.disabled, false);
  console.log('PASS contact.js : 1 requête pour 2 clics, reset uniquement si ok, bouton réactivé');
})().catch((e) => { console.error('FAIL', e.message); process.exit(1); });
