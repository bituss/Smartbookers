<?php
/**
 * REST API: List Users (with pagination and filtering)
 * Method: GET
 * 
 * Query Parameters:
 * - limit: items per page (default 20, max 100)
 * - offset: pagination offset (default 0)
 * - role: filter by role (user, provider, admin)
 * - search: search by name or email
 * - status: active or inactive (deactivated_at)
 * 
 * Examples:
 * GET /api/users/list.php?limit=10&offset=0
 * GET /api/users/list.php?role=user&search=john
 * GET /api/users/list.php?status=active
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     { "id": 1, "name": "User", "email": "user@example.com", "role": "user", "is_active": true },
 *     ...
 *   ],
 *   "pagination": {
 *     "limit": 20,
 *     "offset": 0,
 *     "total": 100
 *   }
 * }
 */

declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

// Csak GET elfogadva
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  http_response_code(405);
  echo json_encode([
    "success" => false,
    "error" => "Csak GET metódus engedélyezett."
  ]);
  exit;
}

// Bejelentkezés ellenőrzése
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode([
    "success" => false,
    "error" => "Nincs bejelentkezés."
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
    "error" => "Adatbázis kapcsolódási hiba."
  ]);
  exit;
}

$hasDeactivated = columnExists($pdo, 'users', 'deactivated_at');

// Query paraméterek feldolgozása
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);
$role = trim((string)($_GET['role'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

// Validáció
$limit = min(max($limit, 1), 100); // 1-100 között
$offset = max(0, $offset);

// WHERE feltételek
if ($hasDeactivated) {
  $where = "WHERE u.deactivated_at IS NULL"; // Alapból csak aktívak
} else {
  $where = "WHERE 1=1"; // nincs deactivated_at oszlop, nincs státusz korlátozás
}
$params = [];

// Státusz szűrés
if ($hasDeactivated) {
  if ($status === 'active') {
    $where = "WHERE u.deactivated_at IS NULL";
  } elseif ($status === 'inactive') {
    $where = "WHERE u.deactivated_at IS NOT NULL";
  }
} else {
  if ($status === 'inactive') {
    http_response_code(400);
    echo json_encode([
      "success" => false,
      "error" => "A status szűrés nem támogatott, mert hiányzik a deactivated_at oszlop."
    ]);
    exit;
  }
}

// Role szűrés
if ($role !== '' && in_array($role, ['user', 'provider', 'admin'])) {
  $where .= " AND u.role = :role";
  $params[':role'] = $role;
}

// Search szűrés
if ($search !== '') {
  $where .= " AND (u.name LIKE :search OR u.email LIKE :search)";
  $params[':search'] = '%' . $search . '%';
}

try {
  // Összszám lekérés (pagination info)
  $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u $where");
  $countStmt->execute($params);
  $totalCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
  
  // Adatok lekérés
  $selectFields = 'u.id, u.name, u.email, u.role, u.created_at';
  if ($hasDeactivated) {
    $selectFields .= ', u.deactivated_at';
  }

  $stmt = $pdo->prepare("
    SELECT $selectFields
    FROM users u
    $where
    ORDER BY u.id DESC
    LIMIT :limit OFFSET :offset
  ");
  
  // Bind limit és offset (csak numerikus érték)
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  
  // Bind többi paraméter
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  
  $stmt->execute();
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Formátum az is_active flaget
  $formattedUsers = array_map(function($u) use ($hasDeactivated) {
    $item = [
      "id"         => (int)$u['id'],
      "name"       => (string)$u['name'],
      "email"      => (string)$u['email'],
      "role"       => (string)$u['role'],
      "created_at" => (string)$u['created_at']
    ];

    if ($hasDeactivated) {
      $item['is_active'] = $u['deactivated_at'] === null;
      $item['deactivated_at'] = $u['deactivated_at'] ?? null;
    } else {
      $item['is_active'] = true;
      $item['deactivated_at'] = null;
    }

    return $item;
  }, $users);
  
  http_response_code(200);
  echo json_encode([
    "success" => true,
    "data" => $formattedUsers,
    "pagination" => [
      "limit"  => $limit,
      "offset" => $offset,
      "total"  => $totalCount,
      "pages"  => ceil($totalCount / $limit)
    ]
  ]);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "error" => "Adatbázis hiba: " . $e->getMessage()
  ]);
}
