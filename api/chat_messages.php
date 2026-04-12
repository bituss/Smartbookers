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
  out(['ok' => false, 'error' => 'Hibás szerepkör.'], 400);
}
$cid = (int)($_GET['conversation_id'] ?? 0);
if ($cid <= 0) {
  out(['ok' => false, 'error' => 'Hibás conversation_id.'], 400);
}
$mysqli = new mysqli("localhost","root","","idopont_foglalas");
if ($mysqli->connect_error) {
  out(['ok' => false, 'error' => 'DB hiba.'], 500);
}
$mysqli->set_charset("utf8mb4");
$defaultAvatar = "/Smartbookers/public/images/avatars/a1.png";
$providerId = 0;
if ($role === 'provider') {
  $st = $mysqli->prepare("SELECT id FROM providers WHERE user_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'error'=>'SQL hiba (providers).'], 500);
  $st->bind_param("i", $userId);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $providerId = (int)($r['id'] ?? 0);
  if ($providerId <= 0) {
    out(['ok' => false, 'error' => 'Nincs szolgáltató profil.'], 403);
  }
}
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
$convUserId     = (int)$conv['user_id'];
$convProviderId = (int)$conv['provider_id'];
$st = $mysqli->prepare("
  SELECT
    m.id,
    m.by_provider,
    m.body,
    m.created_at,
    CASE
      WHEN m.by_provider=0 THEN COALESCE(NULLIF(u.avatar,''), ?)
      ELSE COALESCE(NULLIF(p.avatar,''), ?)
    END AS avatar
  FROM messages m
  LEFT JOIN users u ON u.id = ?
  LEFT JOIN providers p ON p.id = ?
  WHERE m.conversation_id=?
  ORDER BY m.created_at ASC, m.id ASC
");
if (!$st) out(['ok'=>false,'error'=>'SQL hiba (messages).'], 500);
$st->bind_param(
  "ssiii",
  $defaultAvatar, 
  $defaultAvatar, 
  $convUserId,
  $convProviderId,
  $cid
);
$st->execute();
$res = $st->get_result();
$messages = [];
while ($m = $res->fetch_assoc()) {
  $byProvider = (int)$m['by_provider'];
  $isMe = ($role === 'user' && $byProvider === 0)
       || ($role === 'provider' && $byProvider === 1);
  $messages[] = [
    'id'          => (int)$m['id'],
    'body'        => (string)$m['body'],
    'created_at'  => (string)$m['created_at'],
    'by_provider' => $byProvider,
    'avatar'      => (string)$m['avatar'],
    'is_me'       => $isMe,
  ];
}
if ($role === 'user') {
  $st = $mysqli->prepare("
    UPDATE messages
    SET seen_by_user=1
    WHERE conversation_id=? AND by_provider=1
  ");
  if ($st) {
    $st->bind_param("i", $cid);
    $st->execute();
  }
} else {
  $st = $mysqli->prepare("
    UPDATE messages
    SET seen_by_provider=1
    WHERE conversation_id=? AND by_provider=0
  ");
  if ($st) {
    $st->bind_param("i", $cid);
    $st->execute();
  }
}
out(['ok' => true, 'messages' => $messages]);