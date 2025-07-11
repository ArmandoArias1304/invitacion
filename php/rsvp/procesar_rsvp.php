<?php
// Procesar la confirmación de RSVP

// Incluir la configuración de la base de datos
require_once '../config/database.php';

// Verificar si se ha enviado el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener los datos del formulario
    $nombre = $_POST['nombre'];
    $asistencia = $_POST['asistencia'];
    $comentarios = $_POST['comentarios'];

    // Validar los datos
    if (!empty($nombre) && isset($asistencia)) {
        // Preparar la consulta para insertar los datos en la base de datos
        $query = "INSERT INTO rsvps (nombre, asistencia, comentarios) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $nombre, $asistencia, $comentarios);

        // Ejecutar la consulta
        if ($stmt->execute()) {
            // Redirigir a la página de confirmación
            header("Location: confirmar.php?success=1");
            exit();
        } else {
            // Manejar error en la inserción
            header("Location: confirmar.php?error=1");
            exit();
        }
    } else {
        // Manejar error de validación
        header("Location: confirmar.php?error=2");
        exit();
    }
} else {
    // Redirigir si no se accede a este archivo directamente
    header("Location: confirmar.php");
    exit();
}
?>