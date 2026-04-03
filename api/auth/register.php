<?php
/**
 * REST API: User Registration
 * Method: POST
 * 
 * Request body (JSON):
 * {
 *   "name": "Felhasználó Név",
 *   "email": "user@example.com",
 *   "password": "password123"
 * }
 * 
 * Response:
 * Success (201):
 * {
 *   "success": true,
 *   "message": "Sikeres regisztráció!",
 *   "user": {
 *     "id": 1,
 *     "name": "Felhasználó Név",
 *     "email": "user@example.com",
 *     "role": "user"
 *   }
 * }
 * 
 * Error (422):
 * {
 *   "success": false,
 *   "message": "Hiba leírás"
 * }
 */

declare(strict_types=1);
session_start();

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

// Csak POST elfogadva
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode([
    "success" => false,
    "message" => "Csak POST metódus engedélyezett."
  ]);
  exit;
}

// Adatbázis kapcsolat
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

// Request body feldolgozása
$input = json_decode(file_get_contents("php://input"), true);

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

// Validáció
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

// Erős jelszó validáció: nagybetű + szám + speciális karakter
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

// Email foglalt?
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

// Jelszó hash
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
  $pdo->beginTransaction();
  
  // User beszúrása
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
