<?php
/**
 * Logout Handler - Backward Compatible
 * Irányít az API-ra, de böngészőből is működik
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Session törlése
session_destroy();
$_SESSION = [];

// Query paraméter ellenőrzése
$reason = $_GET['reason'] ?? '';

// Üzenet beállítása
$logoutMsg = '';
if ($reason === 'inactive') {
  $logoutMsg = '?logout=inactive';
} else {
  $logoutMsg = '?logout=1';
}

// Redirect a kezdőoldalra
header("Location: /Smartbookers/public/index.php" . $logoutMsg);
exit;
?>
