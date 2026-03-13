<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$msg = '';

// --- Lemondás ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
  $id = (int)$_POST['cancel_id'];
  $pdo->prepare("UPDATE bookings SET cancelled_at = NOW() WHERE id = ? AND cancelled_at IS NULL")
      ->execute([$id]);
  $msg = 'success:Foglalás lemondva.';
}

// --- Törlés ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $id = (int)$_POST['delete_id'];
  $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);
  $msg = 'success:Foglalás törölve.';
}

// --- Szűrés ---
$filter = $_GET['status'] ?? 'all';
$where = '';
if ($filter === 'active')    $where = 'WHERE b.cancelled_at IS NULL';
if ($filter === 'cancelled') $where = 'WHERE b.cancelled_at IS NOT NULL';

$bookings = $pdo->query("
  SELECT b.id, u.name AS user_name, p.business_name, b.booking_time,
         b.cancelled_at, b.created_at, ss.name AS sub_service
  FROM bookings b
  LEFT JOIN users u ON u.id = b.user_id
  LEFT JOIN providers p ON p.id = b.provider_id
  LEFT JOIN sub_services ss ON ss.id = b.sub_service_id
  $where
  ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Foglalások</h1>

<?php if ($msg): ?>
  <?php [$type,$text] = explode(':', $msg, 2); ?>
  <div class="admin-alert <?= $type ?>"><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<div class="admin-form">
  <a href="?status=all"       class="btn-admin <?= $filter==='all'       ?'primary':'ghost' ?>">Mind</a>
  <a href="?status=active"    class="btn-admin <?= $filter==='active'    ?'primary':'ghost' ?>">Aktívak</a>
  <a href="?status=cancelled" class="btn-admin <?= $filter==='cancelled' ?'primary':'ghost' ?>">Lemondottak</a>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Felhasználó</th>
        <th>Szolgáltató</th>
        <th>Alszolgáltatás</th>
        <th>Időpont</th>
        <th>Létrehozva</th>
        <th>Státusz</th>
        <th>Művelet</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($bookings)): ?>
      <tr><td colspan="8" style="text-align:center;color:#64748b;">Nincs foglalás.</td></tr>
    <?php else: foreach ($bookings as $b): ?>
      <tr>
        <td><?= $b['id'] ?></td>
        <td><?= htmlspecialchars($b['user_name'] ?? '–') ?></td>
        <td><?= htmlspecialchars($b['business_name'] ?? '–') ?></td>
        <td><?= htmlspecialchars($b['sub_service'] ?? '–') ?></td>
        <td><?= htmlspecialchars($b['booking_time']) ?></td>
        <td><?= htmlspecialchars($b['created_at']) ?></td>
        <td>
          <?php if ($b['cancelled_at']): ?>
            <span class="badge-cancelled">Lemondva</span>
          <?php else: ?>
            <span class="badge-active">Aktív</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!$b['cancelled_at']): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Biztosan lemondod?');">
              <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
              <button class="btn-admin danger" type="submit">Lemondás</button>
            </form>
          <?php endif; ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Véglegesen törlöd?');">
            <input type="hidden" name="delete_id" value="<?= $b['id'] ?>">
            <button class="btn-admin ghost" type="submit">Törlés</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>