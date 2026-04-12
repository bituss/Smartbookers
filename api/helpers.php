<?php
function sendJson(bool $success, string $message, array $data = null, int $statusCode = 200): void
{
  http_response_code($statusCode);
  $response = [
    "success" => $success,
    "message" => $message
  ];
  if ($data !== null) {
    $response = array_merge($response, $data);
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($response);
  exit;
}
function validateJsonContent(): void
{
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  if ($_SERVER['REQUEST_METHOD'] !== 'GET' && 
      $_SERVER['REQUEST_METHOD'] !== 'DELETE' &&
      strpos($contentType, 'application/json') === false) {
    sendJson(false, 'Content-Type: application/json szükséges', null, 400);
  }
}
function validateMethod(array $allowedMethods): void
{
  $method = $_SERVER['REQUEST_METHOD'];
  if (!in_array($method, $allowedMethods)) {
    sendJson(
      false,
      'Nem támogatott HTTP metódus. Engedélyezett: ' . implode(', ', $allowedMethods),
      null,
      405
    );
  }
}
function validateAuth(): void
{
  if (!isset($_SESSION['user_id'])) {
    sendJson(false, 'Nincs bejelentkezés.', null, 401);
  }
}
function validateAdmin(): void
{
  if (($_SESSION['role'] ?? '') !== 'admin') {
    sendJson(false, 'Admin jogosultság szükséges.', null, 403);
  }
}
function columnExists(PDO $pdo, string $table, string $column): bool
{
  try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    return false;
  }
}
