<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider') {
  header("Location: /Smartbookers/business/provider_login.php");
  exit;
}
$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) die("Kapcsolódási hiba: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");
$uid = (int)$_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT id FROM providers WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$provider_id = (int)($p['id'] ?? 0);
if ($provider_id <= 0) {
  header("Location: /Smartbookers/business/provider_place.php?error=1&msg=" . urlencode("Nincs provider profil."));
  exit;
}
$up = $mysqli->prepare("
  UPDATE bookings
  SET provider_seen = 1
  WHERE provider_id = ?
    AND cancelled_at IS NOT NULL
    AND provider_seen = 0
");
$up->bind_param("i", $provider_id);
$up->execute();
header("Location: /Smartbookers/business/provider_place.php?sent=1&msg=" . urlencode("Rendben, megjelöltem olvasottnak."));
exit;