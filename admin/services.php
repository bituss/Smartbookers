<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service_id'])) {
  $id = (int)$_POST['delete_service_id'];
  $pdo->prepare("DELETE FROM sub_services WHERE service_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
  $msg = 'success:Szolgáltatás és alszolgáltatásai törölve.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sub'])) {
  $serviceId = (int)($_POST['sub_service_id'] ?? 0);
  $subName   = trim($_POST['sub_name'] ?? '');
  if ($serviceId <= 0 || $subName === '') {
    $msg = 'error:Szolgáltatás és név megadása kötelező.';
  } else {
    $pdo->prepare("INSERT INTO sub_services (service_id, name) VALUES (?, ?)")
        ->execute([$serviceId, $subName]);
    $msg = 'success:Alszolgáltatás hozzáadva.';
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sub_id'])) {
  $id = (int)$_POST['delete_sub_id'];
  $pdo->prepare("DELETE FROM sub_services WHERE id = ?")->execute([$id]);
  $msg = 'success:Alszolgáltatás törölve.';
}
$services = $pdo->query("SELECT id, name FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subServices = $pdo->query("
  SELECT ss.id, ss.name, ss.service_id, s.name AS service_name
  FROM sub_services ss
  JOIN services s ON s.id = ss.service_id
  ORDER BY s.name, ss.name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<h1>Szolgáltatások</h1>
<?php if ($msg): ?>
  <?php [$type,$text] = explode(':', $msg, 2); ?>
  <div class="admin-alert <?= $type ?>"><?= htmlspecialchars($text) ?></div>
<?php endif; ?>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Alszolgáltatás</th>
        <th>Főszolgáltatás</th>
        <th>Művelet</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($subServices)): ?>
      <tr><td colspan="4" style="text-align:center;color:#64748b;">Nincs alszolgáltatás.</td></tr>
    <?php else: foreach ($subServices as $ss): ?>
      <tr>
        <td><?= $ss['id'] ?></td>
        <td><?= htmlspecialchars($ss['name']) ?></td>
        <td><span class="badge-role"><?= htmlspecialchars($ss['service_name']) ?></span></td>
        <td>
          <form method="post" style="display:inline;" onsubmit="return confirm('Biztosan törlöd?');">
            <input type="hidden" name="delete_sub_id" value="<?= $ss['id'] ?>">
            <button class="btn-admin danger" type="submit">Törlés</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/admin_footer.php'; ?>
