<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../login.php');
    exit;
}

// Función para verificar el tiempo de sesión (opcional)
function verificarTiempoSesion() {
    $timeout = 7200; // 2 horas en segundos
    
    if (isset($_SESSION['ultimo_acceso'])) {
        if (time() - $_SESSION['ultimo_acceso'] > $timeout) {
            session_destroy();
            header('Location: ../../login.php?timeout=1');
            exit;
        }
    }
    
    $_SESSION['ultimo_acceso'] = time();
}

// Verificar tiempo de sesión
verificarTiempoSesion();

// Incluir la conexión a la base de datos
require_once '../config/database.php';
?>