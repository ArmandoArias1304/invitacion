<?php
// Función para conectar a la base de datos
function conectarDB() {
    $host = 'localhost'; // Cambiar según sea necesario
    $usuario = 'root'; // Cambiar según sea necesario
    $contraseña = ''; // Cambiar según sea necesario
    $nombreBD = 'invitacion_boda'; // Cambiar según sea necesario

    $conexion = new mysqli($host, $usuario, $contraseña, $nombreBD);

    if ($conexion->connect_error) {
        die("Conexión fallida: " . $conexion->connect_error);
    }

    return $conexion;
}

// Función para sanitizar entradas
function sanitizarEntrada($data) {
    $conexion = conectarDB();
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conexion->real_escape_string($data);
}

// Función para cerrar la conexión a la base de datos
function cerrarConexion($conexion) {
    $conexion->close();
}

// Otras funciones útiles pueden ser añadidas aquí
?>