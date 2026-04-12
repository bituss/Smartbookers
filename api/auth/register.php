<?php
declare(strict_types=1);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode([
    "success" => false,
    "message" => "Csak POST metódus engedélyezett."
  ]);
  exit;
}
$host = "localhost";
$db   = "idopont_foglalas";
$user = "root";
$pass = "";
try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "message" => "Adatbázis kapcsolódási hiba."
  ]);
  exit;
}
$input = json_decode(file_get_contents("php:
if (!is_array($input)) {
  $input = [
    'name'     => $_POST['name'] ?? '',
    'email'    => $_POST['email'] ?? '',
    'password' => $_POST['password'] ?? ''
  ];
}
$name     = trim((string)($input['name'] ?? ''));
$email    = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');
if ($name === '' || $email === '' || $password === '') {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "Név, email és jelszó mező kötelezőek."
  ]);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "Hibás email formátum."
  ]);
  exit;
}
if (mb_strlen($password) < 6) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "A jelszó legyen legalább 6 karakter."
  ]);
  exit;
}
if (!preg_match('/[A-Z]/', $password)) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "A jelszó legyen legalább 6 karakter, 1 nagybetű, 1 szám, 1 speciális karakter."
  ]);
  exit;
}
if (!preg_match('/[0-9]/', $password)) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "A jelszó legyen legalább 6 karakter, 1 nagybetű, 1 szám, 1 speciális karakter."
  ]);
  exit;
}
if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\;\'\/]/', $password)) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "A jelszó legyen legalább 6 karakter, 1 nagybetű, 1 szám, 1 speciális karakter."
  ]);
  exit;
}
$chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$chk->execute([$email]);
if ($chk->fetchColumn()) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "Ez az email már létezik."
  ]);
  exit;
}
$hash = password_hash($password, PASSWORD_DEFAULT);
try {
  $pdo->beginTransaction();
  $ins = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
  $ins->execute([$name, $email, $hash]);
  $newUserId = (int)$pdo->lastInsertId();
  $pdo->commit();
  http_response_code(201);
  echo json_encode([
    "success" => true,
    "message" => "Sikeres regisztráció!",
    "user" => [
      "id"    => $newUserId,
      "name"  => $name,
      "email" => $email,
      "role"  => "user"
    ]
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "message" => "Hiba regisztráció közben: " . $e->getMessage()
  ]);
}
