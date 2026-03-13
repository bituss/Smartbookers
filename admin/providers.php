<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$msg = '';

// --- Törlés ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $id = (int)$_POST['delete_id'];
  // Provider rekord törlése + a hozzá tartozó user
  $userId = $pdo->prepare("SELECT user_id FROM providers WHERE id = ?");
  $userId->execute([$id]);
  $uid = $userId->fetchColumn();

  $pdo->prepare("DELETE FROM providers WHERE id = ?")->execute([$id]);
  if ($uid) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
  }
  $msg = 'success:Szolgáltató törölve.';
}

// --- Keresés ---
$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
  $where = "WHERE p.business_name LIKE :q OR u.name LIKE :q OR u.email LIKE :q";
  $params[':q'] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
  SELECT p.id, p.business_name, u.name AS owner_name, u.email,
         s.name AS service_name, t.nev AS telepules, p.created_at
  FROM providers p
  LEFT JOIN users u ON u.id = p.user_id
  LEFT JOIN services s ON s.id = p.service_id
  LEFT JOIN telepulesek t ON t.id = p.telepules_id
  $where
  ORDER BY p.id DESC
");
$stmt->execute($params);
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Szolgáltatók</h1>

<?php if ($msg): ?>
  <?php [$type,$text] = explode(':', $msg, 2); ?>
  <div class="admin-alert <?= $type ?>"><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<form class="admin-form" method="get">
  <input class="admin-input" type="text" name="q" placeholder="Keresés cégnév, név vagy email..."
         value="<?= htmlspecialchars($search) ?>">
  <button class="btn-admin primary" type="submit">Keresés</button>
  <?php if ($search): ?>
    <a href="/Smartbookers/admin/providers.php" class="btn-admin ghost">Összes</a>
  <?php endif; ?>
</form>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Cégnév</th>
        <th>Tulajdonos</th>
        <th>Email</th>
        <th>Szolgáltatás</th>
        <th>Település</th>
        <th>Regisztráció</th>
        <th>Művelet</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($providers)): ?>
      <tr><td colspan="8" style="text-align:center;color:#64748b;">Nincs találat.</td></tr>
    <?php else: foreach ($providers as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['business_name'] ?? '–') ?></td>
        <td><?= htmlspecialchars($p['owner_name'] ?? '–') ?></td>
        <td><?= htmlspecialchars($p['email'] ?? '–') ?></td>
        <td><?= htmlspecialchars($p['service_name'] ?? '–') ?></td>
        <td><?= htmlspecialchars($p['telepules'] ?? '–') ?></td>
        <td><?= htmlspecialchars($p['created_at']) ?></td>
        <td>
          <form method="post" style="display:inline;" onsubmit="return confirm('Biztosan törlöd a szolgáltatót és a hozzá tartozó felhasználót?');">
            <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
            <button class="btn-admin danger" type="submit">Törlés</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
