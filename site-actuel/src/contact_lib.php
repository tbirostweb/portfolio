<?php
/**
 * Bibliothèque commune du formulaire de contact (index.php + send_mail.php).
 * Un seul code pour lire la configuration et signer/vérifier le jeton, afin
 * que les deux endpoints ne divergent jamais.
 */

/** Lecture robuste d'une variable d'environnement (quel que soit variables_order). */
function contact_env(string $key): ?string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? null : (string) $v;
}

/** Charge .env en local s'il existe ; en production les variables viennent de l'environnement. */
function contact_load_env(): void
{
    try {
        Dotenv\Dotenv::createImmutable(__DIR__)->load();
    } catch (Dotenv\Exception\InvalidPathException $e) {
        // Pas de .env : normal en prod.
    }
}

/** Secret HMAC : null s'il est absent ou trop court (< 32 octets). */
function contact_secret(): ?string
{
    $s = contact_env('CONTACT_FORM_SECRET');
    return ($s !== null && strlen($s) >= 32) ? $s : null;
}

/** Kill-switch d'exploitation : CONTACT_FORM_DISABLED=1 coupe le formulaire (503). */
function contact_disabled(): bool
{
    return in_array(strtolower((string) contact_env('CONTACT_FORM_DISABLED')), ['1', 'true', 'yes', 'on'], true);
}

/** Répertoire d'état (quota, nonces). Surchargeable via CONTACT_STATE_DIR. */
function contact_state_dir(): string
{
    return rtrim(contact_env('CONTACT_STATE_DIR') ?? (sys_get_temp_dir() . '/contact_form'), '/');
}

/** Crée (si besoin) un sous-répertoire d'état ; null si indisponible/non inscriptible. */
function contact_state_subdir(string $name): ?string
{
    $dir = contact_state_dir() . '/' . $name;
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return null;
    }
    return is_writable($dir) ? $dir : null;
}

/** Jeton signé : nonce aléatoire + horodatage, HMAC sur "nonce.ts". */
function contact_make_token(string $secret, ?int $now = null): array
{
    $ts    = (string) ($now ?? time());
    $nonce = bin2hex(random_bytes(16));
    return ['ts' => $ts, 'nonce' => $nonce, 'token' => hash_hmac('sha256', $nonce . '.' . $ts, $secret)];
}

function contact_verify_token(string $secret, string $ts, string $nonce, string $token): bool
{
    if (!ctype_digit($ts) || !preg_match('/^[0-9a-f]{32}$/', $nonce)) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $nonce . '.' . $ts, $secret), $token);
}

/** IP dans un des CIDR (IPv4/IPv6) ? */
function contact_ip_in_cidrs(string $ip, array $cidrs): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    foreach ($cidrs as $cidr) {
        $cidr = trim($cidr);
        if ($cidr === '') {
            continue;
        }
        [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $netBin = @inet_pton($net);
        if ($netBin === false || strlen($netBin) !== strlen($bin)) {
            continue;
        }
        $bits = $bits === null ? strlen($bin) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        $rest  = $bits % 8;
        if (substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            continue;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        if ((ord($bin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask)) {
            return true;
        }
    }
    return false;
}

/**
 * IP du visiteur. X-Forwarded-For n'est lu que si REMOTE_ADDR est un proxy
 * de confiance (TRUSTED_PROXIES, liste CIDR ; défaut : réseaux privés où vit
 * Traefik). On remonte la chaîne depuis la droite jusqu'à la première IP non
 * fiable : une valeur injectée par le client (à gauche) n'est jamais retenue.
 */
function contact_client_ip(?array $server = null): string
{
    $server  ??= $_SERVER;
    $remote  = (string) ($server['REMOTE_ADDR'] ?? 'unknown');
    $trusted = array_map('trim', explode(',', contact_env('TRUSTED_PROXIES')
        ?? '127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,::1/128,fc00::/7'));

    if (!contact_ip_in_cidrs($remote, $trusted) || empty($server['HTTP_X_FORWARDED_FOR'])) {
        return $remote;
    }
    $chain = array_reverse(array_map('trim', explode(',', (string) $server['HTTP_X_FORWARDED_FOR'])));
    foreach ($chain as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $remote;
        }
        if (!contact_ip_in_cidrs($ip, $trusted)) {
            return $ip;
        }
    }
    return $remote;
}

/**
 * Quota par IP (fenêtre glissante) + plafond global journalier.
 * Retourne 'ok', 'limited' (429) ou 'unavailable' (stockage HS : fail-closed, 503).
 * Le comptage est atomique (flock exclusif).
 */
function contact_rate_check(string $ip, ?int $now = null, ?int $perIp = null, int $window = 3600): string
{
    $perIp ??= (int) (contact_env('CONTACT_IP_MAX') ?? 5);
    $dir = contact_state_subdir('rl');
    if ($dir === null) {
        return 'unavailable';
    }
    $now ??= time();
    // Purge opportuniste : empreintes IP inactives depuis plus de 24 h.
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        if (basename($f) !== 'global.json' && @filemtime($f) < $now - 86400) {
            @unlink($f);
        }
    }
    $globalMax = (int) (contact_env('CONTACT_DAILY_MAX') ?? 30);

    $ipHandle = @fopen($dir . '/' . hash('sha256', $ip) . '.json', 'c+');
    $glHandle = @fopen($dir . '/global.json', 'c+');
    if (!$ipHandle || !$glHandle) {
        return 'unavailable';
    }
    flock($glHandle, LOCK_EX);
    flock($ipHandle, LOCK_EX);

    $read = static function ($h): array {
        $d = json_decode((string) stream_get_contents($h) ?: '[]', true);
        return is_array($d) ? $d : [];
    };
    $write = static function ($h, array $d): void {
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode(array_values($d)));
        fflush($h);
    };

    $ips = array_filter($read($ipHandle), static fn($t) => is_int($t) && $t > $now - $window);
    $gl  = array_filter($read($glHandle), static fn($t) => is_int($t) && $t > $now - 86400);

    $result = 'ok';
    if (count($ips) >= $perIp) {
        $result = 'limited';
    } elseif ($globalMax > 0 && count($gl) >= $globalMax) {
        $result = 'limited';
    } else {
        $ips[] = $now;
        $gl[]  = $now;
        $write($ipHandle, $ips);
        $write($glHandle, $gl);
    }

    flock($ipHandle, LOCK_UN);
    flock($glHandle, LOCK_UN);
    fclose($ipHandle);
    fclose($glHandle);
    return $result;
}

/**
 * Réserve atomiquement un nonce (usage unique). Retourne 'reserved',
 * 'replay' (déjà utilisé) ou 'unavailable'. Création exclusive (mode 'x').
 */
function contact_nonce_reserve(string $nonce): string
{
    $dir = contact_state_subdir('nonce');
    if ($dir === null) {
        return 'unavailable';
    }
    // Purge opportuniste des états de plus de 2 h (le jeton expire après 1 h).
    foreach (glob($dir . '/*.state') ?: [] as $f) {
        if (@filemtime($f) < time() - 7200) {
            @unlink($f);
        }
    }
    $h = @fopen($dir . '/' . $nonce . '.state', 'x');
    if ($h === false) {
        return is_file($dir . '/' . $nonce . '.state') ? 'replay' : 'unavailable';
    }
    fwrite($h, 'pending');
    fclose($h);
    return 'reserved';
}

/** Met à jour l'état d'un nonce : 'sent', 'failed' (réutilisable), 'unknown' (ambigu : pas de renvoi). */
function contact_nonce_mark(string $nonce, string $state): void
{
    $dir = contact_state_subdir('nonce');
    if ($dir === null) {
        return;
    }
    $file = $dir . '/' . $nonce . '.state';
    if ($state === 'failed') {
        @unlink($file);
        return;
    }
    @file_put_contents($file, $state, LOCK_EX);
}

/** Log applicatif sans donnée personnelle ni secret : code d'événement seulement. */
function contact_log(string $event): void
{
    error_log('contact_form event=' . preg_replace('/[^a-z0-9_.:-]/i', '', $event));
}

/** Réponse texte + code HTTP puis fin. */
function contact_respond(int $code, string $text): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $text;
    exit;
}
