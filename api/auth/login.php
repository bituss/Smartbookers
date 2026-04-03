<?php
declare(strict_types=1);
session_start();

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

// Include helpers - helyes path
$helpers_path = dirname(dirname(__FILE__)) . '/helpers.php';
if (file_exists($helpers_path)) {
  require_once $helpers_path;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
  sendJson(false, 'Adatbázis kapcsolódási hiba.', null, 500);
}

$hasDeactivated = columnExists($pdo, 'users', 'deactivated_at');

// Request body feldolgozása
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
  $input = [
    'email'    => $_POST['email'] ?? '',
    'password' => $_POST['password'] ?? ''
  ];
}

$email    = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

// Validáció
if ($email === '' || $password === '') {
  sendJson(false, 'Email és jelszó mező kötelezőek.', null, 422);
}

// Felhasználó keresése
$sql = "SELECT u.id, u.name, u.email, u.password, u.role";
if ($hasDeactivated) {
  $sql .= ", u.deactivated_at";
}
$sql .= " FROM users u WHERE u.email = ?";
if ($hasDeactivated) {
  $sql .= " AND u.deactivated_at IS NULL";
}
$sql .= " LIMIT 1";
error_log('login SQL: '. $sql);

$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userRow && password_verify($password, $userRow["password"])) {
  // Sikeres bejelentkezés
  $_SESSION["user_id"] = (int)$userRow["id"];
  $_SESSION["name"]    = (string)$userRow["name"];
  $_SESSION["role"]    = (string)$userRow["role"];
  
  sendJson(true, 'Sikeres bejelentkezés!', [
    "user" => [
      "id"    => (int)$userRow["id"],
      "name"  => (string)$userRow["name"],
      "email" => (string)$userRow["email"],
      "role"  => (string)$userRow["role"]
    ]
  ], 200);
} else {
  // Inaktív fiók vagy hibás jelszó
  sendJson(false, 'Hibás email vagy jelszó.', null, 401);
}
