<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider') {
  header("Location: /Smartbookers/business/provider_login.php");
  exit;
}
$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
  header("Location: /Smartbookers/business/provider_profile.php?error=1&msg=" . urlencode("Hibás foglalás."));
  exit;
}
$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) die("Kapcsolódási hiba: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");
$user_id = (int)$_SESSION['user_id'];
$st = $mysqli->prepare("SELECT id FROM providers WHERE user_id = ? LIMIT 1");
$st->bind_param("i", $user_id);
$st->execute();
$prow = $st->get_result()->fetch_assoc();
$provider_id = (int)($prow['id'] ?? 0);
if ($provider_id <= 0) {
  header("Location: /Smartbookers/business/provider_login.php?error=1&msg=" . urlencode("Nincs provider profil."));
  exit;
}
$up = $mysqli->prepare("
  UPDATE bookings
  SET provider_seen = 1
  WHERE id = ?
    AND provider_id = ?
    AND cancelled_at IS NOT NULL
    AND provider_seen = 0
  LIMIT 1
");
$up->bind_param("ii", $booking_id, $provider_id);
$up->execute();
if ($up->affected_rows > 0) {
  header("Location: /Smartbookers/business/provider_profile.php?sent=1&msg=" . urlencode("Lemondás megjelölve: láttam."));
  exit;
}
header("Location: /Smartbookers/business/provider_profile.php?error=1&msg=" . urlencode("Nem sikerült (lehet már láttad vagy nem a te foglalásod)."));
exit;