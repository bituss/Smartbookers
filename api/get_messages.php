<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
function out(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}
if (!isset($_SESSION['user_id'])) {
  out(['ok' => false, 'messages' => []], 401);
}
$userId = (int)$_SESSION['user_id'];
$role   = (string)($_SESSION['role'] ?? 'user');
if ($role !== 'user' && $role !== 'provider') {
  out(['ok' => false, 'messages' => []], 400);
}
$conversationId = (int)($_GET['conversation_id'] ?? 0);
$afterId        = (int)($_GET['after_id'] ?? 0);
if ($conversationId <= 0) {
  out(['ok' => false, 'messages' => []], 400);
}
$mysqli = new mysqli("localhost","root","","idopont_foglalas");
if ($mysqli->connect_error) {
  out(['ok' => false, 'messages' => []], 500);
}
$mysqli->set_charset("utf8mb4");
$providerId = 0;
if ($role === 'provider') {
  $st = $mysqli->prepare("SELECT id FROM providers WHERE user_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'messages'=>[]], 500);
  $st->bind_param("i", $userId);
  $st->execute();
  $p = $st->get_result()->fetch_assoc();
  $providerId = (int)($p['id'] ?? 0);
  if ($providerId <= 0) {
    out(['ok' => false, 'messages' => []], 403);
  }
}
if ($role === 'user') {
  $st = $mysqli->prepare("SELECT id FROM conversations WHERE id=? AND user_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'messages'=>[]], 500);
  $st->bind_param("ii", $conversationId, $userId);
} else {
  $st = $mysqli->prepare("SELECT id FROM conversations WHERE id=? AND provider_id=? LIMIT 1");
  if (!$st) out(['ok'=>false,'messages'=>[]], 500);
  $st->bind_param("ii", $conversationId, $providerId);
}
$st->execute();
if (!$st->get_result()->fetch_assoc()) {
  out(['ok' => false, 'messages' => []], 403);
}
$st = $mysqli->prepare("
  SELECT id, by_provider, body, created_at
  FROM messages
  WHERE conversation_id=? AND id>?
  ORDER BY id ASC
  LIMIT 200
");
if (!$st) out(['ok'=>false,'messages'=>[]], 500);
$st->bind_param("ii", $conversationId, $afterId);
$st->execute();
$res = $st->get_result();
$messages = [];
while ($row = $res->fetch_assoc()) {
  $byProvider = (int)$row['by_provider'];
  $isMe = ($role === 'user' && $byProvider === 0)
       || ($role === 'provider' && $byProvider === 1);
  $messages[] = [
    'id'          => (int)$row['id'],
    'by_provider' => $byProvider,
    'body'        => (string)$row['body'],
    'created_at'  => (string)$row['created_at'],
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
    $st->bind_param("i", $conversationId);
    $st->execute();
  }
} else {
  $st = $mysqli->prepare("
    UPDATE messages
    SET seen_by_provider=1
    WHERE conversation_id=? AND by_provider=0
  ");
  if ($st) {
    $st->bind_param("i", $conversationId);
    $st->execute();
  }
}
out(['ok' => true, 'messages' => $messages]);