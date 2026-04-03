<?php
declare(strict_types=1);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

// Elfogadva: GET, POST, DELETE
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'DELETE'])) {
  http_response_code(405);
  echo json_encode([
    "success" => false,
    "message" => "Nem támogatott HTTP metódus."
  ]);
  exit;
}

// Session törlése
session_destroy();
$_SESSION = [];

http_response_code(200);
echo json_encode([
  "success" => true,
  "message" => "Sikeres kijelentkezés."
]);
