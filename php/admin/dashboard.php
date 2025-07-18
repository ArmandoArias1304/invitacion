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

// Estadísticas de acceso al evento (solo accesos del escáner)
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT ae.id_invitado) as invitados_presentes,
        SUM(ae.cantidad_ingresada) as personas_presentes,
        COUNT(*) as total_accesos
    FROM accesos_evento ae
");
$stmt->execute();
$statsAcceso = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['invitados_presentes'] = $statsAcceso['invitados_presentes'] ?: 0;
$stats['personas_presentes'] = $statsAcceso['personas_presentes'] ?: 0;
$stats['total_accesos'] = $statsAcceso['total_accesos'] ?: 0;

// Calcular porcentaje de asistencia
$stats['porcentaje_asistencia'] = $stats['total_cupos_confirmados'] > 0 ? 
    round(($stats['personas_presentes'] / $stats['total_cupos_confirmados']) * 100, 1) : 0;

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
        /* ===== PALETA DE COLORES MODO OSCURO ELEGANTE ===== */
        :root {
            /* Colores principales (mantienen identidad de marca) */
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            
            /* Paleta oscura elegante */
            --light-gray: rgba(30, 30, 50, 0.9);
            --dark-gray: #64748b;
            --text-dark: #e2e8f0;
            --border-color: rgba(255, 255, 255, 0.1);
            
            /* Fondos específicos modo oscuro */
            --body-background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            --card-background: rgba(30, 30, 50, 0.8);
            --header-background: rgba(30, 30, 50, 0.8);
            --table-background: rgba(30, 30, 50, 0.8);
            --input-background: rgba(30, 30, 50, 0.9);
            --modal-background: rgba(30, 30, 50, 0.95);
            
            /* Sombras elegantes */
            --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-strong: 0 15px 35px rgba(0, 0, 0, 0.3);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            
            /* Header especial (morado pastel) */
            --card-header-background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.2));
            
            /* Estados hover */
            --table-hover-background: rgba(255, 255, 255, 0.05);
            
            /* Layout */
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
            background: var(--body-background);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            backdrop-filter: blur(10px);
            z-index: 1050;
            transition: all 0.2s ease;
            box-shadow: 4px 0 20px rgba(99, 102, 241, 0.2);
            border-radius: 0 12px 12px 0;
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
            transition: all 0.15s ease;
            border-radius: 0 12px 12px 0;
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
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.15);
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            font-size: 1.2rem;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-icon-item:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            transform: scale(1.05);
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
            height: 64px;
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
            background: rgba(0, 0, 0, 0.6);
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
            background: var(--header-background);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-soft);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1020;
            margin-left: 60px;
            transition: all 0.2s ease;
        }

        .main-header.sidebar-open {
            margin-left: 280px;
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
            color: rgba(255, 255, 255, 0.9);
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
            color: rgba(255, 255, 255, 0.9);
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
            margin-left: 60px;
            padding: 2rem;
            min-height: calc(100vh - 80px);
            transition: all 0.2s ease;
        }

        .main-content.sidebar-open {
            margin-left: 280px;
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-background);
            backdrop-filter: blur(20px);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow-card);
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
            box-shadow: var(--shadow-strong);
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
        .stat-icon.acceso { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }

        .stat-label {
            color: var(--dark-gray);
            font-weight: 500;
            font-size: 0.875rem;
            margin: 0;
        }

        .stat-details {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .stat-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.75rem;
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* ===== CARDS ===== */
        .content-card {
            background: var(--card-background);
            backdrop-filter: blur(20px);
            border-radius: 1rem;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .card-header-custom {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--card-header-background);
            backdrop-filter: blur(10px);
            border-radius: 1rem 1rem 0 0;
        }

        .card-header-custom h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
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
            overflow-x: auto;
            overflow-y: auto;
            box-shadow: var(--shadow-soft);
            max-height: 600px;
            max-width: 100%;
        }

        .table-custom {
            margin: 0;
            background: var(--table-background);
        }

        .table-custom th {
            background: var(--light-gray);
            backdrop-filter: blur(10px);
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-custom td {
            padding: 1rem;
            border-color: var(--border-color);
            vertical-align: middle;
            background: var(--table-background);
            color: var(--text-dark);
        }

        .table-custom tbody tr:hover {
            background: var(--table-hover-background);
        }

        .table-custom tbody tr:hover td {
            background: var(--table-hover-background);
        }

        /* ===== FORMULARIO DE EDICIÓN INLINE ===== */
        .edit-form-row {
            background: rgba(30, 30, 50, 0.6);
            border-left: 4px solid var(--primary-color);
        }

        .edit-form-container {
            padding: 1.5rem;
            background: var(--card-background);
            backdrop-filter: blur(10px);
            border-radius: 0.5rem;
            margin: 0.5rem;
            box-shadow: var(--shadow-soft);
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
            background: var(--input-background);
            color: rgba(255, 255, 255, 0.9);
        }

        .edit-form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .edit-form-input::placeholder {
            color: var(--dark-gray);
            opacity: 0.7;
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
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-warning-custom {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-info-custom {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-color);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .badge-danger-custom {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
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
            background: var(--input-background);
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

        @media (min-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 80px;
            }

            .main-header {
                margin-left: 0;
                margin-top: 70px;
            }

            .header-content .user-section {
                display: none !important;
            }

            .sidebar-user-section {
                display: block !important;
            }

            .header-content {
                padding: 1rem;
                justify-content: center !important;
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

            .sidebar-icons {
                display: none;
            }

            .sidebar-trigger {
                display: none;
            }

            .mobile-menu-btn {
                display: flex;
            }

            .sidebar {
                width: 100vw;
                left: -100vw;
                border-radius: 0;
            }

            .sidebar.show {
                left: 0;
            }

            .sidebar-header {
                padding-top: 80px;
            }

            .sidebar-nav {
                padding: 1rem 0 200px 0 !important;
            }

            /* Scroll horizontal para tablas en móvil */
            .table-responsive-custom {
                overflow-x: auto !important;
                max-width: 100vw;
            }
            
            .table-custom {
                min-width: 800px;
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
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(10px);
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
            backdrop-filter: blur(10px);
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
            background: var(--input-background);
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
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }

        .search-filters-section .form-label {
            margin-bottom: 0.25rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .search-filters-section .form-select-sm,
        .search-filters-section .form-control {
            border-color: var(--border-color);
            background: var(--input-background);
            color: rgba(255, 255, 255, 0.9);
        }

        .search-filters-section .form-select-sm:focus,
        .search-filters-section .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background: var(--input-background);
            color: rgba(255, 255, 255, 0.9);
        }

        .search-filters-section .form-control::placeholder {
            color: var(--dark-gray);
            opacity: 0.7;
        }

        .search-filters-section .input-group-text {
            background: var(--input-background);
            border-color: var(--border-color);
            color: var(--text-dark);
        }

        /* Botón hamburguesa para móviles */
        .mobile-menu-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.5rem;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1060;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .mobile-menu-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .mobile-menu-btn:active {
            transform: scale(0.95);
        }

        /* Sección de usuario en la sidebar */
        .sidebar-user-section {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
            display: none;
        }

        .sidebar-user-info {
            color: white;
            text-align: center;
            margin-bottom: 1rem;
        }

        .sidebar-user-name {
            font-weight: 600;
            color: white;
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
        }

        .sidebar-user-role {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
        }

        .sidebar-logout-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
        }

        .sidebar-logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-1px);
        }

        /* ===== EFECTO GLOW MORADO PARA TODAS LAS STAT-CARDS ===== */
.stat-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 
        0 15px 35px rgba(0, 0, 0, 0.3),
        0 0 25px rgba(99, 102, 241, 0.5),
        0 0 50px rgba(139, 92, 246, 0.3) !important;
    border-color: rgba(99, 102, 241, 0.7) !important;
}

/* ===== EFECTOS ESPECÍFICOS POR TIPO DE CARD ===== */
.stat-card:has(.stat-icon.users):hover {
    box-shadow: 
        0 15px 35px rgba(0, 0, 0, 0.3),
        0 0 25px rgba(99, 102, 241, 0.5),
        0 0 50px rgba(139, 92, 246, 0.3) !important;
}

.stat-card:has(.stat-icon.confirmed):hover {
    box-shadow: 
        0 15px 35px rgba(0, 0, 0, 0.3),
        0 0 25px rgba(16, 185, 129, 0.4),
        0 0 50px rgba(99, 102, 241, 0.3) !important;
}

.stat-card:has(.stat-icon.seats):hover {
    box-shadow: 
        0 15px 35px rgba(0, 0, 0, 0.3),
        0 0 25px rgba(245, 158, 11, 0.4),
        0 0 50px rgba(139, 92, 246, 0.3) !important;
}

.stat-card:has(.stat-icon.confirmed-seats):hover {
    box-shadow: 
        0 15px 35px rgba(0, 0, 0, 0.3),
        0 0 25px rgba(16, 185, 129, 0.5),
        0 0 50px rgba(99, 102, 241, 0.3) !important;
}

/* ===== CORRECCIÓN DE TEXTOS EN TABLA ===== */

/* Texto pequeño (tipo de invitado, fecha confirmación) */
.table-custom td small {
    color: var(--dark-gray) !important;
    opacity: 1 !important;
}

.table-custom td small.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Teléfono */
.table-custom td .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Sin teléfono */
.table-custom td .text-danger {
    color: var(--danger-color) !important;
    font-weight: 500;
}

/* Sin mesa asignada */
.table-custom td .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Números de cupos confirmados en 0 */
.table-custom td .text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Texto principal de números */
.table-custom td .text-primary {
    color: var(--primary-color) !important;
}

.table-custom td .text-success {
    color: var(--success-color) !important;
}

/* ===== NOMBRES Y TIPOS ===== */
.table-custom td strong {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* ===== ESPECÍFICO PARA CADA CASO ===== */

/* Tipo de invitado (General, VIP, etc.) */
.table-custom td div small.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
    font-style: italic;
}

/* Fecha de confirmación */
.table-custom td small.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
    font-size: 0.75rem;
}

/* Sin token */
.table-custom .text-danger {
    color: #ff6b6b !important;
    font-weight: 500;
}

/* ===== SOLUCIÓN GENERAL PARA TODO EL TEXTO MUTED ===== */
.table-custom .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

.table-custom small {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* ===== CONFIRMACIONES RECIENTES - MODO OSCURO ===== */

/* Cards de confirmaciones individuales */
.content-card .card-body-custom .p-3.border.rounded-3.bg-light {
    background: rgba(40, 40, 65, 0.9) !important;
    border: 1px solid var(--border-color) !important;
    transition: var(--transition);
}

/* Hover effect para las cards de confirmaciones */
.content-card .card-body-custom .p-3.border.rounded-3:hover {
    background: rgba(45, 45, 70, 0.95) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
}

/* Títulos de confirmaciones */
.content-card .card-body-custom h5.mb-1 {
    color: rgba(255, 255, 255, 0.95) !important;
    font-weight: 600;
}

/* Fechas */
.content-card .card-body-custom small.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Icono de confirmación */
.content-card .card-body-custom .text-success {
    color: var(--success-color) !important;
}

/* ===== SOLUCIÓN MÁS ESPECÍFICA ===== */

/* Selector más directo para las cards de confirmación */
.card-body-custom .row.g-3 .p-3 {
    background: rgba(40, 40, 65, 0.9) !important;
    border: 1px solid var(--border-color) !important;
    backdrop-filter: blur(10px);
}

.card-body-custom .row.g-3 .p-3:hover {
    background: rgba(45, 45, 70, 0.95) !important;
    transform: translateY(-2px);
    box-shadow: 
        0 4px 12px rgba(0, 0, 0, 0.3),
        0 0 15px rgba(99, 102, 241, 0.2);
}

/* Todos los elementos de texto dentro */
.card-body-custom .row.g-3 h5 {
    color: rgba(255, 255, 255, 0.95) !important;
}

.card-body-custom .row.g-3 small {
    color: rgba(255, 255, 255, 0.7) !important;
}

.card-body-custom .row.g-3 .text-success {
    color: var(--success-color) !important;
}

/* ===== CENTRADO SELECTIVO DE TABLA ===== */

/* Headers centrados */
.table-custom th {
    text-align: center !important;
    vertical-align: middle !important;
}

/* Celdas centradas por defecto */
.table-custom td {
    text-align: center !important;
    vertical-align: middle !important;
}

/* EXCEPCIÓN 1: Columna de NOMBRES a la izquierda */
.table-custom td:first-child {
    text-align: left !important;
}

/* EXCEPCIÓN 2: Columna de ACCIONES a la izquierda */
.table-custom td:last-child {
    text-align: left !important;
}

/* Divs con nombres alineados a la izquierda */
.table-custom td:first-child div {
    display: flex;
    flex-direction: column;
    align-items: flex-start !important;
    text-align: left !important;
    padding-left: 2rem !important;
}

/* Botones de acción en fila horizontal */
.table-custom td:last-child .d-flex {
    justify-content: flex-start !important;
    align-items: center;
    flex-direction: row !important;
    gap: 0.25rem;
}

/* ===== TIPO DE INVITADO COMO BADGE DE MESA ===== */

.table-custom td:first-child small.text-muted {
    display: inline-block !important;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
    
    /* Mismo estilo que badge-info-custom */
    background: rgba(99, 102, 241, 0.2) !important;
    color: var(--primary-color) !important;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

/* ===== CENTRADO PARA EL RESTO DE CONTENIDO ===== */

/* Badges centrados */
.table-custom td .badge-custom,
.table-custom td .badge-info-custom,
.table-custom td .badge-success-custom,
.table-custom td .badge-warning-custom {
    display: inline-flex;
    margin: 0 auto;
}

/* Token centrado */
.table-custom td .token-code {
    display: inline-block;
    margin: 0 auto;
}

/* Copy button junto al token */
.table-custom td .copy-btn {
    margin-left: 0.5rem;
    vertical-align: middle;
}

/* ===== LABELS DE FILTROS EN MODO OSCURO ===== */

/* Labels de filtros en blanco */
.form-label.small.text-muted {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
}

/* Alternativa más específica */
.row.g-2 .form-label.small.text-muted {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
}

/* Si los anteriores no funcionan, usa este más específico */
.col-md-2 .form-label.small.text-muted {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
}

@media (max-width: 768px) {
    .main-header {
        margin-left: 0;
        margin-top: 0 !important;
    }
    .header-content {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
        text-align: center;
        position: relative;
        justify-content: center !important;
        display: flex !important;
    }
    .header-brand {
        justify-content: center;
        width: 100%;
    }
    .header-center {
        position: static;
        transform: none;
        width: 100%;
    }
    .dashboard-title {
        font-size: 1.25rem;
    }
    .user-section {
        width: 100%;
        justify-content: center;
        display: none !important;
    }
}
    </style>
</head>
<body>
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="bi bi-list"></i>
</button>
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
        <a href="../scanner/control.php" class="sidebar-icon-item" data-tooltip="Scanner">
            <i class="bi bi-qr-code-scan"></i>
        </a>
    </div>

    <!-- Sidebar Trigger (área invisible para hover) -->
    <div class="sidebar-trigger" id="sidebarTrigger"></div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4 class="sidebar-title">Panel de Control</h4>
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
        <a href="../scanner/control.php" class="sidebar-nav-item">
            <i class="bi bi-qr-code-scan"></i>
            Scanner
        </a>
    </div>
    
    <!-- Sección de usuario en la sidebar (solo visible en móvil) -->
    <div class="sidebar-user-section">
        <div class="sidebar-user-info">
            <p class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['admin_nombre']); ?></p>
            <p class="sidebar-user-role">Administrador</p>
        </div>
        <a href="../../logout.php" class="sidebar-logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            Cerrar Sesión
        </a>
    </div>
</nav>

    <!-- Header -->
    <header class="main-header" id="mainHeader">
        <div class="header-content">
            <div class="header-brand">
                <div class="header-logo">Fn</div>
                <h1 class="header-title">Fastnvite</h1>
            </div>
            <div class="header-center">
                <h2 class="dashboard-title">Dashboard</h2>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['admin_nombre'] ?? 'Usuario Admin'); ?></p>
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
                        <p class="stat-label">Invitaciones Confirmaciones</p>
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
                    <i class="bi bi-people"></i>
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
            
            <!-- Nueva card de acceso en tiempo real -->
            <div class="stat-card" id="accesoCard">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number" id="personasPresentes"><?php echo number_format($stats['personas_presentes']); ?></h3>
                        <p class="stat-label">Personas Ingresadas</p>
                    </div>
                    <div class="stat-icon acceso">
                        <i class="bi bi-door-open"></i>
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
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
let sidebarTimeout;

// Detectar si estamos en móvil
function isMobile() {
    return window.innerWidth <= 768;
}

// Función para mostrar sidebar
function showSidebar() {
    clearTimeout(sidebarTimeout);
    sidebar.classList.add('show');
    sidebarOverlay.classList.add('show');
    
    if (!isMobile()) {
        sidebarIcons.classList.add('hide');
        if (mainContent) mainContent.classList.add('sidebar-open');
        if (mainHeader) mainHeader.classList.add('sidebar-open');
    }
    
    // Cambiar icono del botón hamburguesa
    if (mobileMenuBtn) {
        const icon = mobileMenuBtn.querySelector('i');
        icon.className = 'bi bi-x';
    }
}

// Función para ocultar sidebar
function hideSidebar() {
    sidebar.classList.remove('show');
    sidebarOverlay.classList.remove('show');
    
    if (!isMobile()) {
        sidebarIcons.classList.remove('hide');
        if (mainContent) mainContent.classList.remove('sidebar-open');
        if (mainHeader) mainHeader.classList.remove('sidebar-open');
    }
    
    // Restaurar icono del botón hamburguesa
    if (mobileMenuBtn) {
        const icon = mobileMenuBtn.querySelector('i');
        icon.className = 'bi bi-list';
    }
}

// Eventos solo para desktop (hover)
if (!isMobile()) {
    // Eventos para mostrar sidebar (hover en iconos)
    if (sidebarIcons) {
        sidebarIcons.addEventListener('mouseenter', () => {
            showSidebar();
        });
        
        // Ocultar cuando sale de los iconos
        sidebarIcons.addEventListener('mouseleave', () => {
            sidebarTimeout = setTimeout(() => {
                if (!sidebar.matches(':hover')) {
                    hideSidebar();
                }
            }, 300);
        });
    }

    // Eventos para el área de trigger
    if (sidebarTrigger) {
        sidebarTrigger.addEventListener('mouseenter', () => {
            showSidebar();
        });
        
        // Ocultar cuando sale del área de trigger
        sidebarTrigger.addEventListener('mouseleave', () => {
            sidebarTimeout = setTimeout(() => {
                if (!sidebar.matches(':hover') && !sidebarIcons.matches(':hover')) {
                    hideSidebar();
                }
            }, 500);
        });
    }

    // Mantener sidebar abierto cuando el mouse está sobre él
    sidebar.addEventListener('mouseenter', () => {
        clearTimeout(sidebarTimeout);
    });

    // Ocultar sidebar cuando el mouse sale
    sidebar.addEventListener('mouseleave', () => {
        sidebarTimeout = setTimeout(() => {
            hideSidebar();
        }, 300);
    });
}

// Evento para botón hamburguesa (móvil)
if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (sidebar.classList.contains('show')) {
            hideSidebar();
        } else {
            showSidebar();
        }
    });
}

// Click en trigger para dispositivos táctiles (solo desktop)
if (sidebarTrigger) {
    sidebarTrigger.addEventListener('click', () => {
        if (!isMobile()) {
            if (sidebar.classList.contains('show')) {
                hideSidebar();
            } else {
                showSidebar();
            }
        }
    });
}

// Ocultar sidebar al hacer click en overlay
sidebarOverlay.addEventListener('click', () => {
    hideSidebar();
});

// Ocultar sidebar al hacer click en un enlace de navegación
document.querySelectorAll('.sidebar-nav-item').forEach(item => {
    item.addEventListener('click', (e) => {
        setTimeout(() => {
            hideSidebar();
        }, 100);
    });
});

// Ajustar comportamiento al cambiar tamaño de pantalla
window.addEventListener('resize', () => {
    // Si cambiamos de móvil a desktop, resetear estado
    if (window.innerWidth > 768) {
        // Restaurar elementos de desktop
        if (sidebarIcons) sidebarIcons.style.display = 'flex';
        if (sidebarTrigger) sidebarTrigger.style.display = 'block';
        if (mobileMenuBtn) mobileMenuBtn.style.display = 'none';
        
        // Resetear clases de contenido
        if (mainContent) {
            mainContent.style.paddingTop = '';
            mainContent.classList.remove('sidebar-open');
        }
        if (mainHeader) {
            mainHeader.style.marginTop = '';
            mainHeader.classList.remove('sidebar-open');
        }
        
        // Ocultar sidebar si está abierto
        hideSidebar();
    } else {
        // Ocultar elementos de desktop en móvil
        if (sidebarIcons) sidebarIcons.style.display = 'none';
        if (sidebarTrigger) sidebarTrigger.style.display = 'none';
        if (mobileMenuBtn) mobileMenuBtn.style.display = 'flex';
        
        // Asegurar espaciado para botón hamburguesa
        if (mainContent) {
            mainContent.style.paddingTop = '80px';
            mainContent.classList.remove('sidebar-open');
        }
        if (mainHeader) {
            mainHeader.style.marginTop = '70px';
            mainHeader.classList.remove('sidebar-open');
        }
    }
});

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
            let isFirstLoad = true;
            const source = new EventSource('sse_confirmaciones.php');
            
            source.onmessage = function(event) {
                if (event.data) {
                    const data = JSON.parse(event.data);
                    if (data && data.id_invitado) {
                        // Ignorar la primera carga para evitar mensajes falsos
                        if (isFirstLoad) {
                            isFirstLoad = false;
                            lastId = data.id_confirmacion || 0;
                            return;
                        }
                        
                        // Solo procesar si es una confirmación nueva
                        if (data.id_confirmacion && data.id_confirmacion > lastId) {
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
                }
            };
            
            source.onerror = function(e) {
                console.log('SSE error:', e);
            };
            
            // Marcar como primera carga después de un breve delay
            setTimeout(() => {
                isFirstLoad = false;
            }, 2000);
        }
    })();

    // SSE para estadísticas de acceso en tiempo real
    (function() {
        if (!!window.EventSource) {
            let lastAccesoId = 0;
            let isFirstAccesoLoad = true;
            const sourceAcceso = new EventSource('sse_accesos.php');
            
            sourceAcceso.onmessage = function(event) {
                if (event.data) {
                    try {
                        const data = JSON.parse(event.data);
                        
                        // Ignorar heartbeats
                        if (data.type === 'heartbeat') {
                            return;
                        }
                        
                        // Ignorar la primera carga
                        if (isFirstAccesoLoad) {
                            isFirstAccesoLoad = false;
                            if (data.acceso && data.acceso.id_acceso) {
                                lastAccesoId = data.acceso.id_acceso;
                            }
                            return;
                        }
                        
                        // Solo procesar si es un acceso nuevo
                        if (data.acceso && data.acceso.id_acceso > lastAccesoId) {
                            actualizarEstadisticasAcceso(data.stats);
                            lastAccesoId = data.acceso.id_acceso;
                        }
                    } catch (e) {
                        console.log('Error parsing SSE acceso data:', e);
                    }
                }
            };
            
            sourceAcceso.onerror = function(e) {
                console.log('SSE acceso error:', e);
                // Intentar reconectar después de 5 segundos
                setTimeout(() => {
                    if (sourceAcceso.readyState === EventSource.CLOSED) {
                        location.reload();
                    }
                }, 5000);
            };
            
            // Marcar como primera carga después de un breve delay
            setTimeout(() => {
                isFirstAccesoLoad = false;
            }, 2000);
        }
    })();

    // Función para actualizar estadísticas de acceso
    function actualizarEstadisticasAcceso(stats) {
        if (stats) {
            // Actualizar número principal
            const personasPresentesEl = document.getElementById('personasPresentes');
            
            if (personasPresentesEl) {
                personasPresentesEl.textContent = stats.personas_presentes.toLocaleString();
            }
            
            // Efecto visual de actualización
            const accesoCard = document.getElementById('accesoCard');
            if (accesoCard) {
                accesoCard.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    accesoCard.style.transform = 'scale(1)';
                }, 200);
            }
        }
    }
    </script>
</body>
</html>