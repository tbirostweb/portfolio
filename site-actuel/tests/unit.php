<?php
// Tests unitaires hors conteneur : php tests/unit.php
require __DIR__ . '/../src/contact_lib.php';
$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

// --- jeton ---
$secret = str_repeat('a', 40);
$t = contact_make_token($secret);
check('token valide', contact_verify_token($secret, $t['ts'], $t['nonce'], $t['token']));
check('token altéré refusé', !contact_verify_token($secret, $t['ts'], $t['nonce'], str_repeat('0', 64)));
check('ts altéré refusé', !contact_verify_token($secret, (string) ((int) $t['ts'] + 1), $t['nonce'], $t['token']));
check('autre secret refusé', !contact_verify_token(str_repeat('b', 40), $t['ts'], $t['nonce'], $t['token']));
check('token public (secret vide) refusé', !contact_verify_token($secret, $t['ts'], $t['nonce'], hash_hmac('sha256', $t['nonce'] . '.' . $t['ts'], '')));
check('nonces distincts', contact_make_token($secret)['nonce'] !== $t['nonce']);

// --- secret : absent / court / ok ---
putenv('CONTACT_FORM_SECRET'); unset($_ENV['CONTACT_FORM_SECRET'], $_SERVER['CONTACT_FORM_SECRET']);
check('secret absent => null', contact_secret() === null);
$_ENV['CONTACT_FORM_SECRET'] = 'court';
check('secret court => null', contact_secret() === null);
$_ENV['CONTACT_FORM_SECRET'] = $secret;
check('secret 40 octets => ok', contact_secret() === $secret);

// --- IP / X-Forwarded-For ---
unset($_ENV['TRUSTED_PROXIES']);
check('XFF ignoré si REMOTE_ADDR public', contact_client_ip(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6']) === '203.0.113.9');
check('XFF falsifié (gauche) ignoré derrière proxy', contact_client_ip(['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7']) === '198.51.100.7');
check('proxy chaîné', contact_client_ip(['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.0.0.5']) === '198.51.100.7');
check('XFF invalide => REMOTE_ADDR', contact_client_ip(['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => 'pas-une-ip']) === '172.18.0.2');
$_ENV['TRUSTED_PROXIES'] = '192.0.2.0/24';
check('TRUSTED_PROXIES explicite', contact_client_ip(['REMOTE_ADDR' => '192.0.2.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7']) === '198.51.100.7');
check('privé non fiable si liste explicite', contact_client_ip(['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7']) === '172.18.0.2');
unset($_ENV['TRUSTED_PROXIES']);

// --- quota : fail-closed, limite par IP, plafond global ---
$_ENV['CONTACT_STATE_DIR'] = '/proc/inexistant/rl';
check('stockage absent => unavailable (fail-closed)', contact_rate_check('1.1.1.1') === 'unavailable');
$dir = sys_get_temp_dir() . '/cf_test_' . bin2hex(random_bytes(4));
$_ENV['CONTACT_STATE_DIR'] = $dir; $_ENV['CONTACT_DAILY_MAX'] = '7';
$r = []; for ($i = 0; $i < 6; $i++) { $r[] = contact_rate_check('9.9.9.9', 1000 + $i); }
check('5 ok puis limited', $r === ['ok','ok','ok','ok','ok','limited']);
check('autre IP non impactée', contact_rate_check('8.8.8.8', 1010) === 'ok');
check('plafond global atteint', contact_rate_check('7.7.7.7', 1011) === 'ok' && contact_rate_check('7.7.7.6', 1012) === 'limited');
$_ENV['CONTACT_DAILY_MAX'] = '0'; check('fenêtre IP expirée => ok', contact_rate_check('9.9.9.9', 1000 + 3600 + 5) === 'ok');

// --- nonce : usage unique ---
$n = bin2hex(random_bytes(16));
check('nonce réservé', contact_nonce_reserve($n) === 'reserved');
check('nonce rejoué', contact_nonce_reserve($n) === 'replay');
contact_nonce_mark($n, 'failed');
check('nonce réutilisable après échec franc', contact_nonce_reserve($n) === 'reserved');
contact_nonce_mark($n, 'unknown');
check('nonce ambigu non réutilisable', contact_nonce_reserve($n) === 'replay');
exec('rm -rf ' . escapeshellarg($dir));
exit($fail ? 1 : 0);
