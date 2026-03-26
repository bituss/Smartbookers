<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$msg = '';

// --- Törlés ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $id = (int)$_POST['delete_id'];
  // Ne lehessen az aktuális admin-t törölni
  if ($id === (int)$_SESSION['user_id']) {
    $msg = 'error:Nem törölheted saját magad.';
  } else {
    $pdo->prepare("DELETE FROM providers WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    $msg = 'success:Felhasználó törölve.';
  }
}

// --- Keresés ---
$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
  $where = "WHERE u.name LIKE :q OR u.email LIKE :q";
  $params[':q'] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
  SELECT u.id, u.name, u.email, u.role AS role_name, u.created_at
  FROM users u
  $where
  ORDER BY u.id DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Felhasználók</h1>

<?php if ($msg): ?>
  <?php [$type,$text] = explode(':', $msg, 2); ?>
  <div class="admin-alert <?= $type ?>"><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<form class="admin-form" method="get">
  <input class="admin-input" type="text" name="q" placeholder="Keresés név vagy email alapján..."
         value="<?= htmlspecialchars($search) ?>">
  <button class="btn-admin primary" type="submit">Keresés</button>
  <?php if ($search): ?>
    <a href="/Smartbookers/admin/users.php" class="btn-admin ghost">Összes</a>
  <?php endif; ?>
</form>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Név</th>
        <th>Email</th>
        <th>Szerep</th>
        <th>Regisztráció</th>
        <th>Művelet</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($users)): ?>
      <tr><td colspan="6" style="text-align:center;color:#64748b;">Nincs találat.</td></tr>
    <?php else: foreach ($users as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['name']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge-role"><?= htmlspecialchars($u['role_name']) ?></span></td>
        <td><?= htmlspecialchars($u['created_at']) ?></td>
        <td>
          <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Biztosan törlöd?');">
            <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
            <button class="btn-admin danger" type="submit">Törlés</button>
          </form>
          <?php else: ?>
            <span style="color:#64748b;font-size:12px;">Te</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>