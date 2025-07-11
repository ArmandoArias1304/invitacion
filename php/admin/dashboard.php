<?php
/**
 * PANEL ADMINISTRATIVO DE LA BODA
 * Dashboard para monitorear confirmaciones y asistencia
 */

require_once '../config/database.php';

$message = '';
$messageType = '';

// Procesar eliminación de invitados
if ($_POST && isset($_POST['delete_invitado'])) {
    try {
        $db = getDB();
        $connection = $db->getConnection();
        
        $id_invitado = (int)$_POST['id_invitado'];
        
        $stmt = $connection->prepare("DELETE FROM invitados WHERE id_invitado = ?");
        if ($stmt->execute([$id_invitado])) {
            $message = "Invitado eliminado exitosamente";
            $messageType = 'success';
            
            // Redirigir para actualizar datos
            header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
            exit();
        } else {
            $message = "Error al eliminar invitado";
            $messageType = 'danger';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Mostrar mensaje de éxito si viene de redirección
if (isset($_GET['updated'])) {
    $message = "Invitado actualizado exitosamente";
    $messageType = 'success';
} elseif (isset($_GET['deleted'])) {
    $message = "Invitado eliminado exitosamente";
    $messageType = 'success';
}

// Procesar edición de invitados
if ($_POST && isset($_POST['edit_invitado'])) {
    try {
        $db = getDB();
        $connection = $db->getConnection();
        
        $id_invitado = (int)$_POST['id_invitado'];
        $nombre_completo = sanitizeInput($_POST['nombre_completo']);
        $telefono = sanitizeInput($_POST['telefono']);
        $cupos_disponibles = (int)$_POST['cupos_disponibles'];
        $mesa = (int)$_POST['mesa'];
        $tipo_invitado = sanitizeInput($_POST['tipo_invitado']);
        $cantidad_confirmada = isset($_POST['cantidad_confirmada']) ? (int)$_POST['cantidad_confirmada'] : null;
        
        $stmt = $connection->prepare("
            UPDATE invitados 
            SET nombre_completo = ?, telefono = ?, cupos_disponibles = ?, mesa = ?, tipo_invitado = ?, updated_at = NOW()
            WHERE id_invitado = ?
        ");
        
        if ($stmt->execute([$nombre_completo, $telefono, $cupos_disponibles, $mesa, $tipo_invitado, $id_invitado])) {
            // Si hay cantidad confirmada, actualizar también la confirmación
            if ($cantidad_confirmada !== null) {
                // Verificar si ya existe una confirmación
                $checkStmt = $connection->prepare("SELECT id_confirmacion FROM confirmaciones WHERE id_invitado = ?");
                $checkStmt->execute([$id_invitado]);
                $existingConfirmation = $checkStmt->fetch();
                
                if ($existingConfirmation) {
                    // Actualizar confirmación existente
                    $updateConfStmt = $connection->prepare("
                        UPDATE confirmaciones 
                        SET cantidad_confirmada = ?, fecha_confirmacion = NOW() 
                        WHERE id_invitado = ?
                    ");
                    $updateConfStmt->execute([$cantidad_confirmada, $id_invitado]);
                } else if ($cantidad_confirmada > 0) {
                    // Crear nueva confirmación solo si la cantidad es mayor a 0
                    $insertConfStmt = $connection->prepare("
                        INSERT INTO confirmaciones (id_invitado, cantidad_confirmada, fecha_confirmacion, ip_confirmacion) 
                        VALUES (?, ?, NOW(), ?)
                    ");
                    $insertConfStmt->execute([$id_invitado, $cantidad_confirmada, $_SERVER['REMOTE_ADDR']]);
                }
            }
            
            $message = "Invitado actualizado exitosamente";
            $messageType = 'success';
            
            // Redirigir para evitar reenvío del formulario y actualizar datos
            header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
            exit();
        } else {
            $message = "Error al actualizar invitado";
            $messageType = 'danger';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

try {
    $db = getDB();
    $connection = $db->getConnection();
    
    // Obtener estadísticas generales
    $statsQuery = "
        SELECT 
            COUNT(i.id_invitado) as total_invitados,
            COUNT(c.id_confirmacion) as total_confirmados,
            SUM(CASE WHEN c.cantidad_confirmada > 0 THEN c.cantidad_confirmada ELSE 0 END) as total_personas_confirmadas,
            COUNT(ae.id_acceso) as total_presentes,
            SUM(CASE WHEN ae.id_acceso IS NOT NULL THEN c.cantidad_confirmada ELSE 0 END) as total_personas_presentes
        FROM invitados i
        LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
        LEFT JOIN accesos_evento ae ON i.id_invitado = ae.id_invitado
    ";
    $stmt = $connection->prepare($statsQuery);
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Obtener confirmaciones recientes
    $recentQuery = "
        SELECT 
            i.nombre_completo,
            i.mesa,
            c.cantidad_confirmada,
            c.fecha_confirmacion,
            CASE WHEN ae.id_acceso IS NOT NULL THEN 'Presente' ELSE 'Pendiente' END as status,
            ae.timestamp_escaneo
        FROM confirmaciones c
        JOIN invitados i ON c.id_invitado = i.id_invitado
        LEFT JOIN accesos_evento ae ON i.id_invitado = ae.id_invitado
        ORDER BY c.fecha_confirmacion DESC
        LIMIT 10
    ";
    $stmt = $connection->prepare($recentQuery);
    $stmt->execute();
    $recentConfirmations = $stmt->fetchAll();
    
    // Obtener invitados por mesa
    $mesasQuery = "
        SELECT 
            i.mesa,
            COUNT(i.id_invitado) as total_invitados,
            COUNT(c.id_confirmacion) as confirmados,
            SUM(CASE WHEN c.cantidad_confirmada > 0 THEN c.cantidad_confirmada ELSE 0 END) as personas_confirmadas,
            COUNT(ae.id_acceso) as presentes
        FROM invitados i
        LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
        LEFT JOIN accesos_evento ae ON i.id_invitado = ae.id_invitado
        GROUP BY i.mesa
        ORDER BY i.mesa ASC
    ";
    $stmt = $connection->prepare($mesasQuery);
    $stmt->execute();
    $mesas = $stmt->fetchAll();
    
    // Obtener invitados sin confirmar
    $pendientesQuery = "
        SELECT 
            i.nombre_completo,
            i.telefono,
            i.mesa,
            i.cupos_disponibles,
            i.token
        FROM invitados i
        LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
        WHERE c.id_confirmacion IS NULL
        ORDER BY i.nombre_completo ASC
    ";
    $stmt = $connection->prepare($pendientesQuery);
    $stmt->execute();
    $pendientes = $stmt->fetchAll();
    
    // Obtener todos los invitados para la nueva sección (ordenados alfabéticamente)
    $invitadosQuery = "
        SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion 
        FROM invitados i 
        LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado 
        ORDER BY i.nombre_completo ASC
    ";
    $stmt = $connection->prepare($invitadosQuery);
    $stmt->execute();
    $invitados = $stmt->fetchAll();

} catch (Exception $e) {
    die('Error al cargar datos: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Boda</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <style>
        /* Importar fuente moderna */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:root {
    /* Paleta de colores elegante y moderna */
    --primary-color: #6366f1;           /* Índigo elegante */
    --primary-light: #818cf8;           /* Índigo claro */
    --primary-dark: #4f46e5;            /* Índigo oscuro */
    --secondary-color: #8b5cf6;         /* Púrpura sofisticado */
    --accent-color: #06b6d4;            /* Cyan vibrante */
    --success-color: #10b981;           /* Verde esmeralda */
    --warning-color: #f59e0b;           /* Ámbar */
    --danger-color: #ef4444;            /* Rojo coral */
    --info-color: #3b82f6;             /* Azul cielo */
    
    /* Colores neutros */
    --dark-color: #1f2937;              /* Gris oscuro */
    --dark-light: #374151;              /* Gris medio */
    --light-bg: #f8fafc;               /* Fondo claro */
    --white: #ffffff;                   /* Blanco puro */
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    
    /* Espaciado */
    --border-radius: 16px;
    --border-radius-lg: 24px;
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Reset y estilos base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, var(--light-bg) 0%, #e0e7ff 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-weight: 400;
    line-height: 1.6;
    color: var(--gray-700);
    min-height: 100vh;
}

/* Navbar elegante */
.navbar {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    box-shadow: var(--shadow-lg);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1rem 0;
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    letter-spacing: -0.025em;
}

/* Cards modernas */
.card {
    border: none;
    border-radius: var(--border-radius-lg);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    box-shadow: var(--shadow-lg);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
}

.card-header {
    background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
    border-bottom: 1px solid var(--gray-200);
    padding: 1.5rem;
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
}

.card-body {
    padding: 2rem;
}

.card-footer {
    background: var(--gray-50);
    border-top: 1px solid var(--gray-200);
    padding: 1rem 2rem;
    border-radius: 0 0 var(--border-radius-lg) var(--border-radius-lg);
}

/* Tarjetas de estadísticas elegantes */
.stat-card {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: var(--white);
    text-align: center;
    padding: 2.5rem 2rem;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.8s ease;
}

.stat-card:hover::before {
    transform: translateX(100%);
}

.stat-number {
    font-size: 3rem;
    font-weight: 800;
    display: block;
    margin-bottom: 0.5rem;
    letter-spacing: -0.05em;
}

.stat-label {
    opacity: 0.9;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 600;
}

/* Variaciones de colores para estadísticas */
.stat-card.success {
    background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
}

.stat-card.info {
    background: linear-gradient(135deg, var(--info-color) 0%, #2563eb 100%);
}

.stat-card.warning {
    background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
    color: var(--gray-900);
}

/* Badges de estado modernos */
.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.status-presente {
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: var(--white);
    box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.3);
}

.status-pendiente {
    background: linear-gradient(135deg, var(--warning-color), #d97706);
    color: var(--white);
    box-shadow: 0 4px 14px 0 rgba(245, 158, 11, 0.3);
}

.status-sin-confirmar {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
    color: var(--white);
    box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.3);
}

.status-confirmado {
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: var(--white);
    box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.3);
}

/* Tablas elegantes - VERSIÓN SUTIL */
.table {
    border-collapse: separate;
    border-spacing: 0;
}

.table th {
    border: none;
    color: var(--white);
    font-weight: 600;
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table th:first-child {
    border-radius: var(--border-radius) 0 0 0;
}

.table th:last-child {
    border-radius: 0 var(--border-radius) 0 0;
}

.table td {
    padding: 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
    transition: all 0.2s ease; /* Transición más rápida y sutil */
}

.table tbody tr {
    transition: all 0.2s ease; /* Transición más rápida y sutil */
}

/* HOVER MÁS SUTIL - sin elevación excesiva */
.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(139, 92, 246, 0.03) 100%); /* Mucho más sutil */
    transform: translateY(-1px); /* Solo 1px de elevación en lugar de scale */
}

.table tbody tr:last-child td {
    border-bottom: none;
}

.table tbody tr:last-child td:first-child {
    border-radius: 0 0 0 var(--border-radius);
}

.table tbody tr:last-child td:last-child {
    border-radius: 0 0 var(--border-radius) 0;
}

/* Botones modernos */
.btn {
    border-radius: 50px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.875rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border: none;
    box-shadow: 0 4px 14px 0 rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px 0 rgba(99, 102, 241, 0.4);
    background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary-color) 100%);
}

.btn-outline-primary {
    border: 2px solid var(--primary-color);
    color: var(--primary-color);
    background: transparent;
}

.btn-outline-primary:hover {
    background: var(--primary-color);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(99, 102, 241, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    border-radius: 25px;
}

/* Barra de progreso elegante */
.progress {
    height: 12px;
    border-radius: 50px;
    background: var(--gray-200);
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.progress-bar {
    background: linear-gradient(90deg, var(--primary-color) 0%, var(--accent-color) 100%);
    border-radius: 50px;
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background-image: linear-gradient(45deg, rgba(255,255,255,.2) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.2) 50%, rgba(255,255,255,.2) 75%, transparent 75%, transparent);
    background-size: 1rem 1rem;
    animation: progress-animation 1s linear infinite;
}

@keyframes progress-animation {
    0% { background-position: 1rem 0; }
    100% { background-position: 0 0; }
}

/* Botón flotante elegante */
.refresh-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--white);
    border: none;
    border-radius: 50%;
    width: 64px;
    height: 64px;
    font-size: 1.5rem;
    box-shadow: var(--shadow-xl);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    cursor: pointer;
}

.refresh-btn:hover {
    transform: scale(1.15) rotate(180deg);
    box-shadow: 0 25px 50px -5px rgba(99, 102, 241, 0.4);
}

/* Títulos de sección */
.section-title {
    color: var(--gray-800);
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    letter-spacing: -0.025em;
}

.section-title i {
    color: var(--primary-color);
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.auto-refresh {
    font-size: 0.75rem;
    opacity: 0.7;
    margin-left: auto;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

/* Display de tokens */
.token-display {
    font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    border: 1px solid var(--gray-300);
    letter-spacing: 0.05em;
}

/* Formularios de edición */
.edit-form {
    background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-top: 0.75rem;
    border: 2px solid var(--primary-color);
    box-shadow: var(--shadow-md);
}

.edit-form .form-control, 
.edit-form .form-select {
    border-radius: 12px;
    border: 2px solid var(--gray-300);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    background: var(--white);
}

.edit-form .form-control:focus, 
.edit-form .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

/* Contenedor de filtros */
.filters-container {
    background: linear-gradient(135deg, var(--white) 0%, var(--gray-50) 100%);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
}

.filters-container .form-control,
.filters-container .form-select {
    border-radius: 12px;
    border: 2px solid var(--gray-300);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    background: var(--white);
}

.filters-container .form-control:focus,
.filters-container .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

.filters-container .form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

/* Badges personalizados */
.badge {
    font-weight: 600;
    padding: 0.5rem 0.75rem;
    border-radius: 25px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.bg-secondary {
    background: linear-gradient(135deg, var(--gray-600) 0%, var(--gray-700) 100%) !important;
}

/* Alertas elegantes */
.alert {
    border: none;
    border-radius: var(--border-radius);
    padding: 1rem 1.5rem;
    border-left: 4px solid;
    box-shadow: var(--shadow-md);
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
    border-left-color: var(--success-color);
    color: #065f46;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
    border-left-color: var(--danger-color);
    color: #991b1b;
}

/* Mejoras responsive */
@media (max-width: 768px) {
    .card {
        border-radius: var(--border-radius);
        margin-bottom: 1rem;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .stat-number {
        font-size: 2.5rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .refresh-btn {
        width: 56px;
        height: 56px;
        bottom: 1.5rem;
        right: 1.5rem;
    }
}

/* Animaciones suaves */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.6s ease-out;
}


/* Estilos para scroll personalizado - MÁS DELGADO */
::-webkit-scrollbar {
    width: 4px; /* Reducido de 8px a 4px */
    height: 4px; /* También para scroll horizontal */
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 2px; /* Más pequeño */
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 2px; /* Más pequeño */
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--secondary-color));
}

/* Estados de hover mejorados - MÁS SUTIL */
.table tbody tr:hover td {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.02) 0%, rgba(139, 92, 246, 0.02) 100%); /* Mucho más sutil */
}

/* Mejoras adicionales para elementos específicos - MÁS SUTIL */
.list-group-item {
    border: none;
    border-bottom: 1px solid var(--gray-200);
    padding: 1.25rem;
    transition: all 0.2s ease; /* Más rápida */
}

.list-group-item:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(139, 92, 246, 0.03) 100%); /* Más sutil */
    transform: translateX(2px); /* Reducido de 5px a 2px */
}

.list-group-item:last-child {
    border-bottom: none;
}
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            <i class="fas fa-crown me-2"></i>
            Panel Administrativo - Boda
        </span>
        <div class="d-flex align-items-center text-white">
            <span class="me-3">
                <i class="fas fa-clock me-1"></i>
                <span id="current-time"></span>
            </span>
            <button class="btn btn-outline-light btn-sm" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>
                Actualizar
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">
    
    <!-- Mensajes -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Estadísticas Principales -->
    <div class="row mb-4">
        <div class="col-xl-3 col-lg-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <span class="stat-number"><?php echo $stats['total_invitados']; ?></span>
                    <span class="stat-label">Total Invitados</span>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 mb-3">
            <div class="card stat-card success"> 
                <div class="card-body">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <span class="stat-number"><?php echo $stats['total_confirmados']; ?></span>
                    <span class="stat-label">Confirmados</span>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 mb-3">
            <div class="card stat-card info"> 
                <div class="card-body">
                    <i class="fas fa-user-friends fa-2x mb-2"></i>
                    <span class="stat-number"><?php echo $stats['total_personas_confirmadas']; ?></span>
                    <span class="stat-label">Personas Confirmadas</span>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 mb-3">
           <div class="card stat-card warning"> 
                <div class="card-body">
                    <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                    <span class="stat-number"><?php echo $stats['total_presentes']; ?></span>
                    <span class="stat-label">Ya Llegaron</span>
                </div>
            </div>
        </div>
    </div>

 <!-- Enlaces de navegación -->
<div class="container-fluid mt-4 mb-3">
    <div class="text-center">
        <a href="../../index.html" class="btn btn-outline-primary me-2">
            💌 Invitación
        </a>
        <a href="generador.php" class="btn btn-outline-primary me-2">
            👥 Agregar Invitados
        </a>
        <a href="envios.php" class="btn btn-outline-primary me-2">
            📱 Enviar Invitaciones
        </a>
        <a href="../scanner/control.php" class="btn btn-outline-primary me-2">
            🔍 Control de Acceso
        </a>
        <a href="../rsvp/confirmar.php" class="btn btn-outline-primary">
            ✅ Confirmar Asistencia
        </a>
    </div>
</div>
    
    <!-- Barra de Progreso -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-chart-line me-2"></i>
                        Progreso de Confirmaciones
                    </h5>
                    <?php 
                    $porcentaje = $stats['total_invitados'] > 0 ? 
                        round(($stats['total_confirmados'] / $stats['total_invitados']) * 100, 1) : 0;
                    ?>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $porcentaje; ?>%" 
                             aria-valuenow="<?php echo $porcentaje; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $porcentaje; ?>%
                        </div>
                    </div>
                    <p class="text-muted mb-0">
                        <?php echo $stats['total_confirmados']; ?> de <?php echo $stats['total_invitados']; ?> invitados han confirmado su asistencia
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- NUEVA SECCIÓN: Lista de Invitados Registrados -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-list me-2"></i>
                        Invitados Registrados
                        <span class="badge bg-light text-dark ms-2" id="total-invitados"><?php echo count($invitados); ?></span>
                        <span class="auto-refresh">Gestión completa de invitados</span>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Filtros de búsqueda -->
                    <div class="filters-container">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label small">Buscar por nombre</label>
                                <input type="text" id="search-nombre" class="form-control" 
                                       placeholder="Escribe el nombre..." onkeyup="filterTable()">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label small">Filtrar por mesa</label>
                                <select id="filter-mesa" class="form-select" onchange="filterTable()">
                                    <option value="">Todas las mesas</option>
                                    <?php 
                                    $mesasUnicas = array_unique(array_column($invitados, 'mesa'));
                                    sort($mesasUnicas);
                                    foreach ($mesasUnicas as $mesa): 
                                    ?>
                                        <option value="<?php echo $mesa; ?>">Mesa <?php echo $mesa; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label small">Filtrar por cupos</label>
                                <select id="filter-cupos" class="form-select" onchange="filterTable()">
                                    <option value="">Todos los cupos</option>
                                    <option value="1">1 cupo</option>
                                    <option value="2">2 cupos</option>
                                    <option value="3">3 cupos</option>
                                    <option value="4">4 cupos</option>
                                    <option value="5">5+ cupos</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label small">Filtrar por estado</label>
                                <select id="filter-estado" class="form-select" onchange="filterTable()">
                                    <option value="">Todos los estados</option>
                                    <option value="confirmado">Confirmados</option>
                                    <option value="sin-confirmar">Sin confirmar</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label small">Filtrar por tipo</label>
                                <select id="filter-tipo" class="form-select" onchange="filterTable()">
                                    <option value="">Todos los tipos</option>
                                    <option value="Familia">Familia</option>
                                    <option value="Amigo">Amigo</option>
                                    <option value="Trabajo">Trabajo</option>
                                    <option value="Padrino">Padrino</option>
                                    <option value="Especial">Especial</option>
                                </select>
                            </div>
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Mostrando <span id="filtered-count"><?php echo count($invitados); ?></span> de <?php echo count($invitados); ?> invitados
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover mb-0" id="invitados-table">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Mesa</th>
                                    <th>Cupos</th>
                                    <th>Token</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="invitados-tbody">
                                <?php foreach ($invitados as $invitado): ?>
                                <tr id="invitado-<?php echo $invitado['id_invitado']; ?>" 
                                    data-nombre="<?php echo strtolower(htmlspecialchars($invitado['nombre_completo'])); ?>"
                                    data-mesa="<?php echo $invitado['mesa']; ?>"
                                    data-cupos="<?php echo $invitado['cupos_disponibles']; ?>"
                                    data-estado="<?php echo $invitado['fecha_confirmacion'] ? 'confirmado' : 'sin-confirmar'; ?>"
                                    data-tipo="<?php echo htmlspecialchars($invitado['tipo_invitado']); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($invitado['nombre_completo']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($invitado['tipo_invitado']); ?>
                                            <?php if ($invitado['telefono']): ?>
                                                • <?php echo htmlspecialchars($invitado['telefono']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">Mesa <?php echo $invitado['mesa']; ?></span>
                                    </td>
                                    <td><?php echo $invitado['cupos_disponibles']; ?></td>
                                    <td>
                                        <span class="token-display"><?php echo $invitado['token']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($invitado['fecha_confirmacion']): ?>
                                            <span class="status-badge status-confirmado">
                                                Confirmado (<?php echo $invitado['cantidad_confirmada']; ?>)
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($invitado['fecha_confirmacion'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="status-badge status-pendiente">
                                                Sin confirmar
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="toggleEdit(<?php echo $invitado['id_invitado']; ?>)" 
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Estás seguro de eliminar este invitado?')">
                                                <input type="hidden" name="id_invitado" value="<?php echo $invitado['id_invitado']; ?>">
                                                <button type="submit" name="delete_invitado" 
                                                        class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Fila de edición (oculta por defecto) -->
                                <tr id="edit-<?php echo $invitado['id_invitado']; ?>" style="display: none;">
                                    <td colspan="6">
                                        <form method="POST" class="edit-form">
                                            <input type="hidden" name="id_invitado" value="<?php echo $invitado['id_invitado']; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-2 mb-2">
                                                    <label class="form-label small">Nombre Completo</label>
                                                    <input type="text" name="nombre_completo" class="form-control" 
                                                           value="<?php echo htmlspecialchars($invitado['nombre_completo']); ?>" required>
                                                </div>
                                                
                                                <div class="col-md-2 mb-2">
                                                    <label class="form-label small">Teléfono</label>
                                                    <input type="text" name="telefono" class="form-control" 
                                                           value="<?php echo htmlspecialchars($invitado['telefono']); ?>">
                                                </div>
                                                
                                                <div class="col-md-1 mb-2">
                                                    <label class="form-label small">Cupos</label>
                                                    <select name="cupos_disponibles" class="form-select" required 
                                                            onchange="updateMaxConfirmados(<?php echo $invitado['id_invitado']; ?>, this.value)">
                                                        <?php for($i = 1; $i <= 10; $i++): ?>
                                                            <option value="<?php echo $i; ?>" 
                                                                    <?php echo $invitado['cupos_disponibles'] == $i ? 'selected' : ''; ?>>
                                                                <?php echo $i; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-1 mb-2">
                                                    <label class="form-label small">Mesa</label>
                                                    <input type="number" name="mesa" class="form-control" min="1" max="50"
                                                           value="<?php echo $invitado['mesa']; ?>" required>
                                                </div>
                                                
                                                <div class="col-md-2 mb-2">
                                                    <label class="form-label small">Tipo</label>
                                                    <select name="tipo_invitado" class="form-select">
                                                        <option value="Familia" <?php echo $invitado['tipo_invitado'] == 'Familia' ? 'selected' : ''; ?>>Familia</option>
                                                        <option value="Amigo" <?php echo $invitado['tipo_invitado'] == 'Amigo' ? 'selected' : ''; ?>>Amigo</option>
                                                        <option value="Trabajo" <?php echo $invitado['tipo_invitado'] == 'Trabajo' ? 'selected' : ''; ?>>Trabajo</option>
                                                        <option value="Padrino" <?php echo $invitado['tipo_invitado'] == 'Padrino' ? 'selected' : ''; ?>>Padrino</option>
                                                        <option value="Especial" <?php echo $invitado['tipo_invitado'] == 'Especial' ? 'selected' : ''; ?>>Especial</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-2 mb-2">
                                                    <label class="form-label small">
                                                        Confirmados 
                                                        <i class="fas fa-info-circle text-info" 
                                                           title="Cantidad de personas que realmente asistirán"></i>
                                                    </label>
                                                    <select name="cantidad_confirmada" class="form-select" 
                                                            id="cantidad_confirmada_<?php echo $invitado['id_invitado']; ?>">
                                                        <option value="0" <?php echo (!$invitado['fecha_confirmacion'] || $invitado['cantidad_confirmada'] == 0) ? 'selected' : ''; ?>>
                                                            Sin confirmar
                                                        </option>
                                                        <?php 
                                                        $max_cupos = $invitado['cupos_disponibles'];
                                                        for($i = 1; $i <= $max_cupos; $i++): 
                                                        ?>
                                                            <option value="<?php echo $i; ?>" 
                                                                    <?php echo ($invitado['cantidad_confirmada'] == $i) ? 'selected' : ''; ?>>
                                                                <?php echo $i; ?> persona<?php echo $i > 1 ? 's' : ''; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <small class="text-muted">Máximo: <?php echo $invitado['cupos_disponibles']; ?> cupos</small>
                                                </div>
                                                
                                                <div class="col-md-2 mb-2 d-flex align-items-end">
                                                    <div class="d-flex gap-1 w-100">
                                                        <button type="submit" name="edit_invitado" class="btn btn-success btn-sm"
                                                                onclick="setTimeout(() => { location.reload(); }, 1000);">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                                onclick="cancelEdit(<?php echo $invitado['id_invitado']; ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Total: <span id="total-count-footer"><?php echo count($invitados); ?></span> invitados registrados
                        </small>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Ordenados alfabéticamente (A-Z). Usa los filtros para buscar invitados específicos.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dos columnas principales -->
    <div class="row">
        
        <!-- Columna izquierda -->
        <div class="col-lg-8">
            
            <!-- Confirmaciones Recientes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-clock"></i>
                        Confirmaciones Recientes
                        <span class="auto-refresh">Actualización automática cada 30s</span>
                    </h5>
                    <small class="text-white-50 d-block mt-1">
                        <i class="fas fa-info-circle me-1"></i>
                        Invitados que han confirmado su asistencia. "Estado Físico" indica si ya llegaron al evento.
                    </small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="mesas-table">
                            <thead>
                                <tr>
                                    <th>Invitado</th>
                                    <th>Mesa</th>
                                    <th>Personas</th>
                                    <th>Estado Físico</th>
                                    <th>Confirmado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentConfirmations as $conf): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($conf['nombre_completo']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">Mesa <?php echo $conf['mesa']; ?></span>
                                    </td>
                                    <td><?php echo $conf['cantidad_confirmada']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $conf['status'] == 'Presente' ? 'status-presente' : 'status-pendiente'; ?>">
                                            <?php echo $conf['status'] == 'Presente' ? 'Ya llegó' : 'Por llegar'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($conf['fecha_confirmacion'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Distribución por Mesas -->
            <div class="card">
                <div class="card-header">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-table"></i>
                        Distribución por Mesas
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Mesa</th>
                                    <th>Total Invitados</th>
                                    <th>Confirmados</th>
                                    <th>Personas</th>
                                    <th>Presentes</th>
                                    <th>Progreso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mesas as $mesa): ?>
                                <?php 
                                $progreso = $mesa['total_invitados'] > 0 ? 
                                    round(($mesa['confirmados'] / $mesa['total_invitados']) * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong>Mesa <?php echo $mesa['mesa']; ?></strong></td>
                                    <td><?php echo $mesa['total_invitados']; ?></td>
                                    <td><?php echo $mesa['confirmados']; ?></td>
                                    <td><?php echo $mesa['personas_confirmadas']; ?></td>
                                    <td><?php echo $mesa['presentes']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar" style="width: <?php echo $progreso; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $progreso; ?>%</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Columna derecha -->
        <div class="col-lg-4">
            
            <!-- Gráfica de Estado -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-chart-pie"></i>
                        Estado General
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" width="300" height="300"></canvas>
                </div>
            </div>
            
            <!-- Pendientes de Confirmar -->
            <div class="card">
                <div class="card-header">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        Sin Confirmar
                        <span class="badge bg-danger ms-2"><?php echo count($pendientes); ?></span>
                    </h5>
                </div>
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($pendientes)): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">¡Todos han confirmado!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($pendientes as $pendiente): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($pendiente['nombre_completo']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Mesa <?php echo $pendiente['mesa']; ?> • 
                                            <?php echo $pendiente['cupos_disponibles']; ?> cupos
                                        </small>
                                        <br>
                                        <small class="text-primary">
                                            Token: <?php echo $pendiente['token']; ?>
                                        </small>
                                    </div>
                                    <span class="status-badge status-sin-confirmar">
                                        Pendiente
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
    
</div>

<!-- Botón de actualización flotante -->
<button class="refresh-btn" onclick="location.reload()" title="Actualizar datos">
    <i class="fas fa-sync-alt"></i>
</button>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
// Actualizar hora actual
function updateTime() {
    const now = new Date();
    document.getElementById('current-time').textContent = now.toLocaleTimeString('es-ES');
}
updateTime();
setInterval(updateTime, 1000);

// Gráfica de estado
const ctx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Confirmados', 'Presentes', 'Sin Confirmar'],
        datasets: [{
            data: [
                <?php echo $stats['total_confirmados']; ?>,
                <?php echo $stats['total_presentes']; ?>,
                <?php echo $stats['total_invitados'] - $stats['total_confirmados']; ?>
            ],
            backgroundColor: [
                '#28a745',
                '#ffc107', 
                '#dc3545'
            ],
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            }
        }
    }
});



// Animación de números
document.addEventListener('DOMContentLoaded', function() {
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(element => {
        const finalValue = parseInt(element.textContent);
        let currentValue = 0;
        const increment = finalValue / 30;
        
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                currentValue = finalValue;
                clearInterval(timer);
            }
            element.textContent = Math.floor(currentValue);
        }, 50);
    });
});

// Funciones para editar invitados
function toggleEdit(invitadoId) {
    const editRow = document.getElementById('edit-' + invitadoId);
    const isVisible = editRow.style.display !== 'none';
    
    // Cerrar todas las filas de edición
    document.querySelectorAll('[id^="edit-"]').forEach(row => {
        row.style.display = 'none';
    });
    
    // Si no estaba visible, mostrar esta
    if (!isVisible) {
        editRow.style.display = 'table-row';
        
        // Hacer scroll hasta el elemento
        editRow.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
    }
}

function cancelEdit(invitadoId) {
    document.getElementById('edit-' + invitadoId).style.display = 'none';
}

// Función para actualizar el máximo de confirmados según los cupos
function updateMaxConfirmados(invitadoId, nuevoCupos) {
    const selectConfirmados = document.getElementById('cantidad_confirmada_' + invitadoId);
    const valorActual = selectConfirmados.value;
    
    // Limpiar opciones existentes
    selectConfirmados.innerHTML = '';
    
    // Agregar opción "Sin confirmar"
    const optionSinConfirmar = document.createElement('option');
    optionSinConfirmar.value = '0';
    optionSinConfirmar.textContent = 'Sin confirmar';
    if (valorActual == '0') optionSinConfirmar.selected = true;
    selectConfirmados.appendChild(optionSinConfirmar);
    
    // Agregar opciones según los nuevos cupos
    for (let i = 1; i <= nuevoCupos; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i + (i > 1 ? ' personas' : ' persona');
        
        // Mantener selección si es válida
        if (valorActual == i) {
            option.selected = true;
        }
        
        selectConfirmados.appendChild(option);
    }
    
    // Si el valor actual es mayor que los nuevos cupos, seleccionar el máximo
    if (parseInt(valorActual) > parseInt(nuevoCupos)) {
        selectConfirmados.value = nuevoCupos;
    }
    
    // Actualizar el texto de ayuda
    const smallText = selectConfirmados.nextElementSibling;
    if (smallText && smallText.classList.contains('text-muted')) {
        smallText.textContent = 'Máximo: ' + nuevoCupos + ' cupos';
    }
}

// Función para filtrar la tabla
function filterTable() {
    const searchNombre = document.getElementById('search-nombre').value.toLowerCase();
    const filterMesa = document.getElementById('filter-mesa').value;
    const filterCupos = document.getElementById('filter-cupos').value;
    const filterEstado = document.getElementById('filter-estado').value;
    const filterTipo = document.getElementById('filter-tipo').value;
    
    const rows = document.querySelectorAll('#invitados-tbody tr:not([id^="edit-"])');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const nombre = row.getAttribute('data-nombre') || '';
        const mesa = row.getAttribute('data-mesa') || '';
        const cupos = row.getAttribute('data-cupos') || '';
        const estado = row.getAttribute('data-estado') || '';
        const tipo = row.getAttribute('data-tipo') || '';
        
        let shouldShow = true;
        
        // Filtro por nombre
        if (searchNombre && !nombre.includes(searchNombre)) {
            shouldShow = false;
        }
        
        // Filtro por mesa
        if (filterMesa && mesa !== filterMesa) {
            shouldShow = false;
        }
        
        // Filtro por cupos
        if (filterCupos) {
            if (filterCupos === '5' && parseInt(cupos) < 5) {
                shouldShow = false;
            } else if (filterCupos !== '5' && cupos !== filterCupos) {
                shouldShow = false;
            }
        }
        
        // Filtro por estado
        if (filterEstado && estado !== filterEstado) {
            shouldShow = false;
        }
        
        // Filtro por tipo
        if (filterTipo && tipo !== filterTipo) {
            shouldShow = false;
        }
        
        // Mostrar/ocultar fila
        if (shouldShow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
            // También ocultar fila de edición si existe
            const editRow = document.getElementById('edit-' + row.id.split('-')[1]);
            if (editRow) {
                editRow.style.display = 'none';
            }
        }
    });
    
    // Actualizar contadores
    document.getElementById('filtered-count').textContent = visibleCount;
}

// Función para limpiar filtros
function clearFilters() {
    document.getElementById('search-nombre').value = '';
    document.getElementById('filter-mesa').value = '';
    document.getElementById('filter-cupos').value = '';
    document.getElementById('filter-estado').value = '';
    document.getElementById('filter-tipo').value = '';
    
    filterTable();
}

// Función para actualizar datos dinámicamente después de editar
function updateTableData() {
    // Realizar petición AJAX para obtener datos actualizados
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            // Crear un documento temporal para extraer los datos
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Actualizar estadísticas principales
            const newStats = tempDiv.querySelectorAll('.stat-number');
            const currentStats = document.querySelectorAll('.stat-number');
            
            newStats.forEach((stat, index) => {
                if (currentStats[index]) {
                    currentStats[index].textContent = stat.textContent;
                }
            });
            
            // Actualizar tabla de invitados
            const newTable = tempDiv.querySelector('#invitados-table tbody');
            const currentTable = document.querySelector('#invitados-table tbody');
            
            if (newTable && currentTable) {
                currentTable.innerHTML = newTable.innerHTML;
            }
            
            // Actualizar tabla de mesas
            const newMesasTable = tempDiv.querySelector('#mesas-table tbody');
            const currentMesasTable = document.querySelector('#mesas-table tbody');
            
            if (newMesasTable && currentMesasTable) {
                currentMesasTable.innerHTML = newMesasTable.innerHTML;
            }
            
            // Actualizar barra de progreso
            const newProgress = tempDiv.querySelector('.progress-bar');
            const currentProgress = document.querySelector('.progress-bar');
            
            if (newProgress && currentProgress) {
                currentProgress.style.width = newProgress.style.width;
                currentProgress.textContent = newProgress.textContent;
            }
        })
        .catch(error => {
            console.error('Error actualizando datos:', error);
        });
}
</script>

</body>
</html>