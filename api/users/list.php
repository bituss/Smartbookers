<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  http_response_code(405);
  echo json_encode([
    "success" => false,
    "error" => "Csak GET metódus engedélyezett."
  ]);
  exit;
}
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode([
    "success" => false,
    "error" => "Nincs bejelentkezés."
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
    "error" => "Adatbázis kapcsolódási hiba."
  ]);
  exit;
}
$hasDeactivated = columnExists($pdo, 'users', 'deactivated_at');
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);
$role = trim((string)($_GET['role'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$limit = min(max($limit, 1), 100); 
$offset = max(0, $offset);
if ($hasDeactivated) {
  $where = "WHERE u.deactivated_at IS NULL"; 
} else {
  $where = "WHERE 1=1"; 
}
$params = [];
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
if ($role !== '' && in_array($role, ['user', 'provider', 'admin'])) {
  $where .= " AND u.role = :role";
  $params[':role'] = $role;
}
if ($search !== '') {
  $where .= " AND (u.name LIKE :search OR u.email LIKE :search)";
  $params[':search'] = '%' . $search . '%';
}
try {
  $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u $where");
  $countStmt->execute($params);
  $totalCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
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
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->execute();
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
