<?php
declare(strict_types=1);
session_start();

/* =========================
   Csak bejelentkezett USER
========================= */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  header("Location: /Smartbookers/public/login.php");
  exit;
}

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) die("Kapcsolódási hiba: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");

// Ne dobjon Fatal-t mysqli_sql_exception-re: mi kezeljük
mysqli_report(MYSQLI_REPORT_OFF);

$user_id = (int)$_SESSION['user_id'];

/* =========================
   10 előre definiált avatar
========================= */
$avatars = [];
for ($i = 1; $i <= 10; $i++) $avatars[] = "/Smartbookers/public/images/avatars/a{$i}.png";

/* =========================
   Slot generálás (nyitvatartás -> idősávok)
========================= */
function generateSlotsForDay(string $day, string $startTime, string $endTime, int $slotMinutes): array {
  $slots = [];
  if ($slotMinutes <= 0) return $slots;

  $start = DateTime::createFromFormat('Y-m-d H:i:s', $day . ' ' . $startTime);
  $end   = DateTime::createFromFormat('Y-m-d H:i:s', $day . ' ' . $endTime);
  if (!$start || !$end) return $slots;

  $interval = new DateInterval('PT' . $slotMinutes . 'M');
  $cursor = clone $start;

  while (true) {
    $next = (clone $cursor)->add($interval);
    if ($next > $end) break;
    $slots[] = $cursor->format('Y-m-d H:i:s');
    $cursor = $next;
  }
  return $slots;
}

function hasColumn(mysqli $db, string $table, string $column): bool {
  $q = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");

  if (!$q) {
    return false; // vagy logolhatod: $db->error
  }

  $q->bind_param("s", $column);
  $q->execute();
  $r = $q->get_result();

  return ($r && $r->num_rows > 0);
}

/* =========================
   User adatok
========================= */
$stmt = $mysqli->prepare("SELECT id, name, email, avatar FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) die("Felhasználó nem található.");

$currentAvatar = !empty($user['avatar']) ? (string)$user['avatar'] : "/Smartbookers/public/images/avatars/a1.png";

$success = $_SESSION['success'] ?? "";
unset($_SESSION['success']);
$error = "";

/* dátum limit: ma -> +6 hónap */
$today  = date('Y-m-d');
$maxDay = date('Y-m-d', strtotime('+6 months'));

/* =========================
   AVATAR MENTÉS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_avatar'])) {
  $newAvatar = (string)($_POST['avatar'] ?? '');

  if (!in_array($newAvatar, $avatars, true)) {
    $error = "Érvénytelen avatar választás.";
  } else {
    $st = $mysqli->prepare("UPDATE users SET avatar=? WHERE id=? LIMIT 1");
    $st->bind_param("si", $newAvatar, $user_id);

    if ($st->execute()) {
      $success = "Profilkép frissítve!";
      $currentAvatar = $newAvatar;
      $_SESSION['avatar'] = $newAvatar;
    } else {
      $error = "Hiba mentés közben.";
    }
  }
}

/* =========================
   FOGLALÁS LEMONDÁS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'cancel_booking') {
  $bookingId = (int)($_POST['booking_id'] ?? 0);

  if ($bookingId <= 0) {
    $error = "Érvénytelen foglalás azonosító.";
  } else {
    $st = $mysqli->prepare("
      UPDATE bookings
      SET cancelled_at = NOW(),
          provider_seen = 0
      WHERE id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $st->bind_param("ii", $bookingId, $user_id);

    if ($st->execute() && $st->affected_rows > 0) {
      $success = "Foglalás lemondva.";
    } else {
      $error = "Nem sikerült lemondani (lehet már le volt mondva vagy lejárt).";
    }
  }
}

/* =========================
   SZOLGÁLTATÁSOK LISTÁJA
========================= */
$services = [];
$resS = $mysqli->query("SELECT id, name FROM services ORDER BY name ASC");
if ($resS) while ($row = $resS->fetch_assoc()) $services[] = $row;

/* =========================
   Választások (POST/GET)
========================= */
$selectedServiceId    = (int)($_POST['search_service_id'] ?? ($_GET['service_id'] ?? 0));
$selectedSubServiceId = (int)($_POST['search_sub_service_id'] ?? ($_GET['sub_service_id'] ?? 0));

$searchDay = (string)($_POST['search_day'] ?? ($_GET['day'] ?? $today));
$searchDay = preg_replace('/[^0-9\-]/', '', $searchDay);

/* =========================
   ALSZOLGÁLTATÁSOK listája a kiválasztott service-re
========================= */
$subServices = [];
if ($selectedServiceId > 0) {
  $stSS = $mysqli->prepare("SELECT id, name FROM sub_services WHERE service_id=? ORDER BY name ASC");
  $stSS->bind_param("i", $selectedServiceId);
  $stSS->execute();
  $rsSS = $stSS->get_result();
  while ($r = $rsSS->fetch_assoc()) $subServices[] = $r;

  if ($selectedSubServiceId > 0) {
    $ok = false;
    foreach ($subServices as $ss) {
      if ((int)$ss['id'] === $selectedSubServiceId) { $ok = true; break; }
    }
    if (!$ok) $selectedSubServiceId = 0;
  }
} else {
  $selectedSubServiceId = 0;
}

/* =========================
   SLOT KERESÉS
========================= */
$availableSlots = [];
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'search_slots') {
  $searched = true;

  if ($selectedServiceId <= 0) {
    $error = "Válassz szolgáltatást!";
  } elseif ($selectedSubServiceId <= 0) {
    $error = "Válassz alszolgáltatást!";
  } elseif (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $searchDay)) {
    $error = "Hibás dátum.";
  } elseif ($searchDay < $today) {
    $error = "Múltbeli napra nem lehet keresni.";
  } elseif ($searchDay > $maxDay) {
    $error = "Csak fél évre előre lehet időpontot nézni.";
  } else {

    $stA = $mysqli->prepare("
      SELECT
        a.provider_id,
        a.start_time,
        a.end_time,
        a.slot_minutes,
        p.business_name,
        p.phone
      FROM provider_availability a
      JOIN providers p ON p.id = a.provider_id
      WHERE a.sub_service_id = ?
        AND a.slot_date = ?
        AND a.is_active = 1
      ORDER BY p.business_name ASC, a.start_time ASC
    ");
    $stA->bind_param("is", $selectedSubServiceId, $searchDay);
    $stA->execute();
    $resA = $stA->get_result();

    $byProvider = [];
    while ($a = $resA->fetch_assoc()) {
      $pid = (int)$a['provider_id'];

      if (!isset($byProvider[$pid])) {
        $byProvider[$pid] = [
          'provider_id' => $pid,
          'business_name' => (string)$a['business_name'],
          'phone' => (string)($a['phone'] ?? ''),
          'allSlots' => []
        ];
      }

      $slots = generateSlotsForDay(
        $searchDay,
        (string)$a['start_time'],
        (string)$a['end_time'],
        (int)$a['slot_minutes']
      );

      $byProvider[$pid]['allSlots'] = array_merge($byProvider[$pid]['allSlots'], $slots);
    }

    if (count($byProvider) === 0) {
      $availableSlots = [];
    } else {

      $dayStart = $searchDay . " 00:00:00";
      $dayEnd   = $searchDay . " 23:59:59";

      $providerIds = array_keys($byProvider);
      $placeholders = implode(',', array_fill(0, count($providerIds), '?'));

      $types = str_repeat('i', count($providerIds)) . "ss";
      $params = array_merge($providerIds, [$dayStart, $dayEnd]);

      $sqlB = "
        SELECT provider_id, booking_time
        FROM bookings
        WHERE cancelled_at IS NULL
          AND provider_id IN ($placeholders)
          AND booking_time BETWEEN ? AND ?
      ";

      $stB = $mysqli->prepare($sqlB);

      $bind = [];
      $bind[] = $types;
      foreach ($params as $k => $v) $bind[] = &$params[$k];
      call_user_func_array([$stB, 'bind_param'], $bind);

      $stB->execute();
      $resB = $stB->get_result();

      $taken = [];
      while ($b = $resB->fetch_assoc()) {
        $pid = (int)$b['provider_id'];
        $bt  = (string)$b['booking_time'];
        if (!isset($taken[$pid])) $taken[$pid] = [];
        $taken[$pid][$bt] = true;
      }

      $nowTs = time();

      foreach ($byProvider as $pid => $p) {
        $allSlots = $p['allSlots'];
        sort($allSlots);

        foreach ($allSlots as $slot) {
          if (!empty($taken[$pid][$slot])) continue;

          if ($searchDay === $today) {
            $slotTs = strtotime($slot);
            if ($slotTs !== false && $slotTs < $nowTs) continue;
          }

          $availableSlots[] = [
            'provider_id' => (int)$p['provider_id'],
            'business_name' => (string)$p['business_name'],
            'phone' => (string)$p['phone'],
            'slot' => (string)$slot
          ];
        }
      }

      usort($availableSlots, function($a, $b){
        $t1 = strtotime($a['slot']); $t2 = strtotime($b['slot']);
        if ($t1 === $t2) return strcmp($a['business_name'], $b['business_name']);
        return $t1 <=> $t2;
      });
    }
  }
}

/* =========================
   FOGLALÁS LÉTREHOZÁS + CHAT + AUTO ÜZENET
   !!! CSAK create_booking esetén !!!
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'create_booking') {

  $providerId   = (int)($_POST['provider_id'] ?? 0);
  $serviceId    = (int)($_POST['service_id'] ?? 0);
  $subServiceId = (int)($_POST['sub_service_id'] ?? 0);
  $day          = preg_replace('/[^0-9\-]/', '', (string)($_POST['day'] ?? $today));
  $slot         = (string)($_POST['slot_datetime'] ?? '');

  // visszatöltés
  $selectedServiceId    = $serviceId;
  $selectedSubServiceId = $subServiceId;
  $searchDay            = $day;
  $searched = true;

  if ($serviceId <= 0 || $subServiceId <= 0 || $providerId <= 0) {
    $error = "Hiányzó választás (szolgáltatás/alszolgáltatás).";
  } elseif (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $day) || $day < $today) {
    $error = "Múltbeli napra nem lehet foglalni.";
  } elseif ($day > $maxDay) {
    $error = "Csak fél évre előre lehet foglalni.";
  } elseif (!preg_match('/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/', $slot)) {
    $error = "Érvénytelen idősáv.";
  } else {

    $mysqli->begin_transaction();
    try {
      // 1) booking insert
      $ins = $mysqli->prepare("
        INSERT INTO bookings (provider_id, user_id, booking_time, sub_service_id)
        VALUES (?, ?, ?, ?)
      ");
      $ins->bind_param("iisi", $providerId, $user_id, $slot, $subServiceId);

      if (!$ins->execute()) {
        if ($mysqli->errno === 1062) {
          throw new Exception("Ez az időpont már foglalt.");
        }
        throw new Exception("Nem sikerült foglalni.");
      }
      $bookingId = (int)$mysqli->insert_id;

      // 2) conversation upsert (NE duplikálódjon)
      $conv = $mysqli->prepare("
        INSERT INTO conversations (user_id, provider_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
      ");
      $conv->bind_param("ii", $user_id, $providerId);

      if (!$conv->execute()) {
        throw new Exception("Nem sikerült a beszélgetés létrehozása.");
      }
      $conversationId = (int)$mysqli->insert_id;

      // 3) meta az üzenethez
      $meta = $mysqli->prepare("
        SELECT p.business_name, ss.name AS sub_service_name
        FROM providers p
        LEFT JOIN sub_services ss ON ss.id = ?
        WHERE p.id = ?
        LIMIT 1
      ");
      $meta->bind_param("ii", $subServiceId, $providerId);
      $meta->execute();
      $m = $meta->get_result()->fetch_assoc();

      $providerName   = (string)($m['business_name'] ?? '-');
      $subServiceName = (string)($m['sub_service_name'] ?? '-');

      // 4) auto üzenet: USER küldi a providernek
      $txt =
        "Szia!\n" .
        "Lefoglaltam egy időpontot.\n" .
        "Időpont: " . date("Y-m-d H:i", strtotime($slot)) . "\n" .
        "Szolgáltatás: " . $subServiceName . "\n" .
        "Szolgáltató: " . $providerName;

      // 1 db booking_auto per conversation (update/insert)
      $chkMsg = $mysqli->prepare("
        SELECT id
        FROM messages
        WHERE conversation_id=? AND type='booking_auto'
        ORDER BY id DESC
        LIMIT 1
      ");
      $chkMsg->bind_param("i", $conversationId);
      $chkMsg->execute();
      $existing = $chkMsg->get_result()->fetch_assoc();

      $hasSeenUser = hasColumn($mysqli, "messages", "seen_by_user");
      $hasSeenProv = hasColumn($mysqli, "messages", "seen_by_provider");

      if ($existing) {
        $msgId = (int)$existing['id'];
        $upd = $mysqli->prepare("
          UPDATE messages
          SET body=?, booking_id=?, sender_role='user', sender_user_id=?, sender_provider_id=NULL
          WHERE id=? AND conversation_id=?
        ");
        $upd->bind_param("siiii", $txt, $bookingId, $user_id, $msgId, $conversationId);

        if (!$upd->execute()) {
          throw new Exception("Nem sikerült az automata üzenet frissítése.");
        }
      } else {
        if ($hasSeenUser && $hasSeenProv) {
          $msg = $mysqli->prepare("
            INSERT INTO messages
              (conversation_id, body, sender_role, sender_user_id, sender_provider_id, booking_id, type, seen_by_user, seen_by_provider)
            VALUES
              (?, ?, 'user', ?, NULL, ?, 'booking_auto', 1, 0)
          ");
          $msg->bind_param("isii", $conversationId, $txt, $user_id, $bookingId);
        } else {
          $msg = $mysqli->prepare("
            INSERT INTO messages
              (conversation_id, body, sender_role, sender_user_id, sender_provider_id, booking_id, type)
            VALUES
              (?, ?, 'user', ?, NULL, ?, 'booking_auto')
          ");
          $msg->bind_param("isii", $conversationId, $txt, $user_id, $bookingId);
        }

        if (!$msg->execute()) {
          throw new Exception("Nem sikerült az automata üzenet mentése.");
        }
      }

      $mysqli->commit();

      // chatre dobás
      $createdConversationId = $conversationId;
      $_SESSION['success'] = "Foglalás sikeresen létrehozva.";
      $_SESSION['open_chat'] = $conversationId;
      
      header("Location: /Smartbookers/public/profile.php");
      exit;
    } catch (Throwable $e) {
      $mysqli->rollback();
      $error = $e->getMessage();
    }
  }
}

/* =========================
   SAJÁT FOGLALÁSOK (csak AKTÍV)
========================= */
$myAppointments = [];
$st = $mysqli->prepare("
  SELECT
    b.id AS booking_id,
    b.booking_time AS date_time,
    p.business_name,
    p.phone,
    ss.name AS sub_service_name,
    s.name  AS service_name
  FROM bookings b
  JOIN providers p ON p.id = b.provider_id
  LEFT JOIN sub_services ss ON ss.id = b.sub_service_id
  LEFT JOIN services s ON s.id = ss.service_id
  WHERE b.user_id = ?
    AND b.cancelled_at IS NULL
    AND b.booking_time >= NOW()
  ORDER BY b.booking_time ASC
");
$st->bind_param("i", $user_id);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) $myAppointments[] = $row;

/* HEADER */
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profilom</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<style>
  
  body{font-family: Inter, sans-serif;background: linear-gradient(135deg, #24256e, #ffffff);margin:0;min-height:100vh;}
  .wrap{max-width:1200px; margin:30px auto; padding:0 12px;}
  .title{color:#fff; text-align:center; margin:0 0 18px;}
  .topCard{background: rgba(255,255,255,0.92);border-radius: 16px;box-shadow: 0 10px 25px rgba(0,0,0,.12);padding: 18px;display:flex;gap:18px;align-items:center;flex-wrap:wrap;}
  .avatarNow{width:84px; height:84px; border-radius:999px;border:4px solid rgba(36,37,110,.2);object-fit:cover;flex:0 0 auto;background:#fff;}
  .userMeta{min-width:220px;}
  .userMeta h2{margin:0; color:#0f172a; font-size:20px;}
  .userMeta p{margin:4px 0 0; color:#475569;}
  .msg{margin-top:10px; font-weight:800;}
  
  .err{color:#ef4444;}
  .avatarDropdown{ width:100%; margin-top:12px; }
  .avatarToggle{
    width:100%;padding:14px 16px;border:0;border-radius:14px;
    background: linear-gradient(135deg,#24256e,#000);
    color:#fff;font-weight:900;cursor:pointer;font-size:15px;
    display:flex;align-items:center;justify-content:space-between;gap:10px;
  }
  .avatarPanel{display:none;margin-top:12px;animation: fadeIn .18s ease;}
  .avatarPanel.open{ display:block; }
  @keyframes fadeIn{from{opacity:0; transform:translateY(-4px);}to{opacity:1; transform:translateY(0);}}
  .grid{display:grid;grid-template-columns: repeat(5, minmax(0, 1fr));gap:12px;width:100%;}
  .avBtn{
    border:2px solid transparent;background:#fff;border-radius:16px;padding:10px;
    cursor:pointer;transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
    box-shadow: 0 6px 14px rgba(0,0,0,.08);
  }
  .avBtn:hover{transform: translateY(-2px);}
  .avBtn.active{border-color:#24256e;box-shadow: 0 10px 20px rgba(36,37,110,.18);}
  .avImg{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:14px;display:block;}
  .saveRow{margin-top:12px; width:100%;}
  .saveBtn{
    width:100%;padding:14px 16px;border:0;border-radius:14px;
    background: linear-gradient(135deg,#24256e,#000);
    color:#fff;font-weight:900;cursor:pointer;
  }
  
  .dash{display:flex;gap:18px;margin-top:22px;flex-wrap:wrap;}
  .col{flex:1; min-width:200px;}
  .h3{color:#fff; text-align:center; margin:0 0 10px; font-weight:900;}
  .card{background: rgba(255,255,255,0.92);border-radius: 14px;padding: 16px;margin-bottom: 12px;box-shadow: 0 8px 18px rgba(0,0,0,.10);border-left: 6px solid #4CAF50;}
  .card.free{border-left-color:#2196F3;}
  .meta{margin-top:8px; color:#334155; font-size:.95rem;}
  .meta strong{color:#0f172a;}
  .input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #cbd5e1;margin-top:8px;outline:none;}
  .btnLink{display:block;margin-top:10px;padding:12px;border-radius:12px;font-weight:900;text-align:center;color:#fff;background: linear-gradient(135deg,#24256e,#000);text-decoration:none;border:0;cursor:pointer;width:100%;}
  .cancelBtn{display:block;margin-top:10px;padding:12px;border-radius:12px;font-weight:900;text-align:center;color:#fff;background: linear-gradient(135deg,#ef4444,#991b1b);border:0;width:100%;cursor:pointer;}

  .slotGrid{display:grid;grid-template-columns: repeat(2, minmax(0, 1fr));gap:10px;margin-top:12px;}
  .slotBtn{
    padding:10px 12px;border-radius:12px;border:0;cursor:pointer;
    font-weight:900;color:#fff;background: linear-gradient(135deg,#24256e,#000);
    width:100%;text-align:left;
  }
  .slotBtn small{display:block; font-weight:700; opacity:.9; margin-top:4px;}
  @media (max-width: 900px){.grid{grid-template-columns: repeat(3, minmax(0, 1fr));}}
  @media (max-width: 700px){.slotGrid{grid-template-columns: 1fr;}}
  @media (max-width: 520px){
    .grid{grid-template-columns: repeat(2, minmax(0, 1fr));}
    .topCard{justify-content:center;}
    .userMeta{text-align:center;}
  }
</style>

<?php if (!empty($success)): ?>
<div class="modalOverlay" id="successModal">
  <div class="modalBox">
    <div class="icon">✔</div>
    
    <h2>
    <?php
$title = "Sikeres művelet";

if (str_contains($success, 'lemondva')) {
  $title = "Sikeres lemondás";
}
elseif (str_contains($success, 'bejelentkezés')) {
  $title = "Sikeres bejelentkezés";
}
?>
<h2><?= $title ?></h2>
    </h2>

    <p><?= htmlspecialchars($success) ?></p>

    <button onclick="closeModal()">Rendben</button>
  </div>
</div>
<?php endif; ?>

<style>
.modalOverlay{
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(4px);
  display:flex;
  align-items:center;
  justify-content:center;
  z-index:9999;
  animation: fadeIn 0.3s ease;
}

.modalBox{
  background: #ffffff;
  padding: 30px 40px;
  border-radius: 18px;
  text-align: center;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 25px 60px rgba(0,0,0,0.3);
  animation: scaleIn 0.3s ease;
}

.modalBox h2{
  margin: 10px 0;
  font-size: 22px;
  font-weight: 900;
  color: #0f172a;
}

.modalBox p{
  color:#64748b;
  margin-bottom:20px;
  font-size:14px;
}

.modalBox .icon{
  width:60px;
  height:60px;
  border-radius:50%;
  background:#16a34a;
  color:white;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:28px;
  margin:0 auto 10px;
}

.modalBox button{
  padding:10px 18px;
  border:none;
  border-radius:10px;
  font-weight:800;
  background: linear-gradient(135deg,#24256e,#000);
  color:white;
  cursor:pointer;
}

.modalBox button:hover{
  opacity:0.9;
}

/* Animációk */
@keyframes fadeIn{
  from{opacity:0;}
  to{opacity:1;}
}

@keyframes scaleIn{
  from{transform:scale(0.8); opacity:0;}
  to{transform:scale(1); opacity:1;}
}
</style>

<script>
function closeModal(){
  document.getElementById("successModal").style.display = "none";
}

/* URL tisztítás */
if (window.location.search.includes("success=booking")) {
  window.history.replaceState({}, document.title, window.location.pathname);
}
</script>


<div class="wrap">
  <h1 class="title">Profilom</h1>

  <div class="topCard">
    <img class="avatarNow" src="<?= htmlspecialchars($currentAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="Profilkép">
    <div class="userMeta">
  <h2><?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></p>

  <?php if($error): ?>
    <div class="msg err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
</div>

    <form method="post" style="width:100%;">
      <input type="hidden" name="set_avatar" value="1">
      <input type="hidden" name="avatar" id="avatarInput" value="<?= htmlspecialchars($currentAvatar, ENT_QUOTES, 'UTF-8') ?>">

      <div class="avatarDropdown">
        <button type="button" class="avatarToggle" id="avatarToggle">
          <span>Profilkép választása</span>
          <span id="avatarArrow">▾</span>
        </button>

        <div class="avatarPanel" id="avatarPanel">
          <div class="grid" id="avatarGrid">
            <?php foreach($avatars as $av): ?>
              <?php $isActive = ($av === $currentAvatar); ?>
              <button type="button"
                      class="avBtn <?= $isActive ? 'active' : '' ?>"
                      data-avatar="<?= htmlspecialchars($av, ENT_QUOTES, 'UTF-8') ?>">
                <img class="avImg" src="<?= htmlspecialchars($av, ENT_QUOTES, 'UTF-8') ?>" alt="avatar">
              </button>
            <?php endforeach; ?>
          </div>

          <div class="saveRow">
            <button class="saveBtn" type="submit">Profilkép mentése</button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="dash">
    <div class="col">
      <h3 class="h3">Saját időpontjaid</h3>

      <?php if(count($myAppointments) > 0): ?>
        <?php foreach($myAppointments as $appt): ?>
          <div class="card">
            <strong>
              <?= htmlspecialchars((string)(($appt['service_name'] ?: 'Szolgáltatás') . ($appt['sub_service_name'] ? ' — ' . $appt['sub_service_name'] : '')), ENT_QUOTES, 'UTF-8') ?>
            </strong><br>

            <?= date("Y-m-d H:i", strtotime((string)$appt['date_time'])) ?>

            <div class="meta">
              <strong>Szolgáltató:</strong>
              <?= htmlspecialchars((string)($appt['business_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              <?php if(!empty($appt['phone'])): ?>
                (<?= htmlspecialchars((string)$appt['phone'], ENT_QUOTES, 'UTF-8') ?>)
              <?php endif; ?>
            </div>
            <div id="confirmModal" class="modalOverlay" style="display:none;">
  <div class="modalBox">
    
    <div class="icon" style="background:#ef4444;">!</div>

    <h2>Foglalás lemondása</h2>
    <p>Biztosan le szeretnéd mondani ezt az időpontot?</p>

    <div style="display:flex; gap:10px; justify-content:center;">
      <button onclick="submitCancel()" style="background:#ef4444;">Igen</button>
      <button onclick="closeConfirm()">Mégse</button>
    </div>

  </div>
</div>
            <form method="post">
              <input type="hidden" name="action" value="cancel_booking">
              <input type="hidden" name="booking_id" value="<?= (int)$appt['booking_id'] ?>">
              <button type="button" class="cancelBtn" onclick="openConfirm(this)">
              Lemondás
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:#fff; text-align:center;">Nincs aktív (jövőbeli) foglalásod.</p>
      <?php endif; ?>
    </div>

    
     <!-- JOBB OSZLOP: Szabad időpontok -->
       <div class="appointments-column">
        <h3 class="h3">Válassz szolgáltatást</h3>



     <div style="margin-top:20px; text-align:center;  display: flex;
       flex-direction: column;
       gap: 20px;">
      <a href="/Smartbookers/public/industry.php?slug=kozmetika" class="btn btn-primary btn-block">Kozmetika</a>
      <a href="/Smartbookers/public/industry.php?slug=fodraszat" class="btn btn-primary btn-block">Fodrászat</a>
      <a href="/Smartbookers/public/industry.php?slug=mukorom" class="btn btn-primary btn-block">Műköröm</a>
      <a href="/Smartbookers/public/industry.php?slug=masszazs" class="btn btn-primary btn-block">Masszázs</a>
      <a href="/Smartbookers/public/industry.php?slug=egeszseg" class="btn btn-primary btn-block">Egészség</a>
    </div>
  </div>

  </div>
</div>

<script>

let currentForm = null;

function openConfirm(btn){
  currentForm = btn.closest('form');
  document.getElementById('confirmModal').style.display = 'flex';
}

function closeConfirm(){
  document.getElementById('confirmModal').style.display = 'none';
}

function submitCancel(){
  if(currentForm){
    currentForm.submit();
  }
}

(function(){
  const toggle = document.getElementById('avatarToggle');
  const panel  = document.getElementById('avatarPanel');
  const arrow  = document.getElementById('avatarArrow');

  if(toggle && panel){
    toggle.addEventListener('click', () => {
      panel.classList.toggle('open');
      if(arrow){
        arrow.textContent = panel.classList.contains('open') ? '▴' : '▾';
      }
    });
  }

  const grid = document.getElementById('avatarGrid');
  const input = document.getElementById('avatarInput');
  if(!grid || !input) return;

  grid.addEventListener('click', (e) => {
    const btn = e.target.closest('.avBtn');
    if(!btn) return;

    const av = btn.getAttribute('data-avatar');
    if(!av) return;

    input.value = av;

    grid.querySelectorAll('.avBtn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
})();
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>