<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$userCount     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$providerCount = $pdo->query("SELECT COUNT(*) FROM providers")->fetchColumn();
$activeBook    = $pdo->query("SELECT COUNT(*) FROM bookings WHERE cancelled_at IS NULL")->fetchColumn();
$cancelledBook = $pdo->query("SELECT COUNT(*) FROM bookings WHERE cancelled_at IS NOT NULL")->fetchColumn();
$serviceCount  = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$industryCount = $pdo->query("SELECT COUNT(*) FROM industries")->fetchColumn();

$recent = $pdo->query("
  SELECT b.id, u.name AS user_name, p.business_name, b.booking_time, b.cancelled_at,
         ss.name AS sub_service
  FROM bookings b
  LEFT JOIN users u ON u.id = b.user_id
  LEFT JOIN providers p ON p.id = b.provider_id
  LEFT JOIN sub_services ss ON ss.id = b.sub_service_id
  ORDER BY b.created_at DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Dashboard</h1>

<div class="stat-grid">
  <div class="stat-card">
    <div class="label">Felhasználók</div>
    <div class="value"><?= $userCount ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Szolgáltatók</div>
    <div class="value"><?= $providerCount ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Aktív foglalások</div>
    <div class="value"><?= $activeBook ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Lemondott foglalások</div>
    <div class="value"><?= $cancelledBook ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Szolgáltatások</div>
    <div class="value"><?= $serviceCount ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Iparágak</div>
    <div class="value"><?= $industryCount ?></div>
  </div>
</div>

<h2 style="font-size:18px;font-weight:800;margin:0 0 14px;">Legutóbbi foglalások</h2>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Felhasználó</th>
        <th>Szolgáltató</th>
        <th>Alszolgáltatás</th>
        <th>Időpont</th>
        <th>Státusz</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($recent)): ?>
      <tr><td colspan="6" style="text-align:center;color:#64748b;">Nincs foglalás.</td></tr>
    <?php else: foreach ($recent as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['user_name'] ?? '–') ?></td>
        <td><?= htmlspecialchars($r['business_name'] ?? '–') ?></td>
        <td><?= htmlspecialchars($r['sub_service'] ?? '–') ?></td>
        <td><?= htmlspecialchars($r['booking_time']) ?></td>
        <td>
          <?php if ($r['cancelled_at']): ?>
            <span class="badge-cancelled">Lemondva</span>
          <?php else: ?>
            <span class="badge-active">Aktív</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>