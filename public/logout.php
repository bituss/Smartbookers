<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔥 először állítsd be a siker üzenetet
$_SESSION['logout_success'] = true;

// 🔥 csak a user adatokat töröljük (nem az egészet!)
unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['avatar']);

// ❗ NE destroy-old itt a session-t

// redirect
header("Location: /Smartbookers/public/index.php?logout=1");
exit;
?>