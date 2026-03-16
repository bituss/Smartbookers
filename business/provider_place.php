<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider') {
  header("Location: /Smartbookers/business/provider_login.php");
  exit;
}

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
$mysqli->set_charset("utf8mb4");

$success = "";
$error   = "";

function strv($v): string { return trim((string)$v); }

/* =========================
   Provider profil (FIX főszolgáltatás!)
   providers: user_id, service_id
========================= */
try {
  $stmt = $mysqli->prepare("
    SELECT
      p.id AS provider_id,
      u.name AS owner_name,
      p.service_id,
      s.name AS service_name
    FROM providers p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN services s ON s.id = p.service_id
    WHERE p.user_id = ?
    LIMIT 1
  ");
  $uid = (int)$_SESSION['user_id'];
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $provider = $stmt->get_result()->fetch_assoc();

  if (!$provider) {
    throw new Exception("Nincs provider profil létrehozva (providers.user_id = {$uid}).");
  }
} catch (Throwable $e) {
  die("Végzetes hiba: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$provider_id  = (int)$provider['provider_id'];
$service_id   = (int)($provider['service_id'] ?? 0);
$service_name = (string)($provider['service_name'] ?? '');

/* ha nincs service beállítva */
if ($service_id <= 0) {
  $error = "Nincs beállítva főszolgáltatás ehhez a vállalkozóhoz (providers.service_id). Állítsd be phpMyAdminban!";
}

/* =========================
   Sub-szolgáltatások: csak a provider service-éhez
========================= */
$subServices = [];
if ($service_id > 0) {
  $st = $mysqli->prepare("
    SELECT id, name
    FROM sub_services
    WHERE service_id = ?
    ORDER BY name ASC
  ");
  $st->bind_param("i", $service_id);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) $subServices[] = $row;
}

/* =========================
   Dátum korlátok
========================= */
$today   = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+6 months'));

/* =========================
   Több idősáv mentése (1 gomb)
   - max 17:00-ig
   - slotMinutes: 30/45/60/90/120
   - break: fix 15 perc (generálásnál)
   - sub_service_id mentése availability-be
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ranges'])) {

  $slot_date    = strv($_POST['slot_date'] ?? '');
  $slot_minutes = (int)($_POST['slot_minutes'] ?? 60);
  $pickedSubId  = (int)($_POST['picked_sub_service_id'] ?? 0);
  $ranges       = $_POST['ranges'] ?? [];

  if ($service_id <= 0) {
    $error = "Nincs főszolgáltatás beállítva (providers.service_id).";
  } elseif ($slot_date === '') {
    $error = "Dátum kötelező.";
  } elseif ($slot_date < $today) {
    $error = "Múltbeli napra nem adhatsz meg időpontot.";
  } elseif ($slot_date > $maxDate) {
    $error = "Legfeljebb fél évre előre tudsz időpontot felvenni.";
  } elseif (!in_array($slot_minutes, [30,45,60,90,120], true)) {
    $error = "Érvénytelen időtartam.";
  } elseif ($pickedSubId <= 0) {
    $error = "Válassz al-szolgáltatást az idősávokhoz.";
  } elseif (!is_array($ranges) || count($ranges) === 0) {
    $error = "Adj meg legalább 1 idősávot.";
  } else {

    // ellenőrzés: a kiválasztott sub_service ehhez a service-hez tartozik-e
    $chk = $mysqli->prepare("SELECT id FROM sub_services WHERE id=? AND service_id=? LIMIT 1");
    $chk->bind_param("ii", $pickedSubId, $service_id);
    $chk->execute();
    if (!$chk->get_result()->fetch_row()) {
      $error = "Érvénytelen al-szolgáltatás ehhez a főszolgáltatáshoz.";
    }

    // tisztítás + validálás
    $clean = [];
    if ($error === '') {
      foreach ($ranges as $r) {
        $st = strv($r['start_time'] ?? '');
        $en = strv($r['end_time'] ?? '');
        if ($st === '' || $en === '') continue;

        // max 17:00
        if ($en > "17:00") {
          $error = "A zárás nem lehet 17:00 után.";
          break;
        }

        if (strtotime($st) >= strtotime($en)) {
          $error = "Hibás idősáv: a kezdés nem lehet később/egyenlő, mint a zárás.";
          break;
        }

        $clean[] = [$st, $en];
      }

      if ($error === '' && count($clean) === 0) {
        $error = "Nincs érvényes idősáv megadva.";
      }
    }

    // átfedés ellenőrzés a beküldött sávok között
    if ($error === '') {
      usort($clean, fn($a,$b) => strcmp($a[0], $b[0]));
      for ($i=0; $i<count($clean)-1; $i++) {
        if (strtotime($clean[$i+1][0]) < strtotime($clean[$i][1])) {
          $error = "Az általad megadott idősávok átfednek egymással.";
          break;
        }
      }
    }

    if ($error === '') {
      $mysqli->begin_transaction();
      try {
        $inserted = 0;

        foreach ($clean as [$st, $en]) {

          // DB ütközés (ugyanazon napon, aktív sávok átfedése)
          $chk = $mysqli->prepare("
            SELECT COUNT(*) c
            FROM provider_availability
            WHERE provider_id=?
              AND slot_date=?
              AND is_active=1
              AND NOT (end_time <= ? OR start_time >= ?)
          ");
          $chk->bind_param("isss", $provider_id, $slot_date, $st, $en);
          $chk->execute();
          $cnt = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
          if ($cnt > 0) {
            throw new Exception("Van már átfedő elérhetőséged erre a napra/időre: {$st}-{$en}");
          }

          $ins = $mysqli->prepare("
            INSERT INTO provider_availability
              (provider_id, slot_date, start_time, end_time, slot_minutes, sub_service_id, is_active)
            VALUES (?,?,?,?,?,?,1)
          ");
          $ins->bind_param("isssii", $provider_id, $slot_date, $st, $en, $slot_minutes, $pickedSubId);
          $ins->execute();

          $inserted++;
        }

        $mysqli->commit();
        $success = "Siker! Felvett idősávok: {$inserted} db.";

      } catch (Throwable $e) {
        $mysqli->rollback();
        $error = "Hiba: " . $e->getMessage();
      }
    }
  }
}

/* =========================
   Lemondott foglalások
========================= */

$cancelBookings = [];

$stmt = $mysqli->prepare("
SELECT
  b.id,
  b.booking_time,
  u.name AS user_name,
  u.email AS user_email,
  ss.name AS service_name
FROM bookings b
LEFT JOIN users u ON u.id = b.user_id
LEFT JOIN sub_services ss ON ss.id = b.sub_service_id
WHERE b.provider_id = ?
AND b.cancelled_at IS NOT NULL
AND b.provider_seen = 0
ORDER BY b.cancelled_at DESC
");

$stmt->bind_param("i", $provider_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
  $cancelBookings[] = $row;
}

$cancelCount = count($cancelBookings);

/* =========================
   Kereső (nap alapján)
========================= */
$search_date = strv($_GET['date'] ?? $today);
if ($search_date < $today) $search_date = $today;
if ($search_date > $maxDate) $search_date = $maxDate;

/* ===== Aznapra felvett idősávok (sub_service_id-vel) ===== */
$dayRanges = [];
$stmt = $mysqli->prepare("
  SELECT
    pa.id,
    pa.slot_date,
    pa.start_time,
    pa.end_time,
    pa.slot_minutes,
    pa.sub_service_id,
    pa.is_active,
    ss.name AS sub_service_name
  FROM provider_availability pa
  LEFT JOIN sub_services ss ON ss.id = pa.sub_service_id
  WHERE pa.provider_id = ?
    AND pa.slot_date = ?
  ORDER BY pa.start_time ASC
");
$stmt->bind_param("is", $provider_id, $search_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $dayRanges[] = $row;

/* ===== Aznapra foglalások ===== */
$bookedMap = [];
$stmt = $mysqli->prepare("
  SELECT
    b.id,
    b.booking_time,
    b.cancelled_at,
    u.name  AS user_name,
    u.email AS user_email
  FROM bookings b
  LEFT JOIN users u ON u.id = b.user_id
  WHERE b.provider_id = ?
    AND DATE(b.booking_time) = ?
  ORDER BY b.booking_time ASC
");
$stmt->bind_param("is", $provider_id, $search_date);
$stmt->execute();
$resB = $stmt->get_result();
while ($b = $resB->fetch_assoc()) {
  $k = date("H:i", strtotime($b['booking_time']));
  $bookedMap[$k][] = $b;
}

/* ===== Slotok generálása: slot_minutes + 15 perc szünet ===== */
$BREAK_MIN = 15;
$slots = [];

/* =========================
   Naptár adatok
========================= */

$calendarDays = [];

$stmt = $mysqli->prepare("
SELECT 
DATE(pa.slot_date) as d,
COUNT(DISTINCT pa.id) as slots,
SUM(CASE WHEN b.id IS NOT NULL AND b.cancelled_at IS NULL THEN 1 ELSE 0 END) as booked
FROM provider_availability pa
LEFT JOIN bookings b
ON DATE(b.booking_time)=pa.slot_date
AND b.provider_id=pa.provider_id
WHERE pa.provider_id=?
GROUP BY d
");

$stmt->bind_param("i",$provider_id);
$stmt->execute();
$res=$stmt->get_result();

while($row=$res->fetch_assoc()){
$calendarDays[$row['d']]=$row;
}

foreach ($dayRanges as $r) {
  if ((int)$r['is_active'] !== 1) continue;

  $slotMinutes = (int)$r['slot_minutes'];
  $stepMinutes = $slotMinutes + $BREAK_MIN;

  $start = strtotime($search_date . ' ' . substr($r['start_time'], 0, 5) . ':00');
  $end   = strtotime($search_date . ' ' . substr($r['end_time'],   0, 5) . ':00');

  $endCap = strtotime($search_date . ' 17:00:00');
  if ($end > $endCap) $end = $endCap;

  for ($t = $start; $t + ($slotMinutes*60) <= $end; $t += $stepMinutes*60) {
    $hm = date("H:i", $t);

    $status = "Szabad";
    $name = "-";
    $email = "-";

    if (isset($bookedMap[$hm])) {
      $b0 = $bookedMap[$hm][0];
      if (!empty($b0['cancelled_at'])) $status = "Lemondva";
      else $status = "Foglalt";
      $name  = $b0['user_name'] ?: "-";
      $email = $b0['user_email'] ?: "-";
    }

    $slots[] = [
      'time' => $hm,
      'status' => $status,
      'name' => $name,
      'email' => $email,
      'sub_service' => (string)($r['sub_service_name'] ?? '—'),
      'duration' => $slotMinutes
    ];
  }
}

include '../includes/header.php';
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vállalkozás</title>
  <link rel="stylesheet" href="/Smartbookers/public/css/providerplace.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    .rowLine{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
    .field{display:flex;flex-direction:column;gap:6px;min-width:180px;flex:1}
    .field label{font-weight:800;opacity:.9}
    .field input,.field select{
      width:100%;height:44px;border-radius:12px;border:1px solid rgba(0,0,0,.12);
      padding:0 12px;font:inherit;background:#fff
    }
    .btnMini{height:44px;padding:0 14px;border-radius:12px;border:0;cursor:pointer;font-weight:900}
    .btnAdd{background:#1f2a7a;color:#fff}
    .btnDel{background:#ef4444;color:#fff}
    .btnSave{background:#1f2a7a;color:#fff;height:44px;padding:0 18px;border-radius:12px;border:0;font-weight:900;cursor:pointer}
    .rangeItem{margin-top:10px;padding:12px;border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff}
    .muted{opacity:.8}

    /* modern table */
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;opacity:.7;text-align:left;padding:0 12px}
    td{background:#fff;padding:14px 12px;border-top:1px solid rgba(0,0,0,.06);border-bottom:1px solid rgba(0,0,0,.06)}
    tr td:first-child{border-left:1px solid rgba(0,0,0,.06);border-radius:12px 0 0 12px}
    tr td:last-child{border-right:1px solid rgba(0,0,0,.06);border-radius:0 12px 12px 0}

    .badge{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;font-weight:900;font-size:12px}
    .b-free{background:#90ee90}
    .b-booked{background:#f08080}
    .b-cancel{background:#4e1609}
    .dot{width:8px;height:8px;border-radius:999px;background:rgba(0,0,0,.35)}
    .cancelBox{
margin-top:15px;
border:1px solid rgba(0,0,0,.1);
border-radius:12px;
background:#fff;
overflow:hidden;
}

.cancelHeader{
padding:12px;
font-weight:800;
cursor:pointer;
background:#ffe4e4;
}

.cancelList{
display:none;
padding:10px;
}

.cancelItem{
padding:10px;
border-bottom:1px solid #eee;
}

.cancelItem:last-child{
border-bottom:none;
}
.calendarNav{
display:flex;
justify-content:center;
gap:20px;
margin:15px 0;
font-weight:700;
}

.calendarNav button{
padding:6px 12px;
border:none;
border-radius:8px;
cursor:pointer;
}

#calendar{
display:grid;
grid-template-columns:repeat(7,1fr);
gap:6px;
}

.calDay{
padding:14px;
border-radius:10px;
text-align:center;
font-weight:700;
cursor:pointer;
}

.calFree{
background:#60a5fa;
color:white;
}

.calBooked{
background:#22c55e;
color:white;
}

.calNone{
background:#e5e7eb;
}

/* popup */

.popup{

display:none;
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,.5);
align-items:center;
justify-content:center;
z-index:9999;

}

.popupContent{
background:white;
padding:25px;
border-radius:12px;
width:300px;
text-align:center;
}
.calendarWeek{
display:grid;
grid-template-columns:repeat(7,1fr);
text-align:center;
font-weight:700;
margin-bottom:5px;
}
#calendar{
display:grid;
grid-template-columns:repeat(7,1fr);
gap:6px;
}

.calDay{
padding:14px;
text-align:center;
border-radius:10px;
cursor:pointer;
font-weight:700;
}

.calFree{
background:#4da3ff;
color:white;
}

.calBooked{
background:#4CAF50;
color:white;
}

.calNone{
background:#eee;
color:#999;
}

@media(max-width:768px){

#calendar{
grid-template-columns:repeat(7,1fr);
gap:4px;
}

.calDay{
padding:10px;
font-size:14px;
}

}

@media(max-width:480px){

.calDay{
padding:8px;
font-size:12px;
}

}
  </style>
</head>
<body>

<div class="container">
  <h1>Üdv, <?= htmlspecialchars((string)$provider['owner_name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
  <p class="center">Szolgáltatás: <strong><?= htmlspecialchars($service_name ?: '—', ENT_QUOTES, 'UTF-8') ?></strong></p>

  <?php if($success): ?><div class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if($error): ?><div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if($cancelCount > 0): ?>

<div class="cancelBox">

<div class="cancelHeader" onclick="toggleCancels()">
⚠️ Új lemondás érkezett: <?= (int)$cancelCount ?> db (kattints a megnyitáshoz)
</div>

<div class="cancelList" id="cancelList">

<?php foreach($cancelBookings as $c): ?>

<div class="cancelItem">

<strong><?= htmlspecialchars($c['user_name']) ?></strong><br>

<?= htmlspecialchars($c['service_name']) ?><br>

<?= date("Y-m-d H:i", strtotime($c['booking_time'])) ?><br>

<?= htmlspecialchars($c['user_email']) ?>

</div>

<?php endforeach; ?>

<p class="center" style="margin-top:10px;">
<a class="btn" href="/Smartbookers/business/seen_cancellations.php">
Lemondások megjelölése olvasottnak
</a>
</p>

</div>
</div>

<?php endif; ?>

  <div class="card">
    <h2 class="center" style="margin:0 0 10px;">Idősávok felvétele</h2>

    <?php if($service_id > 0 && count($subServices) === 0): ?>
      <p class="center muted" style="margin:0 0 10px;">
        Nincs al-szolgáltatás felvéve ehhez a főszolgáltatáshoz. Töltsd fel a <strong>sub_services</strong> táblát (service_id = <?= (int)$service_id ?>).
      </p>
    <?php endif; ?>

    <form method="POST" id="rangesForm" autocomplete="off">
      <div class="rowLine">
        <div class="field">
          <label>Dátum</label>
          <input type="date" name="slot_date" min="<?= htmlspecialchars($today) ?>" max="<?= htmlspecialchars($maxDate) ?>" required>
        </div>

        <div class="field">
          <label>Időtartam</label>
          <select name="slot_minutes" id="slotMinutes" required>
            <option value="30">30 perc</option>
            <option value="45">45 perc</option>
            <option value="60" selected>60 perc</option>
            <option value="90">90 perc</option>
            <option value="120">120 perc</option>
          </select>
        </div>

        <div class="field">
          <label>Al-szolgáltatás</label>
          <select name="picked_sub_service_id" id="pickedSubService" required <?= (count($subServices)===0?'disabled':'') ?>>
            <option value="">Válassz...</option>
            <?php foreach($subServices as $ss): ?>
              <option value="<?= (int)$ss['id'] ?>"><?= htmlspecialchars($ss['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btnMini btnAdd" type="button" id="addRangeBtn">+ Új idősáv</button>
      </div>

      <div id="rangesWrap">
        <div class="rangeItem rangeRow">
          <div class="rowLine">
            <div class="field">
              <label>Kezdés</label>
              <input type="time" name="ranges[0][start_time]" class="tStart" step="900" required>
            </div>
            <div class="field">
              <label>Zárás (max 17:00)</label>
              <input type="time" name="ranges[0][end_time]" class="tEnd" step="900" max="17:00" required readonly>
            </div>
            <button class="btnMini btnDel" type="button" onclick="removeRange(this)">Törlés</button>
          </div>
          <div class="muted" style="margin-top:8px;">
            Slot lépés: <strong><span class="durLabel">60</span> perc</strong> + <strong>15 perc szünet</strong>.
          </div>
        </div>
      </div>

      <p class="muted" style="margin:10px 0 0;">
        Tipp: felvehetsz pl. <strong>09:00–12:00</strong> és <strong>13:00–17:00</strong> idősávokat egyetlen mentéssel.
      </p>

      <p class="center" style="margin-top:14px;">
        <button class="btnSave" type="submit" name="save_ranges" <?= (count($subServices)===0?'disabled':'') ?>>Mentés</button>
      </p>
    </form>
  </div>

  <div class="card">
    <h2 class="center" style="margin:0 0 10px;">Kereső (nap alapján)</h2>

    <form method="GET" class="rowLine" style="justify-content:center;">
      <div class="field" style="max-width:260px;flex:0 0 auto;">
        <label>Dátum</label>
        <input type="date" name="date" value="<?= htmlspecialchars($search_date) ?>"
               min="<?= htmlspecialchars($today) ?>" max="<?= htmlspecialchars($maxDate) ?>">
      </div>
      <button class="btnSave" type="submit">Keresés</button>
    </form>

    <h3 class="center" style="margin:14px 0 8px;"><?= htmlspecialchars($search_date) ?> – időpontok</h3>

    <?php if(count($dayRanges) === 0): ?>
      <p class="center muted">Erre a napra nincs felvett idősáv.</p>
    <?php elseif(count($slots) === 0): ?>
      <p class="center muted">A beállított sávokból nem generálható slot.</p>
    <?php else: ?>
      <table>
        <tr>
          <th>Idő</th>
          <th>Al-szolgáltatás</th>
          <th>Időtartam</th>
          <th>Státusz</th>
          <th>Ki foglalta</th>
          <th>Email</th>
        </tr>

        <?php foreach($slots as $s): ?>
          <?php
            $cls = 'b-free'; $dot = '';
            if ($s['status'] === 'Foglalt'){ $cls='b-booked'; $dot=''; }
            if ($s['status'] === 'Lemondva'){ $cls='b-cancel'; $dot=''; }
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($s['time']) ?></strong></td>
            <td><?= htmlspecialchars($s['sub_service']) ?></td>
            <td><?= (int)$s['duration'] ?> perc</td>
            <td><span class="badge <?= $cls ?>"><span class="dot"></span><?= htmlspecialchars($s['status']) ?></span></td>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
  <div class="card">

<h2 class="center">📅 Foglalási naptár</h2>

<div class="calendarNav">
<button onclick="prevMonth()">◀</button>
<span id="monthTitle"></span>
<button onclick="nextMonth()">▶</button>
</div>
<div class="calendarWeek">
<div>H</div>
<div>K</div>
<div>Sze</div>
<div>Cs</div>
<div>P</div>
<div>Szo</div>
<div>V</div>
</div>
<div id="calendar"></div>
<div class="card">

<h3>📖 Naptár használata</h3>

<p>🟦 Kék nap → van szabad időpont</p>
<p>🟩 Zöld nap → van foglalás</p>
<p>⬜ Szürke → nincs megadva időpont</p>

<p>Kattints egy napra a részletek megtekintéséhez.</p>

</div>
</div>
</div>
<div id="dayPopup" class="popup">

<div class="popupContent">

<h3 id="popupDate"></h3>

<div id="popupInfo"></div>

<button onclick="closePopup()">Bezár</button>

</div>

</div>
<script>
  const calendarData = <?= json_encode($calendarDays) ?>;
let rangeIndex = 1;
const BREAK_MIN = 15;

function removeRange(btn){
  const item = btn.closest('.rangeRow');
  if(!item) return;
  const wrap = document.getElementById('rangesWrap');
  if(wrap.querySelectorAll('.rangeRow').length <= 1) return;
  item.remove();
}

function setDurationLabels(){
  const v = parseInt(document.getElementById('slotMinutes').value || '60', 10);
  document.querySelectorAll('.durLabel').forEach(el => el.textContent = String(v));
}

function snapTo15(value){
  if(!value) return value;
  const [h,m] = value.split(':').map(x=>parseInt(x,10));
  if(Number.isNaN(h) || Number.isNaN(m)) return value;
  const total = h*60 + m;
  const snapped = Math.round(total/15)*15;
  const hh = String(Math.floor(snapped/60)).padStart(2,'0');
  const mm = String(snapped%60).padStart(2,'0');
  return `${hh}:${mm}`;
}

function addMinutesHHMM(hhmm, addMin){
  if(!hhmm) return '';
  const [h,m] = hhmm.split(':').map(n=>parseInt(n,10));
  if(Number.isNaN(h) || Number.isNaN(m)) return '';
  const total = h*60 + m + addMin;
  const hh = String(Math.floor(total/60)).padStart(2,'0');
  const mm = String(total%60).padStart(2,'0');
  return `${hh}:${mm}`;
}

function capTo1700(hhmm){
  if(!hhmm) return hhmm;
  return (hhmm > '17:00') ? '17:00' : hhmm;
}

/**
 * Automatikus zárás:
 * end = start + slotMinutes + 15 perc szünet
 */
function autoEndForRow(row){
  const startEl = row.querySelector('.tStart');
  const endEl   = row.querySelector('.tEnd');
  if(!startEl || !endEl) return;

  const slotMinutes = parseInt(document.getElementById('slotMinutes').value || '60', 10);
  const startVal = snapTo15(startEl.value);
  if(!startVal) return;

  startEl.value = startVal;

  const endVal = addMinutesHHMM(startVal, slotMinutes + BREAK_MIN);
  endEl.value = capTo1700(snapTo15(endVal));
}

/** Ha az időtartamot átállítja, minden sorban frissítjük a zárást (ahol van kezdés) */
function autoEndAll(){
  document.querySelectorAll('.rangeRow').forEach(row => autoEndForRow(row));
}

document.getElementById('addRangeBtn').addEventListener('click', () => {
  const wrap = document.getElementById('rangesWrap');

  const div = document.createElement('div');
  div.className = 'rangeItem rangeRow';
  div.innerHTML = `
    <div class="rowLine">
      <div class="field">
        <label>Kezdés</label>
        <input type="time" name="ranges[${rangeIndex}][start_time]" class="tStart" step="900" required>
      </div>
      <div class="field">
        <label>Zárás (auto, max 17:00)</label>
        <input type="time" name="ranges[${rangeIndex}][end_time]" class="tEnd" step="900" max="17:00" required readonly>
      </div>
      <button class="btnMini btnDel" type="button" onclick="removeRange(this)">Törlés</button>
    </div>
    <div class="muted" style="margin-top:8px;">
      Slot lépés: <strong><span class="durLabel">${document.getElementById('slotMinutes').value || 60}</span> perc</strong> + <strong>15 perc szünet</strong>.
    </div>
  `;
  wrap.appendChild(div);
  rangeIndex++;
  setDurationLabels();
});

// időtartam változás -> zárások frissítése
document.getElementById('slotMinutes').addEventListener('change', () => {
  setDurationLabels();
  autoEndAll();
});
setDurationLabels();

/**
 * Kezdés változás -> automatikus zárás
 * Zárást readonly-ra tesszük (hogy tényleg automatikus legyen).
 * Ha mégis szerkeszthetőre akarod, vedd ki a readonly-t fent.
 */
document.addEventListener('change', (e) => {
  const t = e.target;
  if(!(t instanceof HTMLInputElement)) return;

  if(t.classList.contains('tStart')){
    const row = t.closest('.rangeRow');
    if(row) autoEndForRow(row);
  }

  if(t.classList.contains('tEnd')){
    // ha valahol nem readonly, akkor is snap + 17:00 cap
    t.value = capTo1700(snapTo15(t.value));
  }
});

// Első sor: tegyük readonly-ra + automatikus működés
document.querySelectorAll('.tEnd').forEach(el => el.setAttribute('readonly','readonly'));
function toggleCancels(){

let box = document.getElementById("cancelList");

if(box.style.display === "block"){
box.style.display = "none";
}else{
box.style.display = "block";
}

}
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

function renderCalendar(){

const cal=document.getElementById("calendar");
cal.innerHTML="";

let first=new Date(currentYear,currentMonth,1);
let last=new Date(currentYear,currentMonth+1,0);

let startDay = first.getDay();
if(startDay === 0) startDay = 7;

document.getElementById("monthTitle").innerText =
first.toLocaleString('hu',{month:'long',year:'numeric'});

for(let i=1;i<startDay;i++){
let empty=document.createElement("div");
cal.appendChild(empty);
}

for(let d=1; d<=last.getDate(); d++){

let dateStr =
currentYear+"-"+String(currentMonth+1).padStart(2,'0')+"-"+String(d).padStart(2,'0');

let div=document.createElement("div");
div.className="calDay";

if(calendarData[dateStr]){

let data=calendarData[dateStr];

if(data.booked>0){
div.classList.add("calBooked");
}else{
div.classList.add("calFree");
}

}else{

div.classList.add("calNone");

}

div.innerText=d;

div.onclick=()=>openPopup(dateStr);

cal.appendChild(div);

}

}

function prevMonth(){

currentMonth--;

if(currentMonth<0){
currentMonth=11;
currentYear--;
}

renderCalendar();

}

function nextMonth(){

currentMonth++;

if(currentMonth>11){
currentMonth=0;
currentYear++;
}

renderCalendar();

}

function openPopup(date){

document.getElementById("dayPopup").style.display="flex";

document.getElementById("popupDate").innerText=date;

if(calendarData[date]){

let data=calendarData[date];

document.getElementById("popupInfo").innerHTML=
"Idősávok: "+data.slots+"<br>Foglalások: "+data.booked;

}else{

document.getElementById("popupInfo").innerHTML=
"Nincs felvett idősáv";

}

}

function closePopup(){

document.getElementById("dayPopup").style.display="none";

}

renderCalendar();
</script>


<?php include '../includes/footer.php'; ?>
</body>
</html>
