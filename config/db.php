<?php
$host = 'localhost';
$db = 'smartbookers';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
die('Adatbázis hiba');
}
?>