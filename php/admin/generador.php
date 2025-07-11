<?php
/**
 * GENERADOR DE INVITADOS
 * Herramienta para crear y gestionar invitados masivamente
 */

require_once '../config/database.php';

$message = '';
$messageType = '';

// Procesar formularios
if ($_POST) {
    try {
        $db = getDB();
        $connection = $db->getConnection();
        
        // Agregar invitado individual
        if (isset($_POST['add_single'])) {
            $nombre = sanitizeInput($_POST['nombre_completo']);
            $telefono = sanitizeInput($_POST['telefono']);
            $cupos = (int)$_POST['cupos_disponibles'];
            $mesa = (int)$_POST['mesa'];
            $tipo = sanitizeInput($_POST['tipo_invitado']);
            
            // Validar campos obligatorios
            if (empty($nombre) || empty($telefono) || $cupos < 1 || $mesa < 1 || empty($tipo)) {
                $message = "Error: Todos los campos son obligatorios";
                $messageType = 'danger';
            } else {
                // Generar token único
                do {
                    $token = Database::generateToken();
                } while ($db->tokenExists($token));
                
                $stmt = $connection->prepare("
                    INSERT INTO invitados (nombre_completo, telefono, cupos_disponibles, mesa, tipo_invitado, token, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                if ($stmt->execute([$nombre, $telefono, $cupos, $mesa, $tipo, $token])) {
                    $message = "Invitado agregado exitosamente. Token: $token";
                    $messageType = 'success';
                } else {
                    $message = "Error al agregar invitado";
                    $messageType = 'danger';
                }
            }
        }
        
        // Agregar múltiples invitados
        if (isset($_POST['add_multiple'])) {
            $invitados_data = $_POST['invitados_multiple'];
            $added = 0;
            $errors = [];
            $validInvitados = [];
            
            // Validar todos los invitados antes de procesar
            foreach ($invitados_data as $index => $invitado) {
                if (empty($invitado['nombre_completo'])) continue;
                
                // Validar campos obligatorios
                if (empty($invitado['nombre_completo']) || 
                    empty($invitado['telefono']) || 
                    empty($invitado['cupos_disponibles']) || 
                    empty($invitado['mesa']) || 
                    empty($invitado['tipo_invitado'])) {
                    $errors[] = "Fila " . ($index + 1) . ": Todos los campos son obligatorios";
                } else {
                    $validInvitados[] = $invitado;
                }
            }
            
            if (empty($validInvitados)) {
                $message = "Error: No hay invitados válidos para agregar. " . implode(', ', $errors);
                $messageType = 'danger';
            } else {
                $connection->beginTransaction();
                
                foreach ($validInvitados as $invitado) {
                    try {
                        // Generar token único
                        do {
                            $token = Database::generateToken();
                        } while ($db->tokenExists($token));
                        
                        $stmt = $connection->prepare("
                            INSERT INTO invitados (nombre_completo, telefono, cupos_disponibles, mesa, tipo_invitado, token, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            sanitizeInput($invitado['nombre_completo']),
                            sanitizeInput($invitado['telefono']),
                            (int)$invitado['cupos_disponibles'],
                            (int)$invitado['mesa'],
                            sanitizeInput($invitado['tipo_invitado']),
                            $token
                        ]);
                        
                        $added++;
                    } catch (Exception $e) {
                        $errors[] = "Error al procesar invitado: " . $e->getMessage();
                    }
                }
                
                $connection->commit();
                
                if ($added > 0) {
                    $message = "Se agregaron $added invitados exitosamente.";
                    if (!empty($errors)) {
                        $message .= " Errores: " . implode(', ', $errors);
                    }
                    $messageType = 'success';
                } else {
                    $message = "No se pudo agregar ningún invitado. " . implode(', ', $errors);
                    $messageType = 'danger';
                }
            }
        }
        
    } catch (Exception $e) {
        if (isset($connection)) {
            $connection->rollBack();
        }
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Invitados - Boda</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
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
    margin-bottom: 2rem;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--white);
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0 !important;
    padding: 1.5rem;
    border-bottom: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    letter-spacing: -0.025em;
}

.card-body {
    padding: 2rem;
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

.btn-outline-success {
    border: 2px solid var(--success-color);
    color: var(--success-color);
    background: transparent;
}

.btn-outline-success:hover {
    background: var(--success-color);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.3);
}

.btn-outline-info {
    border: 2px solid var(--info-color);
    color: var(--info-color);
    background: transparent;
}

.btn-outline-info:hover {
    background: var(--info-color);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(59, 130, 246, 0.3);
}

.btn-outline-warning {
    border: 2px solid var(--warning-color);
    color: var(--warning-color);
    background: transparent;
}

.btn-outline-warning:hover {
    background: var(--warning-color);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(245, 158, 11, 0.3);
}

.btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: var(--white);
    background: transparent;
}

.btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.2);
    color: var(--white);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    border-radius: 25px;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
    border-radius: 50px;
}

/* Formularios elegantes */
.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid var(--gray-300);
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    background: var(--white);
    font-size: 0.875rem;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

.form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

/* Acciones rápidas */
.quick-actions {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Formulario múltiple */
.multiple-form {
    background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 2px solid var(--gray-200);
    transition: all 0.3s ease;
}

.multiple-form:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-md);
}

/* Botones de acción para filas */
.remove-row {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
    border: none;
    color: var(--white);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.3);
}

.remove-row:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px 0 rgba(239, 68, 68, 0.4);
}

.add-row {
    background: linear-gradient(135deg, var(--success-color), #059669);
    border: none;
    color: var(--white);
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.3);
}

.add-row:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.4);
}

/* Alertas elegantes */
.alert {
    border: none;
    border-radius: var(--border-radius);
    padding: 1rem 1.5rem;
    border-left: 4px solid;
    box-shadow: var(--shadow-md);
    backdrop-filter: blur(20px);
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

.alert-info {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
    border-left-color: var(--info-color);
    color: #1e40af;
}

/* Mensaje de éxito especial */
.success-message {
    background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
    color: var(--white);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: var(--shadow-lg);
}

/* Mejoras responsive */
@media (max-width: 768px) {
    .card {
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .quick-actions {
        padding: 1.5rem;
    }
    
    .multiple-form {
        padding: 1rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
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

/* Estilos para scroll personalizado */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--secondary-color));
}

/* Efectos adicionales */
.form-control:invalid {
    border-color: var(--danger-color);
}

.form-control:valid {
    border-color: var(--success-color);
}

/* Mejoras para inputs con errores */
.form-control[style*="border-color: rgb(220, 53, 69)"] {
    border-color: var(--danger-color) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

.form-control[style*="border-color: rgb(40, 167, 69)"] {
    border-color: var(--success-color) !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}

/* ===============================
   TUTORIAL STYLES
   =============================== */

/* Overlay principal */
.tutorial-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    backdrop-filter: blur(3px);
    animation: tutorialFadeIn 0.3s ease;
}

/* Modal del tutorial */
.tutorial-modal {
    position: absolute;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-xl);
    width: 350px;
    max-width: 90vw;
    transform: scale(0.8) translateY(-20px);
    animation: tutorialSlideIn 0.4s ease forwards;
    border: 2px solid var(--primary-color);
    overflow: hidden;
}

/* Header del tutorial */
.tutorial-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--white);
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tutorial-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
}

.tutorial-progress {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Body del tutorial */
.tutorial-body {
    padding: 1.5rem;
    max-height: 200px;
    overflow-y: auto;
}

.tutorial-body p {
    margin: 0;
    line-height: 1.6;
    color: var(--gray-700);
}

/* Footer del tutorial */
.tutorial-footer {
    padding: 1rem 1.5rem;
    background: var(--gray-50);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--gray-200);
}

.tutorial-nav {
    display: flex;
    gap: 0.5rem;
}

/* Flecha indicadora */
.tutorial-arrow {
    position: absolute;
    width: 0;
    height: 0;
    border: 12px solid transparent;
    z-index: 10001;
    animation: tutorialPulse 2s infinite;
}

.tutorial-arrow.top {
    border-bottom: 12px solid var(--primary-color);
    transform: translateX(-50%) translateY(-12px);
}

.tutorial-arrow.bottom {
    border-top: 12px solid var(--primary-color);
    transform: translateX(-50%) translateY(12px);
}

.tutorial-arrow.left {
    border-right: 12px solid var(--primary-color);
    transform: translateY(-50%) translateX(-12px);
}

.tutorial-arrow.right {
    border-left: 12px solid var(--primary-color);
    transform: translateY(-50%) translateX(12px);
}

/* Elemento resaltado */
.tutorial-highlight {
    position: relative;
    z-index: 9999;
    box-shadow: 0 0 0 4px var(--primary-color), 0 0 0 8px rgba(99, 102, 241, 0.3) !important;
    border-radius: var(--border-radius) !important;
    animation: tutorialGlow 1.5s infinite alternate;
}

/* Spotlight effect */
.tutorial-spotlight {
    position: absolute;
    background: rgba(255, 255, 255, 0.1);
    border: 3px solid var(--primary-color);
    border-radius: var(--border-radius);
    pointer-events: none;
    z-index: 9998;
    animation: tutorialSpotlight 2s infinite;
}

/* Animaciones */
@keyframes tutorialFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes tutorialSlideIn {
    from { 
        transform: scale(0.8) translateY(-20px);
        opacity: 0;
    }
    to { 
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

@keyframes tutorialPulse {
    0%, 100% { 
        transform: translateX(-50%) translateY(-12px) scale(1);
        opacity: 1;
    }
    50% { 
        transform: translateX(-50%) translateY(-12px) scale(1.2);
        opacity: 0.7;
    }
}

@keyframes tutorialGlow {
    from { 
        box-shadow: 0 0 0 4px var(--primary-color), 0 0 0 8px rgba(99, 102, 241, 0.3);
    }
    to { 
        box-shadow: 0 0 0 6px var(--primary-light), 0 0 0 12px rgba(99, 102, 241, 0.5);
    }
}

@keyframes tutorialSpotlight {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 0.9; }
}

/* Responsive */
@media (max-width: 768px) {
    .tutorial-modal {
        width: 320px;
        font-size: 0.9rem;
    }
    
    .tutorial-header {
        padding: 0.75rem 1rem;
    }
    
    .tutorial-body {
        padding: 1rem;
    }
    
    .tutorial-footer {
        padding: 0.75rem 1rem;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .tutorial-nav {
        width: 100%;
        justify-content: space-between;
    }
}

/* Botón del tutorial en navbar */
.tutorial-btn {
    transition: all 0.3s ease;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.tutorial-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
}
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
    <i class="fas fa-user-plus me-2"></i>
    Generador de Invitados
</span>
<button class="btn btn-outline-light btn-sm ms-auto tutorial-btn" onclick="startTutorial()">
    <i class="fas fa-question-circle me-1"></i>
    ¿Cómo agregar?
</button>
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
    
     <!-- Enlaces de navegación -->
<div class="container-fluid mt-4 mb-3">
    <div class="text-center">
        <a href="../../index.html" class="btn btn-outline-primary me-2">
            💌 Invitación
        </a>
        <a href="dashboard.php" class="btn btn-outline-primary me-2">
            👥 Dashboard
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
    
    <div class="row justify-content-center">
        
        <!-- Formularios centrados -->
        <div class="col-lg-10">
            
            <!-- Agregar Individual -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>
                        Agregar Invitado Individual
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" name="nombre_completo" class="form-control" required 
                                       placeholder="Ej: Juan Pérez García">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono *</label>
                                <input type="text" name="telefono" class="form-control" required 
                                       placeholder="9991234567">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Cupos Disponibles *</label>
                                <select name="cupos_disponibles" class="form-select" required>
                                    <option value="1">1 persona</option>
                                    <option value="2">2 personas</option>
                                    <option value="3">3 personas</option>
                                    <option value="4">4 personas</option>
                                    <option value="5">5 personas</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Mesa *</label>
                                <input type="number" name="mesa" class="form-control" min="1" max="50" required 
                                       placeholder="Ej: 5">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo de Invitado *</label>
                                <select name="tipo_invitado" class="form-select" required>
                                    <option value="">Selecciona tipo</option>
                                    <option value="Familia">Familia</option>
                                    <option value="Amigo">Amigo</option>
                                    <option value="Trabajo">Trabajo</option>
                                    <option value="Padrino">Padrino</option>
                                    <option value="Especial">Especial</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" name="add_single" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus me-2"></i>
                                Agregar Invitado
                            </button>
                        </div>
                        
                        <small class="text-muted d-block mt-3 text-center">
                            * Todos los campos son obligatorios. El token se genera automáticamente.
                        </small>
                    </form>
                </div>
            </div>
            
            <!-- Agregar Múltiples -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Agregar Múltiples Invitados
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="multipleForm">
                        <div id="invitados-container">
                            <!-- Fila de ejemplo -->
                            <div class="multiple-form" data-index="0">
                                <div class="row align-items-end">
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Nombre *</label>
                                        <input type="text" name="invitados_multiple[0][nombre_completo]" 
                                               class="form-control" placeholder="Nombre completo">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Teléfono *</label>
                                        <input type="text" name="invitados_multiple[0][telefono]" 
                                               class="form-control" placeholder="Teléfono" required>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Cupos *</label>
                                        <select name="invitados_multiple[0][cupos_disponibles]" class="form-select">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Mesa *</label>
                                        <input type="number" name="invitados_multiple[0][mesa]" 
                                               class="form-control" min="1" max="50" placeholder="Mesa" required>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Tipo *</label>
                                        <select name="invitados_multiple[0][tipo_invitado]" class="form-select" required>
                                            <option value="">Selecciona</option>
                                            <option value="Familia">Familia</option>
                                            <option value="Amigo">Amigo</option>
                                            <option value="Trabajo">Trabajo</option>
                                            <option value="Padrino">Padrino</option>
                                            <option value="Especial">Especial</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <button type="button" class="remove-row" onclick="removeRow(0)" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-center gap-3 mb-3">
                            <button type="button" class="add-row" onclick="addRow()">
                                <i class="fas fa-plus me-1"></i>
                                Agregar Fila
                            </button>
                            <button type="submit" name="add_multiple" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>
                                Guardar Todos
                            </button>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Importante:</strong> Todos los campos marcados con (*) son obligatorios. Completa toda la información antes de guardar.
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
        
    </div>
</div>

<!-- Tutorial Overlay -->
<div id="tutorial-overlay" class="tutorial-overlay" style="display: none;">
    <div id="tutorial-modal" class="tutorial-modal">
        <div class="tutorial-header">
            <h5 id="tutorial-title">Título del paso</h5>
            <div class="tutorial-progress">
                <span id="tutorial-step">1</span> de <span id="tutorial-total">8</span>
            </div>
        </div>
        <div class="tutorial-body">
            <p id="tutorial-content">Contenido del paso...</p>
        </div>
        <div class="tutorial-footer">
            <button class="btn btn-outline-secondary btn-sm" onclick="skipTutorial()">
                Saltar Tutorial
            </button>
            <div class="tutorial-nav">
                <button id="prev-btn" class="btn btn-secondary btn-sm" onclick="prevStep()" disabled>
                    <i class="fas fa-chevron-left me-1"></i>Anterior
                </button>
                <button id="next-btn" class="btn btn-primary btn-sm" onclick="nextStep()">
                    Siguiente<i class="fas fa-chevron-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
    <div id="tutorial-arrow" class="tutorial-arrow"></div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
let rowIndex = 1;

function addRow() {
    const container = document.getElementById('invitados-container');
    const newRow = document.createElement('div');
    newRow.className = 'multiple-form';
    newRow.setAttribute('data-index', rowIndex);
    
    newRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-3 mb-2">
                <label class="form-label">Nombre *</label>
                <input type="text" name="invitados_multiple[${rowIndex}][nombre_completo]" 
                       class="form-control" placeholder="Nombre completo" required>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Teléfono *</label>
                <input type="text" name="invitados_multiple[${rowIndex}][telefono]" 
                       class="form-control" placeholder="Teléfono" required>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Cupos *</label>
                <select name="invitados_multiple[${rowIndex}][cupos_disponibles]" class="form-select" required>
                    <option value="">Sel.</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Mesa *</label>
                <input type="number" name="invitados_multiple[${rowIndex}][mesa]" 
                       class="form-control" min="1" max="50" placeholder="Mesa" required>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Tipo *</label>
                <select name="invitados_multiple[${rowIndex}][tipo_invitado]" class="form-select" required>
                    <option value="">Selecciona</option>
                    <option value="Familia">Familia</option>
                    <option value="Amigo">Amigo</option>
                    <option value="Trabajo">Trabajo</option>
                    <option value="Padrino">Padrino</option>
                    <option value="Especial">Especial</option>
                </select>
            </div>
            <div class="col-md-1 mb-2">
                <button type="button" class="remove-row" onclick="removeRow(${rowIndex})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(newRow);
    
    // Mostrar botón de eliminar en la primera fila si hay más de una
    if (rowIndex === 1) {
        const firstRemoveBtn = document.querySelector('[data-index="0"] .remove-row');
        if (firstRemoveBtn) {
            firstRemoveBtn.style.display = 'flex';
        }
    }
    
    rowIndex++;
}

function removeRow(index) {
    const row = document.querySelector(`[data-index="${index}"]`);
    if (row) {
        row.remove();
        
        // Ocultar botón de eliminar en la primera fila si solo queda una
        const remainingRows = document.querySelectorAll('.multiple-form');
        if (remainingRows.length === 1) {
            const firstRemoveBtn = document.querySelector('[data-index="0"] .remove-row');
            if (firstRemoveBtn) {
                firstRemoveBtn.style.display = 'none';
            }
        }
    }
}

// Limpiar formularios después de envío exitoso
document.addEventListener('DOMContentLoaded', function() {
    // Si hay mensaje de éxito, limpiar formularios
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            // Limpiar formulario individual
            const singleForm = document.querySelector('form:not(#multipleForm)');
            if (singleForm) {
                singleForm.reset();
            }
            
            // Limpiar formulario múltiple
            const multipleForm = document.getElementById('multipleForm');
            if (multipleForm) {
                multipleForm.reset();
                
                // Resetear a solo una fila
                const container = document.getElementById('invitados-container');
                const allRows = container.querySelectorAll('.multiple-form');
                for (let i = 1; i < allRows.length; i++) {
                    allRows[i].remove();
                }
                
                // Ocultar botón de eliminar en la primera fila
                const firstRemoveBtn = document.querySelector('[data-index="0"] .remove-row');
                if (firstRemoveBtn) {
                    firstRemoveBtn.style.display = 'none';
                }
                
                rowIndex = 1;
            }
        }, 3000);
    }
    
    // Validación adicional en el frontend
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Validar formulario individual
            if (form.id !== 'multipleForm') {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#28a745';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Por favor, completa todos los campos obligatorios');
                    return false;
                }
            }
            
            // Validar formulario múltiple
            if (form.id === 'multipleForm') {
                const rows = form.querySelectorAll('.multiple-form');
                let hasValidRow = false;
                let hasInvalidRow = false;
                
                rows.forEach((row, index) => {
                    const fields = row.querySelectorAll('input[required], select[required]');
                    let rowComplete = true;
                    let rowHasData = false;
                    
                    fields.forEach(field => {
                        if (field.value.trim()) {
                            rowHasData = true;
                        }
                        if (!field.value.trim()) {
                            rowComplete = false;
                            field.style.borderColor = '#dc3545';
                        } else {
                            field.style.borderColor = '#28a745';
                        }
                    });
                    
                    if (rowHasData && rowComplete) {
                        hasValidRow = true;
                    } else if (rowHasData && !rowComplete) {
                        hasInvalidRow = true;
                    }
                });
                
                if (!hasValidRow) {
                    e.preventDefault();
                    alert('Debes completar al menos una fila con todos los datos obligatorios');
                    return false;
                }
                
                if (hasInvalidRow) {
                    e.preventDefault();
                    alert('Hay filas incompletas. Completa todos los campos o elimina las filas vacías');
                    return false;
                }
            }
        });
    });
    
    // Restablecer color de borde al escribir
    document.addEventListener('input', function(e) {
        if (e.target.matches('input, select')) {
            e.target.style.borderColor = '';
        }
    });
});

/* ===============================
   TUTORIAL SYSTEM - AGREGAR INVITADOS
   =============================== */

// Configuración del tutorial específica para esta página
const tutorialSteps = [
    {
        target: '.card:first-child .card-header',
        title: '👤 Agregar Individual',
        content: 'Aquí puedes agregar un invitado a la vez. Perfecto para cuando tienes pocos invitados o quieres agregar casos especiales.',
        position: 'bottom'
    },
    {
        target: 'input[name="nombre_completo"]',
        title: '📝 Nombre Completo',
        content: 'Escribe el nombre completo del invitado. Este nombre aparecerá en la invitación y en todos los reportes.',
        position: 'right'
    },
    {
        target: 'input[name="telefono"]',
        title: '📱 Teléfono WhatsApp',
        content: 'Número de WhatsApp donde se enviará la invitación. Formato: 9991234567 (sin espacios ni guiones).',
        position: 'left'
    },
    {
        target: 'select[name="cupos_disponibles"]',
        title: '👥 Cupos Disponibles',
        content: 'Cuántas personas puede traer este invitado. Por ejemplo: si pones 2, puede confirmar para él y un acompañante.',
        position: 'top'
    },
    {
        target: 'input[name="mesa"]',
        title: '🪑 Número de Mesa',
        content: 'Mesa donde se sentará el invitado. Útil para organizar la recepción y generar reportes por mesa.',
        position: 'top'
    },
    {
        target: 'select[name="tipo_invitado"]',
        title: '🏷️ Tipo de Invitado',
        content: 'Categoría del invitado. Te ayuda a organizar y generar estadísticas: familia, amigos, trabajo, etc.',
        position: 'left'
    },
    {
        target: '.card:nth-child(3) .card-header',
        title: '👥 Agregar Múltiples',
        content: '¡Aquí viene lo bueno! Puedes agregar muchos invitados a la vez. Ideal para listas grandes.',
        position: 'bottom'
    },
    {
        target: '.multiple-form:first-child',
        title: '📋 Filas de Invitados',
        content: 'Cada fila es un invitado. Completa todos los campos obligatorios (*) para que funcione correctamente.',
        position: 'right'
    },
    {
        target: '.add-row',
        title: '➕ Agregar Más Filas',
        content: 'Usa este botón para agregar más filas cuando necesites registrar más invitados de una vez.',
        position: 'top'
    },
    {
        target: 'button[name="add_multiple"]',
        title: '💾 Guardar Todo',
        content: '¡Listo! Este botón guarda todos los invitados válidos de una vez. Se generan tokens automáticamente para cada uno.',
        position: 'top'
    }
];

let currentTutorialStep = 0;
let tutorialActive = false;

// Aquí va el resto del código JavaScript del tutorial...
// (Usa exactamente las mismas funciones del tutorial anterior)

// Iniciar tutorial
function startTutorial() {
    if (tutorialActive) return;
    
    tutorialActive = true;
    currentTutorialStep = 0;
    
    // Mostrar overlay
    document.getElementById('tutorial-overlay').style.display = 'block';
    document.getElementById('tutorial-total').textContent = tutorialSteps.length;
    
    // Deshabilitar scroll del body
    document.body.style.overflow = 'hidden';
    
    // Mostrar primer paso
    showTutorialStep(0);
}

// Mostrar paso específico
function showTutorialStep(stepIndex) {
    if (stepIndex < 0 || stepIndex >= tutorialSteps.length) return;
    
    const step = tutorialSteps[stepIndex];
    const modal = document.getElementById('tutorial-modal');
    const arrow = document.getElementById('tutorial-arrow');
    
    // Actualizar contenido
    document.getElementById('tutorial-title').textContent = step.title;
    document.getElementById('tutorial-content').textContent = step.content;
    document.getElementById('tutorial-step').textContent = stepIndex + 1;
    
    // Actualizar botones
    document.getElementById('prev-btn').disabled = stepIndex === 0;
    const nextBtn = document.getElementById('next-btn');
    if (stepIndex === tutorialSteps.length - 1) {
        nextBtn.innerHTML = '<i class="fas fa-check me-1"></i>Finalizar';
    } else {
        nextBtn.innerHTML = 'Siguiente<i class="fas fa-chevron-right ms-1"></i>';
    }
    
    // Encontrar elemento objetivo
    const targetElement = document.querySelector(step.target);
    if (!targetElement) {
        console.warn('Elemento no encontrado:', step.target);
        return;
    }
    
    // Remover highlight anterior
    document.querySelectorAll('.tutorial-highlight').forEach(el => {
        el.classList.remove('tutorial-highlight');
    });
    
    // Scroll al elemento si es necesario
    targetElement.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center',
        inline: 'center'
    });
    
    // Esperar un momento para el scroll
    setTimeout(() => {
        // Destacar elemento
        targetElement.classList.add('tutorial-highlight');
        
        // Posicionar modal y flecha
        positionTutorialModal(targetElement, step.position, modal, arrow);
    }, 300);
}

// Posicionar modal y flecha
function positionTutorialModal(target, position, modal, arrow) {
    const targetRect = target.getBoundingClientRect();
    const modalRect = modal.getBoundingClientRect();
    const viewport = {
        width: window.innerWidth,
        height: window.innerHeight
    };
    
    let modalX, modalY, arrowX, arrowY;
    let arrowClass = '';
    
    // Calcular posición según la posición deseada
    switch (position) {
        case 'right':
            modalX = targetRect.right + 20;
            modalY = targetRect.top + (targetRect.height / 2) - (modalRect.height / 2);
            arrowX = targetRect.right + 8;
            arrowY = targetRect.top + (targetRect.height / 2);
            arrowClass = 'left';
            break;
            
        case 'left':
            modalX = targetRect.left - modalRect.width - 20;
            modalY = targetRect.top + (targetRect.height / 2) - (modalRect.height / 2);
            arrowX = targetRect.left - 8;
            arrowY = targetRect.top + (targetRect.height / 2);
            arrowClass = 'right';
            break;
            
        case 'bottom':
            modalX = targetRect.left + (targetRect.width / 2) - (modalRect.width / 2);
            modalY = targetRect.bottom + 20;
            arrowX = targetRect.left + (targetRect.width / 2);
            arrowY = targetRect.bottom + 8;
            arrowClass = 'top';
            break;
            
        case 'top':
        default:
            modalX = targetRect.left + (targetRect.width / 2) - (modalRect.width / 2);
            modalY = targetRect.top - modalRect.height - 20;
            arrowX = targetRect.left + (targetRect.width / 2);
            arrowY = targetRect.top - 8;
            arrowClass = 'bottom';
            break;
    }
    
    // Ajustar si se sale de la pantalla
    if (modalX < 10) modalX = 10;
    if (modalX + modalRect.width > viewport.width - 10) {
        modalX = viewport.width - modalRect.width - 10;
    }
    if (modalY < 10) modalY = 10;
    if (modalY + modalRect.height > viewport.height - 10) {
        modalY = viewport.height - modalRect.height - 10;
    }
    
    // Aplicar posiciones
    modal.style.left = modalX + 'px';
    modal.style.top = modalY + 'px';
    
    arrow.style.left = arrowX + 'px';
    arrow.style.top = arrowY + 'px';
    arrow.className = 'tutorial-arrow ' + arrowClass;
}

// Siguiente paso
function nextStep() {
    if (currentTutorialStep < tutorialSteps.length - 1) {
        currentTutorialStep++;
        showTutorialStep(currentTutorialStep);
    } else {
        endTutorial();
    }
}

// Paso anterior
function prevStep() {
    if (currentTutorialStep > 0) {
        currentTutorialStep--;
        showTutorialStep(currentTutorialStep);
    }
}

// Saltar tutorial
function skipTutorial() {
    if (confirm('¿Estás seguro de que quieres saltar el tutorial?')) {
        endTutorial();
    }
}

// Finalizar tutorial
function endTutorial() {
    tutorialActive = false;
    
    // Ocultar overlay
    document.getElementById('tutorial-overlay').style.display = 'none';
    
    // Restaurar scroll
    document.body.style.overflow = '';
    
    // Remover highlights
    document.querySelectorAll('.tutorial-highlight').forEach(el => {
        el.classList.remove('tutorial-highlight');
    });
    
    // Mostrar mensaje de finalización
    showToast('¡Tutorial completado! 🎉 Ya puedes enviar tus invitaciones.');
}

// Cerrar tutorial con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && tutorialActive) {
        skipTutorial();
    }
});

// Redimensionar ventana
window.addEventListener('resize', function() {
    if (tutorialActive) {
        showTutorialStep(currentTutorialStep);
    }
});
</script>

</body>
</html>