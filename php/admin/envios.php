<?php
require_once 'auth_check.php';

$mensaje = '';
$error = '';

// Obtener invitados
$db = getDB();
$conn = $db->getConnection();

// Filtros
$filtro_mesa = $_GET['mesa'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_confirmado = $_GET['confirmado'] ?? '';
$buscar = $_GET['buscar'] ?? '';

// Construir consulta
$where_conditions = [];
$params = [];

if (!empty($filtro_mesa)) {
    $where_conditions[] = "i.mesa = ?";
    $params[] = $filtro_mesa;
}

if (!empty($filtro_tipo)) {
    $where_conditions[] = "i.tipo_invitado = ?";
    $params[] = $filtro_tipo;
}

if (!empty($buscar)) {
    $where_conditions[] = "(i.nombre_completo LIKE ? OR i.telefono LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($filtro_confirmado !== '') {
    if ($filtro_confirmado == '1') {
        $where_conditions[] = "c.id_confirmacion IS NOT NULL";
    } else {
        $where_conditions[] = "c.id_confirmacion IS NULL";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion
    FROM invitados i
    LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
    $where_clause
    ORDER BY i.nombre_completo
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$invitados = $stmt->fetchAll();

// Obtener opciones para filtros
$stmt = $conn->prepare("SELECT DISTINCT mesa FROM invitados WHERE mesa IS NOT NULL ORDER BY mesa");
$stmt->execute();
$mesas_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("SELECT DISTINCT tipo_invitado FROM invitados WHERE tipo_invitado IS NOT NULL ORDER BY tipo_invitado");
$stmt->execute();
$tipos_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Procesar envíos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'enviar_individual':
            $id_invitado = (int)$_POST['id_invitado'];
            
            // Obtener datos del invitado
            $stmt = $conn->prepare("SELECT * FROM invitados WHERE id_invitado = ?");
            $stmt->execute([$id_invitado]);
            $invitado = $stmt->fetch();
            
            if ($invitado && !empty($invitado['telefono'])) {
                $mensaje_whatsapp = generarMensajeWhatsApp($invitado);
                $url_whatsapp = generarUrlWhatsApp($invitado['telefono'], $mensaje_whatsapp);
                
                echo "<script>window.open('$url_whatsapp', '_blank');</script>";
                $mensaje = "Enviando invitación a " . htmlspecialchars($invitado['nombre_completo']);
            } else {
                $error = "El invitado no tiene teléfono registrado";
            }
            break;
            
        case 'enviar_masivo':
            $invitados_seleccionados = $_POST['invitados_seleccionados'] ?? [];
            $enviados = 0;
            $errores = [];
            
            foreach ($invitados_seleccionados as $id_invitado) {
                $stmt = $conn->prepare("SELECT * FROM invitados WHERE id_invitado = ?");
                $stmt->execute([$id_invitado]);
                $invitado = $stmt->fetch();
                
                if ($invitado && !empty($invitado['telefono'])) {
                    $enviados++;
                } else {
                    $errores[] = $invitado['nombre_completo'] . " (sin teléfono)";
                }
            }
            
            if ($enviados > 0) {
                $mensaje = "Preparados $enviados envíos. Haz clic en 'Enviar Seleccionados' para abrir WhatsApp.";
            }
            
            if (!empty($errores)) {
                $error = "Errores: " . implode(', ', $errores);
            }
            break;
        case 'marcar_enviado':
            header('Content-Type: application/json');
            $id_invitado = (int)$_POST['id_invitado'];
            try {
                $stmt = $conn->prepare("UPDATE invitados SET enviado_whatsapp = NOW() WHERE id_invitado = ?");
                $resultado = $stmt->execute([$id_invitado]);
                if ($resultado) {
                    echo json_encode([
                        'success' => true,
                        'mensaje' => 'Marcado como enviado',
                        'fecha' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Error al actualizar la base de datos'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error: ' . $e->getMessage()
                ]);
            }
            exit;
    }
}

// Funciones auxiliares movidas a database.php
// generarMensajeWhatsApp() y generarUrlWhatsApp() están ahora en database.php
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envío de Invitaciones - FastInvite</title>
    
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
            --whatsapp-color: #25D366;
            --whatsapp-dark: #128C7E;
            
            /* Paleta oscura elegante */
            --light-gray: rgba(30, 30, 50, 0.9);
            --dark-gray: #64748b;
            --text-dark: #e2e8f0;
            --border-color: rgba(255, 255, 255, 0.1);
            
            /* Fondos específicos modo oscuro */
            --body-background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            --card-background: rgba(30, 30, 50, 0.8);
            --header-background: rgba(30, 30, 50, 0.8);
            --input-background: rgba(30, 30, 50, 0.9);
            --modal-background: rgba(30, 30, 50, 0.95);
            --table-background: rgba(30, 30, 50, 0.8);
            --variables-background: rgba(30, 30, 50, 0.8);
            --variable-item-background: rgba(30, 30, 50, 0.9);
            --mensaje-preview-background: rgba(30, 30, 50, 0.9);
            
            /* Sombras elegantes */
            --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-strong: 0 15px 35px rgba(0, 0, 0, 0.3);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-modal: 0 20px 60px rgba(0, 0, 0, 0.5);
            
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-background);
            backdrop-filter: blur(20px);
            border-radius: 1rem;
            padding: 1.5rem;
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

        .stat-icon.shown { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-icon.phones { background: linear-gradient(135deg, var(--success-color), #059669); }
        .stat-icon.sent { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

        .stat-number {
            font-size: 2rem;
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
        }

        .card-header-custom {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--card-header-background);
            backdrop-filter: blur(10px);
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .card-body-no-padding {
            padding: 0;
        }

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: var(--transition);
            background: var(--input-background);
            color: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-group input::placeholder {
            color: var(--dark-gray);
            opacity: 0.7;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
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

        .btn-sm-custom {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
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

        .btn-warning-custom {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning-custom:hover {
            background: #d97706;
            color: white;
            transform: translateY(-1px);
        }

        .btn-whatsapp {
            background: var(--whatsapp-color);
            color: white;
        }

        .btn-whatsapp:hover {
            background: var(--whatsapp-dark);
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

        /* ===== ALERTS ===== */
        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid;
            font-size: 0.875rem;
            backdrop-filter: blur(10px);
        }

        .alert-success-custom {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-danger-custom {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .alert-warning-custom {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
            border-color: rgba(245, 158, 11, 0.3);
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
            width: 100%;
            border-collapse: collapse;
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
            border-bottom: 1px solid var(--border-color);
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

        .checkbox-column {
            width: 40px;
            text-align: center;
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

        .token-code {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: var(--input-background);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 1060;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content-custom {
            background: var(--modal-background);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            max-width: 450px;
            width: 90%;
            max-height: 70vh;
            box-shadow: var(--shadow-modal);
            display: flex;
            flex-direction: column;
        }

        .modal-header-custom {
            margin-bottom: 1.5rem;
            flex-shrink: 0;
        }

        .modal-header-custom h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .modal-body-custom {
            margin-bottom: 1.5rem;
            flex: 1;
            overflow: hidden;
        }

        .mensaje-preview {
            background: var(--mensaje-preview-background);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: 0.5rem;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.875rem;
            white-space: pre-line;
            border: 1px solid var(--border-color);
            max-height: 300px;
            overflow-y: auto;
            line-height: 1.5;
            color: var(--text-dark);
        }

        /* Scrollbar personalizado para el mensaje */
        .mensaje-preview::-webkit-scrollbar {
            width: 8px;
        }

        .mensaje-preview::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .mensaje-preview::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        .mensaje-preview::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        .modal-footer-custom {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        /* ===== VARIABLES DINÁMICAS - DISEÑO ELEGANTE OSCURO ===== */
        .variables-container {
            background: var(--variables-background);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        .variables-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        /* ===== AVISO IMPORTANTE OSCURO ===== */
        .variables-warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
            backdrop-filter: blur(10px);
        }

        .warning-icon {
            width: 40px;
            height: 40px;
            background: var(--warning-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .warning-content {
            flex: 1;
        }

        .warning-title {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .warning-text {
            color: var(--warning-color);
            margin: 0;
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .warning-text strong {
            font-weight: 700;
            color: var(--warning-color);
        }

        /* ===== HEADER DE VARIABLES ===== */
        .variables-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .variables-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .variables-title h5 {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            margin: 0;
        }

        .variables-title p {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin: 0;
        }

        /* ===== GRID DE VARIABLES ===== */
        .variables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .variable-item {
            background: var(--variable-item-background);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.25rem;
            text-align: center;
            transition: var(--transition);
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .variable-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
            border-color: var(--primary-color);
        }

        .variable-code {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .variable-label {
            font-size: 0.8rem;
            color: var(--dark-gray);
            font-weight: 500;
            display: block;
        }

        /* ===== NOTA INFORMATIVA ===== */
        .variables-note {
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            font-size: 0.875rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 50px;
            }

            .main-header {
                margin-left: 0;
                margin-top: 0 !important;
            }

            .header-content .user-section {
                display: none !important;
            }

            .sidebar-user-section {
                display: block !important;
            }

            .header-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
        justify-content: center !important;
        display: flex !important;
        padding: 1rem;
        position: relative;
    }
    .header-brand {
        justify-content: center;
        width: 100%;
    }
    .user-section {
        width: 100%;
        justify-content: center;
        display: none !important;
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

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .card-header-custom {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .card-header-custom .d-flex {
                flex-wrap: wrap;
                gap: 0.5rem;
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

            /* Variables responsive */
            .variables-container {
                padding: 1.5rem;
            }
            
            .variables-warning {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
                padding: 1rem;
            }
            
            .warning-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            
            .warning-title {
                font-size: 0.9rem;
            }
            
            .warning-text {
                font-size: 0.8rem;
            }
            
            .variables-header {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }
            
            .variables-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.75rem;
            }
            
            .variable-item {
                padding: 1rem;
            }
            
            .variable-code {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            .variables-note {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
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

        @media (max-width: 480px) {
            .variables-grid {
                grid-template-columns: 1fr 1fr;
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

      /* ===== TEXTAREA ESPECÍFICO PARA MENSAJE ===== */
#mensajeGeneral {
    background: rgba(40, 40, 65, 0.9) !important; /* Más claro que el fondo general */
    color: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid var(--border-color) !important;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace !important;
    font-size: 0.875rem;
    line-height: 1.5;
    padding: 1rem !important;
    border-radius: 0.5rem !important;
    resize: vertical;
    min-height: 180px;
}

#mensajeGeneral:focus {
    outline: none !important;
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
    background: rgba(45, 45, 70, 0.95) !important; /* Ligeramente más claro en focus */
    color: rgba(255, 255, 255, 0.95) !important;
}

#mensajeGeneral::placeholder {
    color: var(--dark-gray) !important;
    opacity: 0.7;
}

/* ===== SCROLLBAR PARA EL TEXTAREA ===== */
#mensajeGeneral::-webkit-scrollbar {
    width: 8px;
}

#mensajeGeneral::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

#mensajeGeneral::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 4px;
}

#mensajeGeneral::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

/* ===== LABEL DEL TEXTAREA ===== */
label[for="mensajeGeneral"] {
    color: var(--text-dark) !important;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

/* ===== PREVIEW DEL MENSAJE ===== */
#previewMensaje {
    background: rgba(40, 40, 65, 0.9) !important; /* Mismo fondo que el textarea */
    color: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid var(--border-color) !important;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace !important;
    font-size: 0.875rem;
    line-height: 1.5;
    padding: 1rem !important;
    border-radius: 0.5rem !important;
    min-height: 180px;
    white-space: pre-line;
    overflow-y: auto;
}

/* ===== SCROLLBAR PARA EL PREVIEW ===== */
#previewMensaje::-webkit-scrollbar {
    width: 8px;
}

#previewMensaje::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

#previewMensaje::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 4px;
}

#previewMensaje::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

/* ===== LABEL DEL PREVIEW ===== */
label[class="form-label"]:has(+ #previewMensaje),
.col-md-6 label.form-label {
    color: var(--text-dark) !important;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

/* ===== ALTERNATIVA MÁS ESPECÍFICA ===== */
.col-md-6 .form-label {
    color: var(--text-dark) !important;
}

#previewMensaje.mensaje-preview {
    background: rgba(40, 40, 65, 0.9) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid var(--border-color) !important;
}

/* ===== CARD DE FILTROS ===== */
.card.shadow-sm.border-0.mb-4 {
    background: var(--card-background) !important;
    backdrop-filter: blur(20px);
    border: 1px solid var(--border-color) !important;
    box-shadow: var(--shadow-card) !important;
}

/* ===== HEADER DE FILTROS ===== */
.card-header.bg-gradient {
    background: var(--card-header-background) !important;
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border-color) !important;
}

.card-header.bg-gradient h5 {
    color: rgba(255, 255, 255, 0.9) !important;
}

.card-header.bg-gradient small {
    color: rgba(255, 255, 255, 0.7) !important;
}

.card-header.bg-gradient i {
    color: rgba(255, 255, 255, 0.9) !important;
}

/* ===== BODY DE FILTROS ===== */
.card-body.bg-light {
    background: transparent !important;
    padding: 2rem;
}

/* ===== LABELS DE FILTROS ===== */
.card-body .form-label {
    color: var(--text-dark) !important;
    font-weight: 500;
}

.card-body .form-label i {
    color: var(--text-dark) !important;
}

/* ===== INPUTS DE FILTROS ===== */
.card-body .form-control,
.card-body .form-select {
    background: var(--input-background) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid var(--border-color) !important;
}

.card-body .form-control:focus,
.card-body .form-select:focus {
    background: var(--input-background) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
}

.card-body .form-control::placeholder {
    color: var(--dark-gray) !important;
    opacity: 0.7;
}

/* ===== INPUT GROUP ===== */
.card-body .input-group-text {
    background: var(--input-background) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-dark) !important;
}

.card-body .input-group-text.bg-white {
    background: var(--input-background) !important;
}

.card-body .input-group-text i {
    color: var(--dark-gray) !important;
}

/* ===== BOTONES DE FILTROS ===== */
.card-body .btn-outline-secondary {
    background: transparent;
    color: var(--dark-gray) !important;
    border-color: var(--border-color) !important;
}

.card-body .btn-outline-secondary:hover {
    background: var(--dark-gray) !important;
    color: white !important;
    border-color: var(--dark-gray) !important;
}

.card-body .btn-outline-danger {
    background: transparent;
    color: var(--danger-color) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
}

.card-body .btn-outline-danger:hover {
    background: var(--danger-color) !important;
    color: white !important;
    border-color: var(--danger-color) !important;
}

/* ===== TEXTO INFORMATIVO ===== */
.card-body .text-muted {
    color: var(--dark-gray) !important;
}

.card-body .text-muted i {
    color: var(--dark-gray) !important;
}

/* ===== CONTADOR DE RESULTADOS ===== */
#resultadosContador {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* ===== EFECTO GLOW MORADO PARA STAT-CARDS ===== */
.stat-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 
        var(--shadow-strong),
        0 0 30px rgba(99, 102, 241, 0.4),
        0 0 60px rgba(139, 92, 246, 0.2) !important;
    border-color: rgba(99, 102, 241, 0.6) !important;
}

/* ===== EFECTO GLOW MÁS INTENSO (OPCIONAL) ===== */
.stat-card:hover::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3));
    border-radius: 1rem;
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-card:hover::before {
    opacity: 1;
}

/* ===== VERSIÓN SIMPLE (RECOMENDADA) ===== */
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 
        0 15px 35px rgba(0, 0, 0, 0.3),
        0 0 25px rgba(99, 102, 241, 0.5),
        0 0 50px rgba(139, 92, 246, 0.3) !important;
    border-color: rgba(99, 102, 241, 0.7) !important;
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

    <!-- Main Header -->
    <header class="main-header" id="mainHeader">
        <div class="header-content">
            <div class="header-brand">
                <div class="header-logo">
                    Fn
                </div>
                <h1 class="header-title">Fastnvite</h1>
            </div>
            <div class="header-center">
                <h2 class="dashboard-title">📱 Envío de Invitaciones</h2>
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
        <!-- Messages -->
        <?php if ($mensaje): ?>
            <div class="alert-custom alert-success-custom animate-fade-in">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-custom alert-danger-custom animate-fade-in">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid animate-fade-in">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo count($invitados); ?></h3>
                        <p class="stat-label">Total de Invitaciones</p>
                    </div>
                    <div class="stat-icon shown">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3 class="stat-number"><?php echo count(array_filter($invitados, function($inv) { return !empty($inv['telefono']); })); ?></h3>
                        <p class="stat-label">Con Teléfono</p>
                    </div>
                    <div class="stat-icon phones">
                        <i class="bi bi-telephone"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
    <div class="stat-header">
        <div>
            <h3 class="stat-number"><?php echo count(array_filter($invitados, function($inv) { return !empty($inv['enviado_whatsapp']); })); ?></h3>
            <p class="stat-label">Invitaciones Enviadas</p>
        </div>
        <div class="stat-icon sent">
            <i class="bi bi-send-check"></i>
        </div>
    </div>
</div>
        </div>
        <!-- Mensaje General Editable con advertencia y vista previa en dos columnas -->
        <div class="content-card animate-fade-in">
            <div class="card-header-custom">
                <h3><i class="bi bi-chat-dots me-2"></i>Mensaje General para WhatsApp</h3>
            </div>
            <div class="card-body-custom">
               <!-- Variables Dinámicas - Diseño Elegante con Aviso -->
<div class="variables-container">
    <!-- Aviso Importante -->
    <div class="variables-warning">
        <div class="warning-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div class="warning-content">
            <h6 class="warning-title">¡Importante!</h6>
            <p class="warning-text">El mensaje debe incluir <strong>todas</strong> las variables dinámicas obligatorias para funcionar correctamente.</p>
        </div>
    </div>
    
    <div class="variables-header">
        <div class="variables-icon">
            <i class="bi bi-code-slash"></i>
        </div>
        <div class="variables-title">
            <h5 class="mb-1">Variables Dinámicas Obligatorias</h5>
            <p class="mb-0 text-muted">Incluye estas variables en tu mensaje para personalización automática</p>
        </div>
    </div>
    
    <div class="variables-grid">
        <div class="variable-item">
            <div class="variable-code">{nombre}</div>
            <span class="variable-label">Nombre completo</span>
        </div>
        <div class="variable-item">
            <div class="variable-code">{token}</div>
            <span class="variable-label">Token único</span>
        </div>
        <div class="variable-item">
            <div class="variable-code">{cupos}</div>
            <span class="variable-label">Cupos permitidos</span>
        </div>
        <div class="variable-item">
            <div class="variable-code">{mesa}</div>
            <span class="variable-label">Mesa asignada</span>
        </div>
    </div>
    
    <div class="variables-note">
        <i class="bi bi-info-circle me-2"></i>
        <span>Estas variables se reemplazan automáticamente con los datos reales de cada invitado al enviar</span>
    </div>
</div>
                <div class="row g-4 align-items-stretch mt-3">
                    <div class="col-md-6">
                        <label for="mensajeGeneral" class="form-label">Edita el mensaje que se enviará a los invitados:</label>
                        <textarea id="mensajeGeneral" class="form-control" rows="8" style="font-family:monospace;resize:vertical;min-height:180px;" placeholder="Escribe aquí tu mensaje...">💍 ¡Con amor te invitamos! 💍

🎀 Hola mi querido/a {nombre} 🎀

Es con inmensa felicidad que queremos compartir contigo uno de los días más importantes de nuestras vidas💕

🔐 Tu código especial: *{token}* 
👥 Acompañantes permitidos: {cupos}
🪑 Mesa número: {mesa}

✅ Confirma tu asistencia aquí, por favor:
{url_confirmacion}

📮 Descubre todos los detalles mágicos:
{url_invitacion}

Tu amor y compañía son el regalo más preciado que podríamos recibir💝

¡Esperamos verte en nuestra boda, para celebrar juntos! 🥂✨🎉

Con todo nuestro amor 💕
Guillermo y Wendy 👰🏻‍♀️🤵🏻‍♂️💒</textarea>
                        <button type="button" class="btn btn-outline-secondary mt-2 w-100" onclick="usarEjemploMensaje()">
                            <i class="bi bi-lightbulb me-1"></i> Usar ejemplo
                        </button>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vista previa en tiempo real:</label>
                        <div id="previewMensaje" class="mensaje-preview border rounded p-3 bg-light" style="min-height:180px;white-space:pre-line;font-family:monospace;"></div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        // Vista previa en tiempo real del mensaje y guardado automático en localStorage
        function actualizarPreviewMensaje() {
            const ejemplo = {
                nombre: 'Juan Pérez',
                token: 'ABC123',
                cupos: '2',
                mesa: '5',
                url_confirmacion: 'https://tudominio.com/rsvp/confirmar.php?token=ABC123',
                url_invitacion: 'https://tudominio.com/invitacion/ABC123'
            };
            let texto = document.getElementById('mensajeGeneral').value;
            texto = texto.replaceAll('{nombre}', ejemplo.nombre)
                         .replaceAll('{token}', ejemplo.token)
                         .replaceAll('{cupos}', ejemplo.cupos)
                         .replaceAll('{mesa}', ejemplo.mesa)
                         .replaceAll('{url_confirmacion}', ejemplo.url_confirmacion)
                         .replaceAll('{url_invitacion}', ejemplo.url_invitacion);
            document.getElementById('previewMensaje').textContent = texto;
            // Guardar automáticamente en localStorage
            localStorage.setItem('mensajeGeneralInvitacion', document.getElementById('mensajeGeneral').value);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Recuperar mensaje guardado si existe
            const guardado = localStorage.getItem('mensajeGeneralInvitacion');
            if (guardado !== null) {
                document.getElementById('mensajeGeneral').value = guardado;
            }
            actualizarPreviewMensaje();
            document.getElementById('mensajeGeneral').addEventListener('input', actualizarPreviewMensaje);
        });

        function usarEjemploMensaje() {
            const ejemplo = `💍 ¡Con amor te invitamos! 💍\n\n🎀 Hola mi querido/a {nombre} 🎀\n\nEs con inmensa felicidad que queremos compartir contigo uno de los días más importantes de nuestras vidas💕\n\n🔐 Tu código especial: *{token}* \n👥 Acompañantes permitidos: {cupos}\n🪑 Mesa número: {mesa}\n\n✅ Confirma tu asistencia aquí, por favor:\n{url_confirmacion}\n\n📮 Descubre todos los detalles mágicos:\n{url_invitacion}\n\nTu amor y compañía son el regalo más preciado que podríamos recibir💝\n\n¡Esperamos verte en nuestra boda, para celebrar juntos! 🥂✨🎉\n\nCon todo nuestro amor 💕\nGuillermo y Wendy 👰🏻‍♀️🤵🏻‍♂️💒`;
            document.getElementById('mensajeGeneral').value = ejemplo;
            actualizarPreviewMensaje();
        }
        </script>
        <!-- Filters Card -->
       <!-- Filtros Simplificados -->
<div class="card shadow-sm border-0 mt-4 mb-4 animate-fade-in">
    <div class="card-header bg-gradient text-white border-0" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
        <div class="d-flex align-items-center">
            <i class="bi bi-funnel-fill me-2 fs-5"></i>
            <h5 class="mb-0 fw-semibold">Filtros de Búsqueda</h5>
            <div class="ms-auto">
                <small class="opacity-75" id="resultadosContador">Mostrando todos los invitados</small>
            </div>
        </div>
    </div>
    <div class="card-body bg-light">
        <div class="row g-3 align-items-end">
            <!-- Buscar por Nombre -->
            <div class="col-md-6">
                <label for="buscarNombre" class="form-label fw-medium">
                    <i class="bi bi-search me-1"></i>Buscar por nombre
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" 
                           class="form-control border-start-0 ps-0" 
                           id="buscarNombre" 
                           placeholder="Escribe para buscar..." 
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" onclick="limpiarBusqueda()">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            
            <!-- Filtro por Enviado -->
            <div class="col-md-4">
                <label for="filtroEnviado" class="form-label fw-medium">
                    <i class="bi bi-send me-1"></i>Estado de Envío
                </label>
                <select class="form-select" id="filtroEnviado">
                    <option value="">Todas las invitaciones</option>
                    <option value="enviado">✅ Enviadas</option>
                    <option value="no_enviado">❌ No Enviadas</option>
                </select>
            </div>
            
            <!-- Botón Limpiar -->
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100" onclick="limpiarTodosFiltros()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Limpiar
                </button>
            </div>
        </div>
        
        <!-- Info -->
        <div class="mt-3">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Los filtros se aplican automáticamente mientras escribes
            </small>
        </div>
    </div>
</div>
        <!-- Guests List -->
        <div class="content-card animate-fade-in">
            <div class="card-header-custom">
                <h3><i class="bi bi-list-ul me-2"></i>Lista de Invitados (<?php echo            count($invitados); ?>)</h3>
                </div>
            </div>
            <div class="card-body-no-padding">
                <?php if (!empty($invitados)): ?>
                <div class="table-responsive-custom">
                    <form id="formEnvioMasivo" method="POST">
                        <input type="hidden" name="action" value="enviar_masivo">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                    </th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Mesa</th>
                                    <th>Cupos</th>
                                    <th>Estado</th>
                                    <th>Enviado</th>
                                    <th>Token</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invitados as $invitado): ?>
                                <tr data-id="<?php echo $invitado['id_invitado']; ?>">
                                    <td class="checkbox-column">
                                       
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($invitado['nombre_completo']); ?></strong>
                                            <?php if ($invitado['tipo_invitado']): ?>
                                                <br><span class="badge-custom badge-info-custom"><?php echo ucfirst($invitado['tipo_invitado']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($invitado['telefono']): ?>
                                            <span class="text-success fw-semibold"><?php echo htmlspecialchars($invitado['telefono']); ?></span>
                                        <?php else: ?>
                                            <span class="text-danger">Sin teléfono</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invitado['mesa']): ?>
                                            <span class="badge-custom badge-info-custom">Mesa <?php echo $invitado['mesa']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-primary"><?php echo $invitado['cupos_disponibles']; ?></strong>
                                        <?php if ($invitado['cantidad_confirmada']): ?>
                                            <br><small class="text-success">(Confirmó: <?php echo $invitado['cantidad_confirmada']; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invitado['cantidad_confirmada']): ?>
                                            <span class="badge-custom badge-success-custom">
                                                <i class="bi bi-check-circle"></i> Confirmado
                                            </span>
                                            <?php if ($invitado['fecha_confirmacion']): ?>
    <br><small style="color: 94a3b8; font-weight: 100;">
        <?php echo date('d/m/Y', strtotime($invitado['fecha_confirmacion'])); ?>
    </small>
<?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge-custom badge-warning-custom">
                                                <i class="bi bi-clock"></i> Pendiente
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center enviado-col">
                                        <?php if (!empty($invitado['enviado_whatsapp'])): ?>
                                            <span class="badge bg-success-subtle text-success fs-5"><i class="bi bi-check-circle-fill"></i></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger fs-5"><i class="bi bi-x-circle-fill"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="token-code"><?php echo $invitado['token']; ?></code>
                                        <button type="button" class="btn-custom btn-secondary-custom btn-sm-custom ms-1" onclick="copiarToken('<?php echo $invitado['token']; ?>')" title="Copiar token">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if (!empty($invitado['telefono'])): ?>
                                                <button type="button" class="btn-custom btn-whatsapp btn-sm-custom enviar-wsp-btn" data-id="<?php echo $invitado['id_invitado']; ?>" data-telefono="<?php echo $invitado['telefono']; ?>" title="Enviar por WhatsApp">
                                                    <i class="bi bi-whatsapp"></i>
                                                </button>
                                                <button type="button" class="btn-custom btn-primary-custom btn-sm-custom" onclick="previsualizarMensaje(<?php echo $invitado['id_invitado']; ?>)" title="Previsualizar mensaje">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-danger small">Sin teléfono</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="text-muted">No se encontraron invitados</h4>
                    <p class="text-muted">No hay invitados que coincidan con los filtros seleccionados.</p>
                    <a href="generador.php" class="btn-custom btn-primary-custom mt-3">
                        <i class="bi bi-plus-lg"></i>
                        Generar Invitados
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Modal for Message Preview -->
    <div id="modalPreview" class="modal-overlay">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h3><i class="bi bi-whatsapp text-success me-2"></i>Previsualización del Mensaje</h3>
            </div>
            <div class="modal-body-custom">
                <div id="contenidoMensaje" class="mensaje-preview"></div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-custom btn-secondary-custom" onclick="cerrarModal()">
                    <i class="bi bi-x-lg"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>

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

// Variables globales
let invitadoActual = null;

        // Funciones de selección
        function seleccionarTodos() {
            const checkboxes = document.querySelectorAll('.invitado-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            document.getElementById('selectAll').checked = true;
        }
        
        function deseleccionarTodos() {
            const checkboxes = document.querySelectorAll('.invitado-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
        }
        
        function toggleTodos() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.invitado-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        // Función para enviar seleccionados
        function enviarSeleccionados() {
            const seleccionados = document.querySelectorAll('.invitado-checkbox:checked');
            
            if (seleccionados.length === 0) {
                alert('Por favor selecciona al menos un invitado');
                return;
            }
            
            if (confirm(`¿Enviar invitaciones a ${seleccionados.length} invitado(s)?`)) {
                // Abrir múltiples pestañas de WhatsApp
                seleccionados.forEach((checkbox, index) => {
                    setTimeout(() => {
                        enviarIndividual(checkbox.value);
                    }, index * 1000); // Retraso de 1 segundo entre envíos
                });
            }
        }
        
        // Función para envío individual
        function enviarIndividual(idInvitado) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'enviar_individual';
            form.appendChild(actionInput);
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id_invitado';
            idInput.value = idInvitado;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Función para copiar token
        function copiarToken(token) {
            navigator.clipboard.writeText(token).then(() => {
                // Crear notificación temporal
                const notification = document.createElement('div');
                notification.className = 'alert-custom alert-success-custom';
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.zIndex = '1070';
                notification.innerHTML = `<i class="bi bi-check-circle me-2"></i>Token copiado: ${token}`;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
            }).catch(() => {
                alert('Token copiado: ' + token);
            });
        }
        
        // Función para previsualizar mensaje
      // Función corregida para previsualizar mensaje
function previsualizarMensaje(idInvitado) {
    invitadoActual = idInvitado;
    
    // Buscar la fila correcta usando el data-id
    const fila = document.querySelector(`tr[data-id="${idInvitado}"]`);
    
    if (!fila) {
        console.error('No se encontró la fila del invitado');
        return;
    }
    
    // Extraer datos de las celdas (recordar que la primera celda [0] es el checkbox)
    const celdaNombre = fila.cells[1]; // Nombre
    const celdaTelefono = fila.cells[2]; // Teléfono  
    const celdaMesa = fila.cells[3]; // Mesa
    const celdaCupos = fila.cells[4]; // Cupos
    const celdaToken = fila.cells[7]; // Token (columna 7)
    
    // Extraer el nombre del elemento <strong>
    const elementoNombre = celdaNombre.querySelector('strong');
    const nombre = elementoNombre ? elementoNombre.textContent.trim() : 'Invitado';
    
    // Extraer teléfono
    const telefono = celdaTelefono.textContent.trim();
    
    // Extraer mesa (remover "Mesa " del texto si existe)
    let mesa = celdaMesa.textContent.trim();
    if (mesa.includes('Mesa ')) {
        mesa = mesa.replace('Mesa ', '');
    } else if (mesa === 'Sin asignar') {
        mesa = '';
    }
    
    // Extraer cupos (solo el primer número antes de cualquier salto de línea)
    const cupos = celdaCupos.textContent.trim().split('\n')[0];
    
    // Extraer token del elemento <code>
    const elementoToken = celdaToken.querySelector('code');
    const token = elementoToken ? elementoToken.textContent.trim() : '';
    
    // URLs reales
    const url_confirmacion = `https://fastnvite.com/php/rsvp/confirmar.php?token=${token}`;
    const url_invitacion = `https://fastnvite.com`;
    
    // Obtener el mensaje general del textarea
    let mensaje = document.getElementById('mensajeGeneral').value;
    
    // Verificar que el mensaje no esté vacío
    if (!mensaje.trim()) {
        alert('Por favor, escribe un mensaje antes de previsualizar');
        return;
    }
    
    // Reemplazar variables
    mensaje = mensaje.replaceAll('{nombre}', nombre)
                     .replaceAll('{token}', token)
                     .replaceAll('{cupos}', cupos)
                     .replaceAll('{mesa}', mesa)
                     .replaceAll('{url_confirmacion}', url_confirmacion)
                     .replaceAll('{url_invitacion}', url_invitacion);
    
    // Mostrar el mensaje en el modal
    document.getElementById('contenidoMensaje').textContent = mensaje;
    document.getElementById('modalPreview').style.display = 'flex';
    
    // Debug: mostrar en consola para verificar
    console.log('Datos extraídos:', {
        nombre: nombre,
        telefono: telefono,
        mesa: mesa,
        cupos: cupos,
        token: token
    });
}
        
        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalPreview').style.display = 'none';
            invitadoActual = null;
        }
        
        // Función para enviar desde modal
        function enviarDesdeModal() {
            if (invitadoActual) {
                enviarIndividual(invitadoActual);
                cerrarModal();
            }
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalPreview').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.enviar-wsp-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const idInvitado = this.getAttribute('data-id');
            const telefono = this.getAttribute('data-telefono');
            // Armar mensaje personalizado
            const fila = this.closest('tr');
            const nombre = fila.cells[1].textContent.trim().split('\n')[0];
            const mesa = fila.cells[3].textContent.trim();
            const cupos = fila.cells[4].textContent.trim().split('\n')[0];
            const token = fila.querySelector('code').textContent;
            const url_confirmacion = `https://fastnvite.com/php/rsvp/confirmar.php?token=${token}`;
            const url_invitacion = `https://fastnvite.com`;
            let mensaje = document.getElementById('mensajeGeneral').value;
            mensaje = mensaje.replaceAll('{nombre}', nombre)
                             .replaceAll('{token}', token)
                             .replaceAll('{cupos}', cupos)
                             .replaceAll('{mesa}', (mesa && mesa !== 'Sin asignar') ? mesa : '')
                             .replaceAll('{url_confirmacion}', url_confirmacion)
                             .replaceAll('{url_invitacion}', url_invitacion);
            // Detectar dispositivo
            const isMobile = /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i.test(navigator.userAgent);
            const phone = telefono.replace(/[^0-9]/g, '');
            const wspUrl = isMobile
                ? `https://wa.me/52${phone}?text=${encodeURIComponent(mensaje)}`
                : `https://web.whatsapp.com/send?phone=52${phone}&text=${encodeURIComponent(mensaje)}`;
            window.open(wspUrl, '_blank');
            // Marcar como enviado en la BD
            fetch('envios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=marcar_enviado&id_invitado=${idInvitado}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Cambiar palomita a verde
                    const col = fila.querySelector('.enviado-col');
                    col.innerHTML = '<span class="badge bg-success-subtle text-success fs-5"><i class="bi bi-check-circle-fill"></i></span>';
                }
            });
        });
    });
});


document.addEventListener('DOMContentLoaded', function() {
    // Elementos DOM
    const buscarNombre = document.getElementById('buscarNombre');
    const filtroEnviado = document.getElementById('filtroEnviado');
    const tablaFilas = document.querySelectorAll('.table-custom tbody tr');
    const contadorResultados = document.getElementById('resultadosContador');
    
    // Event listeners para filtrado en tiempo real
    buscarNombre.addEventListener('input', aplicarFiltros);
    filtroEnviado.addEventListener('change', aplicarFiltros);
    
    function aplicarFiltros() {
    const textoBuscar = buscarNombre.value.toLowerCase().trim();
    const enviadoSeleccionado = filtroEnviado.value;
    
    let filasVisibles = 0;
    
    tablaFilas.forEach(fila => {
        let mostrarFila = true;
        
        // Filtro por nombre y teléfono (celdas 1 y 2)
        if (textoBuscar) {
            const celdaNombre = fila.cells[1]; // Celda del nombre
            const celdaTelefono = fila.cells[2]; // Celda del teléfono
            
            // Extraer SOLO el nombre del elemento <strong>, ignorando el tipo de invitado
            const elementoNombre = celdaNombre.querySelector('strong');
            const nombre = elementoNombre ? elementoNombre.textContent.toLowerCase().trim() : '';
            const telefono = celdaTelefono.textContent.toLowerCase().trim();
            
            // Buscar que el nombre EMPIECE con el texto buscado (palabra completa)
            const nombreCoincide = nombre.split(' ').some(palabra => palabra.startsWith(textoBuscar));
            const telefonoCoincide = telefono.includes(textoBuscar);
            
            if (!nombreCoincide && !telefonoCoincide) {
                mostrarFila = false;
            }
        }
        
        // Filtro por enviado (celda 6 - índice correcto)
        if (enviadoSeleccionado && mostrarFila) {
            const celdaEnviado = fila.cells[6]; // Celda correcta del estado enviado
            const iconoEnviado = celdaEnviado.querySelector('i.bi-check-circle-fill');
            const esEnviado = iconoEnviado !== null;
            
            if (enviadoSeleccionado === 'enviado' && !esEnviado) {
                mostrarFila = false;
            } else if (enviadoSeleccionado === 'no_enviado' && esEnviado) {
                mostrarFila = false;
            }
        }
        
        // Mostrar/ocultar fila
        fila.style.display = mostrarFila ? '' : 'none';
        if (mostrarFila) filasVisibles++;
    });
    
    // Actualizar contadores
    actualizarContador(filasVisibles);
}
    
    function actualizarContador(cantidad) {
        const total = tablaFilas.length;
        contadorResultados.textContent = `Mostrando ${cantidad} de ${total} invitados`;
        
        // Actualizar título de la tabla
        const titulo = document.querySelector('.card-header-custom h3');
        if (titulo) {
            titulo.innerHTML = `<i class="bi bi-list-ul me-2"></i>Lista de Invitados (${cantidad})`;
        }
    }
    
    // Funciones globales
    window.limpiarBusqueda = function() {
        buscarNombre.value = '';
        aplicarFiltros();
    }
    
    window.limpiarTodosFiltros = function() {
        buscarNombre.value = '';
        filtroEnviado.value = '';
        aplicarFiltros();
    }
});

</script>
</body>
</html>