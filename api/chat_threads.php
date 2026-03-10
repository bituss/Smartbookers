<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// nagyon fontos: ne törje szét a JSON-t warning/notice
ini_set('display_errors', '0');

function out(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
  out(['unread_total' => 0, 'threads' => []]);
}

$userId = (int)$_SESSION['user_id'];
$role   = (string)$_SESSION['role'];

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) {
  out(['unread_total' => 0, 'threads' => []]);
}
$mysqli->set_charset("utf8mb4");

$defaultAvatar = "/Smartbookers/public/images/avatars/a1.png";

// provider_id ha provider
$providerId = 0;
if ($role === 'provider') {
  $st = $mysqli->prepare("SELECT id FROM providers WHERE user_id=? LIMIT 1");
  if (!$st) out(['unread_total'=>0,'threads'=>[]]);

  $st->bind_param("i", $userId);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $providerId = (int)($r['id'] ?? 0);

  if ($providerId <= 0) {
    out(['unread_total' => 0, 'threads' => []]);
  }
}

if ($role === 'user') {

  $sql = "
    SELECT
      c.id AS conversation_id,
      p.business_name AS title,
      COALESCE(NULLIF(p.avatar,''), ?) AS avatar,

      (SELECT m.body
       FROM messages m
       WHERE m.conversation_id=c.id
       ORDER BY m.created_at DESC, m.id DESC
       LIMIT 1) AS last_message,

      (SELECT COUNT(*)
       FROM messages m2
       WHERE m2.conversation_id=c.id
         AND m2.seen_by_user=0
         AND m2.sender_role='provider') AS unread_count

    FROM conversations c
    JOIN providers p ON p.id = c.provider_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
  ";

  $st = $mysqli->prepare($sql);
  if (!$st) out(['unread_total'=>0,'threads'=>[]]);

  $st->bind_param("si", $defaultAvatar, $userId);

} else {

  $sql = "
    SELECT
      c.id AS conversation_id,
      u.name AS title,
      COALESCE(NULLIF(u.avatar,''), ?) AS avatar,

      (SELECT m.body
       FROM messages m
       WHERE m.conversation_id=c.id
       ORDER BY m.created_at DESC, m.id DESC
       LIMIT 1) AS last_message,

      (SELECT COUNT(*)
       FROM messages m2
       WHERE m2.conversation_id=c.id
         AND m2.seen_by_provider=0
         AND m2.sender_role='user') AS unread_count

    FROM conversations c
    JOIN users u ON u.id = c.user_id
    WHERE c.provider_id = ?
    ORDER BY c.created_at DESC
  ";

  $st = $mysqli->prepare($sql);
  if (!$st) out(['unread_total'=>0,'threads'=>[]]);

  $st->bind_param("si", $defaultAvatar, $providerId);
}

if (!$st->execute()) {
  out(['unread_total'=>0,'threads'=>[]]);
}

$res = $st->get_result();

$threads = [];
$unreadTotal = 0;

while ($row = $res->fetch_assoc()) {
  $row['unread_count'] = (int)($row['unread_count'] ?? 0);
  $unreadTotal += $row['unread_count'];

  if (empty($row['avatar'])) $row['avatar'] = $defaultAvatar;
  if ($row['last_message'] === null) $row['last_message'] = "";

  $threads[] = [
    'conversation_id' => (int)$row['conversation_id'],
    'title' => (string)$row['title'],
    'avatar' => (string)$row['avatar'],
    'last_message' => (string)$row['last_message'],
    'unread_count' => (int)$row['unread_count'],
  ];
}

out([
  'unread_total' => $unreadTotal,
  'threads' => $threads
]);