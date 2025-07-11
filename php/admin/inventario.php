<?php
// inventario.php

// Incluir archivo de configuración de base de datos
require_once '../config/database.php';

// Conectar a la base de datos
$db = new Database();
$conn = $db->getConnection();

// Consultar la lista de invitados y su estado de RSVP
$query = "SELECT nombre, estado_rsvp FROM invitados";
$stmt = $conn->prepare($query);
$stmt->execute();

// Verificar si hay resultados
if ($stmt->rowCount() > 0) {
    echo "<h1>Inventario de Invitados</h1>";
    echo "<table>";
    echo "<tr><th>Nombre</th><th>Estado de RSVP</th></tr>";

    // Mostrar cada invitado y su estado
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['estado_rsvp']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<h1>No hay invitados registrados.</h1>";
}

// Cerrar conexión
$conn = null;
?>