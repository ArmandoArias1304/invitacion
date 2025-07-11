<?php
// estadisticas.php

// Incluir archivo de configuración de base de datos
require_once '../config/database.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Consultar estadísticas de RSVP
$query = "SELECT COUNT(*) as total_invitados, 
                 SUM(CASE WHEN rsvp_status = 'Asistiré' THEN 1 ELSE 0 END) as total_asistentes, 
                 SUM(CASE WHEN rsvp_status = 'No asistiré' THEN 1 ELSE 0 END) as total_no_asistentes 
          FROM invitados";

$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener estadísticas
$total_invitados = $row['total_invitados'];
$total_asistentes = $row['total_asistentes'];
$total_no_asistentes = $row['total_no_asistentes'];

// Mostrar estadísticas
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Invitación</title>
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
    <div class="container">
        <h1>Estadísticas de la Invitación</h1>
        <p>Total de invitados: <?php echo $total_invitados; ?></p>
        <p>Total de asistentes: <?php echo $total_asistentes; ?></p>
        <p>Total de no asistentes: <?php echo $total_no_asistentes; ?></p>
    </div>
</body>
</html>