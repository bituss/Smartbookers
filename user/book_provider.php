<?php
session_start();
$providerId = (int)($_GET['provider_id'] ?? 0);
if ($providerId <= 0) {
    $_SESSION['book_provider'] = null;
    include '../includes/header.php';
    ?>
    <main style="padding:40px 20px; text-align:center;">
      <p style="color:#ef4444; font-size:16px;">&#9888; Érvénytelen QR-kód. Kérjük szkennelje be a szolgáltató QR-kódját.</p>
      <a href="/Smartbookers/public/index.php" class="btn small" style="margin-top:16px;">Főoldal</a>
    </main>
    <?php
    include '../includes/footer.php';
    exit;
}
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    $_SESSION['book_provider'] = $providerId;
    header('Location: /Smartbookers/public/login.php');
    exit;
}
$_SESSION['book_provider'] = null;
$mysqli = new mysqli('localhost', 'root', '', 'idopont_foglalas');
if ($mysqli->connect_error) die('Kapcsolódási hiba: ' . $mysqli->connect_error);
$mysqli->set_charset('utf8mb4');
$st = $mysqli->prepare('
    SELECT p.business_name, p.bio, p.avatar
    FROM providers p
    WHERE p.id = ?
    LIMIT 1
');
$st->bind_param('i', $providerId);
$st->execute();
$provider = $st->get_result()->fetch_assoc();
if (!$provider) {
    include '../includes/header.php';
    ?>
    <main style="padding:40px 20px; text-align:center;">
      <p style="color:#ef4444; font-size:16px;">&#9888; A szolgáltató nem található.</p>
      <a href="/Smartbookers/public/index.php" class="btn small" style="margin-top:16px;">Főoldal</a>
    </main>
    <?php
    include '../includes/footer.php';
    exit;
}
$st2 = $mysqli->prepare('
    SELECT
        pa.id,
        pa.slot_date,
        pa.start_time,
        pa.end_time,
        pa.slot_minutes,
        ss.name AS sub_service_name
    FROM provider_availability pa
    LEFT JOIN sub_services ss ON ss.id = pa.sub_service_id
    LEFT JOIN bookings b
        ON  b.provider_id   = pa.provider_id
        AND DATE(b.booking_time) = pa.slot_date
        AND TIME(b.booking_time) = pa.start_time
        AND b.cancelled_at IS NULL
    WHERE pa.provider_id = ?
      AND pa.is_active    = 1
      AND CONCAT(pa.slot_date, " ", pa.start_time) > NOW()
      AND b.id IS NULL
    ORDER BY pa.slot_date, pa.start_time
');
$st2->bind_param('i', $providerId);
$st2->execute();
$result = $st2->get_result();
$grouped = [];
while ($row = $result->fetch_assoc()) {
    $grouped[$row['slot_date']][] = $row;
}
$businessName = htmlspecialchars($provider['business_name'] ?? 'Ismeretlen szolgáltató', ENT_QUOTES, 'UTF-8');
$defaultAvatar = '/Smartbookers/public/images/avatars/a1.png';
$avatarUrl = !empty($provider['avatar']) ? htmlspecialchars($provider['avatar'], ENT_QUOTES, 'UTF-8') : $defaultAvatar;
include '../includes/header.php';
?>
<style>
.bp-wrap        { max-width: 680px; margin: 32px auto; padding: 0 16px 48px; }
.bp-header      { display:flex; align-items:center; gap:16px; margin-bottom:28px; }
.bp-avatar      { width:64px; height:64px; border-radius:50%; object-fit:cover; background:#1e293b; }
.bp-title       { font-size:22px; font-weight:800; color:#f1f5f9; }
.bp-subtitle    { font-size:13px; color:#94a3b8; margin-top:2px; }
.bp-day         { margin-bottom:24px; }
.bp-day-label   { font-size:13px; font-weight:700; color:#94a3b8; text-transform:uppercase;
                  letter-spacing:.06em; margin-bottom:10px; padding-bottom:6px;
                  border-bottom:1px solid #1e293b; }
.bp-slots       { display:flex; flex-wrap:wrap; gap:10px; }
.bp-slot        { background:#1e293b; border:1px solid #334155; border-radius:10px;
                  padding:12px 16px; min-width:140px; }
.bp-slot-time   { font-size:18px; font-weight:700; color:#f1f5f9; }
.bp-slot-service{ font-size:12px; color:#94a3b8; margin:3px 0 10px; }
.bp-slot a      { display:block; text-align:center; background:#6366f1; color:#fff;
                  border-radius:6px; padding:7px 0; font-size:13px; font-weight:600;
                  text-decoration:none; }
.bp-slot a:hover{ background:#4f46e5; }
.bp-empty       { color:#94a3b8; font-size:15px; padding:24px 0; }
</style>
<main>
<div class="bp-wrap">
  <div class="bp-header">
    <img class="bp-avatar" src="<?= $avatarUrl ?>" alt="<?= $businessName ?>">
    <div>
      <div class="bp-title"><?= $businessName ?></div>
      <div class="bp-subtitle">Szabad időpontok</div>
    </div>
  </div>
  <?php if (empty($grouped)): ?>
    <p class="bp-empty">Jelenleg nincs elérhető szabad időpont ennél a szolgáltatónál.</p>
  <?php else: ?>
    <?php foreach ($grouped as $date => $slots): ?>
      <div class="bp-day">
        <div class="bp-day-label">
          <?= htmlspecialchars(
                (new DateTime($date))->format('Y. m. d.') . ' (' .
                ['vasárnap','hétfő','kedd','szerda','csütörtök','péntek','szombat'][(int)(new DateTime($date))->format('w')] . ')',
                ENT_QUOTES, 'UTF-8'
              ) ?>
        </div>
        <div class="bp-slots">
          <?php foreach ($slots as $slot): ?>
            <div class="bp-slot">
              <div class="bp-slot-time">
                <?= htmlspecialchars(substr($slot['start_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($slot['end_time'])): ?>
                  &ndash; <?= htmlspecialchars(substr($slot['end_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </div>
              <?php if (!empty($slot['sub_service_name'])): ?>
                <div class="bp-slot-service"><?= htmlspecialchars($slot['sub_service_name'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php else: ?>
                <div class="bp-slot-service" style="margin-bottom:10px;"></div>
              <?php endif; ?>
              <a href="/Smartbookers/user/book.php?availability_id=<?= (int)$slot['id'] ?>">Foglalás</a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</main>
<?php include '../includes/footer.php'; ?>
