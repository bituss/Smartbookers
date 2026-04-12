<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER["REQUEST_METHOD"] === 'GET') {
  session_destroy();
  $_SESSION = [];
  http_response_code(200);
  echo json_encode([
    "success" => true,
    "message" => "Sikeres kijelentkezés."
  ]);
  exit;
}
if ($_SERVER["REQUEST_METHOD"] === 'DELETE') {
  session_destroy();
  $_SESSION = [];
  http_response_code(200);
  echo json_encode([
    "success" => true,
    "message" => "Sikeres kijelentkezés."
  ]);
  exit;
}
if ($_SERVER["REQUEST_METHOD"] === 'POST') {
  session_destroy();
  $_SESSION = [];
  http_response_code(200);
  echo json_encode([
    "success" => true,
    "message" => "Sikeres kijelentkezés."
  ]);
  exit;
}
http_response_code(405);
echo json_encode([
  "success" => false,
  "error" => "Csak GET, POST vagy DELETE metódus engedélyezett."
]);
