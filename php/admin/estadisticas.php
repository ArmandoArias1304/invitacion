<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - FastInvite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <?php
    // Incluir la conexión a la base de datos
    require_once '../config/database.php';
    
    // Obtener estadísticas principales
    $db = getDB();
    $pdo = $db->getConnection();
    
    // 1. Estadísticas principales
    $stats = [];
    
    // Total de invitaciones
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM invitados");
    $stats['total_invitaciones'] = $stmt->fetchColumn();
    
    // Confirmaciones (personas que han confirmado)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT c.id_invitado) as total FROM confirmaciones c");
    $stats['confirmaciones'] = $stmt->fetchColumn();
    
    // Pendientes
    $stats['pendientes'] = $stats['total_invitaciones'] - $stats['confirmaciones'];
    
    // Total de invitados (suma de cupos disponibles)
    $stmt = $pdo->query("SELECT SUM(cupos_disponibles) as total FROM invitados");
    $stats['total_invitados'] = $stmt->fetchColumn() ?: 0;
    
    // Cupos confirmados (suma de cantidad_confirmada)
    $stmt = $pdo->query("SELECT SUM(cantidad_confirmada) as total FROM confirmaciones");
    $stats['cupos_confirmados'] = $stmt->fetchColumn() ?: 0;
    
    // Porcentaje de confirmación
    $stats['porcentaje_confirmacion'] = $stats['total_invitaciones'] > 0 ? 
        round(($stats['confirmaciones'] / $stats['total_invitaciones']) * 100) : 0;
    
    // 2. Distribución por tipo
    $stmt = $pdo->query("
        SELECT tipo_invitado, COUNT(*) as cantidad 
        FROM invitados 
        WHERE tipo_invitado IS NOT NULL 
        GROUP BY tipo_invitado 
        ORDER BY cantidad DESC
    ");
    $distribucion_tipos = $stmt->fetchAll();
    
    // 3. Confirmaciones recientes (últimos 5 días)
    $stmt = $pdo->query("
        SELECT DATE(fecha_confirmacion) as fecha, COUNT(*) as cantidad 
        FROM confirmaciones 
        WHERE fecha_confirmacion >= DATE_SUB(NOW(), INTERVAL 5 DAY)
        GROUP BY DATE(fecha_confirmacion) 
        ORDER BY fecha DESC 
        LIMIT 5
    ");
    $confirmaciones_recientes = $stmt->fetchAll();
    

    
    // 5. Datos pendientes/problemas
    $problemas = [];
    
    // Sin teléfono
    $stmt = $pdo->query("SELECT COUNT(*) FROM invitados WHERE telefono IS NULL OR telefono = ''");
    $problemas['sin_telefono'] = $stmt->fetchColumn();
    
    // Sin mesa asignada
    $stmt = $pdo->query("SELECT COUNT(*) FROM invitados WHERE mesa IS NULL");
    $problemas['sin_mesa'] = $stmt->fetchColumn();
    
    // Sin token
    $stmt = $pdo->query("SELECT COUNT(*) FROM invitados WHERE token IS NULL OR token = ''");
    $problemas['sin_token'] = $stmt->fetchColumn();
    
    // Duplicados de teléfono
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT telefono FROM invitados 
            WHERE telefono IS NOT NULL AND telefono != ''
            GROUP BY telefono HAVING COUNT(*) > 1
        ) as duplicados
    ");
    $problemas['duplicados'] = $stmt->fetchColumn();
    
    // 6. Colores para distribución
    $colores_tipos = [
        'familia' => 'var(--primary-color)',
        'amigo' => 'var(--success-color)', 
        'trabajo' => 'var(--warning-color)',
        'general' => 'var(--secondary-color)',
        'padrinos' => '#8b5cf6',
        'padres' => 'var(--danger-color)'
    ];
    ?>
    
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
            
            /* Sombras elegantes */
            --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-strong: 0 15px 35px rgba(0, 0, 0, 0.3);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            
            /* Header especial (morado pastel) */
            --card-header-background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.2));
            
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.3),
                0 0 25px rgba(99, 102, 241, 0.5),
                0 0 50px rgba(139, 92, 246, 0.3) !important;
            border-color: rgba(99, 102, 241, 0.7) !important;
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

        .stat-icon.invitations { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-icon.confirmed { background: linear-gradient(135deg, var(--success-color), #059669); }
        .stat-icon.pending { background: linear-gradient(135deg, var(--warning-color), #d97706); }
        .stat-icon.guests { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

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

        .card-body-custom.text-center {
    min-height: 300px;
    padding: 2.5rem 1rem; /* Más padding vertical */
}

        /* ===== PROGRESS BARS ===== */
        .progress-container {
            margin-bottom: 1.5rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-dark);
        }

        .progress-bar-custom {
            width: 100%;
            height: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 1s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-success { background: linear-gradient(90deg, var(--success-color), #059669); }
        .progress-warning { background: linear-gradient(90deg, var(--warning-color), #d97706); }
        .progress-primary { background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); }

        /* ===== CIRCULAR PROGRESS ===== */
        .circular-progress {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
        }

        .circular-progress svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .circular-progress .track {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 8;
        }

        .circular-progress .progress {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dasharray 1s ease-in-out;
        }

        .circular-progress .progress.success {
            stroke: url(#gradientSuccess);
        }

        .circular-progress .progress.warning {
            stroke: url(#gradientWarning);
        }

        .circular-progress .progress.primary {
            stroke: url(#gradientPrimary);
        }

        .circular-progress .percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: bold;
            color: rgba(255, 255, 255, 0.9);
        }

        /* ===== TIMELINE ===== */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            padding-left: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            background: var(--primary-color);
            border-radius: 50%;
            border: 3px solid var(--card-background);
        }

        .timeline-date {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-bottom: 0.25rem;
        }

        .timeline-content {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
        }

        /* ===== DISTRIBUTION CHART ===== */
        .distribution-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .distribution-item:last-child {
            border-bottom: none;
        }

        .distribution-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .distribution-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .distribution-value {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        /* ===== TABLE ===== */
        .table-responsive-custom {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            max-height: 400px;
            overflow-y: auto;
        }

        .table-custom {
            margin: 0;
            background: var(--card-background);
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
            text-align: center;
        }

        .table-custom td {
            padding: 1rem;
            border-color: var(--border-color);
            vertical-align: middle;
            background: var(--card-background);
            color: var(--text-dark);
            text-align: center;
        }

        .table-custom tbody tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .table-custom tbody tr:hover td {
            background: rgba(255, 255, 255, 0.05);
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

        @keyframes countUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .counter {
            animation: countUp 0.8s ease-out;
        }

        /* ===== RESPONSIVE ===== */
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
    /* ===== TIMELINE SCROLLEABLE ===== */
.content-card:has(.timeline) .card-body-custom {
    min-height: 347px;
    max-height: 347px; /* Altura fija */
    overflow-y: auto; /* Scroll vertical */
}

/* Scrollbar personalizado */
.content-card:has(.timeline) .card-body-custom::-webkit-scrollbar {
    width: 8px;
}

.content-card:has(.timeline) .card-body-custom::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.content-card:has(.timeline) .card-body-custom::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 4px;
}

.content-card:has(.timeline) .card-body-custom::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
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
    <!-- Botón hamburguesa para móvil -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar Icons (siempre visible en desktop) -->
    <div class="sidebar-icons" id="sidebarIcons">
        <a href="dashboard.php" class="sidebar-icon-item" data-tooltip="Dashboard">
            <i class="bi bi-house"></i>
        </a>
        <a href="generador.php" class="sidebar-icon-item" data-tooltip="Generar Invitados">
            <i class="bi bi-person-plus"></i>
        </a>
        <a href="envios.php" class="sidebar-icon-item" data-tooltip="Enviar Invitaciones">
            <i class="bi bi-whatsapp"></i>
        </a>
        <a href="estadisticas.php" class="sidebar-icon-item active" data-tooltip="Ver Estadísticas">
            <i class="bi bi-graph-up"></i>
        </a>
    </div>

    <!-- Sidebar Trigger (área invisible para hover en desktop) -->
    <div class="sidebar-trigger" id="sidebarTrigger"></div>

    <!-- Sidebar Overlay (fondo oscuro al abrir sidebar en móvil) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar principal -->
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
            <a href="estadisticas.php" class="sidebar-nav-item active">
                <i class="bi bi-graph-up"></i>
                Ver Estadísticas
            </a>
        </div>
        <!-- Sección de usuario en la sidebar (solo visible en móvil) -->
        <div class="sidebar-user-section">
            <div class="sidebar-user-info">
                <p class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['admin_nombre'] ?? 'Usuario Admin'); ?></p>
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
                <h2 class="dashboard-title">Estadísticas</h2>
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
                        <h3 class="stat-number counter"><?php echo $stats['total_invitaciones']; ?></h3>
                        <p class="stat-label">Total Invitaciones</p>
                    </div>
                    <div class="stat-icon invitations">
                        <i class="bi bi-envelope-paper"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number counter"><?php echo $stats['confirmaciones']; ?></h3>
                        <p class="stat-label">Confirmaciones</p>
                    </div>
                    <div class="stat-icon confirmed">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number counter"><?php echo $stats['pendientes']; ?></h3>
                        <p class="stat-label">Pendientes</p>
                    </div>
                    <div class="stat-icon pending">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number counter"><?php echo $stats['cupos_confirmados']; ?></h3>
                        <p class="stat-label">Invitados Confirmados</p>
                    </div>
                    <div class="stat-icon guests">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="content-card animate-fade-in">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-pie-chart me-2"></i>Resumen General</h3>
                    </div>
                    <div class="card-body-custom text-center">
                        <div class="circular-progress">
                            <svg viewBox="0 0 120 120">
                                <defs>
                                    <linearGradient id="gradientSuccess" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#10b981"/>
                                        <stop offset="100%" style="stop-color:#059669"/>
                                    </linearGradient>
                                </defs>
                                <circle class="track" cx="60" cy="60" r="54"></circle>
                                <circle class="progress success" cx="60" cy="60" r="54" 
                                        stroke-dasharray="339.3" 
                                        stroke-dashoffset="<?php echo 339.3 - (339.3 * $stats['porcentaje_confirmacion'] / 100); ?>"></circle>
                            </svg>
                            <div class="percentage"><?php echo $stats['porcentaje_confirmacion']; ?>%</div>
                        </div>
                        <h5 class="text-center mb-0" style="color: rgba(255, 255, 255, 0.9);">Confirmaciones</h5>
                        <p class="text-center" style="color: var(--dark-gray); font-size: 0.875rem;">
                            <?php echo $stats['confirmaciones']; ?> de <?php echo $stats['total_invitaciones']; ?> invitaciones
                        </p>
                        
                        <!-- Stats adicionales -->
                        <div class="row g-2 mt-3">
                            <div class="col-6">
                                <div style="background: rgba(255, 255, 255, 0.05); padding: 0.75rem; border-radius: 0.5rem;">
                                    <div style="color: var(--success-color); font-weight: 600; font-size: 1.1rem;">
                                        <?php echo $stats['cupos_confirmados']; ?>
                                    </div>
                                    <div style="color: var(--dark-gray); font-size: 0.8rem;">Invitados</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255, 255, 255, 0.05); padding: 0.75rem; border-radius: 0.5rem;">
                                    <div style="color: var(--warning-color); font-weight: 600; font-size: 1.1rem;">
                                        <?php echo $stats['total_invitados']; ?>
                                    </div>
                                    <div style="color: var(--dark-gray); font-size: 0.8rem;">Cupos Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="content-card animate-fade-in">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-diagram-3 me-2"></i>Distribución por Tipo</h3>
                    </div>
                    <div class="card-body-custom">
                        <?php if (!empty($distribucion_tipos)): ?>
                            <?php foreach ($distribucion_tipos as $tipo): ?>
                            <div class="distribution-item">
                                <div class="distribution-label">
                                    <div class="distribution-color" style="background: <?php echo $colores_tipos[$tipo['tipo_invitado']] ?? 'var(--dark-gray)'; ?>;"></div>
                                    <span><?php echo ucfirst($tipo['tipo_invitado'] ?? 'Sin tipo'); ?></span>
                                </div>
                                <div class="distribution-value"><?php echo $tipo['cantidad']; ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--dark-gray); text-align: center;">No hay datos de tipos de invitado</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline and Executive Summary -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="content-card animate-fade-in">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-calendar-event me-2"></i>Confirmaciones Recientes</h3>
                    </div>
                    <div class="card-body-custom timeline-container">
                        <?php if (!empty($confirmaciones_recientes)): ?>
                            <div class="timeline">
                                <?php foreach ($confirmaciones_recientes as $index => $confirmacion): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date">
                                        <?php 
                                        $fecha = new DateTime($confirmacion['fecha']);
                                        $hoy = new DateTime();
                                        $ayer = new DateTime('-1 day');
                                        
                                        if ($fecha->format('Y-m-d') == $hoy->format('Y-m-d')) {
                                            echo 'Hoy';
                                        } elseif ($fecha->format('Y-m-d') == $ayer->format('Y-m-d')) {
                                            echo 'Ayer';
                                        } else {
                                            echo $fecha->format('d M');
                                        }
                                        ?>
                                    </div>
                                    <div class="timeline-content">
                                        <strong style="color: rgba(255, 255, 255, 0.9);">
                                            <?php echo $confirmacion['cantidad']; ?> nueva<?php echo $confirmacion['cantidad'] > 1 ? 's' : ''; ?> confirmación<?php echo $confirmacion['cantidad'] > 1 ? 'es' : ''; ?>
                                        </strong>
                                        <p style="margin: 0; color: var(--dark-gray); font-size: 0.875rem;">
                                            <?php 
                                            if ($index == 0) {
                                                echo $confirmacion['cantidad'] >= 5 ? 'Alta actividad' : 'Actividad normal';
                                            } else {
                                                echo 'Confirmaciones del día';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="color: var(--dark-gray); padding: 2rem;">
                                <i class="bi bi-calendar-x" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>No hay confirmaciones recientes</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="content-card animate-fade-in">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-clipboard-data me-2"></i>Resumen Ejecutivo</h3>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-4">
                            <div class="col-12">
                                <h5 style="color: rgba(255, 255, 255, 0.9); margin-bottom: 1rem;">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Aspectos Positivos
                                </h5>
                                <ul style="color: var(--text-dark); padding-left: 1.5rem; margin-bottom: 2rem;">
                                    <li>Tasa de confirmación del <?php echo $stats['porcentaje_confirmacion']; ?>%</li>
                                    <li><?php echo $stats['cupos_confirmados']; ?> invitados han confirmado su asistencia</li>
                                    <?php if (!empty($confirmaciones_recientes)): ?>
                                    <li>Actividad reciente: <?php echo array_sum(array_column($confirmaciones_recientes, 'cantidad')); ?> confirmaciones en los últimos días</li>
                                    <?php endif; ?>
                                    <li><?php echo count($distribucion_tipos); ?> tipos diferentes de invitados</li>
                                </ul>
                            </div>
                            <div class="col-12">
                                <h5 style="color: rgba(255, 255, 255, 0.9); margin-bottom: 1rem;">
                                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                    Áreas de Mejora
                                </h5>
                                <ul style="color: var(--text-dark); padding-left: 1.5rem;">
                                    <?php if ($problemas['sin_telefono'] > 0): ?>
                                    <li><?php echo $problemas['sin_telefono']; ?> invitados sin número de teléfono</li>
                                    <?php endif; ?>
                                    <?php if ($problemas['sin_mesa'] > 0): ?>
                                    <li><?php echo $problemas['sin_mesa']; ?> invitados sin mesa asignada</li>
                                    <?php endif; ?>
                                    <li><?php echo $stats['pendientes']; ?> confirmaciones pendientes</li>
                                    <?php if ($problemas['duplicados'] > 0): ?>
                                    <li><?php echo $problemas['duplicados']; ?> números de teléfono duplicados</li>
                                    <?php endif; ?>
                                    <?php if ($problemas['sin_token'] > 0): ?>
                                    <li><?php echo $problemas['sin_token']; ?> invitados sin token generado</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    
            
            <div class="col-lg-12">
                <div class="content-card animate-fade-in">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-exclamation-triangle me-2"></i>Datos Pendientes</h3>
                    </div>
                    <div class="card-body-custom">
                        <div class="table-responsive-custom">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Problema</th>
                                        <th>Cantidad</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($problemas['sin_telefono'] > 0): ?>
                                    <tr>
                                        <td>Sin teléfono</td>
                                        <td><strong style="color: var(--danger-color);"><?php echo $problemas['sin_telefono']; ?></strong></td>
                                        <td><button class="btn btn-primary-custom btn-sm">Corregir</button></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php if ($problemas['sin_mesa'] > 0): ?>
                                    <tr>
                                        <td>Sin mesa asignada</td>
                                        <td><strong style="color: var(--warning-color);"><?php echo $problemas['sin_mesa']; ?></strong></td>
                                        <td><button class="btn btn-warning-custom btn-sm">Asignar</button></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php if ($problemas['sin_token'] > 0): ?>
                                    <tr>
                                        <td>Sin token</td>
                                        <td><strong style="color: var(--danger-color);"><?php echo $problemas['sin_token']; ?></strong></td>
                                        <td><button class="btn btn-success-custom btn-sm">Generar</button></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php if ($problemas['duplicados'] > 0): ?>
                                    <tr>
                                        <td>Teléfonos duplicados</td>
                                        <td><strong style="color: var(--danger-color);"><?php echo $problemas['duplicados']; ?></strong></td>
                                        <td><button class="btn btn-danger-custom btn-sm">Revisar</button></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php if ($problemas['sin_telefono'] == 0 && $problemas['sin_mesa'] == 0 && $problemas['sin_token'] == 0 && $problemas['duplicados'] == 0): ?>
                                    <tr>
                                        <td colspan="3" class="text-center" style="color: var(--success-color);">
                                            <i class="bi bi-check-circle me-2"></i>¡Todo está en orden!
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
       
       

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
        function isMobile() { return window.innerWidth <= 768; }
        function showSidebar() {
            clearTimeout(sidebarTimeout);
            sidebar.classList.add('show');
            sidebarOverlay.classList.add('show');
            if (!isMobile()) {
                sidebarIcons.classList.add('hide');
                if (mainContent) mainContent.classList.add('sidebar-open');
                if (mainHeader) mainHeader.classList.add('sidebar-open');
            }
            if (mobileMenuBtn) {
                const icon = mobileMenuBtn.querySelector('i');
                icon.className = 'bi bi-x';
            }
        }
        function hideSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            if (!isMobile()) {
                sidebarIcons.classList.remove('hide');
                if (mainContent) mainContent.classList.remove('sidebar-open');
                if (mainHeader) mainHeader.classList.remove('sidebar-open');
            }
            if (mobileMenuBtn) {
                const icon = mobileMenuBtn.querySelector('i');
                icon.className = 'bi bi-list';
            }
        }
        if (!isMobile()) {
            sidebarIcons.addEventListener('mouseenter', () => { showSidebar(); });
            sidebarTrigger.addEventListener('mouseenter', () => { showSidebar(); });
            sidebar.addEventListener('mouseenter', () => { clearTimeout(sidebarTimeout); });
            sidebar.addEventListener('mouseleave', () => { sidebarTimeout = setTimeout(() => { hideSidebar(); }, 300); });
            sidebarTrigger.addEventListener('mouseleave', () => {
                sidebarTimeout = setTimeout(() => {
                    if (!sidebar.matches(':hover') && !sidebarIcons.matches(':hover')) {
                        hideSidebar();
                    }
                }, 500);
            });
            sidebarIcons.addEventListener('mouseleave', () => {
                sidebarTimeout = setTimeout(() => {
                    if (!sidebar.matches(':hover')) {
                        hideSidebar();
                    }
                }, 300);
            });
        }
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
        sidebarOverlay.addEventListener('click', () => {
            hideSidebar();
        });
        document.querySelectorAll('.sidebar-nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                setTimeout(() => {
                    hideSidebar();
                }, 100);
            });
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                if (sidebarIcons) sidebarIcons.style.display = 'flex';
                if (sidebarTrigger) sidebarTrigger.style.display = 'block';
                if (mobileMenuBtn) mobileMenuBtn.style.display = 'none';
                if (mainContent) {
                    mainContent.style.paddingTop = '';
                    mainContent.classList.remove('sidebar-open');
                }
                if (mainHeader) {
                    mainHeader.style.marginTop = '';
                    mainHeader.classList.remove('sidebar-open');
                }
                hideSidebar();
            } else {
                if (sidebarIcons) sidebarIcons.style.display = 'none';
                if (sidebarTrigger) sidebarTrigger.style.display = 'none';
                if (mobileMenuBtn) mobileMenuBtn.style.display = 'flex';
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

        // Animate numbers on load
        function animateNumbers() {
            const counters = document.querySelectorAll('.counter');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 16);
            });
        }

       

        function actualizarDatos() {
            location.reload();
        }

        // Initialize animations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateNumbers, 300);
        });

        // Responsive handling
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>