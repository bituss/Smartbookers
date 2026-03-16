<?php

include "libs/phpqrcode/qrlib.php";

$url = "http://localhost/Smartbookers"; // ide a foglalási oldal linkje

QRcode::png($url);

?>