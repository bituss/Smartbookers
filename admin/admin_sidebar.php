<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: /Smartbookers/admin/adminlogin.php');
  exit;
}
try {
  $pdo_admin = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4', 'root', '');
  $checkColumn = $pdo_admin->query("SHOW COLUMNS FROM users LIKE 'deactivated_at'")->fetch();
  $hasDeactivated = !empty($checkColumn);
  if ($hasDeactivated) {
    $stmt_admin = $pdo_admin->prepare("SELECT deactivated_at FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $stmt_admin->execute([(int)$_SESSION['user_id']]);
    $admin_check = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    if ($admin_check && $admin_check['deactivated_at'] !== null) {
      session_destroy();
      $_SESSION = [];
      header("Location: /Smartbookers/admin/adminlogin.php?reason=inactive");
      exit;
    }
  }
} catch (Exception $e) {
}
$_adminPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – SmartBookers</title>
<link href="https:
<link rel="stylesheet" href="/Smartbookers/public/css/admin.css">
</head>
<body>
<div class="admin-wrap">
  
  <div class="admin-topbar">
    <button type="button" class="admin-burger" id="adminBurger" aria-label="Menü">☰</button>
    <span class="admin-topbar-title">SmartBookers</span>
  </div>
  
  <div class="admin-overlay" id="adminOverlay"></div>
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand">SmartBookers</div>
    <nav>
      <a href="/Smartbookers/admin/dashboard.php"  class="<?= $_adminPage==='dashboard.php'  ?'active':'' ?>">📊 Dashboard</a>
      <a href="/Smartbookers/admin/users.php"      class="<?= $_adminPage==='users.php'      ?'active':'' ?>">👤 Felhasználók</a>
      <a href="/Smartbookers/admin/providers.php"   class="<?= $_adminPage==='providers.php'   ?'active':'' ?>">🏢 Szolgáltatók</a>
      <a href="/Smartbookers/admin/bookings.php"    class="<?= $_adminPage==='bookings.php'    ?'active':'' ?>">📅 Foglalások</a>
      <a href="/Smartbookers/admin/services.php"    class="<?= $_adminPage==='services.php'    ?'active':'' ?>">🛠 Szolgáltatások</a>
      <a href="/Smartbookers/admin/industries.php"  class="<?= $_adminPage==='industries.php'  ?'active':'' ?>">🏷 Iparágak</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="/Smartbookers/public/logout.php">Kilépés →</a>
    </div>
  </aside>
  <main class="admin-main">
