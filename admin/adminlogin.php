<?php
session_start();
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
  header('Location: /Smartbookers/admin/dashboard.php');
  exit;
}
$error = '';
$reason = $_GET['reason'] ?? '';
if ($reason === 'inactive') {
  $error = 'A fiókod inaktív. Kérj segítséget az adminisztrátortól.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($email === '' || $password === '') {
    $error = 'Minden mező kitöltése kötelező.';
  } else {
    try {
      $pdo = new PDO(
        'mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
      );
      $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'deactivated_at'")->fetch();
      $hasDeactivated = !empty($checkColumn);
      $sql = "SELECT u.id, u.name, u.password, u.role AS role_name";
      if ($hasDeactivated) {
        $sql .= ", u.deactivated_at";
      }
      $sql .= " FROM users u WHERE u.email = :email AND u.role = 'admin'";
      if ($hasDeactivated) {
        $sql .= " AND u.deactivated_at IS NULL";
      }
      $sql .= " LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':email' => $email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = 'admin';
        header('Location: /Smartbookers/admin/dashboard.php');
        exit;
      } else {
        $error = 'Hibás email vagy jelszó, vagy a fiók inaktív.';
      }
    } catch (PDOException $e) {
      $error = 'Adatbázis hiba: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin belépés – SmartBookers</title>
<link href="https:
<link rel="stylesheet" href="/Smartbookers/public/css/admin.css">
</head>
<body>
<div class="admin-login-wrap">
  <div class="admin-login-card">
    <h1>Admin belépés</h1>
    <p class="subtitle">SmartBookers kezelőfelület</p>
    <?php if ($error): ?>
      <div class="admin-alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="admin1@admin.hu"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      <label for="password">Jelszó</label>
      <input type="password" id="password" name="password" placeholder="••••••" required>
      <button type="submit" class="btn-login">Belépés</button>
    </form>
  </div>
</div>
</body>
</html>