<?php
session_start();

// Ha már be van jelentkezve adminként, ugorjon a dashboardra
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
  header('Location: /Smartbookers/admin/dashboard.php');
  exit;
}

$error = '';

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

      $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.password, u.role AS role_name
        FROM users u
        WHERE u.email = :email AND u.role = 'admin'
        LIMIT 1
      ");
      $stmt->execute([':email' => $email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = 'admin';
        header('Location: /Smartbookers/admin/dashboard.php');
        exit;
      } else {
        $error = 'Hibás email vagy jelszó.';
      }
    } catch (PDOException $e) {
      $error = 'Adatbázis hiba.';
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
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