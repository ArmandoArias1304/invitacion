<?php
/**
 * SISTEMA DE ENVÍO DE INVITACIONES
 * Herramienta para generar links y mensajes de WhatsApp
 */

require_once '../config/database.php';

// Configuración de URLs (cambiar cuando subas al servidor)
$base_url = 'http://localhost/invitacion-boda'; // Cambiar por tu dominio real
$confirmacion_url = $base_url . '/php/rsvp/confirmar.php?token=';
$invitacion_url = $base_url . '/index.html';

$message = '';
$messageType = '';

// Procesar acciones
if ($_POST) {
    try {
        $db = getDB();
        $connection = $db->getConnection();
        
        // Marcar como enviado
        if (isset($_POST['mark_sent'])) {
            $id_invitado = (int)$_POST['id_invitado'];
            
            $stmt = $connection->prepare("
                UPDATE invitados 
                SET enviado_whatsapp = NOW() 
                WHERE id_invitado = ?
            ");
            
            if ($stmt->execute([$id_invitado])) {
                $message = "Marcado como enviado";
                $messageType = 'success';
            }
        }
        
        // Generar links masivos
        if (isset($_POST['generate_all_links'])) {
            $message = "Links generados para todos los invitados";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Obtener todos los invitados
try {
    $db = getDB();
    $stmt = $db->getConnection()->prepare("
        SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion,
               CASE WHEN i.enviado_whatsapp IS NULL THEN 0 ELSE 1 END as ya_enviado
        FROM invitados i 
        LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado 
        ORDER BY i.mesa ASC, i.nombre_completo ASC
    ");
    $stmt->execute();
    $invitados = $stmt->fetchAll();
    
    // Estadísticas
    $total_invitados = count($invitados);
    $confirmados = count(array_filter($invitados, function($inv) { return $inv['fecha_confirmacion']; }));
    $pendientes = $total_invitados - $confirmados;
    $enviados = count(array_filter($invitados, function($inv) { return $inv['ya_enviado']; }));
    $por_enviar = $total_invitados - $enviados;
    
} catch (Exception $e) {
    $invitados = [];
    $message = "Error al cargar invitados: " . $e->getMessage();
    $messageType = 'danger';
}

// Templates de mensajes - Solo ejemplo
$example_message = "🤵👰 ¡Nos casamos!\n\nQuerido/a {NOMBRE_INVITADO},\n\nTenemos el honor de invitarte a nuestra boda. Tu presencia hará que este día sea aún más especial.\n\n📅 Confirma tu asistencia aquí:\n{LINK_CONFIRMACION}\n\nTu código para confirmar es: {TOKEN_INVITADO}\n\n💕 También puedes ver nuestra invitación digital:\n{invitacion}\n\n¡Esperamos verte el gran día!\n\nCon amor,\n[Nombres de los novios]";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Envío - Boda</title>
    
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
    --whatsapp-color: #25d366;          /* Verde WhatsApp */
    
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

/* Estadísticas mini */
.stats-mini {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--white);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    text-align: center;
    box-shadow: var(--shadow-lg);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stats-mini::before {
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

.stats-mini:hover::before {
    transform: translateX(100%);
}

.stats-mini:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
}

.stat-number {
    font-size: 2.5rem;
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

/* Botón WhatsApp especial */
.btn-whatsapp {
    background: linear-gradient(135deg, var(--whatsapp-color) 0%, #128c7e 100%);
    border: none;
    color: var(--white);
    border-radius: 25px;
    padding: 0.5rem 1rem;
    font-weight: 600;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 14px 0 rgba(37, 211, 102, 0.3);
    position: relative;
    overflow: hidden;
}

.btn-whatsapp::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-whatsapp:hover::before {
    left: 100%;
}

.btn-whatsapp:hover {
    background: linear-gradient(135deg, #128c7e 0%, #0d7377 100%);
    color: var(--white);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px 0 rgba(37, 211, 102, 0.4);
    text-decoration: none;
}

.btn-whatsapp:disabled {
    background: var(--gray-400);
    color: var(--gray-600);
    cursor: not-allowed;
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

/* Otros botones de outline */
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

.btn-outline-danger {
    border: 2px solid var(--danger-color);
    color: var(--danger-color);
    background: transparent;
}

.btn-outline-danger:hover {
    background: var(--danger-color);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(239, 68, 68, 0.3);
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

/* Tablas elegantes */
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



.table td {
    padding: 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
    transition: all 0.2s ease;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(139, 92, 246, 0.03) 100%);
    transform: translateY(-1px);
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

.status-enviado {
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: var(--white);
    box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.3);
}

.status-no-enviado {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
    color: var(--white);
    box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.3);
}

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

/* Botón copiar */
.copy-btn {
    background: linear-gradient(135deg, var(--gray-600) 0%, var(--gray-700) 100%);
    border: none;
    color: var(--white);
    border-radius: 8px;
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    margin-left: 0.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px 0 rgba(75, 85, 99, 0.3);
}

.copy-btn:hover {
    background: linear-gradient(135deg, var(--gray-700) 0%, var(--gray-800) 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px 0 rgba(75, 85, 99, 0.4);
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

/* Área de texto especial para mensajes */
textarea.form-control {
    resize: vertical;
    min-height: 150px;
    font-family: 'Inter', system-ui, sans-serif;
    line-height: 1.5;
}

/* Vista previa de mensaje */
.message-preview {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border-left: 4px solid var(--primary-color);
    padding: 1.5rem;
    border-radius: 0 var(--border-radius) var(--border-radius) 0;
    white-space: pre-wrap;
    font-family: 'Inter', system-ui, sans-serif;
    max-height: 300px;
    overflow-y: auto;
    box-shadow: var(--shadow-sm);
    min-height: 150px;
    position: relative;
}

.message-preview:empty::before {
    content: 'La vista previa aparecerá aquí...';
    color: var(--gray-500);
    font-style: italic;
}

/* Contenedor de URLs */
.url-display {
    background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    padding: 1rem;
    font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
    font-size: 0.875rem;
    word-break: break-all;
    box-shadow: var(--shadow-sm);
}

/* Protección de placeholders */
.placeholder-protection {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border: 2px solid var(--warning-color);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.placeholder-tag {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--white);
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
    font-size: 0.75rem;
    margin: 0.25rem;
    display: inline-block;
    font-weight: 600;
    letter-spacing: 0.05em;
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

/* Modal elegante */
.modal-content {
    border-radius: var(--border-radius-lg);
    border: none;
    box-shadow: var(--shadow-xl);
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--white);
    border-bottom: none;
    padding: 1.5rem;
}

.modal-title {
    font-weight: 600;
    letter-spacing: -0.025em;
}

.btn-close {
    filter: brightness(0) invert(1);
}

.modal-body {
    padding: 2rem;
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
    
    .stats-mini {
        margin-bottom: 1rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .btn-whatsapp {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
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
    width: 6px;
    height: 6px;
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

/* Estados especiales de inputs */
.form-control[style*="border-color: rgb(255, 193, 7)"] {
    border-color: var(--warning-color) !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1) !important;
}

.form-control[style*="border-color: rgb(40, 167, 69)"] {
    border-color: var(--success-color) !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}

/* Toasts */
.toast {
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    backdrop-filter: blur(20px);
}

/* Efecto de carga para botones */
.btn.loading {
    position: relative;
    color: transparent;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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
    background: rgba(0, 0, 0, 0.3);  /* ← Menos opaco */
    z-index: 10000;
    backdrop-filter: blur(1px);      /* ← Menos blur */
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
    box-shadow: 0 0 0 4px #ef4444, 0 0 0 8px rgba(239, 68, 68, 0.3) !important;
    border-radius: var(--border-radius) !important;
    animation: tutorialGlow 1.5s infinite alternate;
    background: rgba(255, 255, 255, 0.95) !important;
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
        box-shadow: 0 0 0 4px #ef4444, 0 0 0 8px rgba(239, 68, 68, 0.3);
    }
    to { 
        box-shadow: 0 0 0 6px #f87171, 0 0 0 12px rgba(239, 68, 68, 0.5);
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
    <i class="fab fa-whatsapp me-2"></i>
    Sistema de Envío
</span>
<button class="btn btn-outline-light btn-sm ms-auto tutorial-btn" onclick="startTutorial()">
    <i class="fas fa-question-circle me-1"></i>
    ¿Cómo usar?
</button>
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
    
    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-mini">
                <span class="stat-number"><?php echo $total_invitados; ?></span>
                <span class="stat-label">Total Invitados</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-mini">
                <span class="stat-number"><?php echo $enviados; ?></span>
                <span class="stat-label">Ya Enviados</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-mini">
                <span class="stat-number"><?php echo $por_enviar; ?></span>
                <span class="stat-label">Por Enviar</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-mini">
                <span class="stat-number"><?php echo $confirmados; ?></span>
                <span class="stat-label">Confirmados</span>
            </div>
        </div>
    </div>

    <!-- Enlaces de navegación -->
<div class="container-fluid mt-4 mb-3">
    <div class="text-center">
        <a href="../../index.html" class="btn btn-outline-primary me-2">
            💌 Invitación
        </a>
        <a href="dashboard.php" class="btn btn-outline-primary me-2">
            👥 Dashboard
        </a>
        <a href="generador.php" class="btn btn-outline-primary me-2">
            📱 Agregar invitados
        </a>
        <a href="../scanner/control.php" class="btn btn-outline-primary me-2">
            🔍 Control de Acceso
        </a>
        <a href="../rsvp/confirmar.php" class="btn btn-outline-primary">
            ✅ Confirmar Asistencia
        </a>
    </div>
</div>
    
    <div class="row">
        
        <!-- Columna única reorganizada -->
        <div class="col-12">
            
            <!-- Editor de Mensaje Personalizado -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Crear Mensaje de Invitación
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Información sobre placeholders -->
                    <div class="placeholder-protection">
                        <h6><i class="fas fa-info-circle me-2"></i>Variables automáticas (OBLIGATORIAS):</h6>
                        <p class="mb-2">Estas variables se reemplazarán automáticamente para cada invitado:</p>
                        <div>
                            <span class="placeholder-tag">{NOMBRE_INVITADO}</span> = Nombre del invitado
                            <span class="placeholder-tag">{TOKEN_INVITADO}</span> = Código único del invitado
                            <span class="placeholder-tag">{LINK_CONFIRMACION}</span> = Link de confirmación personalizado
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Importante:</strong> Estas variables deben estar presentes en tu mensaje o no funcionará correctamente.
                        </small>
                    </div>
                    
                    <!-- Ejemplo de mensaje -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>Ejemplo de mensaje:</h6>
                        <div class="message-preview" style="font-size: 0.9rem; max-height: 150px;">
                            <?php echo htmlspecialchars($example_message); ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="useExample()">
                            <i class="fas fa-copy me-1"></i>Usar este ejemplo
                        </button>
                    </div>
                    
                    <div class="row">
                        
                        <!-- Editor principal -->
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <strong>Escribe tu mensaje personalizado:</strong>
                                    <span class="badge bg-success ms-2" id="save-status" style="display: none;">
                                        <i class="fas fa-save me-1"></i>Guardado automáticamente
                                    </span>
                                </label>
                                <textarea id="custom-message" class="form-control" rows="12" 
                                          placeholder="Escribe aquí tu mensaje personalizado para WhatsApp...

Recuerda incluir estas variables:
{NOMBRE_INVITADO} - para el nombre
{LINK_CONFIRMACION} - para el link de confirmación  
{TOKEN_INVITADO} - para el código único

Ejemplo: Hola {NOMBRE_INVITADO}, te invitamos a nuestra boda..."></textarea>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-save me-1"></i>
                                        Tu mensaje se guarda automáticamente mientras escribes.
                                    </small>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearMessage()">
                                            <i class="fas fa-trash me-1"></i>Limpiar todo
                                        </button>
                                        <small class="text-success" id="auto-save-indicator" style="display: none;">
                                            <i class="fas fa-check-circle me-1"></i>Guardado
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vista previa -->
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Vista previa del mensaje:</strong></label>
                                <div class="message-preview" id="message-preview" style="min-height: 200px;">
                                    Escribe tu mensaje en el editor para ver la vista previa aquí...
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-eye me-1"></i>
                                    Esta es una vista previa con datos de ejemplo (Juan Pérez).
                                </small>
                            </div>
                          
                            </div>
                        </div>
                        
                    </div>
                    
                </div>
            </div>
            
        </div>
        
    </div>
    
    <!-- Lista de Invitados para Envío -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-paper-plane me-2"></i>
                        Enviar Invitaciones
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Invitado</th>
                                    <th>Mesa</th>
                                    <th>Token</th>
                                    <th>Enviado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invitados as $invitado): ?>
                                <?php 
                                $nombre_simple = explode(' ', $invitado['nombre_completo'])[0];
                                $whatsapp_link = $confirmacion_url . $invitado['token'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($invitado['nombre_completo']); ?></strong>
                                        <br>
                                        <?php if ($invitado['telefono']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($invitado['telefono']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">Mesa <?php echo $invitado['mesa']; ?></span>
                                    </td>
                                    <td>
                                        <span class="token-display"><?php echo $invitado['token']; ?></span>
                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo $invitado['token']; ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ($invitado['ya_enviado']): ?>
                                            <span class="status-badge status-enviado">
                                                <i class="fas fa-check me-1"></i>
                                                Enviado
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($invitado['enviado_whatsapp'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="status-badge status-no-enviado">
                                                <i class="fas fa-times me-1"></i>
                                                No enviado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <!-- Botón WhatsApp -->
                                            <button class="btn-whatsapp btn-sm" 
                                                    onclick="sendWhatsApp('<?php echo $invitado['telefono']; ?>', '<?php echo $nombre_simple; ?>', '<?php echo $whatsapp_link; ?>', '<?php echo $invitado['token']; ?>', <?php echo $invitado['id_invitado']; ?>)"
                                                    <?php echo !$invitado['telefono'] ? 'disabled title="Sin teléfono"' : ''; ?>>
                                                <i class="fab fa-whatsapp"></i>
                                                WhatsApp
                                            </button>
                                            
                                            <!-- Copiar Link -->
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="copyToClipboard('<?php echo $whatsapp_link; ?>')">
                                                <i class="fas fa-link"></i>
                                            </button>
                                            
                                            <!-- Ver Link -->
                                            <button class="btn btn-outline-info btn-sm" 
                                                    onclick="showLinkModal('<?php echo htmlspecialchars($invitado['nombre_completo']); ?>', '<?php echo $whatsapp_link; ?>', '<?php echo $invitado['token']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Modal para mostrar link -->
<div class="modal fade" id="linkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Link de Invitación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="modal-invitado-name"></h6>
                <div class="mb-3">
                    <label class="form-label">Link de Confirmación:</label>
                    <div class="url-display" id="modal-link"></div>
                    <button class="btn btn-outline-primary btn-sm mt-2" onclick="copyModalLink()">
                        <i class="fas fa-copy me-1"></i>
                        Copiar Link
                    </button>
                </div>
                
                <div>
                    <label class="form-label">Mensaje para WhatsApp:</label>
                    <textarea id="modal-message" class="form-control" rows="8" readonly></textarea>
                    <button class="btn btn-outline-primary btn-sm mt-2" onclick="copyModalMessage()">
                        <i class="fas fa-copy me-1"></i>
                        Copiar Mensaje
                    </button>
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
// Configuración
const baseUrl = '<?php echo $base_url; ?>';
const invitacionUrl = '<?php echo $invitacion_url; ?>';
const exampleMessage = <?php echo json_encode($example_message); ?>;

let currentMessage = '';

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando editor de mensajes...');
    
    // Cargar mensaje guardado o mostrar placeholder
    loadSavedMessage();
    
    // Configurar auto-guardado
    setupAutoSave();
    
    // Actualizar vista previa inicial
    updatePreview();
});

// Cargar mensaje guardado
function loadSavedMessage() {
    const savedMessage = localStorage.getItem('custom_wedding_message');
    const textarea = document.getElementById('custom-message');
    
    if (savedMessage && textarea) {
        textarea.value = savedMessage;
        currentMessage = savedMessage;
        showSaveStatus('✅ Mensaje guardado cargado');
        console.log('Mensaje guardado cargado');
    } else if (textarea) {
        textarea.value = '';
        currentMessage = '';
    }
}

// Configurar auto-guardado
function setupAutoSave() {
    const textarea = document.getElementById('custom-message');
    if (!textarea) return;
    
    let saveTimeout;
    
    textarea.addEventListener('input', function() {
        currentMessage = this.value;
        
        // Validar placeholders
        validatePlaceholders(this);
        
        // Actualizar vista previa
        updatePreview();
        
        // Auto-guardar después de 1 segundo
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            saveMessage();
        }, 1000);
    });
    
    // También guardar cuando pierde el foco
    textarea.addEventListener('blur', function() {
        clearTimeout(saveTimeout);
        saveMessage();
    });
}

// Guardar mensaje
function saveMessage() {
    const textarea = document.getElementById('custom-message');
    if (!textarea) return;
    
    const message = textarea.value.trim();
    
    if (message) {
        localStorage.setItem('custom_wedding_message', message);
        showSaveStatus('Guardado automáticamente');
        console.log('Mensaje guardado:', message.substring(0, 50) + '...');
    }
}

// Actualizar vista previa
function updatePreview() {
    const textarea = document.getElementById('custom-message');
    const preview = document.getElementById('message-preview');
    
    if (!textarea || !preview) return;
    
    const message = textarea.value;
    
    if (!message.trim()) {
        preview.textContent = 'Escribe tu mensaje en el editor para ver la vista previa aquí...';
        return;
    }
    
    // Generar vista previa con datos de ejemplo
    let previewMessage = message
        .replace(/{NOMBRE_INVITADO}/g, 'Juan Pérez')
        .replace(/{LINK_CONFIRMACION}/g, baseUrl + '/php/rsvp/confirmar.php?token=ABC7-XY92-MN54')
        .replace(/{TOKEN_INVITADO}/g, 'ABC7-XY92-MN54')
        .replace(/{invitacion}/g, invitacionUrl);
    
    preview.textContent = previewMessage;
}

// Usar ejemplo
function useExample() {
    const textarea = document.getElementById('custom-message');
    if (!textarea) return;
    
    if (textarea.value && !confirm('¿Reemplazar tu mensaje actual con el ejemplo?')) {
        return;
    }
    
    textarea.value = exampleMessage;
    currentMessage = exampleMessage;
    
    // Actualizar vista previa
    updatePreview();
    
    // Guardar
    saveMessage();
    
    showToast('Ejemplo cargado en el editor');
}

// Limpiar mensaje
function clearMessage() {
    if (!confirm('¿Estás seguro de que quieres borrar todo el mensaje?')) {
        return;
    }
    
    const textarea = document.getElementById('custom-message');
    if (textarea) {
        textarea.value = '';
        currentMessage = '';
        
        // Eliminar guardado
        localStorage.removeItem('custom_wedding_message');
        
        // Actualizar vista previa
        updatePreview();
        
        // Ocultar indicadores
        hideSaveStatus();
        
        showToast('Mensaje eliminado');
    }
}

// Mostrar indicador de guardado
function showSaveStatus(text = 'Guardado automáticamente') {
    const saveStatus = document.getElementById('save-status');
    const autoSaveIndicator = document.getElementById('auto-save-indicator');
    
    if (saveStatus) {
        saveStatus.innerHTML = `<i class="fas fa-save me-1"></i>${text}`;
        saveStatus.style.display = 'inline-block';
    }
    
    if (autoSaveIndicator) {
        autoSaveIndicator.style.display = 'inline-block';
        setTimeout(() => {
            autoSaveIndicator.style.display = 'none';
        }, 2000);
    }
    
    // Ocultar después de un tiempo
    if (text.includes('automáticamente')) {
        setTimeout(() => {
            if (saveStatus) saveStatus.style.display = 'none';
        }, 4000);
    }
}

// Ocultar indicador de guardado
function hideSaveStatus() {
    const saveStatus = document.getElementById('save-status');
    const autoSaveIndicator = document.getElementById('auto-save-indicator');
    
    if (saveStatus) saveStatus.style.display = 'none';
    if (autoSaveIndicator) autoSaveIndicator.style.display = 'none';
}

// Validar placeholders
function validatePlaceholders(textarea) {
    const requiredPlaceholders = ['{NOMBRE_INVITADO}', '{LINK_CONFIRMACION}', '{TOKEN_INVITADO}'];
    const content = textarea.value;
    let missingPlaceholders = [];
    
    requiredPlaceholders.forEach(placeholder => {
        if (!content.includes(placeholder)) {
            missingPlaceholders.push(placeholder);
        }
    });
    
    // Cambiar color del borde
    if (missingPlaceholders.length > 0) {
        textarea.style.borderColor = '#ffc107';
        textarea.title = 'Faltan variables obligatorias: ' + missingPlaceholders.join(', ');
    } else {
        textarea.style.borderColor = '#28a745';
        textarea.title = 'Todas las variables están presentes ✓';
    }
}

// Enviar por WhatsApp
function sendWhatsApp(telefono, nombre, link, token, idInvitado) {
    if (!currentMessage || !currentMessage.trim()) {
        alert('Por favor escribe tu mensaje personalizado primero');
        return;
    }
    
    // Validar variables obligatorias
    const requiredPlaceholders = ['{NOMBRE_INVITADO}', '{LINK_CONFIRMACION}', '{TOKEN_INVITADO}'];
    const missingPlaceholders = requiredPlaceholders.filter(placeholder => !currentMessage.includes(placeholder));
    
    if (missingPlaceholders.length > 0) {
        alert(`Tu mensaje debe incluir estas variables obligatorias:\n${missingPlaceholders.join(', ')}\n\nSin ellas el mensaje no funcionará correctamente.`);
        return;
    }
    
    // Personalizar mensaje
    let mensaje = currentMessage
        .replace(/{NOMBRE_INVITADO}/g, nombre)
        .replace(/{LINK_CONFIRMACION}/g, link)
        .replace(/{TOKEN_INVITADO}/g, token)
        .replace(/{invitacion}/g, invitacionUrl);
    
    // Crear URL de WhatsApp
    let whatsappUrl = 'https://wa.me/';
    
    if (telefono && telefono.trim() !== '') {
        // Limpiar número de teléfono
        let numeroLimpio = telefono.replace(/\D/g, '');
        if (numeroLimpio.startsWith('1')) {
            numeroLimpio = '52' + numeroLimpio; // México
        } else if (!numeroLimpio.startsWith('52')) {
            numeroLimpio = '52' + numeroLimpio; // Agregar código de México
        }
        whatsappUrl += numeroLimpio;
    }
    
    whatsappUrl += '?text=' + encodeURIComponent(mensaje);
    
    // Abrir WhatsApp
    window.open(whatsappUrl, '_blank');
    
    // Marcar como enviado automáticamente
    if (idInvitado) {
        markAsSent(idInvitado);
    }
}// Copiar al portapapeles
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copiado al portapapeles');
    }).catch(err => {
        console.error('Error al copiar: ', err);
        // Fallback para navegadores antiguos
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Copiado al portapapeles');
    });
}

// Mostrar modal con link
function showLinkModal(nombre, link, token) {
    document.getElementById('modal-invitado-name').textContent = nombre;
    document.getElementById('modal-link').textContent = link;
    
    // Generar mensaje personalizado
    if (currentMessage && currentMessage.trim()) {
        let mensaje = currentMessage
            .replace(/{NOMBRE_INVITADO}/g, nombre.split(' ')[0])
            .replace(/{LINK_CONFIRMACION}/g, link)
            .replace(/{TOKEN_INVITADO}/g, token)
            .replace(/{invitacion}/g, invitacionUrl);
        document.getElementById('modal-message').value = mensaje;
    } else {
        document.getElementById('modal-message').value = 'No hay mensaje personalizado creado. Por favor escribe tu mensaje primero.';
    }
    
    new bootstrap.Modal(document.getElementById('linkModal')).show();
}

// Copiar link del modal
function copyModalLink() {
    const link = document.getElementById('modal-link').textContent;
    copyToClipboard(link);
}

// Copiar mensaje del modal
function copyModalMessage() {
    const message = document.getElementById('modal-message').value;
    copyToClipboard(message);
}

// Mostrar toast de notificación
function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remover después de ocultarse
    toast.addEventListener('hidden.bs.toast', () => {
        document.body.removeChild(toast);
    });
}

// Marcar como enviado
function markAsSent(idInvitado) {
    fetch('envios.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `mark_sent=1&id_invitado=${idInvitado}`
    })
    .then(response => response.text())
    .then(data => {
        showToast('Marcado como enviado');
        setTimeout(() => {
            location.reload();
        }, 1500);
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Envío masivo
let massiveSendData = [];
let currentIndex = 0;
let totalToSend = 0;

function startMassiveSend(forceResend = false) {
    if (!currentMessage || !currentMessage.trim()) {
        alert('Por favor escribe tu mensaje personalizado primero');
        return;
    }
    
    // Validar variables obligatorias
    const requiredPlaceholders = ['{NOMBRE_INVITADO}', '{LINK_CONFIRMACION}', '{TOKEN_INVITADO}'];
    const missingPlaceholders = requiredPlaceholders.filter(placeholder => !currentMessage.includes(placeholder));
    
    if (missingPlaceholders.length > 0) {
        alert(`Tu mensaje debe incluir estas variables obligatorias:\n${missingPlaceholders.join(', ')}\n\nSin ellas el envío masivo no funcionará correctamente.`);
        return;
    }
    
    // Obtener lista de invitados a enviar
    massiveSendData = <?php echo json_encode($invitados); ?>;
    
    // Filtrar invitados
    massiveSendData = massiveSendData.filter(invitado => {
        // Debe tener teléfono
        if (!invitado.telefono || invitado.telefono.trim() === '') return false;
        
        // Si no es forzado, solo los no enviados
        if (!forceResend && invitado.ya_enviado) return false;
        
        return true;
    });
    
    totalToSend = massiveSendData.length;
    
    if (totalToSend === 0) {
        alert('No hay invitados pendientes de envío');
        return;
    }
    
    if (!confirm(`¿Enviar tu mensaje personalizado a ${totalToSend} invitados?\n\nEsto abrirá WhatsApp automáticamente para cada uno.`)) {
        return;
    }
    
    // Mostrar progreso
    document.getElementById('mass-send-progress').style.display = 'block';
    currentIndex = 0;
    
    // Iniciar envío
    sendNextMessage();
}

function sendNextMessage() {
    if (currentIndex >= totalToSend) {
        // Completado
        updateProgress(100, 'Envío completado', `${totalToSend}/${totalToSend}`);
        setTimeout(() => {
            document.getElementById('mass-send-progress').style.display = 'none';
            showToast('¡Envío masivo completado!');
            location.reload();
        }, 2000);
        return;
    }
    
    const invitado = massiveSendData[currentIndex];
    const progress = Math.round((currentIndex / totalToSend) * 100);
    
    updateProgress(progress, `Enviando a ${invitado.nombre_completo}`, `${currentIndex + 1}/${totalToSend}`);
    
    // Enviar mensaje
    const nombre = invitado.nombre_completo.split(' ')[0];
    const link = baseUrl + '/php/rsvp/confirmar.php?token=' + invitado.token;
    
    // Crear mensaje personalizado
    let mensaje = currentMessage
        .replace(/{NOMBRE_INVITADO}/g, nombre)
        .replace(/{LINK_CONFIRMACION}/g, link)
        .replace(/{TOKEN_INVITADO}/g, invitado.token)
        .replace(/{invitacion}/g, invitacionUrl);
    
    // Crear URL de WhatsApp
    let whatsappUrl = 'https://wa.me/';
    let numeroLimpio = invitado.telefono.replace(/\D/g, '');
    if (numeroLimpio.startsWith('1')) {
        numeroLimpio = '52' + numeroLimpio;
    } else if (!numeroLimpio.startsWith('52')) {
        numeroLimpio = '52' + numeroLimpio;
    }
    whatsappUrl += numeroLimpio + '?text=' + encodeURIComponent(mensaje);
    
    // Abrir WhatsApp
    window.open(whatsappUrl, '_blank');
    
    // Marcar como enviado
    markAsSent(invitado.id_invitado);
    
    // Continuar con el siguiente después de 3 segundos
    currentIndex++;
    setTimeout(sendNextMessage, 3000);
}

function updateProgress(percent, text, count) {
    document.getElementById('progress-bar').style.width = percent + '%';
    document.getElementById('progress-text').textContent = text;
    document.getElementById('progress-count').textContent = count;
}

/* ===============================
   TUTORIAL SYSTEM
   =============================== */

// Configuración del tutorial
const tutorialSteps = [
    {
        target: '#custom-message',
        title: '📝 Crear tu Mensaje',
        content: 'Aquí escribes tu invitación personalizada. Tu mensaje se guarda automáticamente mientras escribes. ¡Puedes ser creativo y usar emojis!',
        position: 'right'
    },
    {
        target: '.placeholder-protection',
        title: '🏷️ Variables Mágicas',
        content: 'Estas variables son OBLIGATORIAS y se reemplazan automáticamente: {NOMBRE_INVITADO} se convierte en "Juan", {LINK_CONFIRMACION} en el link único, etc.',
        position: 'bottom'
    },
    {
        target: '#message-preview',
        title: '👀 Vista Previa',
        content: 'Aquí ves exactamente cómo se verá tu mensaje con datos de ejemplo. Se actualiza en tiempo real mientras escribes.',
        position: 'left'
    },
    {
        target: '.alert-info button',
        title: '💡 Usar Ejemplo',
        content: 'Si no sabes cómo empezar, usa este botón para cargar un mensaje de ejemplo que puedes personalizar.',
        position: 'top'
    },
    {
        target: '.table tbody tr:first-child .btn-whatsapp',
        title: '📱 Enviar por WhatsApp',
        content: 'Haz clic para abrir WhatsApp con tu mensaje personalizado. Se marca automáticamente como enviado.',
        position: 'left'
    },
    {
        target: '.table tbody tr:first-child .status-badge',
        title: '📊 Estado del Envío',
        content: 'Verde = Ya enviado, Rojo = Pendiente. Puedes ver cuándo se envió cada invitación.',
        position: 'left'
    },
    {
        target: '.table tbody tr:first-child .btn-outline-primary',
        title: '🔗 Copiar Link',
        content: 'Copia el link único de confirmación para enviarlo por otros medios (SMS, email, etc.).',
        position: 'top'
    },
    {
        target: '.stats-mini',
        title: '📈 Estadísticas',
        content: '¡Listo! Aquí puedes ver el progreso: total de invitados, enviados, pendientes y confirmaciones recibidas.',
        position: 'bottom'
    }
];

let currentTutorialStep = 0;
let tutorialActive = false;

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