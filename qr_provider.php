<?php

include "libs/phpqrcode/qrlib.php";

$provider_id = $_GET['provider'] ?? 0;

if(!$provider_id){
    die("Nincs provider.");
}

/* a foglalási oldal linkje */
$url = "http://localhost/Smartbookers/book.php?provider=".$provider_id;

/* QR generálás */
QRcode::png($url);

?>