<?php
require_once '../utils/qr_generator.php';

// Verificar si se ha enviado el ID de la confirmación
if (isset($_POST['confirmation_id'])) {
    $confirmation_id = $_POST['confirmation_id'];

    // Generar el QR code
    $qr_code = generateQRCode($confirmation_id);

    // Guardar el QR code en la carpeta de uploads
    $file_path = '../uploads/qr_codes/' . $confirmation_id . '.png';
    imagepng($qr_code, $file_path);

    // Devolver la ruta del QR code generado
    echo json_encode(['success' => true, 'file_path' => $file_path]);
} else {
    echo json_encode(['success' => false, 'message' => 'No se proporcionó el ID de confirmación.']);
}
?>