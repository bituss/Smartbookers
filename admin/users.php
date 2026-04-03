<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$msg = '';
$msgType = '';

// Ellenőrzik, hogy az adatbázis sémában létezik-e a deactivated_at oszlop
$checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'deactivated_at'")->fetch();
$hasDeactivated = !empty($checkColumn);

// --- Keresés ---
$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
  $where = "WHERE u.name LIKE :q OR u.email LIKE :q";
  $params[':q'] = '%' . $search . '%';
}

// SQL lekérdezés konstruálása
$selectCols = "u.id, u.name, u.email, u.role AS role_name, u.created_at";
if ($hasDeactivated) {
  $selectCols .= ", u.deactivated_at";
}
$orderBy = "u.id DESC";
if ($hasDeactivated) {
  $orderBy = "u.deactivated_at DESC, u.id DESC";
}

$stmt = $pdo->prepare("
  SELECT $selectCols
  FROM users u
  $where
  ORDER BY $orderBy
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Felhasználók</h1>

<div id="msgContainer"></div>

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
        <th>Inaktiválva</th>
        <th>Státusz</th>
        <th>Művelet</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($users)): ?>
      <tr><td colspan="8" style="text-align:center;color:#64748b;">Nincs találat.</td></tr>
    <?php else: foreach ($users as $u): 
      // Ha nincs deactivated_at oszlop, feltételezzük, hogy aktív
      $isActive = $hasDeactivated ? ($u['deactivated_at'] === null) : true;
      $statusClass = $isActive ? 'active' : 'inactive';
      $statusText = $isActive ? 'Aktív' : 'Inaktív';
    ?>
      <tr style="<?= $isActive ? '' : 'opacity: 0.6;' ?>">
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['name']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge-role"><?= htmlspecialchars($u['role_name']) ?></span></td>
        <td><?= htmlspecialchars($u['created_at']) ?></td>
        <td><?= ($hasDeactivated && $u['deactivated_at']) ? htmlspecialchars(substr($u['deactivated_at'], 0, 10)) : '—' ?></td>
        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
        <td>
          <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
            <div style="display:flex; gap:4px;">
              <?php if ($isActive): ?>
                <button class="btn-admin danger btn-deactivate" data-user-id="<?= $u['id'] ?>" data-user-name="<?= htmlspecialchars($u['name']) ?>">Inaktiválás</button>
              <?php else: ?>
                <button class="btn-admin success btn-reactivate" data-user-id="<?= $u['id'] ?>" data-user-name="<?= htmlspecialchars($u['name']) ?>">Reaktiválás</button>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <span style="color:#64748b;font-size:12px;">Te</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<style>
.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.badge.active {
  background: #e0f2fe;
  color: #0369a1;
}

.badge.inactive {
  background: #fee2e2;
  color: #991b1b;
}

.btn-deactivate,
.btn-reactivate {
  cursor: pointer;
  white-space: nowrap;
  font-size: 12px;
  padding: 6px 10px;
}
</style>

<script>
document.querySelectorAll('.btn-deactivate').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    const userId = btn.dataset.userId;
    const userName = btn.dataset.userName;
    
    if (!confirm(`Biztosan inaktiválod "${userName}" fiókját?`)) return;
    
    try {
      const response = await fetch(`/Smartbookers/api/users/${userId}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' }
      });
      
      const result = await response.json();
      
      if (response.ok) {
        showMessage('Felhasználó sikeresen inaktiválva.', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showMessage(result.message, 'error');
      }
    } catch (error) {
      showMessage('Hálózati hiba történt.', 'error');
    }
  });
});

document.querySelectorAll('.btn-reactivate').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    const userId = btn.dataset.userId;
    const userName = btn.dataset.userName;
    
    if (!confirm(`Biztosan reaktiválod "${userName}" fiókját?`)) return;
    
    try {
      const response = await fetch(`/Smartbookers/api/admin/users.php`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          action: 'reactivate',
          user_id: userId 
        })
      });
      
      const result = await response.json();
      
      if (response.ok) {
        showMessage('Felhasználó sikeresen reaktiválva.', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showMessage(result.message, 'error');
      }
    } catch (error) {
      showMessage('Hálózati hiba történt.', 'error');
    }
  });
});

function showMessage(message, type = 'success') {
  const container = document.getElementById('msgContainer');
  container.innerHTML = `<div class="admin-alert ${type}">${message}</div>`;
  container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>