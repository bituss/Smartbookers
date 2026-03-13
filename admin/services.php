<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$msg = '';

// --- Új szolgáltatás hozzáadása ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
  $name = trim($_POST['service_name'] ?? '');
  if ($name === '') {
    $msg = 'error:A név megadása kötelező.';
  } else {
    $exists = $pdo->prepare("SELECT COUNT(*) FROM services WHERE name = ?");
    $exists->execute([$name]);
    if ($exists->fetchColumn() > 0) {
      $msg = 'error:Ez a szolgáltatás már létezik.';
    } else {
      $pdo->prepare("INSERT INTO services (name) VALUES (?)")->execute([$name]);
      $msg = 'success:Szolgáltatás hozzáadva.';
    }
  }
}

// --- Szolgáltatás törlése ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service_id'])) {
  $id = (int)$_POST['delete_service_id'];
  $pdo->prepare("DELETE FROM sub_services WHERE service_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
  $msg = 'success:Szolgáltatás és alszolgáltatásai törölve.';
}

// --- Új alszolgáltatás hozzáadása ---
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

// --- Alszolgáltatás törlése ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sub_id'])) {
  $id = (int)$_POST['delete_sub_id'];
  $pdo->prepare("DELETE FROM sub_services WHERE id = ?")->execute([$id]);
  $msg = 'success:Alszolgáltatás törölve.';
}

// --- Adatok lekérdezése ---
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

<!-- Új szolgáltatás -->
<div class="admin-section-head">
  <h2 style="font-size:18px;font-weight:800;margin:0;">Fő szolgáltatások</h2>
</div>

<form class="admin-form" method="post">
  <input class="admin-input" type="text" name="service_name" placeholder="Új szolgáltatás neve..." required>
  <button class="btn-admin primary" type="submit" name="add_service" value="1">Hozzáadás</button>
</form>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Név</th>
        <th>Alszolg. száma</th>
        <th>Művelet</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($services as $s): ?>
      <?php $subCount = count(array_filter($subServices, fn($ss) => $ss['service_id'] == $s['id'])); ?>
      <tr>
        <td><?= $s['id'] ?></td>
        <td><?= htmlspecialchars($s['name']) ?></td>
        <td><?= $subCount ?></td>
        <td>
          <form method="post" style="display:inline;" onsubmit="return confirm('Törlöd a szolgáltatást és összes alszolgáltatását?');">
            <input type="hidden" name="delete_service_id" value="<?= $s['id'] ?>">
            <button class="btn-admin danger" type="submit">Törlés</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Alszolgáltatások -->
<div class="admin-section-head" style="margin-top:28px;">
  <h2 style="font-size:18px;font-weight:800;margin:0;">Alszolgáltatások</h2>
</div>

<form class="admin-form" method="post">
  <select class="admin-select" name="sub_service_id" required>
    <option value="">Válassz szolgáltatást...</option>
    <?php foreach ($services as $s): ?>
      <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input class="admin-input" type="text" name="sub_name" placeholder="Új alszolgáltatás neve..." required>
  <button class="btn-admin primary" type="submit" name="add_sub" value="1">Hozzáadás</button>
</form>

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
