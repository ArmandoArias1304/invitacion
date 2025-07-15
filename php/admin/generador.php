<?php
require_once 'auth_check.php';

$mensaje = '';
$error = '';

// Función para verificar si el teléfono ya existe
function telefonoExiste($telefono, $conn) {
    if (empty($telefono)) return false;
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM invitados WHERE telefono = ?");
    $stmt->execute([$telefono]);
    return $stmt->fetchColumn() > 0;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $conn = $db->getConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generar_individual':
                $nombre_completo = sanitizeInput($_POST['nombre_completo']);
                $telefono = sanitizeInput($_POST['telefono']);
                $cupos_disponibles = (int)$_POST['cupos_disponibles'];
                $mesa = $_POST['mesa'] ? (int)$_POST['mesa'] : null;
                $tipo_invitado = sanitizeInput($_POST['tipo_invitado']);
                
                // Validaciones
                if (empty($nombre_completo)) {
                    $error = 'El nombre completo es requerido';
                } elseif (!empty($telefono) && strlen($telefono) !== 10) {
                    $error = 'El teléfono debe tener exactamente 10 dígitos';
                } elseif (!empty($telefono) && !ctype_digit($telefono)) {
                    $error = 'El teléfono solo debe contener números';
                } elseif (!empty($telefono) && telefonoExiste($telefono, $conn)) {
                    $error = 'Ya existe un invitado con este número de teléfono. Por favor usa otro número.';
                } else {
                    try {
                        // Generar token único
                        do {
                            $token = Database::generateToken();
                        } while ($db->tokenExists($token));
                        
                        $stmt = $conn->prepare("
                            INSERT INTO invitados (nombre_completo, telefono, cupos_disponibles, mesa, tipo_invitado, token)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([$nombre_completo, $telefono, $cupos_disponibles, $mesa, $tipo_invitado, $token]);
                        $id_insertado = $conn->lastInsertId();
                        $mensaje = "✅ Invitado generado exitosamente!<br><strong>ID:</strong> $id_insertado<br><strong>Token:</strong> $token<br><br><a href='dashboard.php' class='btn btn-success-custom'>Ver en Dashboard</a>";
                        
                    } catch (Exception $e) {
                        $error = 'Error al generar invitado: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'generar_masivo':
                $lista_invitados = $_POST['lista_invitados'];
                $lineas = explode("\n", $lista_invitados);
                $generados = 0;
                $errores = [];
                $telefonos_procesados = [];
                
                foreach ($lineas as $num_linea => $linea) {
                    $linea = trim($linea);
                    if (empty($linea)) continue;
                    
                    $datos = explode(',', $linea);
                    if (count($datos) < 1) {
                        $errores[] = "Línea " . ($num_linea + 1) . ": Formato inválido";
                        continue;
                    }
                    
                    $nombre_completo = trim($datos[0]);
                    $telefono = isset($datos[1]) ? trim($datos[1]) : '';
                    $cupos_disponibles = isset($datos[2]) ? (int)trim($datos[2]) : 1;
                    $mesa = isset($datos[3]) && !empty(trim($datos[3])) ? (int)trim($datos[3]) : null;
                    $tipo_invitado = isset($datos[4]) ? trim($datos[4]) : 'general';
                    
                    // Validaciones por línea
                    if (empty($nombre_completo)) {
                        $errores[] = "Línea " . ($num_linea + 1) . ": Nombre vacío";
                        continue;
                    }
                    
                    if (!empty($telefono)) {
                        if (strlen($telefono) !== 10 || !ctype_digit($telefono)) {
                            $errores[] = "Línea " . ($num_linea + 1) . ": Teléfono '$telefono' debe tener exactamente 10 dígitos";
                            continue;
                        }
                        
                        if (telefonoExiste($telefono, $conn)) {
                            $errores[] = "Línea " . ($num_linea + 1) . ": Teléfono '$telefono' ya existe en la base de datos";
                            continue;
                        }
                        
                        if (in_array($telefono, $telefonos_procesados)) {
                            $errores[] = "Línea " . ($num_linea + 1) . ": Teléfono '$telefono' duplicado en la lista";
                            continue;
                        }
                        
                        $telefonos_procesados[] = $telefono;
                    }
                    
                    try {
                        do {
                            $token = Database::generateToken();
                        } while ($db->tokenExists($token));
                        
                        $stmt = $conn->prepare("
                            INSERT INTO invitados (nombre_completo, telefono, cupos_disponibles, mesa, tipo_invitado, token)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([$nombre_completo, $telefono, $cupos_disponibles, $mesa, $tipo_invitado, $token]);
                        $generados++;
                        
                    } catch (Exception $e) {
                        $errores[] = "Línea " . ($num_linea + 1) . " ('$nombre_completo'): " . $e->getMessage();
                    }
                }
                
                $mensaje = "Se generaron $generados invitados exitosamente.";
                if (!empty($errores)) {
                    $error = implode('<br>', $errores);
                }
                break;
                
            case 'verificar_telefono':
                header('Content-Type: application/json');
                $telefono = sanitizeInput($_POST['telefono']);
                
                $response = ['existe' => false, 'valido' => true, 'mensaje' => ''];
                
                if (!empty($telefono)) {
                    if (strlen($telefono) !== 10 || !ctype_digit($telefono)) {
                        $response['valido'] = false;
                        $response['mensaje'] = 'El teléfono debe tener exactamente 10 dígitos';
                    } elseif (telefonoExiste($telefono, $conn)) {
                        $response['existe'] = true;
                        $response['mensaje'] = 'Este número ya está registrado';
                    } else {
                        $response['mensaje'] = 'Número disponible';
                    }
                }
                
                echo json_encode($response);
                exit;
        }
    }
}

// Obtener estadísticas
$db = getDB();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados");
$stmt->execute();
$total_invitados = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados WHERE mesa IS NOT NULL");
$stmt->execute();
$con_mesa = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados WHERE telefono IS NOT NULL AND telefono != ''");
$stmt->execute();
$con_telefono = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Invitados - FastInvite</title>
    
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
        .stat-icon.tables { background: linear-gradient(135deg, var(--warning-color), #d97706); }
        .stat-icon.phones { background: linear-gradient(135deg, var(--success-color), #059669); }

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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: var(--transition);
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.8rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-grid-full {
            grid-column: 1 / -1;
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
            cursor: pointer;
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

        .btn-secondary-custom {
            background: var(--dark-gray);
            color: white;
        }

        .btn-secondary-custom:hover {
            background: var(--text-dark);
            color: white;
            transform: translateY(-1px);
        }

        .btn:disabled {
            background: var(--dark-gray) !important;
            cursor: not-allowed;
            transform: none !important;
            opacity: 0.6;
        }

        /* ===== ALERTS ===== */
        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid;
            font-size: 0.875rem;
        }

        .alert-success-custom {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .alert-danger-custom {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-color: rgba(239, 68, 68, 0.2);
        }

        /* ===== VALIDATION ===== */
        .input-group {
            position: relative;
        }

        .validation-message {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            display: none;
        }

        .validation-success {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .validation-error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .validation-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .input-validated {
            border-color: var(--success-color) !important;
        }

        .input-error {
            border-color: var(--danger-color) !important;
        }

        .spinner {
            display: none;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        .help-text {
            font-size: 0.75rem;
            color: var(--dark-gray);
            margin-top: 0.25rem;
        }

        /* ===== TEMPLATE BOX ===== */
        .template-box {
            background: var(--light-gray);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }

        .template-box h5 {
            color: var(--text-dark);
            font-size: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .template-textarea {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.8rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            padding: 0.75rem;
            resize: none;
            height: 100px;
            width: 100%;
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

            .form-grid {
                grid-template-columns: 1fr;
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
                padding-top: 100px;
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
    </style>
</head>
<body>
    <!-- Sidebar Icons (always visible) -->
    <div class="sidebar-icons" id="sidebarIcons">
        <a href="dashboard.php" class="sidebar-icon-item" data-tooltip="Dashboard">
            <i class="bi bi-house"></i>
        </a>
        <a href="generador.php" class="sidebar-icon-item active" data-tooltip="Generar Invitados">
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
                <h2 class="dashboard-title">👥 Generador de Invitados</h2>
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
                        <h3 class="stat-number"><?php echo number_format($total_invitados); ?></h3>
                        <p class="stat-label">Total de Invitados</p>
                    </div>
                    <div class="stat-icon users">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo number_format($con_mesa); ?></h3>
                        <p class="stat-label">Con Mesa Asignada</p>
                    </div>
                    <div class="stat-icon tables">
                        <i class="bi bi-table"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo number_format($con_telefono); ?></h3>
                        <p class="stat-label">Con Teléfono</p>
                    </div>
                    <div class="stat-icon phones">
                        <i class="bi bi-telephone"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($mensaje): ?>
            <div class="alert-custom alert-success-custom animate-fade-in">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-custom alert-danger-custom animate-fade-in">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="row g-4">
            <!-- Individual Guest Generation -->
            <div class="col-lg-6">
                <div class="content-card animate-fade-in">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-person-plus me-2"></i>Generar Invitado Individual</h3>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" id="formIndividual">
                            <input type="hidden" name="action" value="generar_individual">
                            
                            <div class="form-group">
                                <label for="nombre_completo">Nombre Completo *</label>
                                <input type="text" id="nombre_completo" name="nombre_completo" required>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <div class="input-group">
                                        <input type="tel" id="telefono" name="telefono" placeholder="1234567890" maxlength="10" pattern="[0-9]{10}">
                                        <div class="spinner" id="telefonoSpinner"></div>
                                    </div>
                                    <div class="help-text">Exactamente 10 dígitos, solo números</div>
                                    <div class="validation-message" id="telefonoValidation"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cupos_disponibles">Cupos Disponibles</label>
                                    <input type="number" id="cupos_disponibles" name="cupos_disponibles" value="1" min="1" max="10">
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="mesa">Mesa</label>
                                    <input type="number" id="mesa" name="mesa" min="1" placeholder="Opcional">
                                </div>
                                
                                <div class="form-group">
                                    <label for="tipo_invitado">Tipo de Invitado</label>
                                    <select id="tipo_invitado" name="tipo_invitado">
                                        <option value="general">General</option>
                                        <option value="familia">Familia</option>
                                        <option value="amigo">Amigo</option>
                                        <option value="trabajo">Trabajo</option>
                                        <option value="padrinos">Padrinos</option>
                                        <option value="padres">Padres</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-custom btn-primary-custom" id="btnGenerar">
                                <i class="bi bi-plus-circle"></i>
                                Generar Invitado
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Mass Generation -->
            <div class="col-lg-6">
                <div class="content-card animate-fade-in">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-people-fill me-2"></i>Generación Masiva</h3>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST">
                            <input type="hidden" name="action" value="generar_masivo">
                            
                            <div class="form-group">
                                <label for="lista_invitados">Lista de Invitados</label>
                                <textarea id="lista_invitados" name="lista_invitados" placeholder="Formato: Nombre Completo, Teléfono, Cupos, Mesa, Tipo&#10;Juan Pérez, 1234567890, 2, 1, familia&#10;María García, 0987654321, 1, 2, amigo" required></textarea>
                                <div class="help-text">
                                    Formato por línea: Nombre, Teléfono (10 dígitos), Cupos, Mesa, Tipo<br>
                                    Solo el nombre es obligatorio. Separar con comas.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-custom btn-success-custom">
                                <i class="bi bi-upload"></i>
                                Generar Masivamente
                            </button>
                        </form>
                        
                        <div class="template-box">
                            <h5><i class="bi bi-clipboard-data text-primary"></i>Plantilla de Ejemplo</h5>
                            <textarea readonly class="template-textarea">Juan Pérez González, 1234567890, 2, 1, familia
María García López, 0987654321, 1, 2, amigo
Carlos Rodríguez, , 1, 3, trabajo
Ana Martínez, 5555555555, 3, , padrinos</textarea>
                            <div class="help-text">
                                Copia este formato y modifica con tus datos. Los teléfonos deben tener exactamente 10 dígitos.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Information Card -->
        <div class="content-card animate-fade-in">
            <div class="card-header-custom">
                <h3><i class="bi bi-info-circle me-2"></i>Información Importante</h3>
            </div>
            <div class="card-body-custom">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); width: 40px; height: 40px; font-size: 1.2rem;">
                                <i class="bi bi-key"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Tokens</h5>
                                <p class="text-muted small mb-0">Cada invitado recibe un token único de 12 caracteres que servirá para acceder a su invitación y confirmar asistencia.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon phones" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Teléfonos</h5>
                                <p class="text-muted small mb-0"><strong>Importante:</strong> Los números de teléfono deben tener exactamente 10 dígitos y ser únicos. No se permiten duplicados.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon tables" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                <i class="bi bi-table"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Mesas</h5>
                                <p class="text-muted small mb-0">La asignación de mesas es opcional pero recomendada para eventos grandes. Puedes asignarlas después.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon users" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                <i class="bi bi-people"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Cupos</h5>
                                <p class="text-muted small mb-0">Indica cuántas personas puede traer cada invitado. Por defecto es 1 (solo el invitado principal).</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

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

        // Función para mostrar sidebar
        function showSidebar() {
            clearTimeout(sidebarTimeout);
            sidebar.classList.add('show');
            sidebarOverlay.classList.add('show');
            sidebarIcons.classList.add('hide');
            if (mainContent) mainContent.classList.add('sidebar-open');
            if (mainHeader) mainHeader.classList.add('sidebar-open');
        }

        // Función para ocultar sidebar
        function hideSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            sidebarIcons.classList.remove('hide');
            if (mainContent) mainContent.classList.remove('sidebar-open');
            if (mainHeader) mainHeader.classList.remove('sidebar-open');
        }

        // Eventos para mostrar sidebar (hover en iconos)
        sidebarIcons.addEventListener('mouseenter', () => {
            showSidebar();
        });

        // Eventos para el área de trigger
        sidebarTrigger.addEventListener('mouseenter', () => {
            showSidebar();
        });

        // Click en trigger para dispositivos táctiles
        sidebarTrigger.addEventListener('click', () => {
            if (sidebar.classList.contains('show')) {
                hideSidebar();
            } else {
                showSidebar();
            }
        });

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

        // Ocultar cuando sale del área de trigger
        sidebarTrigger.addEventListener('mouseleave', () => {
            sidebarTimeout = setTimeout(() => {
                if (!sidebar.matches(':hover') && !sidebarIcons.matches(':hover')) {
                    hideSidebar();
                }
            }, 500);
        });

        // Ocultar cuando sale de los iconos
        sidebarIcons.addEventListener('mouseleave', () => {
            sidebarTimeout = setTimeout(() => {
                if (!sidebar.matches(':hover')) {
                    hideSidebar();
                }
            }, 300);
        });

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

        // Phone validation
        let validacionTimeout;
        
        // Validación en tiempo real del teléfono
        document.getElementById('telefono').addEventListener('input', function(e) {
            // Solo permitir números
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
            
            const validationDiv = document.getElementById('telefonoValidation');
            const spinner = document.getElementById('telefonoSpinner');
            const btnGenerar = document.getElementById('btnGenerar');
            
            // Limpiar timeout anterior
            clearTimeout(validacionTimeout);
            
            if (value === '') {
                validationDiv.style.display = 'none';
                e.target.classList.remove('input-validated', 'input-error');
                btnGenerar.disabled = false;
                return;
            }
            
            if (value.length !== 10) {
                validationDiv.className = 'validation-message validation-error';
                validationDiv.textContent = `Faltan ${10 - value.length} dígitos`;
                validationDiv.style.display = 'block';
                e.target.classList.remove('input-validated');
                e.target.classList.add('input-error');
                btnGenerar.disabled = true;
                return;
            }
            
            // Mostrar spinner y validar con el servidor
            spinner.style.display = 'block';
            validationDiv.style.display = 'none';
            e.target.classList.remove('input-validated', 'input-error');
            
            validacionTimeout = setTimeout(() => {
                fetch('generador.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verificar_telefono&telefono=${value}`
                })
                .then(response => response.json())
                .then(data => {
                    spinner.style.display = 'none';
                    
                    if (!data.valido) {
                        validationDiv.className = 'validation-message validation-error';
                        validationDiv.textContent = data.mensaje;
                        e.target.classList.add('input-error');
                        btnGenerar.disabled = true;
                    } else if (data.existe) {
                        validationDiv.className = 'validation-message validation-error';
                        validationDiv.textContent = '❌ ' + data.mensaje + '. Usa otro número.';
                        e.target.classList.add('input-error');
                        btnGenerar.disabled = true;
                    } else {
                        validationDiv.className = 'validation-message validation-success';
                        validationDiv.textContent = '✅ ' + data.mensaje;
                        e.target.classList.add('input-validated');
                        btnGenerar.disabled = false;
                    }
                    
                    validationDiv.style.display = 'block';
                })
                .catch(error => {
                    spinner.style.display = 'none';
                    console.error('Error:', error);
                    validationDiv.className = 'validation-message validation-warning';
                    validationDiv.textContent = 'Error al verificar el teléfono';
                    validationDiv.style.display = 'block';
                });
            }, 500);
        });
        
        // Validación del formulario antes de enviar
        document.getElementById('formIndividual').addEventListener('submit', function(e) {
            const telefono = document.getElementById('telefono').value;
            const validationDiv = document.getElementById('telefonoValidation');
            
            if (telefono && telefono.length !== 10) {
                e.preventDefault();
                alert('El teléfono debe tener exactamente 10 dígitos');
                return;
            }
            
            if (validationDiv.classList.contains('validation-error') && validationDiv.style.display === 'block') {
                e.preventDefault();
                alert('Por favor corrige el error en el teléfono antes de continuar');
                return;
            }
        });
        
        // Validación de formulario masivo
        document.querySelector('form[method="POST"]:nth-of-type(2)').addEventListener('submit', function(e) {
            const textarea = document.getElementById('lista_invitados');
            const lineas = textarea.value.trim().split('\n');
            let errores = [];
            let telefonos = [];
            
            lineas.forEach((linea, index) => {
                linea = linea.trim();
                if (!linea) return;
                
                const datos = linea.split(',');
                if (datos.length < 1 || !datos[0].trim()) {
                    errores.push(`Línea ${index + 1}: Falta el nombre`);
                    return;
                }
                
                if (datos.length > 1) {
                    const telefono = datos[1].trim();
                    if (telefono) {
                        if (telefono.length !== 10 || !/^\d+$/.test(telefono)) {
                            errores.push(`Línea ${index + 1}: Teléfono '${telefono}' debe tener exactamente 10 dígitos`);
                        } else if (telefonos.includes(telefono)) {
                            errores.push(`Línea ${index + 1}: Teléfono '${telefono}' duplicado en la lista`);
                        } else {
                            telefonos.push(telefono);
                        }
                    }
                }
            });
            
            if (errores.length > 0) {
                e.preventDefault();
                alert('Errores encontrados:\n' + errores.slice(0, 10).join('\n') + 
                     (errores.length > 10 ? '\n... y ' + (errores.length - 10) + ' errores más' : ''));
            }
        });
        
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>