<?php
/**
 * Admin sidebar + auth guard.
 * Minden admin oldal elején: include 'admin_sidebar.php';
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: /Smartbookers/admin/adminlogin.php');
  exit;
}

// aktuális fájlnév az active link jelöléséhez
$_adminPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – SmartBookers</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/Smartbookers/public/css/admin.css">
</head>
<body>
<div class="admin-wrap">

  <aside class="admin-sidebar">
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
