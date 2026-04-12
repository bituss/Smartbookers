<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
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
$hasDeactivated = columnExists($pdo, 'users', 'deactivated_at');
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode([
    "success" => false,
    "message" => "Nincs bejelentkezés."
  ]);
  exit;
}
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = trim($_SERVER['PATH_INFO'] ?? '', '/');
$parts = explode('/', $pathInfo);
$userId = null;
if (!empty($parts[0]) && $parts[0] !== '') {
  if ($parts[0] === 'me') {
    $userId = (int)$_SESSION['user_id'];
  } else {
    $userId = (int)$parts[0];
  }
}
if ($method === 'GET') {
  if ($userId === null) {
    http_response_code(400);
    echo json_encode([
      "success" => false,
      "message" => "User ID szükséges."
    ]);
    exit;
  }
  if ($userId !== (int)$_SESSION['user_id'] && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
      "success" => false,
      "message" => "Nincs hozzáférés."
    ]);
    exit;
  }
  $selectFields = 'u.id, u.name, u.email, u.role, u.avatar, u.created_at';
  if ($hasDeactivated) {
    $selectFields .= ', u.deactivated_at';
  }
  $stmt = $pdo->prepare("SELECT $selectFields FROM users u WHERE u.id = ?");
  $stmt->execute([$userId]);
  $userData = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$userData) {
    http_response_code(404);
    echo json_encode([
      "success" => false,
      "message" => "Felhasználó nem található."
    ]);
    exit;
  }
  $responseUser = [
    "id"         => (int)$userData['id'],
    "name"       => (string)$userData['name'],
    "email"      => (string)$userData['email'],
    "role"       => (string)$userData['role'],
    "avatar"     => $userData['avatar'] ?? null,
    "created_at" => (string)$userData['created_at']
  ];
  if ($hasDeactivated) {
    $responseUser['deactivated_at'] = $userData['deactivated_at'] ?? null;
    $responseUser['is_active'] = $userData['deactivated_at'] === null;
  } else {
    $responseUser['is_active'] = true;
  }
  http_response_code(200);
  echo json_encode([
    "success" => true,
    "user" => $responseUser
  ]);
}
elseif ($method === 'PUT' || $method === 'PATCH') {
  if ($userId === null) {
    http_response_code(400);
    echo json_encode([
      "success" => false,
      "message" => "User ID szükséges."
    ]);
    exit;
  }
  if ($userId !== (int)$_SESSION['user_id'] && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
      "success" => false,
      "message" => "Nincs hozzáférés."
    ]);
    exit;
  }
  $input = json_decode(file_get_contents("php:
  if (!is_array($input)) {
    $input = $_POST;
  }
  $stmt = $pdo->prepare("SELECT u.* FROM users u WHERE u.id = ?");
  $stmt->execute([$userId]);
  $userData = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$userData) {
    http_response_code(404);
    echo json_encode([
      "success" => false,
      "message" => "Felhasználó nem található."
    ]);
    exit;
  }
  $name      = trim((string)($input['name'] ?? $userData['name']));
  $avatar    = trim((string)($input['avatar'] ?? $userData['avatar']));
  $password  = (string)($input['password'] ?? '');
  $errors = [];
  if ($name === '') {
    $errors[] = "Név mező kötelező.";
  }
  if ($password !== '') {
    if (mb_strlen($password) < 6) {
      $errors[] = "A jelszó legyen legalább 6 karakter.";
    }
  }
  if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
      "success" => false,
      "message" => implode(" ", $errors)
    ]);
    exit;
  }
  try {
    $pdo->beginTransaction();
    if ($password !== '') {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $updateStmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, avatar = ?, password = ?
        WHERE id = ?
      ");
      $updateStmt->execute([$name, $avatar ?: null, $hash, $userId]);
    } else {
      $updateStmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, avatar = ?
        WHERE id = ?
      ");
      $updateStmt->execute([$name, $avatar ?: null, $userId]);
    }
    $pdo->commit();
    $selectFields = 'u.id, u.name, u.email, u.role, u.avatar, u.created_at';
    if ($hasDeactivated) {
      $selectFields .= ', u.deactivated_at';
    }
    $stmt = $pdo->prepare("SELECT $selectFields FROM users u WHERE u.id = ?");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $responseUser = [
      "id" => (int)$updatedUser['id'],
      "name" => (string)$updatedUser['name'],
      "email" => (string)$updatedUser['email'],
      "role" => (string)$updatedUser['role'],
      "avatar" => $updatedUser['avatar'] ?? null,
      "created_at" => (string)$updatedUser['created_at']
    ];
    if ($hasDeactivated) {
      $responseUser['deactivated_at'] = $updatedUser['deactivated_at'] ?? null;
      $responseUser['is_active'] = $updatedUser['deactivated_at'] === null;
    } else {
      $responseUser['is_active'] = true;
    }
    http_response_code(200);
    echo json_encode([
      "success" => true,
      "message" => "Felhasználó sikeresen frissítve.",
      "user" => $responseUser
    ]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
      "success" => false,
      "message" => "Hiba a frissítés közben: " . $e->getMessage()
    ]);
  }
}
elseif ($method === 'DELETE') {
  if ($userId === null) {
    http_response_code(400);
    echo json_encode([
      "success" => false,
      "message" => "User ID szükséges."
    ]);
    exit;
  }
  if ($userId !== (int)$_SESSION['user_id'] && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
      "success" => false,
      "message" => "Nincs hozzáférés."
    ]);
    exit;
  }
  if (!$hasDeactivated) {
    http_response_code(500);
    echo json_encode([
      "success" => false,
      "message" => "A rendszer nem támogatja az inaktiválást. Futtasd a migrate_soft_delete.sql migrációt."
    ]);
    exit;
  }
  $stmt = $pdo->prepare("SELECT u.id, u.deactivated_at FROM users u WHERE u.id = ?");
  $stmt->execute([$userId]);
  $userData = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$userData) {
    http_response_code(404);
    echo json_encode([
      "success" => false,
      "message" => "Felhasználó nem található."
    ]);
    exit;
  }
  if ($userData['deactivated_at'] !== null) {
    http_response_code(422);
    echo json_encode([
      "success" => false,
      "message" => "A felhasználó már inaktív."
    ]);
    exit;
  }
  try {
    $deactivatedAt = date('Y-m-d H:i:s');
    $updateStmt = $pdo->prepare("
      UPDATE users 
      SET deactivated_at = ?
      WHERE id = ?
    ");
    $updateStmt->execute([$deactivatedAt, $userId]);
    http_response_code(200);
    echo json_encode([
      "success" => true,
      "message" => "Felhasználó sikeresen inaktiválva.",
      "user" => [
        "id"             => $userId,
        "deactivated_at" => $deactivatedAt,
        "is_active"      => false
      ]
    ]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
      "success" => false,
      "message" => "Hiba az inaktiválás közben: " . $e->getMessage()
    ]);
  }
}
else {
  http_response_code(405);
  echo json_encode([
    "success" => false,
    "message" => "Nem támogatott HTTP metódus."
  ]);
}
