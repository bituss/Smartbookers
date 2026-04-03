<?php
/**
 * REST API: Session Management (Logout)
 * 
 * DELETE /api/auth/session.php   - Logout (DELETE session)
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Sikeres kijelentkezés."
 * }
 */

declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

// GET is engedélyezve a böngészőből való logout linkhez
if ($_SERVER["REQUEST_METHOD"] === 'GET') {
  // Backward compatibility: böngészőből DELETE nem működik könnyen
  // GET logout link továbbra is működik
  session_destroy();
  $_SESSION = [];
  
  http_response_code(200);
  echo json_encode([
    "success" => true,
    "message" => "Sikeres kijelentkezés."
  ]);
  exit;
}

// DELETE: Proper REST
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

// POST: Backward compatibility (a logout form POST-ol)
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

// Nem támogatott metódus
http_response_code(405);
echo json_encode([
  "success" => false,
  "error" => "Csak GET, POST vagy DELETE metódus engedélyezett."
]);
