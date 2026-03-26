<?php

$provider_id = (int)($_GET['provider'] ?? 0);

if (!$provider_id) {
    http_response_code(400);
    exit;
}

require_once __DIR__ . '/config/app.php';

$bookingUrl = getQrBaseUrl() . '/Smartbookers/user/book_provider.php?provider_id=' . $provider_id;

// QR kép lekérése a qrserver.com API-tól (ingyenes, nem kér API kulcsot)
$apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($bookingUrl);

$png = @file_get_contents($apiUrl);

if ($png === false) {
    // Ha nincs internet vagy a kérés sikertelen: üres PNG-t adunk vissza
    http_response_code(503);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
echo $png;
