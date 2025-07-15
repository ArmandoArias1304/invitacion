<?php
require_once 'auth_check.php';

// Obtener estadísticas
$db = getDB();
$conn = $db->getConnection();

// Estadísticas generales
$stats = [];

// Total de invitados
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados");
$stmt->execute();
$stats['total_invitados'] = $stmt->fetchColumn();

// Total confirmados
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM confirmaciones");
$stmt->execute();
$stats['total_confirmados'] = $stmt->fetchColumn();

// Total de cupos disponibles
$stmt = $conn->prepare("SELECT SUM(cupos_disponibles) as total FROM invitados");
$stmt->execute();
$stats['total_cupos'] = $stmt->fetchColumn();

// Total de cupos confirmados
$stmt = $conn->prepare("SELECT SUM(cantidad_confirmada) as total FROM confirmaciones");
$stmt->execute();
$stats['total_cupos_confirmados'] = $stmt->fetchColumn() ?: 0;

// Confirmaciones por día (últimos 7 días)
$stmt = $conn->prepare("
    SELECT DATE(fecha_confirmacion) as fecha, COUNT(*) as cantidad
    FROM confirmaciones 
    WHERE fecha_confirmacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_confirmacion)
    ORDER BY fecha DESC
");
$stmt->execute();
$confirmaciones_diarias = $stmt->fetchAll();

// TODOS LOS INVITADOS (modificado para mostrar todos y ordenar por nombre)
$stmt = $conn->prepare("
    SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion
    FROM invitados i
    LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
    ORDER BY i.nombre_completo ASC
");
$stmt->execute();
$todos_invitados = $stmt->fetchAll();

// Distribución por mesas
$stmt = $conn->prepare("
    SELECT 
        COALESCE(i.mesa, 'Sin asignar') as mesa,
        COUNT(i.id_invitado) as total_invitados,
        SUM(i.cupos_disponibles) as total_cupos,
        COUNT(c.id_confirmacion) as confirmados,
        SUM(COALESCE(c.cantidad_confirmada, 0)) as cupos_confirmados
    FROM invitados i
    LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
    GROUP BY COALESCE(i.mesa, 'Sin asignar')
    ORDER BY 
        CASE 
            WHEN i.mesa IS NULL THEN 1 
            ELSE 0 
        END,
        CAST(i.mesa AS UNSIGNED) ASC
");
$stmt->execute();
$distribucion_mesas = $stmt->fetchAll();

// Manejar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'eliminar_invitado':
            $id_invitado = (int)$_POST['id_invitado'];
            try {
                $stmt = $conn->prepare("DELETE FROM invitados WHERE id_invitado = ?");
                $result = $stmt->execute([$id_invitado]);
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'actualizar_invitado':
            $id_invitado = (int)$_POST['id_invitado'];
            $nombre_completo = trim($_POST['nombre_completo']);
            $telefono = trim($_POST['telefono']);
            $mesa = trim($_POST['mesa']);
            $cupos_disponibles = (int)$_POST['cupos_disponibles'];
            $tipo_invitado = trim($_POST['tipo_invitado']);
            $cantidad_confirmada = isset($_POST['cantidad_confirmada']) ? (int)$_POST['cantidad_confirmada'] : 0;
            
            try {
                // Validar que los cupos confirmados no excedan los disponibles
                if ($cantidad_confirmada > $cupos_disponibles) {
                    echo json_encode(['success' => false, 'error' => 'Los cupos confirmados no pueden exceder los cupos disponibles']);
                    exit;
                }
                
                // Actualizar tabla de invitados
                $stmt = $conn->prepare("
                    UPDATE invitados 
                    SET nombre_completo = ?, telefono = ?, mesa = ?, cupos_disponibles = ?, tipo_invitado = ?
                    WHERE id_invitado = ?
                ");
                $result = $stmt->execute([$nombre_completo, $telefono, $mesa, $cupos_disponibles, $tipo_invitado, $id_invitado]);
                
                if ($result) {
                    // Manejar confirmaciones
                    if ($cantidad_confirmada > 0) {
                        // Verificar si ya existe una confirmación
                        $stmt = $conn->prepare("SELECT id_confirmacion FROM confirmaciones WHERE id_invitado = ?");
                        $stmt->execute([$id_invitado]);
                        $confirmacion_existente = $stmt->fetch();
                        
                        if ($confirmacion_existente) {
                            // Actualizar confirmación existente
                            $stmt = $conn->prepare("
                                UPDATE confirmaciones 
                                SET cantidad_confirmada = ?, fecha_confirmacion = NOW()
                                WHERE id_invitado = ?
                            ");
                            $stmt->execute([$cantidad_confirmada, $id_invitado]);
                        } else {
                            // Crear nueva confirmación
                            $stmt = $conn->prepare("
                                INSERT INTO confirmaciones (id_invitado, cantidad_confirmada, fecha_confirmacion)
                                VALUES (?, ?, NOW())
                            ");
                            $stmt->execute([$id_invitado, $cantidad_confirmada]);
                        }
                    } else {
                        // Si cantidad_confirmada es 0, eliminar la confirmación si existe
                        $stmt = $conn->prepare("DELETE FROM confirmaciones WHERE id_invitado = ?");
                        $stmt->execute([$id_invitado]);
                    }
                }
                
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        
        // NUEVO: Confirmar invitado
        case 'confirmar_invitado':
            $id_invitado = (int)$_POST['id_invitado'];
            $cantidad_confirmada = (int)$_POST['cantidad_confirmada'];
            
            try {
                // Usar la función confirmarAsistencia de la clase Database que genera el QR automáticamente
                $db = getDB();
                $result = $db->confirmarAsistencia($id_invitado, $cantidad_confirmada, 'Confirmado desde dashboard');
                
                if ($result) {
                    // Obtener estadísticas actualizadas
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM confirmaciones");
                    $stmt->execute();
                    $total_confirmados = $stmt->fetchColumn();
                    
                    $stmt = $conn->prepare("SELECT SUM(cantidad_confirmada) as total FROM confirmaciones");
                    $stmt->execute();
                    $total_cupos_confirmados = $stmt->fetchColumn() ?: 0;
                    
                    echo json_encode([
                        'success' => true,
                        'stats' => [
                            'total_confirmados' => $total_confirmados,
                            'total_cupos_confirmados' => $total_cupos_confirmados
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No se pudo guardar la confirmación']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'toggle_estado':
            // Implementar si tienes campo de estado activo/inactivo
            break;
    }
    
}

// Manejar peticiones AJAX para actualización automática
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener_invitados') {
    header('Content-Type: application/json');
    
    try {
        // Obtener invitados actualizados (ordenados por nombre)
        $stmt = $conn->prepare("
            SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion
            FROM invitados i
            LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
            ORDER BY i.nombre_completo ASC
        ");
        $stmt->execute();
        $invitados_actualizados = $stmt->fetchAll();
        
        // Obtener estadísticas actualizadas
        $stats_actualizadas = [];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados");
        $stmt->execute();
        $stats_actualizadas['total_invitados'] = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM confirmaciones");
        $stmt->execute();
        $stats_actualizadas['total_confirmados'] = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT SUM(cupos_disponibles) as total FROM invitados");
        $stmt->execute();
        $stats_actualizadas['total_cupos'] = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT SUM(cantidad_confirmada) as total FROM confirmaciones");
        $stmt->execute();
        $stats_actualizadas['total_cupos_confirmados'] = $stmt->fetchColumn() ?: 0;
        
        // Obtener distribución por mesas
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(i.mesa, 'Sin asignar') as mesa,
                COUNT(i.id_invitado) as total_invitados,
                SUM(i.cupos_disponibles) as total_cupos,
                COUNT(c.id_confirmacion) as confirmados,
                SUM(COALESCE(c.cantidad_confirmada, 0)) as cupos_confirmados
            FROM invitados i
            LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
            GROUP BY COALESCE(i.mesa, 'Sin asignar')
            ORDER BY 
                CASE 
                    WHEN i.mesa IS NULL THEN 1 
                    ELSE 0 
                END,
                CAST(i.mesa AS UNSIGNED) ASC
        ");
        $stmt->execute();
        $distribucion_mesas = $stmt->fetchAll();
        
        // Obtener confirmaciones recientes (últimos 7 días)
        $stmt = $conn->prepare("
            SELECT DATE(fecha_confirmacion) as fecha, COUNT(*) as cantidad
            FROM confirmaciones 
            WHERE fecha_confirmacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(fecha_confirmacion)
            ORDER BY fecha DESC
        ");
        $stmt->execute();
        $confirmaciones_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Formatear fecha para JS
        foreach ($confirmaciones_recientes as &$conf) {
            $conf['fecha'] = date('d/m/Y', strtotime($conf['fecha']));
        }
        
        echo json_encode([
            'success' => true,
            'invitados' => $invitados_actualizados,
            'stats' => $stats_actualizadas,
            'mesas' => $distribucion_mesas,
            'confirmaciones_recientes' => $confirmaciones_recientes
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Manejar peticiones AJAX para actualización parcial de invitado y stats
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['action']) &&
    $_GET['action'] === 'obtener_invitado_y_stats' &&
    isset($_GET['id'])
) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        // Obtener datos del invitado
        $stmt = $conn->prepare("SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion FROM invitados i LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado WHERE i.id_invitado = ?");
        $stmt->execute([$id]);
        $invitado = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invitado) throw new Exception('Invitado no encontrado');

        // Obtener estadísticas actualizadas
        $stats = [];
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados");
        $stmt->execute();
        $stats['total_invitados'] = $stmt->fetchColumn();
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM confirmaciones");
        $stmt->execute();
        $stats['total_confirmados'] = $stmt->fetchColumn();
        $stmt = $conn->prepare("SELECT SUM(cupos_disponibles) as total FROM invitados");
        $stmt->execute();
        $stats['total_cupos'] = $stmt->fetchColumn();
        $stmt = $conn->prepare("SELECT SUM(cantidad_confirmada) as total FROM confirmaciones");
        $stmt->execute();
        $stats['total_cupos_confirmados'] = $stmt->fetchColumn() ?: 0;

        // Buscar la mesa del invitado
        $mesa = $invitado['mesa'] ?? null;
        $mesaDatos = null;
        if ($mesa) {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(i.mesa, 'Sin asignar') as mesa,
                    COUNT(i.id_invitado) as total_invitados,
                    SUM(i.cupos_disponibles) as total_cupos,
                    COUNT(c.id_confirmacion) as confirmados,
                    SUM(COALESCE(c.cantidad_confirmada, 0)) as cupos_confirmados
                FROM invitados i
                LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
                WHERE COALESCE(i.mesa, 'Sin asignar') = ?
                GROUP BY COALESCE(i.mesa, 'Sin asignar')
            ");
            $stmt->execute([$mesa]);
            $mesaDatos = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Obtener confirmaciones recientes (últimos 7 días)
        $stmt = $conn->prepare("
            SELECT DATE(fecha_confirmacion) as fecha, COUNT(*) as cantidad
            FROM confirmaciones 
            WHERE fecha_confirmacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(fecha_confirmacion)
            ORDER BY fecha DESC
        ");
        $stmt->execute();
        $confirmaciones_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Formatear fecha para JS
        foreach ($confirmaciones_recientes as &$conf) {
            $conf['fecha'] = date('d/m/Y', strtotime($conf['fecha']));
        }

        // Preparar datos para JS
        $datosInvitado = [
            'cantidad_confirmada' => $invitado['cantidad_confirmada'],
            'estado' => $invitado['cantidad_confirmada'] > 0 ? 'confirmado' : 'pendiente',
            'fecha_confirmacion' => $invitado['fecha_confirmacion'],
            'stats' => $stats,
            'mesa' => $mesa,
            'mesaDatos' => $mesaDatos
        ];
        echo json_encode([
            'success' => true,
            'invitado' => $datosInvitado,
            'stats' => $stats,
            'mesa' => $mesa,
            'mesaDatos' => $mesaDatos,
            'confirmaciones_recientes' => $confirmaciones_recientes
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FastInvite</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-gray: #f8fafc;
            --dark-gray: #64748b;
            --text-dark: #1e293b;
            --border-color: #e2e8f0;
            --sidebar-width: 280px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            left: -280px; /* Cambiado para que se oculte completamente */
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            backdrop-filter: blur(10px);
            z-index: 1050;
            transition: all 0.2s ease; /* Transición más rápida y suave */
            box-shadow: 4px 0 20px rgba(99, 102, 241, 0.2);
            border-radius: 0 12px 12px 0; /* Bordes redondeados solo a la derecha */
        }

        .sidebar.show {
            left: 0;
        }

        /* Barra de iconos visible siempre */
        .sidebar-icons {
            position: fixed;
            left: 0;
            top: 0;
            width: 60px;
            height: 100vh;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.95), rgba(139, 92, 246, 0.95));
            backdrop-filter: blur(10px);
            z-index: 1045;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 140px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transition: all 0.15s ease; /* Transición más rápida */
            border-radius: 0 12px 12px 0; /* Bordes redondeados solo a la derecha */
        }

        .sidebar-icons.hide {
            left: -60px;
        }

        .sidebar-icon-item {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0.5rem 0;
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.9); /* Más opaco */
            background: rgba(255, 255, 255, 0.15); /* Más visible */
            cursor: pointer;
            transition: all 0.15s ease; /* Transición más rápida */
            text-decoration: none;
            font-size: 1.2rem;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1); /* Borde sutil */
        }

        .sidebar-icon-item:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            transform: scale(1.05); /* Menos zoom */
            border-color: rgba(255, 255, 255, 0.3);
        }

        .sidebar-icon-item.active {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
        }

        /* Tooltip para los iconos */
        .sidebar-icon-item::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 60px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1060;
        }

        .sidebar-icon-item:hover::after {
            opacity: 1;
            visibility: visible;
        }

        .sidebar-trigger {
            position: fixed;
            left: 0;
            top: 0;
            width: 60px;
            height: 100vh;
            z-index: 1040;
            background: transparent;
            cursor: pointer;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-title {
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .sidebar-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav-item {
            display: block;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            height: 64px; /* Altura fija para coincidir con iconos */
            display: flex;
            align-items: center;
        }

        .sidebar-nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
            transform: translateX(5px);
        }

        .sidebar-nav-item i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(2px);
            z-index: 1030;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* ===== HEADER ===== */
        .main-header {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1020;
            margin-left: 60px; /* Espacio para la barra de iconos */
            transition: all 0.2s ease; /* Transición más rápida */
        }

        .main-header.sidebar-open {
            margin-left: 280px; /* Cuando el sidebar está abierto */
        }

        .header-content {
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-logo {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .header-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .header-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
        }

        .dashboard-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 500;
            color: var(--text-dark);
            margin: 0;
        }

        .user-role {
            font-size: 0.875rem;
            color: var(--dark-gray);
        }

        .logout-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 60px; /* Espacio para la barra de iconos */
            padding: 2rem;
            min-height: calc(100vh - 80px);
            transition: all 0.2s ease; /* Transición más rápida */
        }

        .main-content.sidebar-open {
            margin-left: 280px; /* Cuando el sidebar está abierto */
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.users { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-icon.confirmed { background: linear-gradient(135deg, var(--success-color), #059669); }
        .stat-icon.seats { background: linear-gradient(135deg, var(--warning-color), #d97706); }
        .stat-icon.confirmed-seats { background: linear-gradient(135deg, #10b981, #047857); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .stat-label {
            color: var(--dark-gray);
            font-weight: 500;
            font-size: 0.875rem;
            margin: 0;
        }

        /* ===== CARDS ===== */
        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .card-header-custom {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--light-gray);
            border-radius: 1rem 1rem 0 0;
        }

        .card-header-custom h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .card-body-custom {
            padding: 2rem;
        }

        /* ===== BUTTONS ===== */
        .btn-custom {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-custom {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        .btn-success-custom {
            background: var(--success-color);
            color: white;
        }

        .btn-success-custom:hover {
            background: #059669;
            color: white;
            transform: translateY(-1px);
        }

        .btn-danger-custom {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger-custom:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-1px);
        }

        .btn-sm-custom {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        /* ===== TABLE ===== */
        .table-responsive-custom {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-height: 600px; /* Altura máxima de la tabla */
            overflow-y: auto; /* Scroll vertical cuando sea necesario */
        }

        .table-custom {
            margin: 0;
        }

        .table-custom th {
            background: var(--light-gray);
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky; /* Header fijo */
            top: 0;
            z-index: 10;
        }

        .table-custom td {
            padding: 1rem;
            border-color: var(--border-color);
            vertical-align: middle;
        }

        .table-custom tbody tr:hover {
            background: var(--light-gray);
        }

        /* ===== FORMULARIO DE EDICIÓN INLINE ===== */
        .edit-form-row {
            background: var(--light-gray);
            border-left: 4px solid var(--primary-color);
        }

        .edit-form-container {
            padding: 1.5rem;
            background: white;
            border-radius: 0.5rem;
            margin: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .edit-form-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .edit-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .edit-form-group {
            display: flex;
            flex-direction: column;
        }

        .edit-form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .edit-form-input {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: var(--transition);
            background: white;
        }

        .edit-form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .edit-form-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn-save {
            background: var(--success-color);
            color: white;
        }

        .btn-save:hover {
            background: #059669;
            color: white;
        }

        .btn-cancel {
            background: var(--dark-gray);
            color: white;
        }

        .btn-cancel:hover {
            background: var(--text-dark);
            color: white;
        }

        /* Personalización del scrollbar */
        .table-responsive-custom::-webkit-scrollbar {
            width: 8px;
        }

        .table-responsive-custom::-webkit-scrollbar-track {
            background: var(--light-gray);
            border-radius: 4px;
        }

        .table-responsive-custom::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        .table-responsive-custom::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* ===== BADGES ===== */
        .badge-custom {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success-custom {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .badge-warning-custom {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .badge-info-custom {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .badge-danger-custom {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        /* ===== PROGRESS BAR ===== */
        .progress-custom {
            height: 8px;
            border-radius: 4px;
            background: var(--border-color);
            overflow: hidden;
        }

        .progress-bar-custom {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .progress-success { background: linear-gradient(90deg, var(--success-color), #059669); }
        .progress-warning { background: linear-gradient(90deg, var(--warning-color), #d97706); }
        .progress-danger { background: linear-gradient(90deg, var(--danger-color), #dc2626); }

        /* ===== TOKEN CODE ===== */
        .token-code {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: var(--light-gray);
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.8rem;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        .copy-btn {
            background: var(--dark-gray);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
            margin-left: 0.5rem;
            transition: var(--transition);
        }

        .copy-btn:hover {
            background: var(--text-dark);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .main-header {
                margin-left: 0;
            }

            .header-content {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                position: relative;
            }

            .header-center {
                position: static;
                transform: none;
            }

            .dashboard-title {
                font-size: 1.25rem;
            }

            .user-section {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .card-header-custom,
            .card-body-custom {
                padding: 1rem;
            }

            .table-custom th,
            .table-custom td {
                padding: 0.75rem 0.5rem;
                font-size: 0.875rem;
            }

            .sidebar {
                width: 100vw;
                left: -100vw;
            }

            .sidebar.show {
                left: 0;
            }

            .sidebar-icons {
                width: 50px;
                padding-top: 100px; /* Menos padding en móviles */
            }

            .sidebar-icon-item {
                width: 36px;
                height: 36px;
                font-size: 1rem;
                margin: 0.25rem 0;
            }

            .sidebar-trigger {
                width: 50px;
            }
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        /* ===== UPDATE INDICATORS ===== */
        .update-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success-color);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: none;
            z-index: 1060;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        @keyframes slideInRight {
            from { 
                transform: translateX(100%); 
                opacity: 0;
            }
            to { 
                transform: translateX(0); 
                opacity: 1;
            }
        }
        
        /* Animación de rotación para el botón refrescar */
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* ===== MODAL DE CONFIRMACIÓN ===== */
        .confirmation-modal .modal-header {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            border-radius: 1rem 1rem 0 0;
            border-bottom: none;
        }

        .confirmation-info {
            background: var(--light-gray);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .confirmation-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            justify-content: center;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .quantity-display {
            background: var(--primary-color);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .edit-form-group small {
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: var(--dark-gray);
        }

        .edit-form-input:invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
        }

        .search-filters-section {
            background: var(--light-gray);
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }
        .search-filters-section .form-label {
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        .search-filters-section .form-select-sm,
        .search-filters-section .form-control {
            border-color: var(--border-color);
        }
        .search-filters-section .form-select-sm:focus,
        .search-filters-section .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
        }
        .search-filters-section .input-group-text {
            background: white;
            border-color: var(--border-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar Icons (always visible) -->
    <div class="sidebar-icons" id="sidebarIcons">
        <a href="dashboard.php" class="sidebar-icon-item active" data-tooltip="Dashboard">
            <i class="bi bi-house"></i>
        </a>
        <a href="generador.php" class="sidebar-icon-item" data-tooltip="Generar Invitados">
            <i class="bi bi-person-plus"></i>
        </a>
        <a href="envios.php" class="sidebar-icon-item" data-tooltip="Enviar Invitaciones">
            <i class="bi bi-whatsapp"></i>
        </a>
        <a href="estadisticas.php" class="sidebar-icon-item" data-tooltip="Ver Estadísticas">
            <i class="bi bi-graph-up"></i>
        </a>
    </div>

    <!-- Sidebar Trigger (área invisible para hover) -->
    <div class="sidebar-trigger" id="sidebarTrigger"></div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4 class="sidebar-title">🎉 Panel de Control</h4>
            <p class="sidebar-subtitle">Sistema de Invitaciones</p>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-nav-item">
                <i class="bi bi-house"></i>
                Dashboard
            </a>
            <a href="generador.php" class="sidebar-nav-item">
                <i class="bi bi-person-plus"></i>
                Generar Invitados
            </a>
            <a href="envios.php" class="sidebar-nav-item">
                <i class="bi bi-whatsapp"></i>
                Enviar Invitaciones
            </a>
            <a href="estadisticas.php" class="sidebar-nav-item">
                <i class="bi bi-graph-up"></i>
                Ver Estadísticas
            </a>
        </div>
    </nav>

    <!-- Main Header -->
    <header class="main-header" id="mainHeader">
        <div class="header-content">
            <div class="header-brand">
                <div class="header-logo">
                    FI
                </div>
                <h1 class="header-title">FastInvite</h1>
            </div>
            
            <div class="header-center">
                <h2 class="dashboard-title">🎉 Dashboard de Invitaciones</h2>
            </div>
            
            <div class="user-section">
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['admin_nombre']); ?></p>
                    <p class="user-role">Administrador</p>
                </div>
                <a href="../../logout.php" class="logout-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Statistics Cards -->
        <div class="stats-grid animate-fade-in">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo number_format($stats['total_invitados']); ?></h3>
                        <p class="stat-label">Total de Invitaciones</p>
                    </div>
                    <div class="stat-icon users">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo number_format($stats['total_confirmados']); ?></h3>
                        <p class="stat-label">Total de Invitaciones Confirmaciones</p>
                    </div>
                    <div class="stat-icon confirmed">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo number_format($stats['total_cupos']); ?></h3>
                        <p class="stat-label">Invitados</p>
                    </div>
                    <div class="stat-icon seats">
                        <i class="bi bi-chair"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo number_format($stats['total_cupos_confirmados']); ?></h3>
                        <p class="stat-label">Invitados Confirmados</p>
                    </div>
                    <div class="stat-icon confirmed-seats">
                        <i class="bi bi-check2-square"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Guests Table -->
        <div class="content-card animate-fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h3><i class="bi bi-list-ul me-2"></i>Todos los Invitados (<?php echo count($todos_invitados); ?>)</h3>
                <div class="d-flex gap-2">
                    <button onclick="refrescarDatos()" class="btn btn-outline-primary btn-sm-custom" title="Refrescar datos">
                        <i class="bi bi-arrow-clockwise"></i>
                        Refrescar
                    </button>
                    <a href="generador.php" class="btn btn-primary-custom btn-sm-custom">
                        <i class="bi bi-plus-lg"></i>
                        Agregar Invitado
                    </a>
                </div>
            </div>
            <!-- Sección de búsqueda y filtros -->
            <div class="search-filters-section mb-3">
                <!-- Barra de búsqueda general -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Buscar por nombre, teléfono, token...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-secondary w-100" onclick="limpiarTodosFiltros()">
                            <i class="bi bi-x-circle"></i> Limpiar todos los filtros
                        </button>
                    </div>
                </div>
                <!-- Filtros por categorías -->
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Mesa:</label>
                        <select class="form-select form-select-sm" id="filterMesa">
                            <option value="">Todas las mesas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Estado:</label>
                        <select class="form-select form-select-sm" id="filterEstado">
                            <option value="">Todos los estados</option>
                            <option value="confirmado">Confirmados</option>
                            <option value="pendiente">Pendientes</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Tipo:</label>
                        <select class="form-select form-select-sm" id="filterTipo">
                            <option value="">Todos los tipos</option>
                            <option value="general">General</option>
                            <option value="familia">Familia</option>
                            <option value="amigo">Amigo</option>
                            <option value="trabajo">Trabajo</option>
                            <option value="padrinos">Padrinos</option>
                            <option value="padres">Padres</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Cupos:</label>
                        <select class="form-select form-select-sm" id="filterCupos">
                            <option value="">Todos los cupos</option>
                            <option value="1">1 cupo</option>
                            <option value="2">2 cupos</option>
                            <option value="3">3 cupos</option>
                            <option value="4+">4+ cupos</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Teléfono:</label>
                        <select class="form-select form-select-sm" id="filterTelefono">
                            <option value="">Todos</option>
                            <option value="con_telefono">Con teléfono</option>
                            <option value="sin_telefono">Sin teléfono</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Token:</label>
                        <select class="form-select form-select-sm" id="filterToken">
                            <option value="">Todos</option>
                            <option value="con_token">Con token</option>
                            <option value="sin_token">Sin token</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($todos_invitados)): ?>
                <div class="table-responsive-custom">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Mesa</th>
                                <th>Cupos</th>
                                <th>Confirmados</th>
                                <th>Token</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todos_invitados as $invitado): ?>
                            <tr data-id="<?php echo $invitado['id_invitado']; ?>" id="row-<?php echo $invitado['id_invitado']; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($invitado['nombre_completo']); ?></strong>
                                        <?php if ($invitado['tipo_invitado']): ?>
                                            <br><small class="text-muted"><?php echo ucfirst($invitado['tipo_invitado']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($invitado['telefono']): ?>
                                        <span class="text-muted"><?php echo htmlspecialchars($invitado['telefono']); ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">Sin teléfono</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($invitado['mesa']): ?>
                                        <span class="badge badge-info-custom">Mesa <?php echo $invitado['mesa']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-primary"><?php echo $invitado['cupos_disponibles']; ?></strong>
                                </td>
                                <td>
                                    <?php if ($invitado['cantidad_confirmada']): ?>
                                        <strong class="text-success"><?php echo $invitado['cantidad_confirmada']; ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($invitado['token']): ?>
                                        <code class="token-code"><?php echo htmlspecialchars($invitado['token']); ?></code>
                                        <button class="copy-btn" onclick="copiarToken('<?php echo htmlspecialchars($invitado['token']); ?>')" title="Copiar token">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-danger">Sin token</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($invitado['cantidad_confirmada']): ?>
                                        <span class="badge badge-success-custom">
                                            <i class="bi bi-check-circle"></i> Confirmado
                                        </span>
                                        <?php if ($invitado['fecha_confirmacion']): ?>
                                            <br><small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($invitado['fecha_confirmacion'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-warning-custom">
                                            <i class="bi bi-clock"></i> Pendiente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <button class="btn btn-primary-custom btn-sm-custom" onclick="editarInvitado(<?php echo $invitado['id_invitado']; ?>)" title="Editar invitado">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-success-custom btn-sm-custom" onclick="abrirModalConfirmacion(<?php echo $invitado['id_invitado']; ?>, '<?php echo htmlspecialchars($invitado['nombre_completo']); ?>', <?php echo $invitado['cupos_disponibles']; ?>, <?php echo $invitado['cantidad_confirmada'] ?? 0; ?>)" title="Confirmar asistencia">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                        <button class="btn btn-danger-custom btn-sm-custom" onclick="eliminarInvitado(<?php echo $invitado['id_invitado']; ?>)" title="Eliminar invitado">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Fila de edición (inicialmente oculta) -->
                            <tr id="edit-row-<?php echo $invitado['id_invitado']; ?>" class="edit-form-row" style="display: none;">
                                <td colspan="8">
                                    <div class="edit-form-container">
                                        <div class="edit-form-title">
                                            <i class="bi bi-pencil text-primary"></i>
                                            Editar Invitado: <?php echo htmlspecialchars($invitado['nombre_completo']); ?>
                                        </div>
                                        <form onsubmit="guardarCambios(event, <?php echo $invitado['id_invitado']; ?>)">
                                            <div class="edit-form-grid">
                                                <div class="edit-form-group">
                                                    <label class="edit-form-label">Nombre Completo</label>
                                                    <input type="text" class="edit-form-input" name="nombre_completo" 
                                                           value="<?php echo htmlspecialchars($invitado['nombre_completo']); ?>" required>
                                                </div>
                                                <div class="edit-form-group">
                                                    <label class="edit-form-label">Teléfono</label>
                                                    <input type="text" class="edit-form-input" name="telefono" 
                                                           value="<?php echo htmlspecialchars($invitado['telefono'] ?? ''); ?>">
                                                </div>
                                                <div class="edit-form-group">
                                                    <label class="edit-form-label">Mesa</label>
                                                    <input type="text" class="edit-form-input" name="mesa" 
                                                           value="<?php echo htmlspecialchars($invitado['mesa'] ?? ''); ?>">
                                                </div>
                                                <div class="edit-form-group">
                                                    <label class="edit-form-label">Cupos Disponibles</label>
                                                    <input type="number" class="edit-form-input" name="cupos_disponibles" 
                                                           value="<?php echo $invitado['cupos_disponibles']; ?>" min="1" required
                                                           onchange="validarCuposConfirmados(this.form.cantidad_confirmada, this)">
                                                </div>
                                                <div class="edit-form-group">
                                                    <label class="edit-form-label">Cupos Confirmados</label>
                                                    <input type="number" class="edit-form-input" name="cantidad_confirmada" 
                                                           value="<?php echo $invitado['cantidad_confirmada'] ?? 0; ?>" min="0" 
                                                           max="<?php echo $invitado['cupos_disponibles']; ?>"
                                                           onchange="validarCuposConfirmados(this, this.form.cupos_disponibles)">
                                                    <small class="text-muted">Cantidad de personas que han confirmado asistencia</small>
                                                </div>
                                                <div class="edit-form-group">
                                                    <label class="edit-form-label">Tipo de Invitado</label>
                                                    <select class="edit-form-input" name="tipo_invitado">
                                                        <option value="">Seleccionar tipo</option>
                                                        <option value="general" <?php echo ($invitado['tipo_invitado'] == 'general') ? 'selected' : ''; ?>>General</option>
                                                        <option value="vip" <?php echo ($invitado['tipo_invitado'] == 'vip') ? 'selected' : ''; ?>>VIP</option>
                                                        <option value="familia" <?php echo ($invitado['tipo_invitado'] == 'familia') ? 'selected' : ''; ?>>Familia</option>
                                                        <option value="amigo" <?php echo ($invitado['tipo_invitado'] == 'amigo') ? 'selected' : ''; ?>>Amigo</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="edit-form-actions">
                                                <button type="button" class="btn btn-cancel btn-sm-custom" onclick="cancelarEdicion(<?php echo $invitado['id_invitado']; ?>)">
                                                    <i class="bi bi-x-lg"></i> Cancelar
                                                </button>
                                                <button type="submit" class="btn btn-save btn-sm-custom">
                                                    <i class="bi bi-check-lg"></i> Guardar Cambios
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="text-muted">No hay invitados registrados</h4>
                    <p class="text-muted">Comienza agregando tu primer invitado</p>
                    <a href="generador.php" class="btn btn-primary-custom mt-3">
                        <i class="bi bi-plus-lg"></i>
                        Agregar Primer Invitado
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tables Distribution -->
        <div class="content-card animate-fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h3><i class="bi bi-table me-2"></i>Distribución por Mesas</h3>
                <span class="badge badge-info-custom">
                    <?php echo count($distribucion_mesas); ?> mesas configuradas
                </span>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($distribucion_mesas)): ?>
                <div class="table-responsive-custom">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Mesa</th>
                                <th>Total Invitados</th>
                                <th>Confirmados</th>
                                <th>Total Cupos</th>
                                <th>Cupos Confirmados</th>
                                <th>% Confirmación</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($distribucion_mesas as $mesa): ?>
                            <?php 
                                $porcentaje = $mesa['total_invitados'] > 0 ? 
                                    round(($mesa['confirmados'] / $mesa['total_invitados']) * 100, 1) : 0;
                                
                                // Determinar estado y colores
                                if ($porcentaje >= 70) {
                                    $estado = 'Excelente';
                                    $badge_class = 'badge-success-custom';
                                    $progress_class = 'progress-success';
                                } elseif ($porcentaje >= 40) {
                                    $estado = 'Regular';
                                    $badge_class = 'badge-warning-custom';
                                    $progress_class = 'progress-warning';
                                } else {
                                    $estado = 'Preocupante';
                                    $badge_class = 'badge-danger-custom';
                                    $progress_class = 'progress-danger';
                                }
                            ?>
                            <tr>
                                <td>
                                    <?php if ($mesa['mesa'] == 'Sin asignar'): ?>
                                        <span class="badge" style="background: var(--dark-gray); color: white;">
                                            <i class="bi bi-question-circle"></i> Sin Asignar
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-info-custom">
                                            <i class="bi bi-table"></i> Mesa <?php echo $mesa['mesa']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 1.1rem;"><?php echo $mesa['total_invitados']; ?></strong>
                                </td>
                                <td>
                                    <strong class="text-success"><?php echo $mesa['confirmados']; ?></strong>
                                </td>
                                <td>
                                    <span class="text-primary"><?php echo $mesa['total_cupos']; ?></span>
                                </td>
                                <td>
                                    <strong class="text-success"><?php echo $mesa['cupos_confirmados']; ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress-custom" style="width: 100px;">
                                            <div class="progress-bar-custom <?php echo $progress_class; ?>" 
                                                 style="width: <?php echo $porcentaje; ?>%"></div>
                                        </div>
                                        <strong><?php echo $porcentaje; ?>%</strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $estado; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-table text-muted" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="text-muted">No hay mesas configuradas</h4>
                    <p class="text-muted">Las mesas aparecerán aquí cuando agregues invitados con mesa asignada</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <?php if (!empty($confirmaciones_diarias)): ?>
        <div class="content-card animate-fade-in">
            <div class="card-header-custom">
                <h3><i class="bi bi-activity me-2"></i>Confirmaciones Recientes</h3>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <?php foreach ($confirmaciones_diarias as $confirmacion): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1"><?php echo $confirmacion['cantidad']; ?> confirmaciones</h5>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($confirmacion['fecha'])); ?>
                                    </small>
                                </div>
                                <i class="bi bi-calendar-check text-success" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Update Indicator -->
    <div class="update-indicator" id="updateIndicator"></div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar functionality
        const sidebar = document.getElementById('sidebar');
        const sidebarTrigger = document.getElementById('sidebarTrigger');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarIcons = document.getElementById('sidebarIcons');
        const mainContent = document.getElementById('mainContent');
        const mainHeader = document.getElementById('mainHeader');
        let sidebarTimeout;

        // Variables globales para el modal
        let currentGuestId = null;
        let currentMaxSeats = 0;
        let currentQuantity = 1;

        console.log('Elementos encontrados:', {
            sidebar: !!sidebar,
            sidebarTrigger: !!sidebarTrigger,
            sidebarOverlay: !!sidebarOverlay,
            sidebarIcons: !!sidebarIcons,
            mainContent: !!mainContent,
            mainHeader: !!mainHeader
        });

        // Función para mostrar sidebar
        function showSidebar() {
            console.log('Mostrando sidebar');
            clearTimeout(sidebarTimeout);
            sidebar.classList.add('show');
            sidebarOverlay.classList.add('show');
            sidebarIcons.classList.add('hide');
            if (mainContent) mainContent.classList.add('sidebar-open');
            if (mainHeader) mainHeader.classList.add('sidebar-open');
        }

        // Función para ocultar sidebar
        function hideSidebar() {
            console.log('Ocultando sidebar');
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            sidebarIcons.classList.remove('hide');
            if (mainContent) mainContent.classList.remove('sidebar-open');
            if (mainHeader) mainHeader.classList.remove('sidebar-open');
        }

        // Eventos para mostrar sidebar (hover en iconos)
        sidebarIcons.addEventListener('mouseenter', () => {
            console.log('Mouse entró en iconos');
            showSidebar();
        });

        // Eventos para el área de trigger
        sidebarTrigger.addEventListener('mouseenter', () => {
            console.log('Mouse entró en trigger');
            showSidebar();
        });

        // Click en trigger para dispositivos táctiles
        sidebarTrigger.addEventListener('click', () => {
            console.log('Click en trigger');
            if (sidebar.classList.contains('show')) {
                hideSidebar();
            } else {
                showSidebar();
            }
        });

        // Mantener sidebar abierto cuando el mouse está sobre él
        sidebar.addEventListener('mouseenter', () => {
            console.log('Mouse entró en sidebar');
            clearTimeout(sidebarTimeout);
        });

        // Ocultar sidebar cuando el mouse sale
        sidebar.addEventListener('mouseleave', () => {
            console.log('Mouse salió del sidebar');
            sidebarTimeout = setTimeout(() => {
                hideSidebar();
            }, 300);
        });

        // Ocultar cuando sale del área de trigger
        sidebarTrigger.addEventListener('mouseleave', () => {
            console.log('Mouse salió del trigger');
            sidebarTimeout = setTimeout(() => {
                if (!sidebar.matches(':hover') && !sidebarIcons.matches(':hover')) {
                    hideSidebar();
                }
            }, 500);
        });

        // Ocultar cuando sale de los iconos
        sidebarIcons.addEventListener('mouseleave', () => {
            console.log('Mouse salió de iconos');
            sidebarTimeout = setTimeout(() => {
                if (!sidebar.matches(':hover')) {
                    hideSidebar();
                }
            }, 300);
        });

        // Ocultar sidebar al hacer click en overlay
        sidebarOverlay.addEventListener('click', () => {
            console.log('Click en overlay');
            hideSidebar();
        });

        // Ocultar sidebar al hacer click en un enlace de navegación
        document.querySelectorAll('.sidebar-nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                console.log('Click en nav item');
                // No ocultar inmediatamente para permitir la navegación
                setTimeout(() => {
                    hideSidebar();
                }, 100);
            });
        });

        // Función de prueba
        function testSidebar() {
            console.log('Probando sidebar...');
            if (sidebar.classList.contains('show')) {
                hideSidebar();
            } else {
                showSidebar();
            }
        }

        console.log('Para probar manualmente, ejecuta testSidebar() en la consola');

        // Existing functions
        function eliminarInvitado(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este invitado?')) {
                fetch('dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=eliminar_invitado&id_invitado=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al eliminar el invitado: ' + (data.error || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el invitado');
                });
            }
        }
        
        function editarInvitado(id) {
            // Cerrar cualquier otra fila de edición abierta
            document.querySelectorAll('.edit-form-row').forEach(row => {
                if (row.id !== `edit-row-${id}`) {
                    row.style.display = 'none';
                }
            });
            
            // Mostrar/ocultar la fila de edición
            const editRow = document.getElementById(`edit-row-${id}`);
            if (editRow.style.display === 'none') {
                editRow.style.display = 'table-row';
                // Scroll suave hacia el formulario
                editRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                editRow.style.display = 'none';
            }
        }

        function cancelarEdicion(id) {
            const editRow = document.getElementById(`edit-row-${id}`);
            editRow.style.display = 'none';
        }

        function guardarCambios(event, id) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'actualizar_invitado');
            formData.append('id_invitado', id);
            
            // Mostrar indicador de carga
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
            submitBtn.disabled = true;
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Invitado y confirmaciones actualizados correctamente', 'success');
                    // Ocultar formulario de edición
                    cancelarEdicion(id);
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Error al actualizar invitado: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al actualizar invitado', 'error');
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Validación en tiempo real para cupos confirmados
        function validarCuposConfirmados(inputConfirmados, inputDisponibles) {
            const confirmados = parseInt(inputConfirmados.value) || 0;
            const disponibles = parseInt(inputDisponibles.value) || 0;
            
            if (confirmados > disponibles) {
                inputConfirmados.value = disponibles;
                showNotification('Los cupos confirmados no pueden exceder los disponibles', 'warning');
            }
            
            inputConfirmados.setAttribute('max', disponibles);
        }
        
        // Función para abrir el modal
        function abrirModalConfirmacion(guestId, guestName, maxSeats, confirmedSeats) {
            currentGuestId = guestId;
            currentMaxSeats = maxSeats;
            currentQuantity = confirmedSeats > 0 ? confirmedSeats : 1;
            
            document.getElementById('modal-guest-name').textContent = guestName;
            document.getElementById('modal-available-seats').textContent = maxSeats;
            document.getElementById('modal-confirmed-seats').textContent = confirmedSeats;
            document.getElementById('quantityDisplay').textContent = currentQuantity;
            
            actualizarBotonesQuantity();
            
            const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            modal.show();
        }

        function cambiarCantidad(cambio) {
            const nuevaCantidad = currentQuantity + cambio;
            if (nuevaCantidad >= 1 && nuevaCantidad <= currentMaxSeats) {
                currentQuantity = nuevaCantidad;
                document.getElementById('quantityDisplay').textContent = currentQuantity;
                actualizarBotonesQuantity();
            }
        }

        function actualizarBotonesQuantity() {
            document.getElementById('decreaseBtn').disabled = currentQuantity <= 1;
            document.getElementById('increaseBtn').disabled = currentQuantity >= currentMaxSeats;
        }

        function confirmarAsistencia() {
            const confirmBtn = document.getElementById('confirmBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Confirmando...';
            confirmBtn.disabled = true;

            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=confirmar_invitado&id_invitado=${currentGuestId}&cantidad_confirmada=${currentQuantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`¡Confirmación exitosa! ${currentQuantity} cupo(s) confirmado(s)`, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
                    modal.hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error al confirmar: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .finally(() => {
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }
        
        function copiarToken(token) {
            // Copiar token al portapapeles
            navigator.clipboard.writeText(token).then(function() {
                // Mostrar feedback visual
                showNotification('Token copiado: ' + token, 'success');
            }).catch(function(err) {
                console.error('Error al copiar: ', err);
                showNotification('Token copiado: ' + token, 'info');
            });
        }

        // Notification function
        function showNotification(message, type = 'success') {
            const indicator = document.getElementById('updateIndicator');
            indicator.textContent = message;
            indicator.className = `update-indicator ${type}`;
            indicator.style.display = 'block';
            
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 3000);
        }
        
        /**
         * FUNCIÓN PARA RECIBIR ACTUALIZACIONES EN TIEMPO REAL
         */
        function actualizarFilaInvitado(idInvitado, datos) {
            const scrollY = window.scrollY;
            const activo = document.activeElement;
            const idActivo = activo ? activo.id : null;

            const fila = document.querySelector(`tr[data-id="${idInvitado}"]`);
            if (!fila) return;

            // Actualizar solo las celdas necesarias
            const celdaConfirmados = fila.cells[4];
            if (celdaConfirmados && datos.cantidad_confirmada !== undefined) {
                celdaConfirmados.innerHTML = datos.cantidad_confirmada > 0 ?
                    `<strong class="text-success">${datos.cantidad_confirmada}</strong>` :
                    '<span class="text-muted">0</span>';
            }
            const celdaEstado = fila.cells[6];
            if (celdaEstado && datos.estado === 'confirmado') {
                const fecha = datos.fecha_confirmacion ?
                    new Date(datos.fecha_confirmacion).toLocaleDateString('es-ES') :
                    new Date().toLocaleDateString('es-ES');
                celdaEstado.innerHTML = `
                    <span class="badge badge-success-custom">
                        <i class="bi bi-check-circle"></i> Confirmado
                    </span>
                    <br><small class="text-muted">${fecha}</small>
                `;
            }

            if (datos.stats) {
                actualizarEstadisticasDesdeDatos(datos.stats);
            }
            if (datos.mesa && datos.mesaDatos) {
                actualizarFilaMesa(datos.mesa, datos.mesaDatos);
            }
            window.scrollTo({ top: scrollY });
            if (idActivo) {
                const nuevoActivo = document.getElementById(idActivo);
                if (nuevoActivo) nuevoActivo.focus();
            }
            showNotification(`¡Nueva confirmación! Invitado #${idInvitado} actualizado`, 'success');
        }

        function actualizarFilaMesa(mesa, datos) {
            const filas = document.querySelectorAll('.content-card table.table-custom tbody tr');
            for (const fila of filas) {
                const celdaMesa = fila.cells[0];
                if (celdaMesa && celdaMesa.textContent.includes(mesa)) {
                    fila.cells[1].textContent = datos.total_invitados;
                    fila.cells[2].textContent = datos.confirmados;
                    fila.cells[3].textContent = datos.total_cupos;
                    fila.cells[4].textContent = datos.cupos_confirmados;
                    // Actualizar % confirmación y estado
                    const porcentaje = datos.total_invitados > 0 ? Math.round((datos.confirmados / datos.total_invitados) * 100) : 0;
                    let estado = 'Preocupante', badge = 'badge-danger-custom', progress = 'progress-danger';
                    if (porcentaje >= 70) { estado = 'Excelente'; badge = 'badge-success-custom'; progress = 'progress-success'; }
                    else if (porcentaje >= 40) { estado = 'Regular'; badge = 'badge-warning-custom'; progress = 'progress-warning'; }
                    fila.cells[5].innerHTML = `<div class=\"d-flex align-items-center gap-2\"><div class=\"progress-custom\" style=\"width: 100px;\"><div class=\"progress-bar-custom ${progress}\" style=\"width: ${porcentaje}%\"></div></div><strong>${porcentaje}%</strong></div>`;
                    fila.cells[6].innerHTML = `<span class=\"badge ${badge}\">${estado}</span>`;
                    break;
                }
            }
        }

        // Nueva función para actualizar confirmaciones recientes dinámicamente
        function actualizarConfirmacionesRecientes(confirmaciones) {
            const contenedor = document.querySelector('.content-card .card-body-custom .row.g-3');
            if (!contenedor) return;
            contenedor.innerHTML = '';
            if (Array.isArray(confirmaciones) && confirmaciones.length > 0) {
                confirmaciones.forEach(conf => {
                    const col = document.createElement('div');
                    col.className = 'col-md-6 col-lg-4';
                    col.innerHTML = `
                        <div class=\"p-3 border rounded-3 bg-light\">
                            <div class=\"d-flex justify-content-between align-items-center\">
                                <div>
                                    <h5 class=\"mb-1\">${conf.cantidad} confirmaciones</h5>
                                    <small class=\"text-muted\">${conf.fecha}</small>
                                </div>
                                <i class=\"bi bi-calendar-check text-success\" style=\"font-size: 1.5rem;\"></i>
                            </div>
                        </div>
                    `;
                    contenedor.appendChild(col);
                });
            } else {
                contenedor.innerHTML = '<div class="text-center text-muted">Sin confirmaciones recientes</div>';
            }
        }

        // Optimizar BroadcastChannel para actualización parcial
        if ('BroadcastChannel' in window) {
            const channel = new BroadcastChannel('confirmaciones_boda');
            channel.onmessage = function(event) {
                if (event.data && event.data.tipo === 'confirmacion') {
                    // Petición AJAX para obtener solo los datos necesarios
                    fetch(`dashboard.php?action=obtener_invitado_y_stats&id=${event.data.idInvitado}`)
                        .then(res => res.json())
                        .then(data => {
                            actualizarFilaInvitado(event.data.idInvitado, data.invitado);
                            if (data.stats) actualizarEstadisticasDesdeDatos(data.stats);
                            if (data.mesa && data.mesaDatos) actualizarFilaMesa(data.mesa, data.mesaDatos);
                            if (data.confirmaciones_recientes) actualizarConfirmacionesRecientes(data.confirmaciones_recientes);
                        });
                }
            };
        }

        /**
         * FUNCIÓN PARA ACTUALIZAR ESTADÍSTICAS DESDE DATOS EXTERNOS
         */
        function actualizarEstadisticasDesdeDatos(stats) {
            try {
                const contadorConfirmaciones = document.querySelector('.stat-card:nth-child(2) .stat-number');
                if (contadorConfirmaciones && stats.total_confirmados !== undefined) {
                    const valorActual = parseInt(contadorConfirmaciones.textContent.replace(/,/g, '')) || 0;
                    const nuevoValor = stats.total_confirmados;
                    
                    if (valorActual !== nuevoValor) {
                        contadorConfirmaciones.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                        contadorConfirmaciones.style.transform = 'scale(1.05)';
                        contadorConfirmaciones.style.transition = 'all 0.3s ease';
                        contadorConfirmaciones.textContent = nuevoValor.toLocaleString();
                        
                        setTimeout(() => {
                            contadorConfirmaciones.style.backgroundColor = '';
                            contadorConfirmaciones.style.transform = 'scale(1)';
                        }, 1000);
                    }
                }
                
                const contadorCupos = document.querySelector('.stat-card:nth-child(4) .stat-number');
                if (contadorCupos && stats.total_cupos_confirmados !== undefined) {
                    const valorActual = parseInt(contadorCupos.textContent.replace(/,/g, '')) || 0;
                    const nuevoValor = stats.total_cupos_confirmados;
                    
                    if (valorActual !== nuevoValor) {
                        contadorCupos.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                        contadorCupos.style.transform = 'scale(1.05)';
                        contadorCupos.style.transition = 'all 0.3s ease';
                        contadorCupos.textContent = nuevoValor.toLocaleString();
                        
                        setTimeout(() => {
                            contadorCupos.style.backgroundColor = '';
                            contadorCupos.style.transform = 'scale(1)';
                        }, 1000);
                    }
                }
                
                console.log('✅ Estadísticas actualizadas desde datos externos');
                
            } catch (error) {
                console.error('❌ Error al actualizar estadísticas:', error);
            }
        }

        // Función para refrescar datos del dashboard
        function refrescarDatos() {
            console.log('🔄 Refrescando datos del dashboard...');
            
            const btnRefrescar = document.querySelector('button[onclick="refrescarDatos()"]');
            const iconoOriginal = btnRefrescar.innerHTML;
            btnRefrescar.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Refrescando...';
            btnRefrescar.disabled = true;
            
            fetch('dashboard.php?action=obtener_invitados')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ Datos actualizados recibidos');
                        
                        if (data.stats) {
                            const contadorInvitados = document.querySelector('.stat-card:nth-child(1) .stat-number');
                            if (contadorInvitados) {
                                contadorInvitados.textContent = data.stats.total_invitados.toLocaleString();
                            }
                            
                            const contadorConfirmados = document.querySelector('.stat-card:nth-child(2) .stat-number');
                            if (contadorConfirmados) {
                                contadorConfirmados.textContent = data.stats.total_confirmados.toLocaleString();
                            }
                            
                            const contadorCupos = document.querySelector('.stat-card:nth-child(3) .stat-number');
                            if (contadorCupos) {
                                contadorCupos.textContent = data.stats.total_cupos.toLocaleString();
                            }
                            
                            const contadorCuposConfirmados = document.querySelector('.stat-card:nth-child(4) .stat-number');
                            if (contadorCuposConfirmados) {
                                contadorCuposConfirmados.textContent = data.stats.total_cupos_confirmados.toLocaleString();
                            }
                        }
                        
                        showNotification('Datos actualizados correctamente', 'success');
                        
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                        
                    } else {
                        console.error('❌ Error al actualizar datos:', data.error);
                        showNotification('Error al actualizar datos', 'error');
                    }
                })
                .catch(error => {
                    console.error('❌ Error en la petición:', error);
                    showNotification('Error al actualizar datos', 'error');
                })
                .finally(() => {
                    btnRefrescar.innerHTML = iconoOriginal;
                    btnRefrescar.disabled = false;
                });
        }
        
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Inicializar filtros al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            inicializarFiltros();
            configurarEventListeners();
        });
        function inicializarFiltros() {
            // Llenar el filtro de mesas con las mesas disponibles
            const selectMesa = document.getElementById('filterMesa');
            const mesas = new Set();
            document.querySelectorAll('.table-custom tbody tr:not([class*="edit-form-row"])').forEach(row => {
                const mesaCell = row.cells[2];
                if (mesaCell) {
                    const mesaText = mesaCell.textContent.trim();
                    if (mesaText && !mesaText.includes('Sin asignar')) {
                        const mesaNum = mesaText.replace('Mesa ', '');
                        mesas.add(mesaNum);
                    }
                }
            });
            // Ordenar mesas numéricamente
            Array.from(mesas).sort((a, b) => parseInt(a) - parseInt(b)).forEach(mesa => {
                const option = document.createElement('option');
                option.value = mesa;
                option.textContent = `Mesa ${mesa}`;
                selectMesa.appendChild(option);
            });
            // Agregar opción "Sin asignar"
            const optionSinAsignar = document.createElement('option');
            optionSinAsignar.value = 'sin_asignar';
            optionSinAsignar.textContent = 'Sin asignar';
            selectMesa.appendChild(optionSinAsignar);
        }
        function configurarEventListeners() {
            // Event listeners para todos los filtros
            document.getElementById('searchInput').addEventListener('input', aplicarFiltros);
            document.getElementById('filterMesa').addEventListener('change', aplicarFiltros);
            document.getElementById('filterEstado').addEventListener('change', aplicarFiltros);
            document.getElementById('filterTipo').addEventListener('change', aplicarFiltros);
            document.getElementById('filterCupos').addEventListener('change', aplicarFiltros);
            document.getElementById('filterTelefono').addEventListener('change', aplicarFiltros);
            document.getElementById('filterToken').addEventListener('change', aplicarFiltros);
        }
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterMesa = document.getElementById('filterMesa').value;
            const filterEstado = document.getElementById('filterEstado').value;
            const filterTipo = document.getElementById('filterTipo').value;
            const filterCupos = document.getElementById('filterCupos').value;
            const filterTelefono = document.getElementById('filterTelefono').value;
            const filterToken = document.getElementById('filterToken').value;
            const rows = document.querySelectorAll('.table-custom tbody tr:not([class*="edit-form-row"])');
            let visibleCount = 0;
            rows.forEach(row => {
                let shouldShow = true;
                // Filtro de búsqueda general
                if (searchTerm) {
                    const cells = row.querySelectorAll('td');
                    const rowText = Array.from(cells).map(cell => cell.textContent.toLowerCase()).join(' ');
                    shouldShow = shouldShow && rowText.includes(searchTerm);
                }
                // Filtro por mesa
                if (filterMesa && shouldShow) {
                    const mesaCell = row.cells[2].textContent.trim();
                    if (filterMesa === 'sin_asignar') {
                        shouldShow = mesaCell.includes('Sin asignar');
                    } else {
                        shouldShow = mesaCell.includes(`Mesa ${filterMesa}`);
                    }
                }
                // Filtro por estado
                if (filterEstado && shouldShow) {
                    const estadoCell = row.cells[6].textContent.toLowerCase();
                    if (filterEstado === 'confirmado') {
                        shouldShow = estadoCell.includes('confirmado');
                    } else if (filterEstado === 'pendiente') {
                        shouldShow = estadoCell.includes('pendiente');
                    }
                }
                // Filtro por tipo
                if (filterTipo && shouldShow) {
                    const tipoCell = row.cells[0].textContent.toLowerCase();
                    shouldShow = tipoCell.includes(filterTipo);
                }
                // Filtro por cupos
                if (filterCupos && shouldShow) {
                    const cuposCell = row.cells[3].textContent.trim();
                    const cuposNum = parseInt(cuposCell);
                    if (filterCupos === '4+') {
                        shouldShow = cuposNum >= 4;
                    } else {
                        shouldShow = cuposNum === parseInt(filterCupos);
                    }
                }
                // Filtro por teléfono
                if (filterTelefono && shouldShow) {
                    const telefonoCell = row.cells[1].textContent.trim();
                    if (filterTelefono === 'con_telefono') {
                        shouldShow = !telefonoCell.includes('Sin teléfono');
                    } else if (filterTelefono === 'sin_telefono') {
                        shouldShow = telefonoCell.includes('Sin teléfono');
                    }
                }
                // Filtro por token
                if (filterToken && shouldShow) {
                    const tokenCell = row.cells[5].textContent.trim();
                    if (filterToken === 'con_token') {
                        shouldShow = !tokenCell.includes('Sin token');
                    } else if (filterToken === 'sin_token') {
                        shouldShow = tokenCell.includes('Sin token');
                    }
                }
                row.style.display = shouldShow ? '' : 'none';
                if (shouldShow) visibleCount++;
            });
            // Actualizar contador
            actualizarContadorFiltros(visibleCount);
        }
        function limpiarTodosFiltros() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterMesa').value = '';
            document.getElementById('filterEstado').value = '';
            document.getElementById('filterTipo').value = '';
            document.getElementById('filterCupos').value = '';
            document.getElementById('filterTelefono').value = '';
            document.getElementById('filterToken').value = '';
            aplicarFiltros();
        }
        function actualizarContadorFiltros(count) {
            const titulo = document.querySelector('.card-header-custom h3');
            const textoOriginal = titulo.textContent.split('(')[0];
            titulo.innerHTML = `<i class="bi bi-list-ul me-2"></i>${textoOriginal}(${count})`;
        }
    </script>

    <!-- Modal de Confirmación -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content confirmation-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle"></i>
                        Confirmar Asistencia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="confirmation-info">
                        <h5><i class="bi bi-person text-primary"></i> Información del Invitado</h5>
                        <div class="confirmation-detail">
                            <span>Nombre:</span>
                            <span id="modal-guest-name">-</span>
                        </div>
                        <div class="confirmation-detail">
                            <span>Cupos Disponibles:</span>
                            <span id="modal-available-seats">-</span>
                        </div>
                        <div class="confirmation-detail">
                            <span>Ya Confirmados:</span>
                            <span id="modal-confirmed-seats">-</span>
                        </div>
                    </div>
                    
                    <div class="text-center mb-3">
                        <strong>¿Cuántas personas asistirán?</strong>
                    </div>
                    
                    <div class="quantity-selector">
                        <button type="button" class="quantity-btn" id="decreaseBtn" onclick="cambiarCantidad(-1)">
                            <i class="bi bi-dash"></i>
                        </button>
                        <div class="quantity-display" id="quantityDisplay">1</div>
                        <button type="button" class="quantity-btn" id="increaseBtn" onclick="cambiarCantidad(1)">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel btn-sm-custom" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success-custom btn-sm-custom" onclick="confirmarAsistencia()" id="confirmBtn">
                        <i class="bi bi-check-lg"></i> Confirmar Asistencia
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        if (!!window.EventSource) {
            let lastId = 0;
            const source = new EventSource('sse_confirmaciones.php');
            source.onmessage = function(event) {
                if (event.data) {
                    const data = JSON.parse(event.data);
                    if (data && data.id_invitado) {
                        // Petición AJAX para obtener datos completos y actualizados del invitado
                        fetch(`dashboard.php?action=obtener_invitado_y_stats&id=${data.id_invitado}`)
                            .then(res => res.json())
                            .then(resp => {
                                if (resp && resp.invitado) {
                                    actualizarFilaInvitado(data.id_invitado, resp.invitado);
                                    if (resp.stats) actualizarEstadisticasDesdeDatos(resp.stats);
                                    if (resp.mesa && resp.mesaDatos) actualizarFilaMesa(resp.mesa, resp.mesaDatos);
                                    if (resp.confirmaciones_recientes) actualizarConfirmacionesRecientes(resp.confirmaciones_recientes);
                                }
                            });
                        lastId = data.id_confirmacion;
                    }
                }
            };
            source.onerror = function(e) {
                console.log('SSE error:', e);
            };
        }
    })();
    </script>
</body>
</html>