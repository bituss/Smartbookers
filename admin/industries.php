<?php include __DIR__ . '/admin_sidebar.php'; ?>
<?php
$pdo = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4','root','',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_industry'])) {
  $name = trim($_POST['ind_name'] ?? '');
  $slug = trim($_POST['ind_slug'] ?? '');
  $desc = trim($_POST['ind_desc'] ?? '');
  if ($name === '' || $slug === '') {
    $msg = 'error:Név és slug megadása kötelező.';
  } else {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    $exists = $pdo->prepare("SELECT COUNT(*) FROM industries WHERE slug = ?");
    $exists->execute([$slug]);
    if ($exists->fetchColumn() > 0) {
      $msg = 'error:Ez a slug már létezik.';
    } else {
      $pdo->prepare("INSERT INTO industries (name, slug, description, is_active) VALUES (?, ?, ?, 1)")
          ->execute([$name, $slug, $desc ?: null]);
      $msg = 'success:Iparág hozzáadva.';
    }
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_industry'])) {
  $id   = (int)$_POST['edit_id'];
  $name = trim($_POST['edit_name'] ?? '');
  $slug = trim($_POST['edit_slug'] ?? '');
  $desc = trim($_POST['edit_desc'] ?? '');
  if ($name === '' || $slug === '') {
    $msg = 'error:Név és slug megadása kötelező.';
  } else {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    $pdo->prepare("UPDATE industries SET name = ?, slug = ?, description = ? WHERE id = ?")
        ->execute([$name, $slug, $desc ?: null, $id]);
    $msg = 'success:Iparág frissítve.';
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
  $id = (int)$_POST['toggle_id'];
  $pdo->prepare("UPDATE industries SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
  $msg = 'success:Státusz módosítva.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $id = (int)$_POST['delete_id'];
  $pdo->prepare("DELETE FROM industries WHERE id = ?")->execute([$id]);
  $msg = 'success:Iparág törölve.';
}
$industries = $pdo->query("SELECT * FROM industries ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
  $stmt = $pdo->prepare("SELECT * FROM industries WHERE id = ?");
  $stmt->execute([$editId]);
  $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<h1>Iparágak</h1>
<?php if ($msg): ?>
  <?php [$type,$text] = explode(':', $msg, 2); ?>
  <div class="admin-alert <?= $type ?>"><?= htmlspecialchars($text) ?></div>
<?php endif; ?>
<div class="admin-form-wrap">
  <h2><?= $editRow ? 'Iparág szerkesztése' : 'Új iparág hozzáadása' ?></h2>
  <form method="post" class="admin-form">
    <?php if ($editRow): ?>
      <input type="hidden" name="edit_industry" value="1">
      <input type="hidden" name="edit_id" value="<?= $editRow['id'] ?>">
    <?php else: ?>
      <input type="hidden" name="add_industry" value="1">
    <?php endif; ?>
    <label>
      Név
      <input type="text" name="<?= $editRow ? 'edit_name' : 'ind_name' ?>" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
    </label>
    <label>
      Slug
      <input type="text" name="<?= $editRow ? 'edit_slug' : 'ind_slug' ?>" value="<?= htmlspecialchars($editRow['slug'] ?? '') ?>" required>
    </label>
    <label>
      Leírás
      <textarea name="<?= $editRow ? 'edit_desc' : 'ind_desc' ?>" rows="3"><?= htmlspecialchars($editRow['description'] ?? '') ?></textarea>
    </label>
    <div class="admin-form-actions">
      <button type="submit" class="btn-admin success"><?= $editRow ? 'Mentés' : 'Hozzáadás' ?></button>
      <?php if ($editRow): ?>
        <a href="/Smartbookers/admin/industries.php" class="btn-admin ghost">Mégse</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Név</th>
        <th>Slug</th>
        <th>Leírás</th>
        <th>Státusz</th>
        <th>Műveletek</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($industries)): ?>
      <tr><td colspan="6" style="text-align:center;color:#64748b;">Nincs iparág.</td></tr>
    <?php else: foreach ($industries as $i): ?>
      <tr>
        <td><?= $i['id'] ?></td>
        <td><?= htmlspecialchars($i['name']) ?></td>
        <td><code><?= htmlspecialchars($i['slug']) ?></code></td>
        <td><?= htmlspecialchars($i['description'] ?? '–') ?></td>
        <td>
          <?php if ($i['is_active']): ?>
            <span class="badge-active">Aktív</span>
          <?php else: ?>
            <span class="badge-cancelled">Inaktív</span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:6px;flex-wrap:wrap;">
          
          <form method="post" style="display:inline;">
            <input type="hidden" name="toggle_id" value="<?= $i['id'] ?>">
            <button class="btn-admin <?= $i['is_active'] ? 'ghost' : 'success' ?>" type="submit">
              <?= $i['is_active'] ? 'Deaktiválás' : 'Aktiválás' ?>
            </button>
          </form>
          
          <a href="?edit=<?= $i['id'] ?>" class="btn-admin ghost">Szerkesztés</a>
          
          <form method="post" style="display:inline;" onsubmit="return confirm('Biztosan törlöd?');">
            <input type="hidden" name="delete_id" value="<?= $i['id'] ?>">
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/admin_footer.php'; ?>
