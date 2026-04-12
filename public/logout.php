<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
session_destroy();
$_SESSION = [];
$reason = $_GET['reason'] ?? '';
$logoutMsg = '';
if ($reason === 'inactive') {
  $logoutMsg = '?logout=inactive';
} else {
  $logoutMsg = '?logout=1';
}
header("Location: /Smartbookers/public/index.php" . $logoutMsg);
exit;
?>
