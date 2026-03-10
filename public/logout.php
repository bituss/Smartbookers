<?php
// Indítsuk el a session-t, ha még nincs elindítva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Töröljük az összes session változót
$_SESSION = [];

// Ha van session cookie, töröljük is
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000, // múltba állítjuk, hogy törlődjön
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Megsemmisítjük a session-t
session_destroy();

// Átirányítás az index.php-ra **biztonságos módon**
// Ezzel relatív útvonalat használunk, így bárhol legyen a fájl, működik
$indexPath = dirname($_SERVER['SCRIPT_NAME']) . '/index.php';
header("Location: $indexPath");
exit;
?>
