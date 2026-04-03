<?php
/**
 * REST API: Provider Registration
 * Method: POST
 * 
 * Request body (JSON):
 * {
 *   "name": "Vállalkozó Név",
 *   "business_name": "Üzletnem",
 *   "email": "provider@example.com",
 *   "password": "Pass123!",
 *   "password_confirm": "Pass123!",
 *   "phone": "06701234567",
 *   "service_id": 1,
 *   "industry_id": 1,
 *   "zip": "1111",
 *   "city": "Budapest",
 *   "utca": "Fő utca",
 *   "hazszam": "1"
 * }
 * 
 * Response:
 * Success (201):
 * {
 *   "success": true,
 *   "message": "Sikeres regisztráció!",
 *   "provider": {
 *     "id": 1,
 *     "user_id": 2,
 *     "name": "Vállalkozó Név",
 *     "email": "provider@example.com",
 *     "business_name": "Üzletnem"
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
  $input = $_POST;
}

// Adatok begyűjtése
$name          = trim((string)($input['name'] ?? ''));
$business_name = trim((string)($input['business_name'] ?? ''));
$email         = trim((string)($input['email'] ?? ''));
$password      = (string)($input['password'] ?? '');
$password2     = (string)($input['password_confirm'] ?? '');
$phone         = trim((string)($input['phone'] ?? ''));
$service_id    = (int)($input['service_id'] ?? 0);
$industry_id   = (int)($input['industry_id'] ?? 0);
$zip           = trim((string)($input['zip'] ?? ''));
$city          = trim((string)($input['city'] ?? ''));
$utca          = trim((string)($input['utca'] ?? ''));
$hazszam       = trim((string)($input['hazszam'] ?? ''));

// Jelszó összetettség validáció
function isStrongPassword(string $pw): bool {
  if (mb_strlen($pw) < 6) return false;
  if (!preg_match('/[A-Z]/', $pw)) return false;
  if (!preg_match('/\d/', $pw)) return false;
  if (!preg_match('/[^A-Za-z0-9]/', $pw)) return false;
  return true;
}

// Validáció
$errors = [];

if ($name === '') $errors[] = "Név mező kötelező.";
if ($business_name === '') $errors[] = "Üzletnem mező kötelező.";
if ($email === '') $errors[] = "Email mező kötelező.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Hibás email formátum.";
if ($password === '') $errors[] = "Jelszó mező kötelező.";
if ($password2 === '') $errors[] = "Jelszó megerősítés mező kötelező.";
if ($phone === '') $errors[] = "Telefonszám mező kötelező.";
if ($service_id <= 0) $errors[] = "Érvényes szolgáltatás szükséges.";
if ($zip === '') $errors[] = "Irányítószám mező kötelező.";
if (!preg_match('/^[0-9]{4}$/', $zip)) $errors[] = "Az irányítószám 4 számjegy legyen.";
if ($city === '') $errors[] = "Város mező kötelező.";
if ($utca === '') $errors[] = "Utca mező kötelező.";
if ($hazszam === '') $errors[] = "Házszám mező kötelező.";
if ($password !== $password2) $errors[] = "A jelszó és a megerősítés nem egyezik.";
if (!isStrongPassword($password)) $errors[] = "A jelszónak legalább 6 karakteresnek kell lennie, és tartalmaznia kell: 1 nagybetűt, 1 számot és 1 speciális karaktert.";

if (!empty($errors)) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => implode(" ", $errors)
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

// Szolgáltatás ellenőrzése
$checkService = $pdo->prepare("SELECT id FROM services WHERE id = ? LIMIT 1");
$checkService->execute([$service_id]);
if (!$checkService->fetchColumn()) {
  http_response_code(422);
  echo json_encode([
    "success" => false,
    "message" => "Érvénytelen szolgáltatás választás."
  ]);
  exit;
}

try {
  $pdo->beginTransaction();
  
  // Település: keres / beszúr
  $selTown = $pdo->prepare("SELECT id FROM telepulesek WHERE iranyitoszam = ? AND nev = ? LIMIT 1");
  $selTown->execute([$zip, $city]);
  $telepulesId = (int)($selTown->fetchColumn() ?: 0);
  
  if ($telepulesId <= 0) {
    $insTown = $pdo->prepare("INSERT INTO telepulesek (iranyitoszam, nev) VALUES (?, ?)");
    $insTown->execute([$zip, $city]);
    $telepulesId = (int)$pdo->lastInsertId();
  }
  
  // User beszúrása
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $insU = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'provider')");
  $insU->execute([$name, $email, $hash]);
  $newUserId = (int)$pdo->lastInsertId();
  
  // Provider behúrása
  $insP = $pdo->prepare("
    INSERT INTO providers (user_id, business_name, phone, telepules_id, industry_id, service_id, utca, hazszam)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $insP->execute([
    $newUserId,
    $business_name,
    $phone,
    $telepulesId,
    ($industry_id > 0) ? $industry_id : null,
    $service_id,
    $utca,
    $hazszam
  ]);
  
  $newProviderId = (int)$pdo->lastInsertId();
  
  $pdo->commit();
  
  http_response_code(201);
  echo json_encode([
    "success" => true,
    "message" => "Sikeres regisztráció!",
    "provider" => [
      "id"            => $newProviderId,
      "user_id"       => $newUserId,
      "name"          => $name,
      "email"         => $email,
      "business_name" => $business_name
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
