<?php
require('libs/fpdf/fpdf.php');
$provider_id = (int)($_GET['provider'] ?? 0);
if (!$provider_id) {
    die("Nincs provider.");
}
require_once __DIR__ . '/config/app.php';
$bookingUrl = getQrBaseUrl() . '/Smartbookers/user/book_provider.php?provider_id=' . $provider_id;
$apiUrl     = 'https:
$png = @file_get_contents($apiUrl);
if ($png === false) {
    die("Nem sikerült a QR kód letöltése. Ellenőrizze az internetkapcsolatot.");
}
$tmpFile = sys_get_temp_dir() . '/sb_qr_' . $provider_id . '.png';
file_put_contents($tmpFile, $png);
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 24);
$pdf->Cell(0, 20, 'SmartBookers', 0, 1, 'C');
$pdf->SetFont('Arial', '', 16);
$pdf->Cell(0, 10, 'Foglalj idopontot!', 0, 1, 'C');
$pdf->Image($tmpFile, 80, 60, 50);
$pdf->SetY(125);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Szkenneld be a kameraval', 0, 1, 'C');
@unlink($tmpFile);
$pdf->Output('D', 'qr_plakat.pdf');
