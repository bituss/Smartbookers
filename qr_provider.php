<?php

include "libs/phpqrcode/qrlib.php";

$provider_id = $_GET['provider'] ?? 0;

if(!$provider_id){
    die("Nincs provider.");
}

$url =  "http://localhost/Smartbookers/book_provider.php?provider_id=".$provider_id;

QRcode::png($url);