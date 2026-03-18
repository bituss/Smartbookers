<?php
session_start();

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
  header("Location: /Smartbookers/public/login.php");
  exit;
}

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) die("Kapcsolódási hiba: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");

$user_id = (int)$_SESSION['user_id'];

$sentMsg = $_GET['msg'] ?? '';
$isOk  = isset($_GET['sent']);
$isErr = isset($_GET['error']);

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Felhasználói fiók</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/Smartbookers/public/css/userdashboard.css">
</head>
<body>

<?php
echo "<h2>Szia, " . htmlspecialchars($_SESSION['name'] ?? 'Felhasználó', ENT_QUOTES, 'UTF-8') . "!</h2>";

if ($isOk) {
  echo '<div class="alert ok">✅ ' . htmlspecialchars($sentMsg ?: 'Siker!', ENT_QUOTES, 'UTF-8') . '</div>';
}
if ($isErr) {
  echo '<div class="alert err">⚠️ ' . htmlspecialchars($sentMsg ?: 'Hiba történt.', ENT_QUOTES, 'UTF-8') . '</div>';
}

/* =========================
   1) Saját foglalások (bookings)
   - cancelled_at alapján aktív/inaktív
   - sub_service név is megjelenik
========================= */
$myBookings = [];
$st1 = $mysqli->prepare("
  SELECT
    b.id AS booking_id,
    b.booking_time,
    b.cancelled_at,
    b.provider_seen,

    p.business_name,
    s.name AS service_name,
    ss.name AS sub_service_name
  FROM bookings b
  JOIN providers p ON p.id = b.provider_id
  LEFT JOIN services s ON s.id = p.service_id
  LEFT JOIN sub_services ss ON ss.id = b.sub_service_id
  WHERE b.user_id = ?
AND b.cancelled_at IS NULL
AND b.booking_time >= NOW()
ORDER BY b.booking_time ASC
");
$st1->bind_param("i", $user_id);
$st1->execute();
$rs1 = $st1->get_result();
while ($row = $rs1->fetch_assoc()) $myBookings[] = $row;


/* =========================
   2) Szabad időpontok (provider_availability)
   - csak jövőbeliek
   - csak aktív slotok
   - kiszűrjük, ami foglalt (bookings.cancelled_at IS NULL)
========================= */
$freeSlots = [];
$st2 = $mysqli->prepare("
  SELECT
    a.id AS availability_id,
    a.slot_date,
    a.start_time,
    a.end_time,
    a.slot_minutes,
    a.sub_service_id,

    p.business_name,
    s.name AS service_name,
    ss.name AS sub_service_name
  FROM provider_availability a
  JOIN providers p ON p.id = a.provider_id
  LEFT JOIN services s ON s.id = p.service_id
  LEFT JOIN sub_services ss ON ss.id = a.sub_service_id

  LEFT JOIN bookings b
    ON b.provider_id = a.provider_id
   AND b.booking_time = CONCAT(a.slot_date, ' ', a.start_time)
   AND b.cancelled_at IS NULL

  WHERE a.is_active = 1
    AND CONCAT(a.slot_date, ' ', a.start_time) >= NOW()
    AND b.id IS NULL

  ORDER BY a.slot_date ASC, a.start_time ASC
");
$st2->execute();
$rs2 = $st2->get_result();
while ($row = $rs2->fetch_assoc()) $freeSlots[] = $row;
?>

<div class="dashboard-container">

  <!-- BAL OSZLOP: Saját foglalások -->
  <div class="appointments-column">
    <h3>Saját időpontjaid</h3>

    <?php if(count($myBookings) > 0): ?>
      <?php foreach($myBookings as $b): ?>
        <?php
          $isCancelled = !empty($b['cancelled_at']);
          $cardClass = 'booked';
          $dt = date("Y-m-d H:i", strtotime($b['booking_time']));
        ?>
        <div class="appointment-card <?= $cardClass ?>">
          <strong>
            <?= htmlspecialchars($b['service_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            <?php if(!empty($b['sub_service_name'])): ?>
              • <?= htmlspecialchars($b['sub_service_name'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </strong><br>
          <?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?>

          <div class="meta">
            <strong>Szolgáltató:</strong>
            <?= htmlspecialchars($b['business_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>

          <?php if(!$isCancelled): ?>
            <a class="btn btn-primary btn-block"
               href="/Smartbookers/user/cancel_booking.php?booking_id=<?= (int)$b['booking_id'] ?>"
               onclick="return confirm('Biztosan lemondod ezt az időpontot?');">
              Lemondás
            </a>
          <?php else: ?>
            <div class="meta">
              <strong>Státusz:</strong> Lemondva
              (<?= date("Y-m-d H:i", strtotime($b['cancelled_at'])) ?>)
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="color:#fff; text-align:center;">Nincsenek még foglalt időpontjaid.</p>
    <?php endif; ?>
  </div>

  <!-- JOBB OSZLOP: Szabad időpontok -->
  <div class="appointments-column">
    <h3>Válassz szolgáltatást</h3>



    <div style="margin-top:20px; text-align:center;">
      <a href="/Smartbookers/public/industry.php?slug=kozmetika" class="btn btn-primary btn-block">Kozmetika</a>
      <a href="/Smartbookers/public/industry.php?slug=fodraszat" class="btn btn-primary btn-block">Fodrászat</a>
      <a href="/Smartbookers/public/industry.php?slug=mukorom" class="btn btn-primary btn-block">Műköröm</a>
      <a href="/Smartbookers/public/industry.php?slug=masszazs" class="btn btn-primary btn-block">Masszázs</a>
      <a href="/Smartbookers/public/industry.php?slug=egeszseg" class="btn btn-primary btn-block">Egészség</a>
    </div>
  </div>

</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>