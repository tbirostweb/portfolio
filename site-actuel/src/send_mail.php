<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// En production (Dokploy), les variables sont injectées dans l'environnement.
// En local, on les charge depuis le .env s'il existe.
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    // Pas de .env : normal en prod.
}

/**
 * Renvoie l'IP du visiteur. Derrière un proxy (Dokploy/Traefik), on prend
 * la première IP de X-Forwarded-For ; sinon on retombe sur REMOTE_ADDR.
 */
function contact_client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Rate limit fichier par IP : true si la limite est dépassée.
 * Pas de base de données sur ce projet, donc stockage sur le filesystem
 * du conteneur (réinitialisé à chaque redéploiement, ce qui reste acceptable
 * pour ce cas d'usage).
 */
function contact_rate_limited(string $ip, int $maxRequests = 5, int $windowSeconds = 3600): bool
{
    $dir = sys_get_temp_dir() . '/contact_form_rl';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $handle = fopen($file, 'c+');
    if (!$handle) {
        return false; // On ne bloque pas l'envoi si le filesystem n'est pas disponible.
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $timestamps = json_decode($raw ?: '[]', true);
    if (!is_array($timestamps)) {
        $timestamps = [];
    }

    $now = time();
    $timestamps = array_values(array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
        return $t > $now - $windowSeconds;
    }));

    $limited = count($timestamps) >= $maxRequests;
    if (!$limited) {
        $timestamps[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($timestamps));
        fflush($handle);
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    return $limited;
}

/**
 * Construit le corps HTML de l'email de notification reçu par le propriétaire
 * du site. $name/$email/$message doivent déjà être validés mais pas encore
 * échappés : l'échappement HTML est fait ici, au seul endroit où ça compte.
 */
function contact_render_email_html(string $name, string $email, string $message): string
{
    $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $date        = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('d/m/Y à H:i');

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau message</title>
</head>
<body style="margin:0;padding:0;background-color:#E5E2D6;font-family:'Helvetica Neue',Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#E5E2D6;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#FBFAF6;border-radius:12px;overflow:hidden;border:1px solid #CFCABC;">
          <tr>
            <td style="background-color:#231F20;padding:28px 32px;">
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="font-size:0;line-height:0;vertical-align:middle;">
                    <span style="display:inline-block;width:9px;height:9px;background-color:#F0451E;border-radius:50%;"></span>
                  </td>
                  <td style="padding-left:10px;color:#F0EDE4;font-size:13px;line-height:1;letter-spacing:.08em;text-transform:uppercase;font-weight:600;vertical-align:middle;">Formulaire de contact</td>
                </tr>
              </table>
              <div style="color:#F0EDE4;font-size:22px;font-weight:700;margin-top:14px;">Nouveau message reçu</div>
              <div style="color:#AEA99C;font-size:13px;margin-top:6px;">{$date}</div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E9E5DB;width:90px;color:#F0451E;font-size:12px;letter-spacing:.06em;text-transform:uppercase;font-weight:600;vertical-align:top;">Nom</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E9E5DB;color:#231F20;font-size:15px;">{$safeName}</td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #E9E5DB;color:#F0451E;font-size:12px;letter-spacing:.06em;text-transform:uppercase;font-weight:600;vertical-align:top;">Email</td>
                  <td style="padding:10px 0;border-bottom:1px solid #E9E5DB;color:#231F20;font-size:15px;"><a href="mailto:{$safeEmail}" style="color:#231F20;text-decoration:underline;">{$safeEmail}</a></td>
                </tr>
              </table>
              <div style="color:#F0451E;font-size:12px;letter-spacing:.06em;text-transform:uppercase;font-weight:600;margin-bottom:10px;">Message</div>
              <div style="color:#231F20;font-size:15px;line-height:1.6;background-color:#E9E5DB;border-radius:8px;padding:16px 18px;">{$safeMessage}</div>
              <div style="margin-top:28px;text-align:center;">
                <a href="mailto:{$safeEmail}" style="display:inline-block;background-color:#F0451E;color:#FFFFFF;text-decoration:none;font-size:13px;font-weight:600;letter-spacing:.03em;padding:12px 26px;border-radius:6px;">Répondre à {$safeName}</a>
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 32px;background-color:#E9E5DB;color:#6E6A5F;font-size:12px;text-align:center;">
              Envoyé automatiquement depuis le formulaire de contact de theo-birost.fr
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Évaluation anti-spam / anti-phishing du message.
 * Ne bloque pas (sauf cas extrême) : attribue un score et des raisons.
 *   - flag  : score élevé  => l'email est marqué (sujet + en-têtes X-Spam-*)
 *             pour être filtré automatiquement vers les indésirables.
 *   - block : spam évident => on jette silencieusement (réponse OK factice).
 *
 * @return array{score:int, reasons:array<int,string>, flag:bool, block:bool}
 */
function contact_spam_assessment(string $name, string $email, string $message): array
{
    $score = 0;
    $reasons = [];
    $add = function (int $pts, string $why) use (&$score, &$reasons) {
        $score += $pts;
        $reasons[] = $why;
    };
    $haystack = mb_strtolower($name . ' ' . $message);

    // 1) Nombre de liens
    $links = preg_match_all('#https?://|www\.#i', $message);
    if ($links >= 6)     { $add(5, "beaucoup de liens ($links)"); }
    elseif ($links >= 3) { $add(3, "plusieurs liens ($links)"); }

    // 2) Raccourcisseurs d'URL (typiques du phishing)
    if (preg_match('#\b(bit\.ly|tinyurl\.com|t\.co|goo\.gl|ow\.ly|is\.gd|buff\.ly|cutt\.ly|rebrand\.ly|shorturl\.at|adf\.ly)\b#i', $message)) {
        $add(4, "raccourcisseur d'URL");
    }

    // 3) Mots-clés arnaque / phishing / SEO-spam
    $spamWords = [
        'viagra', 'cialis', 'casino', 'porn', 'bitcoin', 'crypto', 'forex', 'loan', 'payday',
        'nigerian prince', 'wire transfer', 'inheritance', 'western union', 'gift card',
        'verify your account', 'confirm your password', 'suspended account', 'reset your password',
        'click here to claim', 'seo service', 'référencement garanti', 'backlink', 'guaranteed ranking',
        'first page of google', 'make money', 'work from home', 'free money', 'investment opportunity',
        'you have won', 'claim your prize', 'act now', 'limited time offer', 'increase your traffic',
    ];
    $hits = 0;
    foreach ($spamWords as $w) {
        if (mb_strpos($haystack, $w) !== false) { $hits++; }
    }
    if ($hits > 0) { $add(min(6, 2 * $hits), "mots suspects ($hits)"); }

    // 4) Écriture non latine (cyrillique / CJK) => quasi toujours du spam ici
    if (preg_match('/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}]/u', $message)) {
        $add(4, 'caractères non latins');
    }

    // 5) Balises HTML / BBCode dans le message
    if (preg_match('#<[a-z][\s\S]*>|\[url|\[link#i', $message)) {
        $add(3, 'balises HTML/BBCode');
    }

    // 6) Cri en majuscules
    $letters = preg_replace('/[^a-zA-ZÀ-ÿ]/u', '', $message);
    if (mb_strlen((string) $letters) > 20) {
        $upper = preg_replace('/[^A-ZÀ-Þ]/u', '', $message);
        if (mb_strlen((string) $upper) / max(1, mb_strlen((string) $letters)) > 0.6) {
            $add(2, 'majuscules excessives');
        }
    }

    // 7) Nom contenant une URL
    if (preg_match('#https?://|www\.#i', $name)) { $add(3, 'URL dans le nom'); }

    // 8) Domaine email : jetable, ou sans MX (adresse probablement fausse)
    $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
    $disposable = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'yopmail.com', 'tempmail.com', 'trashmail.com', 'sharklasers.com', 'getnada.com', 'maildrop.cc', 'temp-mail.org'];
    if ($domain !== '' && in_array($domain, $disposable, true)) {
        $add(4, 'email jetable');
    } elseif ($domain !== '' && function_exists('checkdnsrr') && !checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        $add(3, 'domaine email sans MX');
    }

    return [
        'score'   => $score,
        'reasons' => $reasons,
        'flag'    => $score >= 4,
        'block'   => $score >= 12,
    ];
}

/** Lecture robuste d'une variable d'environnement (quel que soit variables_order). */
function contact_env(string $key): ?string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? null : (string) $v;
}

/**
 * Profils SMTP disponibles, dans l'ordre de priorité.
 * Primaire = SMTP_* ; secours = SMTP2_*. On garde ceux qui sont complets.
 * @return array<int,array{host:string,user:string,pass:string,secure:string,port:int}>
 */
function contact_smtp_profiles(): array
{
    $profiles = [];
    foreach (['', '2'] as $suffix) {
        $host = contact_env("SMTP{$suffix}_HOST");
        $user = contact_env("SMTP{$suffix}_USERNAME");
        $pass = contact_env("SMTP{$suffix}_PASSWORD");
        if ($host !== null && $user !== null && $pass !== null) {
            $profiles[] = [
                'host'   => $host,
                'user'   => $user,
                'pass'   => $pass,
                'secure' => contact_env("SMTP{$suffix}_SECURE") ?? 'tls',
                'port'   => (int) (contact_env("SMTP{$suffix}_PORT") ?? '587'),
            ];
        }
    }
    return $profiles;
}

/** Applique un profil SMTP à une instance PHPMailer. */
function contact_apply_smtp(PHPMailer $m, array $p): void
{
    $m->isSMTP();
    $m->Host       = $p['host'];
    $m->SMTPAuth   = true;
    $m->Username   = $p['user'];
    $m->Password   = $p['pass'];
    $m->SMTPSecure = $p['secure'];
    $m->Port       = $p['port'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Rate limit par IP : coupe le spam en rafale avant tout autre traitement ---
    if (contact_rate_limited(contact_client_ip())) {
        http_response_code(429);
        echo "Trop de tentatives. Merci de réessayer plus tard.";
        exit;
    }

    // --- Honeypot : les bots génériques remplissent tous les champs, y compris celui-ci ---
    // On répond comme si tout allait bien pour ne pas leur indiquer qu'ils sont détectés.
    if (!empty($_POST['website'])) {
        http_response_code(200);
        echo "Merci ! Votre message a bien été envoyé.";
        exit;
    }

    // --- Jeton signé HMAC + délai minimum : bloque les POST directs sans passage par la page ---
    $ts    = $_POST['ts'] ?? '';
    $token = $_POST['token'] ?? '';
    $expectedToken = hash_hmac('sha256', (string) $ts, contact_env('CONTACT_FORM_SECRET') ?? '');

    if (!ctype_digit((string) $ts) || !hash_equals($expectedToken, (string) $token)) {
        http_response_code(400);
        echo "Requête invalide. Merci de recharger la page et réessayer.";
        exit;
    }

    $elapsed = time() - (int) $ts;
    if ($elapsed > 3600) {
        http_response_code(400);
        echo "Formulaire expiré. Merci de recharger la page et réessayer.";
        exit;
    }
    if ($elapsed < 3) {
        // Envoyé trop vite pour un humain : traité en silence comme le honeypot.
        http_response_code(200);
        echo "Merci ! Votre message a bien été envoyé.";
        exit;
    }

    // --- Validation stricte des champs ---
    $name    = str_replace(["\r", "\n"], '', strip_tags(trim($_POST["name"] ?? '')));
    $email   = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $message = trim($_POST["message"] ?? '');

    if (
        $name === '' || mb_strlen($name) > 100
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
        || $message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000
    ) {
        http_response_code(400);
        echo "Veuillez remplir tous les champs correctement et réessayer.";
        exit;
    }

    // --- Anti-spam / anti-phishing : scoring ---
    $spam = contact_spam_assessment($name, $email, $message);

    // Spam évident : on répond OK (comme le honeypot) sans rien envoyer.
    if ($spam['block']) {
        http_response_code(200);
        echo "Merci ! Votre message a bien été envoyé.";
        exit;
    }

    $profiles = contact_smtp_profiles();
    if (empty($profiles)) {
        http_response_code(500);
        echo "Le message n'a pas pu être envoyé. Merci d'écrire directement à contact@theo-birost.fr.";
        exit;
    }

    // Destinataire des notifications (par défaut la boîte OVH ; surchargeable via CONTACT_TO).
    $recipient = contact_env('CONTACT_TO') ?? 'contact@theo-birost.fr';

    $bodyHtml = contact_render_email_html($name, $email, $message);
    $bodyText = "Nouveau message depuis le formulaire de contact.\n\n"
        . "Nom : $name\n"
        . "Email : $email\n\n"
        . "Message :\n$message";

    // On tente chaque profil SMTP dans l'ordre : le 1er qui envoie l'emporte, sinon on bascule sur le suivant.
    $sent = false;
    foreach ($profiles as $p) {
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            contact_apply_smtp($mail, $p);

            // L'expéditeur DOIT être le compte authentifié (OVH comme Gmail l'exigent) ; le visiteur va en Reply-To.
            $mail->setFrom($p['user'], 'Formulaire theo-birost.fr');
            $mail->addAddress($recipient, 'Théo Birost');
            $mail->addReplyTo($email, $name);
            $mail->addCustomHeader('X-Mail-Source', 'theo-birost.fr (portfolio)');

            $mail->isHTML(true);
            $mail->Subject = "[Portfolio] Nouveau message de $name";

            // Message jugé douteux : on le marque pour que la boîte le filtre en indésirables.
            if ($spam['flag']) {
                $mail->Subject = '[⚠ SPAM?] ' . $mail->Subject;
                $mail->addCustomHeader('X-Spam-Flag', 'YES');
                $mail->addCustomHeader('X-Spam-Score', (string) $spam['score']);
                $mail->addCustomHeader('X-Spam-Reasons', substr(implode('; ', $spam['reasons']), 0, 200));
                $mail->Priority = 5;
            }

            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText;
            $mail->send();
            $sent = true;
            break;
        } catch (Exception $e) {
            // Échec : on tente le profil de secours suivant.
        }
    }

    if ($sent) {
        http_response_code(200);
        echo "Merci ! Votre message a bien été envoyé.";
    } else {
        http_response_code(500);
        echo "Le message n'a pas pu être envoyé. Merci d'écrire directement à contact@theo-birost.fr.";
    }

} else {
    http_response_code(403);
    echo "Un problème est survenu lors de l'envoi, veuillez réessayer.";
}