<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../includes/PHPMailer/src/Exception.php';
require __DIR__ . '/../includes/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../includes/PHPMailer/src/SMTP.php';
$config = require __DIR__ . '/../includes/mail_config.php';
function clean(string $v): string {
  $v = trim($v);
  $v = str_replace(["\r", "\n"], " ", $v);
  return $v;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /Smartbookers/public/contact.php');
  exit;
}
if (!empty($_POST['website'] ?? '')) {
  header('Location: /Smartbookers/public/contact.php?sent=1');
  exit;
}
$name    = clean($_POST['name'] ?? '');
$email   = clean($_POST['email'] ?? '');
$topic   = clean($_POST['topic'] ?? '');
$phone   = clean($_POST['phone'] ?? '');
$message = trim((string)($_POST['message'] ?? ''));
$privacy = isset($_POST['privacy']);
$errors = [];
if ($name === '' || mb_strlen($name) < 2) $errors[] = 'Név kötelező.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Érvényes email kötelező.';
if ($topic === '') $errors[] = 'Téma kötelező.';
if ($message === '' || mb_strlen($message) < 10) $errors[] = 'Az üzenet legyen legalább 10 karakter.';
if (!$privacy) $errors[] = 'Adatkezelési tájékoztató elfogadása kötelező.';
if ($errors) {
  $q = http_build_query(['error' => 1, 'msg' => implode(' ', $errors)]);
  header("Location: /Smartbookers/public/contact.php?$q");
  exit;
}
$topicMap = [
  'support' => 'Technikai segítség',
  'business' => 'Vállalkozói csomag',
  'partnership' => 'Együttműködés',
  'other' => 'Egyéb',
];
$topicLabel = $topicMap[$topic] ?? $topic;
try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = $config['smtp_host'];
  $mail->SMTPAuth   = true;
  $mail->Username   = $config['smtp_user'];
  $mail->Password   = $config['smtp_pass'];
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = (int)$config['smtp_port'];
  $mail->CharSet    = 'UTF-8';
  $mail->setFrom($config['from_email'], $config['from_name']);
  $mail->addAddress($config['to_email'], $config['to_name']);
  $mail->addReplyTo($email, $name);
  $mail->Subject = "Kapcsolat űrlap: {$topicLabel} — {$name}";
  $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
  $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
  $safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
  $safePhone   = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
  $safeTopic   = htmlspecialchars($topicLabel, ENT_QUOTES, 'UTF-8');
  $mail->isHTML(true);
  $mail->Body = "
    <h2>Új üzenet a SmartBookers kapcsolat űrlapról</h2>
    <p><strong>Név:</strong> {$safeName}</p>
    <p><strong>Email:</strong> {$safeEmail}</p>
    <p><strong>Telefon:</strong> " . ($safePhone !== '' ? $safePhone : '—') . "</p>
    <p><strong>Téma:</strong> {$safeTopic}</p>
    <hr>
    <p><strong>Üzenet:</strong></p>
    <p>{$safeMessage}</p>
  ";
  $mail->AltBody =
    "Új üzenet a SmartBookers kapcsolat űrlapról\n\n" .
    "Név: {$name}\n" .
    "Email: {$email}\n" .
    "Telefon: " . ($phone !== '' ? $phone : '—') . "\n" .
    "Téma: {$topicLabel}\n\n" .
    "Üzenet:\n{$message}\n";
  $mail->send();
  header('Location: /Smartbookers/public/contact.php?sent=1');
  exit;
} catch (Exception $e) {
  $q = http_build_query(['error' => 1, 'msg' => 'Nem sikerült elküldeni az üzenetet. Ellenőrizd az App Passwordot.']);
  header("Location: /Smartbookers/public/contact.php?$q");
  exit;
}
