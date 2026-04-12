<?php
$provider_id = (int)($_GET['provider'] ?? 0);
if (!$provider_id) {
    http_response_code(400);
    exit;
}
require_once __DIR__ . '/config/app.php';
$bookingUrl = getQrBaseUrl() . '/Smartbookers/user/book_provider.php?provider_id=' . $provider_id;
$apiUrl = 'https:
$png = @file_get_contents($apiUrl);
if ($png === false) {
    http_response_code(503);
    exit;
}
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
echo $png;
