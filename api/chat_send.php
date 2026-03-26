<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
  out(['ok' => false, 'error' => 'Nincs belépve.'], 401);
}

$userId = (int)$_SESSION['user_id'];
$role   = (string)$_SESSION['role'];

if ($role !== 'user' && $role !== 'provider') {
  out(['ok' => false, 'error' => 'Nincs jogosultság.'], 403);
}

$cid  = (int)($_POST['conversation_id'] ?? 0);
$body = trim((string)($_POST['body'] ?? ''));

if ($cid <= 0) {
  out(['ok' => false, 'error' => 'Hibás conversation_id.'], 400);
}
if ($body === '' || mb_strlen($body) > 2000) {
  out(['ok' => false, 'error' => 'Üzenet üres vagy túl hosszú.'], 400);
}

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) {
  out(['ok' => false, 'error' => 'DB hiba.'], 500);
}
$mysqli->set_charset("utf8mb4");

/**
 * Provider azonosító (ha provider küld)
 */
$providerId = 0;
if ($role === 'provider') {
  $st = $mysqli->prepare("SELECT id FROM providers WHERE user_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'error'=>'SQL hiba (providers).'], 500);

  $st->bind_param("i", $userId);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $providerId = (int)($r['id'] ?? 0);

  if ($providerId <= 0) {
    out(['ok' => false, 'error' => 'Provider profil hiányzik.'], 403);
  }
}

/**
 * Jogosultság + conversation résztvevők
 */
if ($role === 'user') {
  $st = $mysqli->prepare("
    SELECT id, user_id, provider_id
    FROM conversations
    WHERE id=? AND user_id=?
    LIMIT 1
  ");
  if (!$st) out(['ok'=>false,'error'=>'SQL hiba (conv user).'], 500);
  $st->bind_param("ii", $cid, $userId);
} else {
  $st = $mysqli->prepare("
    SELECT id, user_id, provider_id
    FROM conversations
    WHERE id=? AND provider_id=?
    LIMIT 1
  ");
  if (!$st) out(['ok'=>false,'error'=>'SQL hiba (conv provider).'], 500);
  $st->bind_param("ii", $cid, $providerId);
}

$st->execute();
$conv = $st->get_result()->fetch_assoc();

if (!$conv) {
  out(['ok' => false, 'error' => 'Nincs jogosultság ehhez a beszélgetéshez.'], 403);
}

/**
 * seen mezők:
 * - ha user küldi -> seen_by_user=1, seen_by_provider=0
 * - ha provider küldi -> seen_by_provider=1, seen_by_user=0
 */
$seenByUser = ($role === 'user') ? 1 : 0;
$seenByProv = ($role === 'provider') ? 1 : 0;
$byProvider  = ($role === 'provider') ? 1 : 0;

/**
 * Mentés
 */
$st = $mysqli->prepare("
  INSERT INTO messages
    (conversation_id, by_provider, body, seen_by_user, seen_by_provider)
  VALUES
    (?, ?, ?, ?, ?)
");
if (!$st) out(['ok'=>false,'error'=>'SQL hiba (insert message).'], 500);

$st->bind_param("iisii", $cid, $byProvider, $body, $seenByUser, $seenByProv);

if (!$st->execute()) {
  out(['ok' => false, 'error' => 'Nem sikerült menteni az üzenetet.'], 500);
}

out([
  'ok' => true,
  'message_id' => (int)$st->insert_id
]);