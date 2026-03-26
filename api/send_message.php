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
  out(['ok' => false, 'msg' => 'Nincs belépve.'], 401);
}

$userId = (int)$_SESSION['user_id'];
$role   = (string)($_SESSION['role'] ?? 'user');

if ($role !== 'user' && $role !== 'provider') {
  out(['ok' => false, 'msg' => 'Nincs jogosultság.'], 403);
}

$conversationId = (int)($_POST['conversation_id'] ?? 0);
$body = trim((string)($_POST['body'] ?? ''));

if ($conversationId <= 0 || $body === '' || mb_strlen($body) > 1000) {
  out(['ok' => false, 'msg' => 'Hibás adat.'], 400);
}

$mysqli = new mysqli("localhost","root","","idopont_foglalas");
if ($mysqli->connect_error) {
  out(['ok' => false, 'msg' => 'DB hiba.'], 500);
}
$mysqli->set_charset("utf8mb4");

/**
 * Provider azonosító (ha provider)
 */
$providerId = 0;
if ($role === 'provider') {
  $st = $mysqli->prepare("SELECT id FROM providers WHERE user_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'msg'=>'SQL hiba (providers).'], 500);

  $st->bind_param("i", $userId);
  $st->execute();
  $p = $st->get_result()->fetch_assoc();
  $providerId = (int)($p['id'] ?? 0);

  if ($providerId <= 0) {
    out(['ok' => false, 'msg' => 'Provider profil hiányzik.'], 403);
  }
}

/**
 * Jogosultság ellenőrzés
 */
if ($role === 'user') {
  $st = $mysqli->prepare("SELECT id FROM conversations WHERE id=? AND user_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'msg'=>'SQL hiba (conv user).'], 500);

  $st->bind_param("ii", $conversationId, $userId);
} else {
  $st = $mysqli->prepare("SELECT id FROM conversations WHERE id=? AND provider_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'msg'=>'SQL hiba (conv provider).'], 500);

  $st->bind_param("ii", $conversationId, $providerId);
}

$st->execute();
if (!$st->get_result()->fetch_assoc()) {
  out(['ok' => false, 'msg' => 'Nincs jogosultság.'], 403);
}

/**
 * Mentés
 */
$byProvider = ($role === 'provider') ? 1 : 0;
$seenByUser = ($role === 'user') ? 1 : 0;
$seenByProv = ($role === 'provider') ? 1 : 0;

$st = $mysqli->prepare("
  INSERT INTO messages
    (conversation_id, by_provider, body, seen_by_user, seen_by_provider)
  VALUES
    (?, ?, ?, ?, ?)
");
if (!$st) out(['ok'=>false,'msg'=>'SQL hiba (insert message).'], 500);

$st->bind_param("iisii", $conversationId, $byProvider, $body, $seenByUser, $seenByProv);

if (!$st->execute()) {
  out(['ok' => false, 'msg' => 'Nem sikerült menteni.'], 500);
}

$messageId = (int)$st->insert_id;

/**
 * conversations.last_message_at frissítés:
 * A dumpodban nincs ilyen oszlop, ezért ezt csak akkor csináld,
 * ha tényleg létrehoztad a mezőt.
 *
 * Ha NINCS last_message_at, akkor ezt a blokkot hagyd kikommentelve,
 * vagy add hozzá az oszlopot SQL-lel.
 */
// $st = $mysqli->prepare("UPDATE conversations SET last_message_at=NOW() WHERE id=?");
// if ($st) {
//   $st->bind_param("i", $conversationId);
//   $st->execute();
// }

out(['ok' => true, 'message_id' => $messageId]);