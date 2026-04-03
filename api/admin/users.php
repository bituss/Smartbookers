<?php
/**
 * REST API: Admin User Management
 * 
 * PATCH  /api/admin/users.php   - Reactivate user
 */

declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

// Admin ellenőrzése
if (($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode([
    "success" => false,
    "message" => "Admin jogosultság szükséges."
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

$hasDeactivated = columnExists($pdo, 'users', 'deactivated_at');

$method = $_SERVER['REQUEST_METHOD'];

// PATCH: Felhasználó reaktiválása
if ($method === 'PATCH') {
  // Request body feldolgozása
  $input = json_decode(file_get_contents("php://input"), true);
  
  if (!is_array($input) || !isset($input['user_id'])) {
    http_response_code(400);
    echo json_encode([
      "success" => false,
      "message" => "user_id mező szükséges."
    ]);
    exit;
  }
  
  $userId = (int)$input['user_id'];

  if (!$hasDeactivated) {
    http_response_code(500);
    echo json_encode([
      "success" => false,
      "message" => "A rendszer nem támogatja a reaktiválást. Futtasd a migrate_soft_delete.sql migrációt."
    ]);
    exit;
  }
  
  // Felhasználó letöltése
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
  
  // Aktív-e már?
  if ($userData['deactivated_at'] === null) {
    http_response_code(422);
    echo json_encode([
      "success" => false,
      "message" => "Ez a felhasználó már aktív."
    ]);
    exit;
  }
  
  try {
    $updateStmt = $pdo->prepare("
      UPDATE users 
      SET deactivated_at = NULL
      WHERE id = ?
    ");
    $updateStmt->execute([$userId]);
    
    // Frissített adat
    $stmt = $pdo->prepare("
      SELECT u.id, u.name, u.email, u.role, u.created_at, u.deactivated_at
      FROM users u
      WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
      "success" => true,
      "message" => "Felhasználó sikeresen reaktiválva.",
      "user" => [
        "id"             => (int)$updatedUser['id'],
        "name"           => (string)$updatedUser['name'],
        "email"          => (string)$updatedUser['email'],
        "role"           => (string)$updatedUser['role'],
        "created_at"     => (string)$updatedUser['created_at'],
        "deactivated_at" => null,
        "is_active"      => true
      ]
    ]);
    
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
      "success" => false,
      "message" => "Hiba a reaktiválás közben: " . $e->getMessage()
    ]);
  }
}

else {
  http_response_code(405);
  echo json_encode([
    "success" => false,
    "message" => "Csak PATCH metódus engedélyezett."
  ]);
}

