<?php
require_once 'path/to/qrcode/qrcode.min.php'; // Asegúrate de ajustar la ruta según sea necesario

function generarQRCode($data, $filename) {
    $qrCode = new QRCode();
    $qrCode->setText($data);
    $qrCode->setSize(300);
    $qrCode->setMargin(10);
    $qrCode->setErrorCorrectionLevel('L');

    // Generar el código QR y guardarlo en el archivo especificado
    $qrCode->save($filename);
}

function mostrarQRCode($filename) {
    if (file_exists($filename)) {
        header('Content-Type: image/png');
        readfile($filename);
    } else {
        echo 'El código QR no existe.';
    }
}
?>