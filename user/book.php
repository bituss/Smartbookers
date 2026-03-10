<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
  header("Location: /Smartbookers/public/login.php");
  exit;
}

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) die("Kapcsolódási hiba: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");

$userId = (int)$_SESSION['user_id'];
$availabilityId = (int)($_GET['availability_id'] ?? 0);

if ($availabilityId <= 0) {
  header("Location: /Smartbookers/user/dashboard.php?error=1&msg=" . urlencode("⚠️ Hibás időpont."));
  exit;
}

/* 1) Availability betöltése */
$st = $mysqli->prepare("
  SELECT
    a.id,
    a.provider_id,
    a.sub_service_id,
    a.slot_date,
    a.start_time,
    a.is_active,
    p.business_name,
    ss.name AS sub_service_name
  FROM provider_availability a
  JOIN providers p ON p.id = a.provider_id
  LEFT JOIN sub_services ss ON ss.id = a.sub_service_id
  WHERE a.id = ?
  LIMIT 1
");
$st->bind_param("i", $availabilityId);
$st->execute();
$slot = $st->get_result()->fetch_assoc();

if (!$slot) {
  header("Location: /Smartbookers/user/dashboard.php?error=1&msg=" . urlencode("⚠️ Hibás időpont."));
  exit;
}
if ((int)$slot['is_active'] !== 1) {
  header("Location: /Smartbookers/user/dashboard.php?error=1&msg=" . urlencode("⚠️ Ez az időpont már nem aktív."));
  exit;
}

$providerId   = (int)$slot['provider_id'];
$subServiceId = (int)($slot['sub_service_id'] ?? 0);

$providerName = (string)($slot['business_name'] ?? '');
$subServiceName = (string)($slot['sub_service_name'] ?? '');

$dtStr = $slot['slot_date'] . ' ' . $slot['start_time'];

/* időpont validálás */
$ts = strtotime($dtStr);
if ($ts === false || $ts < time() - 60) {
  header("Location: /Smartbookers/user/dashboard.php?error=1&msg=" . urlencode("⚠️ Ez az időpont már elmúlt."));
  exit;
}

/* 2) Foglaltság ellenőrzése (bookings alapján) */
$st2 = $mysqli->prepare("
  SELECT id
  FROM bookings
  WHERE provider_id = ?
    AND booking_time = ?
    AND cancelled_at IS NULL
  LIMIT 1
");
$st2->bind_param("is", $providerId, $dtStr);
$st2->execute();
$already = $st2->get_result()->fetch_assoc();

if ($already) {
  header("Location: /Smartbookers/user/dashboard.php?error=1&msg=" . urlencode("⚠️ Ezt az időpontot már lefoglalták."));
  exit;
}

$mysqli->begin_transaction();

try {
  /* 3) Foglalás beszúrása */
  $ins = $mysqli->prepare("
    INSERT INTO bookings (provider_id, user_id, booking_time, sub_service_id)
    VALUES (?, ?, ?, ?)
  ");
  $ins->bind_param("iisi", $providerId, $userId, $dtStr, $subServiceId);

  if (!$ins->execute()) {
    throw new Exception("Nem sikerült a foglalás mentése.");
  }

  $bookingId = (int)$mysqli->insert_id;

  /* 4) Conversation upsert */
  $conv = $mysqli->prepare("
    INSERT INTO conversations (user_id, provider_id)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
  ");
  $conv->bind_param("ii", $userId, $providerId);

  if (!$conv->execute()) {
    throw new Exception("Nem sikerült a beszélgetés létrehozása.");
  }

  $conversationId = (int)$mysqli->insert_id;

  /* 5) Automata üzenet */
  $txt =
    "Szia! Lefoglaltam egy időpontot.\n" .
    
    "Szolgáltatás: " . ($subServiceName !== '' ? $subServiceName : '-') . "\n" .
    "Időpont: " . date("Y-m-d H:i", strtotime($dtStr));

  // Ha van seen_by_user/provider oszlop, töltsük
  $hasSeenUser = $mysqli->query("SHOW COLUMNS FROM messages LIKE 'seen_by_user'");
  $hasSeenCols = ($hasSeenUser && $hasSeenUser->num_rows > 0);

  if ($hasSeenCols) {
    $msg = $mysqli->prepare("
      INSERT IGNORE INTO messages
        (conversation_id, body, sender_role, booking_id, type, seen_by_user, seen_by_provider)
      VALUES
        (?, ?, 'system', ?, 'booking_auto', 1, 0)
    ");
    $msg->bind_param("isi", $conversationId, $txt, $bookingId);
  } else {
    $msg = $mysqli->prepare("
      INSERT IGNORE INTO messages
        (conversation_id, body, sender_role, booking_id, type)
      VALUES
        (?, ?, 'system', ?, 'booking_auto')
    ");
    $msg->bind_param("isi", $conversationId, $txt, $bookingId);
  }

  if (!$msg->execute()) {
    throw new Exception("Nem sikerült az automata üzenet mentése.");
  }

  $mysqli->commit();

  // ✅ Ettől “felugrik” (megnyílik) a chat közvetlenül foglalás után
  header("Location: /Smartbookers/chat/chat.php?conversation_id=" . $conversationId);
  exit;

} catch (Throwable $e) {
  $mysqli->rollback();
  header("Location: /Smartbookers/user/dashboard.php?error=1&msg=" . urlencode("⚠️ Nem sikerült a foglalás."));
  exit;
}