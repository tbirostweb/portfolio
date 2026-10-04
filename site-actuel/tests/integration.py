#!/usr/bin/env python3
"""Tests d'intégration : image Docker + faux serveur SMTP local (aucun envoi réel).
Usage : docker build -t portfolio-audit . && python3 tests/integration.py
Secrets de test fictifs uniquement."""
import base64, hashlib, hmac, re, socket, subprocess, sys, threading, time, urllib.request, urllib.error, secrets, concurrent.futures as cf

IMAGE = 'portfolio-audit'; PORT = 28431; SECRET = 'test-secret-' + 'x' * 32
FAILS = 0
def check(name, ok):
    global FAILS
    print(('PASS ' if ok else 'FAIL ') + name)
    if not ok: FAILS += 1

class Sink:
    """Faux SMTP : mode 'ok' ou 'reject_data' (554 après DATA)."""
    def __init__(self, mode='ok'):
        self.mode = mode; self.count = 0
        self.srv = socket.socket(); self.srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.srv.bind(('0.0.0.0', 0)); self.srv.listen(8); self.port = self.srv.getsockname()[1]
        threading.Thread(target=self.loop, daemon=True).start()
    def loop(self):
        while True:
            try: c, _ = self.srv.accept()
            except OSError: return
            threading.Thread(target=self.handle, args=(c,), daemon=True).start()
    def handle(self, c):
        f = c.makefile('rwb', buffering=0)
        w = lambda s: f.write((s + '\r\n').encode())
        w('220 sink ESMTP')
        data = False
        try:
            while True:
                line = f.readline().decode(errors='replace')
                if not line: break
                if data:
                    if line.strip() == '.':
                        data = False
                        if self.mode == 'reject_data': w('554 rejected')
                        else: self.count += 1; w('250 queued')
                    continue
                u = line.strip().upper()
                if u.startswith('EHLO') or u.startswith('HELO'): w('250-sink'); w('250 AUTH LOGIN PLAIN')
                elif u.startswith('AUTH LOGIN'): w('334 VXNlcm5hbWU6'); f.readline(); w('334 UGFzc3dvcmQ6'); f.readline(); w('235 ok')
                elif u.startswith('AUTH PLAIN'): w('235 ok')
                elif u.startswith('MAIL') or u.startswith('RCPT') or u.startswith('RSET'): w('250 ok')
                elif u.startswith('DATA'): w('354 go'); data = True
                elif u.startswith('QUIT'): w('221 bye'); break
                else: w('250 ok')
        finally: c.close()

def run(name, **env):
    subprocess.run(['docker', 'rm', '-f', name], capture_output=True)
    args = ['docker', 'run', '-d', '--name', name, '-p', f'{PORT}:8080', '--add-host', 'host.docker.internal:host-gateway']
    for k, v in env.items(): args += ['-e', f'{k}={v}']
    subprocess.run(args + [IMAGE], check=True, capture_output=True)
    for _ in range(40):
        try:
            if http('/health.php')[0] == 200: return
        except Exception: pass
        time.sleep(0.5)
    print(subprocess.run(['docker', 'logs', name], capture_output=True, text=True).stderr[-800:]); raise SystemExit('container KO')

def stop(name): subprocess.run(['docker', 'rm', '-f', name], capture_output=True)

def http(path, data=None, headers=None):
    req = urllib.request.Request(f'http://127.0.0.1:{PORT}{path}', data=data, headers=headers or {})
    try:
        r = urllib.request.urlopen(req, timeout=30); return r.status, r.read().decode(), r.headers
    except urllib.error.HTTPError as e: return e.code, e.read().decode(), e.headers

def form_fields():
    s, body, _ = http('/')
    f = {k: re.search(r'name="%s" value="([^"]*)"' % k, body) for k in ('ts', 'nonce', 'token')}
    return None if not all(f.values()) else {k: v.group(1) for k, v in f.items()}

def post(fields, extra=None, raw=None):
    import urllib.parse
    d = dict(fields or {}); d.update({'name': 'Test', 'email': 'test@example.com', 'message': 'Message de test synthétique'}); d.update(extra or {})
    body = raw if raw is not None else urllib.parse.urlencode(d, doseq=True).encode()
    return http('/send_mail.php', body, {'Content-Type': 'application/x-www-form-urlencoded'})

def smtp_env(sink, **kw):
    e = dict(SMTP_HOST='host.docker.internal', SMTP_USERNAME='t@example.test', SMTP_PASSWORD='pw-fictif', SMTP_PORT=sink.port, SMTP_SECURE='none',
             CONTACT_TO='dest@example.test', CONTACT_FORM_SECRET=SECRET)
    e.update(kw); return e

def sign(ts, nonce): return hmac.new(SECRET.encode(), f'{nonce}.{ts}'.encode(), hashlib.sha256).hexdigest()

N = 'portfolio-it'
# ---------- 1. image : non-root, pas d'outils, healthcheck, fichiers protégés ----------
sink = Sink(); run(N, **smtp_env(sink, CONTACT_IP_MAX=50))
uid = subprocess.run(['docker', 'exec', N, 'id', '-u'], capture_output=True, text=True).stdout.strip()
check('process non-root (uid=%s)' % uid, uid not in ('', '0'))
cfg = subprocess.run(['docker', 'inspect', '--format', '{{.Config.User}}', N], capture_output=True, text=True).stdout.strip()
check('USER www-data configuré', cfg == 'www-data')
check('composer absent de l\'image finale', subprocess.run(['docker', 'exec', N, 'sh', '-c', 'command -v composer'], capture_output=True).returncode != 0)
check('code non inscriptible', subprocess.run(['docker', 'exec', N, 'sh', '-c', 'touch /var/www/html/x'], capture_output=True).returncode != 0)
time.sleep(12)
hs = subprocess.run(['docker', 'inspect', '--format', '{{.State.Health.Status}}', N], capture_output=True, text=True).stdout.strip()
check('healthcheck healthy (%s)' % hs, hs == 'healthy')
for p, codes in {'/js/': (403, 404), '/img/': (403, 404), '/vendor/autoload.php': (403,), '/composer.json': (403,), '/contact_lib.php': (403,), '/.env': (403, 404), '/.htaccess': (403,)}.items():
    check(f'{p} refusé', http(p)[0] in codes)
check('pages légales 200', http('/mentions-legales.html')[0] == 200 and http('/confidentialite.html')[0] == 200)
s, body, h = http('/')
check('en-têtes sécurité + CSP hash inline', 'frame-ancestors' in h.get('Content-Security-Policy', '') and h.get('X-Content-Type-Options') == 'nosniff')
inline = re.search(r'<script>(.*?)</script>', body, re.S).group(1)
hsh = base64.b64encode(hashlib.sha256(inline.encode()).digest()).decode()
check('hash CSP = script inline', hsh in h.get('Content-Security-Policy', ''))
s404 = http('/inexistant')
check('404 personnalisée + en-têtes', s404[0] == 404 and 'nosniff' == s404[2].get('X-Content-Type-Options'))
check('liens légaux + minlength + status', all(x in body for x in ('mentions-legales.html', 'confidentialite.html', 'minlength="10"', 'role="status"')))

# ---------- 2. nominal / rejeu / altération / types / taille ----------
f = form_fields(); check('jeton présent dans la page', f is not None)
time.sleep(3.2)
s, t, _ = post(f); check('POST nominal 200', s == 200); time.sleep(0.5); check('1 mail reçu', sink.count == 1)
s, t, _ = post(f); check('rejeu même jeton => 409', s == 409); time.sleep(0.5); check('toujours 1 mail', sink.count == 1)
f2 = form_fields(); time.sleep(3.2)
check('token altéré => 400', post({**f2, 'token': '0' * 64})[0] == 400)
check('nonce altéré => 400', post({**f2, 'nonce': 'a' * 32})[0] == 400)
check('champ tableau => 400 (pas de 500)', post(f2, {'name[]': 'x'}, raw=None)[0] in (400,))
check('message tableau => 400', post(f2, {'message': ['a', 'b']})[0] == 400)
now = int(time.time()); fut = str(now + 3600); nn = secrets.token_hex(16)
check('timestamp futur signé => 400', post({'ts': fut, 'nonce': nn, 'token': sign(fut, nn)})[0] == 400)
old = str(now - 7200); nn = secrets.token_hex(16)
check('jeton expiré signé => 400', post({'ts': old, 'nonce': nn, 'token': sign(old, nn)})[0] == 400)
check('corps > 32K => 413', post(f2, {'message': 'é' * 40000})[0] == 413)
check('GET send_mail.php => 403', http('/send_mail.php')[0] == 403)
check('CRLF nom: pas de 500', post(form_fields() or f2, {'name': 'A\r\nBcc: x@y.z'})[0] in (200, 400))
check('1 seul mail au total', sink.count == 1)

# ---------- 3. concurrence : 2 POST simultanés, au plus 1 mail ----------
stop(N); sink = Sink(); run(N, **smtp_env(sink, CONTACT_DAILY_MAX=100))
f = form_fields(); time.sleep(3.2)
with cf.ThreadPoolExecutor(2) as ex: res = [x.result()[0] for x in [ex.submit(post, f), ex.submit(post, f)]]
time.sleep(1); check('2 POST simultanés => {200,409} (%s)' % sorted(res), sorted(res) == [200, 409]); check('1 seul mail', sink.count == 1)

# ---------- 4. échec ambigu après DATA : pas de renvoi ----------
stop(N); sink = Sink('reject_data'); run(N, **smtp_env(sink))
f = form_fields(); time.sleep(3.2)
s, _, _ = post(f); check('SMTP refus DATA => 500 générique', s == 500)
s, _, _ = post(f); check('retry même jeton après ambigu => 409', s == 409)

# ---------- 5. failover avant DATA (connexion primaire impossible) ----------
stop(N); sink = Sink(); e = smtp_env(sink, SMTP_PORT=1)
e.update(SMTP2_HOST='host.docker.internal', SMTP2_USERNAME='t2@example.test', SMTP2_PASSWORD='pw-fictif', SMTP2_PORT=sink.port, SMTP2_SECURE='none')
run(N, **e); f = form_fields(); time.sleep(3.2)
s, _, _ = post(f); time.sleep(0.5); check('bascule secours (200, 1 mail)', s == 200 and sink.count == 1)

# ---------- 6. secret absent / court, kill-switch, stockage HS, quota ----------
for label, env in {'secret absent': {}, 'secret court': {'CONTACT_FORM_SECRET': 'court'}, 'kill-switch': {'CONTACT_FORM_DISABLED': '1'}, 'stockage quota HS': {'CONTACT_STATE_DIR': '/proc/nope'}}.items():
    stop(N); sink = Sink(); e = smtp_env(sink)
    if label in ('secret absent', 'secret court'): e.pop('CONTACT_FORM_SECRET')
    e.update(env); run(N, **e)
    if label != 'stockage quota HS':
        check(f'{label}: page sans jeton signé', form_fields() is None)
    s, _, _ = post({'ts': str(int(time.time()) - 10), 'nonce': 'a' * 32, 'token': sign(str(int(time.time()) - 10), 'a' * 32)})
    time.sleep(0.5); check(f'{label}: 503 et 0 SMTP', s == 503 and sink.count == 0)
stop(N); sink = Sink(); run(N, **smtp_env(sink))
codes = [post({'ts': '1', 'nonce': 'a' * 32, 'token': 'x'})[0] for _ in range(7)]
check('quota IP: 6e requête => 429 (%s)' % codes, codes[:5] == [400] * 5 and codes[5] == 429)
stop(N); sink = Sink(); run(N, **smtp_env(sink, CONTACT_DAILY_MAX=2))
codes = [post({'ts': '1', 'nonce': 'a' * 32, 'token': 'x'})[0] for _ in range(3)]
check('plafond global => 429 (%s)' % codes, codes == [400, 400, 429])
stop(N)
print('RESULT:', 'OK' if not FAILS else f'{FAILS} échec(s)'); sys.exit(1 if FAILS else 0)
