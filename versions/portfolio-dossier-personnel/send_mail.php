<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

header('Content-Type: text/html; charset=UTF-8');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self'");

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$mail = new PHPMailer(true);


$maxAttempts = 1;
$timeFrame = 240;

if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = [];
}


$_SESSION['attempts'] = array_filter($_SESSION['attempts'], function ($timestamp) use ($timeFrame) {
    return $timestamp > time() - $timeFrame;
});

if (count($_SESSION['attempts']) >= $maxAttempts) {
    header("Location: index.php?status=error&message=" . urlencode("Trop de tentatives. Réessayez plus tard.") . "#contact");
    exit;
}


$_SESSION['attempts'][] = time();

try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USERNAME'];
    $mail->Password   = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = $_ENV['SMTP_SECURE'] === 'TLS' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $_ENV['SMTP_PORT'];

    $mail->setFrom('votre-email@gmail.com', 'Votre Nom');
    $mail->addAddress('tbirost@gmail.com');

    $mail->CharSet = 'UTF-8';


    function clean_input($data) {
        $data = trim($data);
        $data = strip_tags($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    $firstname = clean_input($_POST["firstname"] ?? '');
    $lastname  = clean_input($_POST["lastname"] ?? '');
    $email     = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $message   = clean_input($_POST["message"] ?? '');

    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("L'email fourni n'est pas valide.");
    }

    // Vérification de la longueur du message
    if (empty($message) || strlen($message) > 2000) {
        throw new Exception("Le message est vide ou trop long.");
    }

    $mail->isHTML(false);
    $mail->Subject = "Nouveau message de $firstname";
    $mail->Body    = "Nom: $firstname $lastname\nEmail: $email\nMessage:\n$message";

    $mail->send();
    header("Location: index.php?status=success#contact");
    exit;

} catch (Exception $e) {
    $errorMessage = urlencode("Erreur lors de l'envoi  ;) " );
    header("Location: index.php?status=error&message=$errorMessage#contact");
    exit;
}
?>
