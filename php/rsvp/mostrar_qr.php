<?php
// mostrar_qr.php

// Incluir el archivo de configuración de la base de datos
require_once '../config/database.php';

// Verificar si se ha pasado un ID de invitado
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Consultar la base de datos para obtener la información del invitado
    $query = "SELECT * FROM invitados WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $invitado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invitado) {
        // Generar el código QR
        require_once '../utils/qr_generator.php';
        $qrCode = generateQRCode($invitado['codigo_confirmacion']);

        // Mostrar el código QR
        echo '<h1>Tu Código QR de Confirmación</h1>';
        echo '<img src="' . $qrCode . '" alt="Código QR">';
    } else {
        echo '<h1>Invitado no encontrado</h1>';
    }
} else {
    echo '<h1>ID de invitado no proporcionado</h1>';
}
?>