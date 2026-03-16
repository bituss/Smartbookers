<?php

require('libs/fpdf/fpdf.php');

$provider_id = $_GET['provider'] ?? 0;

if(!$provider_id){
    die("Nincs provider.");
}

$qr = "qr_codes/provider_".$provider_id.".png";

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',22);
$pdf->Cell(0,20,'SmartBookers',0,1,'C');

$pdf->SetFont('Arial','',16);
$pdf->Cell(0,10,'Foglalj idopontot',0,1,'C');

$pdf->Image($qr,80,70,50);

$pdf->SetY(140);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,'Szkenneld a kameraval',0,1,'C');

$pdf->Output();