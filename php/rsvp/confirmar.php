<?php
/**
 * FORMULARIO DE CONFIRMACIÓN DE ASISTENCIA
 * Invitación Digital de Boda
 */

require_once '../config/database.php';

// Obtener token desde URL o POST
$token = '';
$invitado = null;
$error = '';
$success = '';

// Si viene el token por URL
if (isset($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
}

// Si es POST (formulario enviado)
if ($_POST) {
    if (isset($_POST['token'])) {
        $token = sanitizeInput($_POST['token']);
    }
    
    // Procesar confirmación - verificar si se envió el botón de confirmar
    if (isset($_POST['confirmar_asistencia'])) {
        try {
            $id_invitado = (int)$_POST['id_invitado'];
            $cantidad_confirmada = (int)$_POST['cantidad_confirmada'];
            $observaciones = sanitizeInput($_POST['observaciones'] ?? '');
            
            $db = getDB();
            $db->confirmarAsistencia($id_invitado, $cantidad_confirmada, $observaciones);
            
            $success = '¡Confirmación exitosa! Tu código QR ha sido generado.';
            
            // Recargar datos del invitado
            $invitado = $db->getInvitadoPorToken($token);
            
        } catch (Exception $e) {
            $error = 'Error al confirmar asistencia: ' . $e->getMessage();
        }
    }
}

// Buscar invitado por token
if ($token && !$invitado) {
    if (!isValidTokenFormat($token)) {
        $error = 'Formato de código inválido.';
    } else {
        try {
            $db = getDB();
            $invitado = $db->getInvitadoPorToken($token);
            
            if (!$invitado) {
                $error = 'Código no encontrado. Verifica que sea correcto.';
            }
        } catch (Exception $e) {
            $error = 'Error al buscar invitado: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Asistencia - Nuestra Boda</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #d4af37;
            --secondary-color: #8b6f47;
            --accent-color: #f8f5f0;
            --text-dark: #2c2c2c;
            --white: #ffffff;
            --gradient-primary: linear-gradient(135deg, #d4af37 0%, #b8941f 100%);
            --font-primary: 'Playfair Display', serif;
            --font-secondary: 'Lato', sans-serif;
        }
        
        body {
            font-family: var(--font-secondary);
            background: var(--accent-color);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .main-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: var(--gradient-primary);
            color: var(--white);
            text-align: center;
            padding: 2rem;
            border: none;
        }
        
        .card-title {
            font-family: var(--font-primary);
            font-size: 2rem;
            margin: 0;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
        }
        
        .invitado-info {
            background: var(--white);
            border: 2px solid var(--primary-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .invitado-info h5 {
            color: var(--primary-color);
            font-family: var(--font-primary);
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .qr-section {
            text-align: center;
            background: var(--accent-color);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 1.5rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--secondary-color);
            transform: translateX(-5px);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="main-container">
        
        <!-- Link para volver -->
        <a href="../../index.html" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>
            Volver a la invitación
        </a>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-check-circle me-2"></i>
                    Confirmación de Asistencia
                </h2>
                <p class="mb-0">Por favor confirma tu asistencia a nuestra boda</p>
            </div>
            
            <div class="card-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$invitado): ?>
                    <!-- Formulario para ingresar token -->
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="token" class="form-label">
                                <i class="fas fa-key me-2"></i>
                                Código de Invitación
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="token" 
                                   name="token" 
                                   value="<?php echo htmlspecialchars($token); ?>"
                                   placeholder="Ej: KX7M-N9P2-Q4R8"
                                   pattern="[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}(-[A-Z0-9]{4})?"
                                   required>
                            <div class="form-text">
                                Ingresa el código que recibiste por WhatsApp
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>
                            Buscar Invitación
                        </button>
                    </form>
                    
                <?php else: ?>
                    <!-- Información del invitado -->
                    <div class="invitado-info">
                        <h5>
                            <i class="fas fa-user me-2"></i>
                            Información del Invitado
                        </h5>
                        
                        <div class="info-item">
                            <span><strong>Nombre:</strong></span>
                            <span><?php echo htmlspecialchars($invitado['nombre_completo']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span><strong>Mesa asignada:</strong></span>
                            <span>Mesa <?php echo $invitado['mesa']; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span><strong>Cupos disponibles:</strong></span>
                            <span><?php echo $invitado['cupos_disponibles']; ?> persona(s)</span>
                        </div>
                        
                        <?php if ($invitado['fecha_confirmacion']): ?>
                            <div class="info-item">
                                <span><strong>Confirmado el:</strong></span>
                                <span><?php echo date('d/m/Y H:i', strtotime($invitado['fecha_confirmacion'])); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span><strong>Cantidad confirmada:</strong></span>
                                <span><?php echo $invitado['cantidad_confirmada']; ?> persona(s)</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($invitado['fecha_confirmacion']): ?>
                        <!-- Ya confirmó - Solo mostrar información y QR -->
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>¡Asistencia ya confirmada!</strong><br>
                            Tu confirmación fue registrada exitosamente el <?php echo date('d/m/Y', strtotime($invitado['fecha_confirmacion'])); ?>.
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>¿Necesitas hacer cambios?</strong><br>
                            Si necesitas modificar tu confirmación, contacta directamente a los novios.
                        </div>
                        
                        <!-- Mostrar código QR -->
                        <?php
                        $tokenQR = getDB()->getTokenQR($invitado['id_invitado']);
                        if ($tokenQR):
                        ?>
                        <div class="qr-section">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-qrcode me-2"></i>
                                Tu Código QR para el Evento
                            </h5>
                            <p class="mb-3">Presenta este código el día de la boda:</p>
                            
                            <!-- Aquí se generará el QR -->
                            <div id="qrcode" class="mb-3"></div>
                            
                            <p class="small text-muted">
                                Código: <?php echo htmlspecialchars($tokenQR['token_unico']); ?>
                            </p>
                            
                            <button class="btn btn-outline-primary" onclick="descargarQR()">
                                <i class="fas fa-download me-2"></i>
                                Descargar QR
                            </button>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- Formulario de confirmación - Solo si NO ha confirmado -->
                        <form id="formConfirmacion" method="POST" action="">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="id_invitado" value="<?php echo $invitado['id_invitado']; ?>">
                            
                            <div class="mb-3">
                                <label for="cantidad_confirmada" class="form-label">
                                    <i class="fas fa-users me-2"></i>
                                    ¿Cuántas personas van a asistir?
                                </label>
                                <select class="form-select" id="cantidad_confirmada" name="cantidad_confirmada" required>
                                    <option value="">Selecciona la cantidad</option>
                                    <?php for ($i = 0; $i <= $invitado['cupos_disponibles']; $i++): ?>
                                        <option value="<?php echo $i; ?>">
                                            <?php echo $i; ?> persona<?php echo $i != 1 ? 's' : ''; ?>
                                            <?php echo $i == 0 ? ' (No asistiré)' : ''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        
                            <button type="submit" name="confirmar_asistencia" class="btn btn-primary w-100" id="btnConfirmar">
                                <i class="fas fa-check me-2"></i>
                                Confirmar Asistencia
                            </button>
                        </form>
                    <?php endif; ?>
                    
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<!-- QR Code Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>

<script>
/**
 * FUNCIÓN PARA ACTUALIZAR AUTOMÁTICAMENTE LA TABLA DE INVITADOS
 * Esta función se ejecuta DESPUÉS de que se procese la confirmación
 * y actualiza la tabla en el dashboard sin necesidad de recargar la página
 */
function actualizarTablaInvitados(idInvitado, cantidadConfirmada, token) {
    console.log('🔄 Iniciando actualización automática de tabla...', {
        idInvitado,
        cantidadConfirmada,
        token
    });
    
    // Función para actualizar la tabla en el dashboard (si está abierto)
    function actualizarDashboard() {
        // Buscar si hay una ventana del dashboard abierta
        const dashboardWindows = window.opener || window.parent;
        
        if (dashboardWindows && dashboardWindows !== window) {
            try {
                console.log('🔍 Buscando dashboard abierto...');
                
                // Intentar actualizar la tabla en el dashboard
                if (typeof dashboardWindows.actualizarFilaInvitado === 'function') {
                    dashboardWindows.actualizarFilaInvitado(idInvitado, {
                        cantidad_confirmada: cantidadConfirmada,
                        fecha_confirmacion: new Date().toISOString(),
                        estado: 'confirmado'
                    });
                    console.log('✅ Tabla del dashboard actualizada exitosamente');
                } else {
                    console.log('ℹ️ Función de actualización no disponible en el dashboard');
                }
            } catch (error) {
                console.log('ℹ️ No se pudo actualizar el dashboard:', error.message);
            }
        } else {
            console.log('ℹ️ No se encontró ventana del dashboard abierta');
        }
    }
    
    // Función para mostrar indicador visual de actualización
    function mostrarIndicadorActualizacion() {
        // Crear un indicador visual temporal
        const indicador = document.createElement('div');
        indicador.id = 'indicador-actualizacion';
        indicador.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(45deg, #28a745, #20c997);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 9999;
                font-weight: 500;
                animation: slideInRight 0.3s ease;
            ">
                <i class="fas fa-sync-alt me-2"></i>
                Actualizando tabla de invitados...
            </div>
        `;
        
        // Agregar estilos CSS para la animación
        if (!document.getElementById('estilos-actualizacion')) {
            const estilos = document.createElement('style');
            estilos.id = 'estilos-actualizacion';
            estilos.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(estilos);
        }
        
        document.body.appendChild(indicador);
        
        // Remover el indicador después de 3 segundos
        setTimeout(() => {
            if (indicador.parentNode) {
                indicador.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (indicador.parentNode) {
                        indicador.parentNode.removeChild(indicador);
                    }
                }, 300);
            }
        }, 3000);
    }
    
    // Ejecutar todas las funciones de actualización
    try {
        // Mostrar indicador visual
        mostrarIndicadorActualizacion();
        
        // Actualizar dashboard (si está abierto)
        actualizarDashboard();
        
        console.log('🎉 Proceso de actualización completado exitosamente');
        
    } catch (error) {
        console.error('❌ Error durante la actualización:', error);
    }
}

// Generar QR Code si existe el token
<?php if ($invitado && $invitado['fecha_confirmacion']): ?>
    <?php $tokenQR = getDB()->getTokenQR($invitado['id_invitado']); ?>
    <?php if ($tokenQR): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Datos para el QR
        const qrData = {
            token: '<?php echo $tokenQR['token_unico']; ?>',
            nombre: '<?php echo addslashes($invitado['nombre_completo']); ?>',
            mesa: <?php echo $invitado['mesa']; ?>,
            cantidad: <?php echo $invitado['cantidad_confirmada']; ?>
        };
        
        // Generar QR
        const qr = qrcode(0, 'M');
        qr.addData(JSON.stringify(qrData));
        qr.make();
        
        // Crear imagen del QR
        const qrElement = document.getElementById('qrcode');
        qrElement.innerHTML = qr.createImgTag(4, 8);
        
        // Agregar estilos a la imagen
        const img = qrElement.querySelector('img');
        if (img) {
            img.style.border = '3px solid #d4af37';
            img.style.borderRadius = '10px';
            img.style.backgroundColor = 'white';
            img.style.padding = '10px';
        }
    });
    
    // Función mejorada para descargar QR con diseño elegante
    function descargarQR() {
        const imgElement = document.querySelector('#qrcode img');
        if (!imgElement) {
            alert('Error: No se encontró el código QR');
            return;
        }
        
        // Crear canvas con tamaño adecuado (tamaño carta)
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Configurar tamaño del canvas (más grande para mejor calidad)
        canvas.width = 800;
        canvas.height = 1000;
        
        // Datos del invitado
        const datosInvitado = {
            nombre: '<?php echo addslashes($invitado['nombre_completo']); ?>',
            mesa: <?php echo $invitado['mesa']; ?>,
            cantidad: <?php echo $invitado['cantidad_confirmada']; ?>,
            fecha: '<?php echo date('d/m/Y', strtotime($invitado['fecha_confirmacion'])); ?>'
        };
        
        // ⚠️ PERSONALIZAR ESTOS DATOS SEGÚN TU BODA
        const nombresNovios = "Guillermo & Wendy"; // 👈 Cambiar por los nombres reales
        const fechaBoda = "15 de Agosto, 2025"; // 👈 Cambiar por la fecha real
        const lugarBoda = "Salón de Eventos El Jardín"; // 👈 Agregar el lugar si deseas
        
        // Crear gradiente de fondo
        const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
        gradient.addColorStop(0, '#f8f5f0');
        gradient.addColorStop(0.5, '#ffffff');
        gradient.addColorStop(1, '#f8f5f0');
        
        // Aplicar fondo
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Agregar borde decorativo
        ctx.strokeStyle = '#d4af37';
        ctx.lineWidth = 8;
        ctx.strokeRect(20, 20, canvas.width - 40, canvas.height - 40);
        
        // Borde interno
        ctx.strokeStyle = '#b8941f';
        ctx.lineWidth = 2;
        ctx.strokeRect(35, 35, canvas.width - 70, canvas.height - 70);
        
        // Función para dibujar texto centrado
        function drawCenteredText(text, y, fontSize, color = '#2c2c2c', fontWeight = 'normal') {
            ctx.fillStyle = color;
            ctx.font = `${fontWeight} ${fontSize}px Georgia, serif`;
            ctx.textAlign = 'center';
            ctx.fillText(text, canvas.width / 2, y);
        }
        
        // Función para dibujar texto con sombra
        function drawTextWithShadow(text, y, fontSize, color = '#2c2c2c', fontWeight = 'normal') {
            // Sombra
            ctx.fillStyle = 'rgba(0,0,0,0.1)';
            ctx.font = `${fontWeight} ${fontSize}px Georgia, serif`;
            ctx.textAlign = 'center';
            ctx.fillText(text, canvas.width / 2 + 2, y + 2);
            
            // Texto principal
            ctx.fillStyle = color;
            ctx.fillText(text, canvas.width / 2, y);
        }
        
        // Título principal
        drawTextWithShadow(nombresNovios, 120, 48, '#d4af37', 'bold');
        
        // Subtítulo
        drawCenteredText('Nuestra Boda', 160, 24, '#8b6f47', 'italic');
        
        // Fecha de la boda
        drawCenteredText(fechaBoda, 190, 20, '#666666');
        
        // Línea decorativa
        ctx.strokeStyle = '#d4af37';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(canvas.width / 2 - 100, 210);
        ctx.lineTo(canvas.width / 2 + 100, 210);
        ctx.stroke();
        
        // Sección del invitado
        drawCenteredText('Invitado:', 270, 28, '#2c2c2c', 'bold');
        drawTextWithShadow(datosInvitado.nombre, 310, 36, '#d4af37', 'bold');
        
        // Información del invitado
        const infoY = 360;
        drawCenteredText('Mesa: ' + datosInvitado.mesa, infoY, 24, '#666666');
        drawCenteredText('Personas: ' + datosInvitado.cantidad, infoY + 35, 24, '#666666');
        drawCenteredText('Confirmado: ' + datosInvitado.fecha, infoY + 70, 20, '#888888');
        
        // Esperar a que la imagen del QR esté cargada
        const img = new Image();
        img.crossOrigin = 'anonymous';
        
        img.onload = function() {
            // Calcular posición centrada para el QR
            const qrSize = 280;
            const qrX = (canvas.width - qrSize) / 2;
            const qrY = 480;
            
            // Fondo blanco para el QR
            ctx.fillStyle = 'white';
            ctx.fillRect(qrX - 20, qrY - 20, qrSize + 40, qrSize + 40);
            
            // Borde dorado para el QR
            ctx.strokeStyle = '#d4af37';
            ctx.lineWidth = 4;
            ctx.strokeRect(qrX - 20, qrY - 20, qrSize + 40, qrSize + 40);
            
            // Dibujar el QR
            ctx.drawImage(img, qrX, qrY, qrSize, qrSize);
            
            // Texto debajo del QR
            drawCenteredText('Presenta este código el día de la boda', qrY + qrSize + 60, 20, '#666666');
            
            // Decoración en las esquinas
            drawCornerDecorations(ctx, canvas.width, canvas.height);
            
            // Crear y descargar
            const link = document.createElement('a');
            link.download = `invitacion-boda-${datosInvitado.nombre.toLowerCase().replace(/\s+/g, '-')}.png`;
            link.href = canvas.toDataURL('image/png', 1.0);
            
            // Simular click para descargar
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
        
        img.onerror = function() {
            alert('Error al cargar la imagen del QR');
        };
        
        // Cargar la imagen
        img.src = imgElement.src;
    }
    
    // Función para dibujar decoraciones en las esquinas
    function drawCornerDecorations(ctx, width, height) {
        const cornerSize = 60;
        const margin = 50;
        
        ctx.strokeStyle = '#d4af37';
        ctx.lineWidth = 3;
        
        // Esquina superior izquierda
        ctx.beginPath();
        ctx.moveTo(margin, margin + cornerSize);
        ctx.lineTo(margin, margin);
        ctx.lineTo(margin + cornerSize, margin);
        ctx.stroke();
        
        // Esquina superior derecha
        ctx.beginPath();
        ctx.moveTo(width - margin - cornerSize, margin);
        ctx.lineTo(width - margin, margin);
        ctx.lineTo(width - margin, margin + cornerSize);
        ctx.stroke();
        
        // Esquina inferior izquierda
        ctx.beginPath();
        ctx.moveTo(margin, height - margin - cornerSize);
        ctx.lineTo(margin, height - margin);
        ctx.lineTo(margin + cornerSize, height - margin);
        ctx.stroke();
        
        // Esquina inferior derecha
        ctx.beginPath();
        ctx.moveTo(width - margin - cornerSize, height - margin);
        ctx.lineTo(width - margin, height - margin);
        ctx.lineTo(width - margin, height - margin - cornerSize);
        ctx.stroke();
        
        // Pequeños círculos decorativos
        ctx.fillStyle = '#d4af37';
        const circlePositions = [
            [margin + cornerSize + 10, margin + cornerSize + 10],
            [width - margin - cornerSize - 10, margin + cornerSize + 10],
            [margin + cornerSize + 10, height - margin - cornerSize - 10],
            [width - margin - cornerSize - 10, height - margin - cornerSize - 10]
        ];
        
        circlePositions.forEach(pos => {
            ctx.beginPath();
            ctx.arc(pos[0], pos[1], 4, 0, 2 * Math.PI);
            ctx.fill();
        });
    }
    
    <?php endif; ?>
<?php endif; ?>

// Auto-formatear token mientras se escribe
document.addEventListener('DOMContentLoaded', function() {
    const tokenInput = document.getElementById('token');
    if (tokenInput) {
        tokenInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^A-Z0-9]/g, '').toUpperCase();
            let formatted = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += '-';
                }
                formatted += value[i];
            }
            
            e.target.value = formatted;
        });
    }
    
    // Manejar formulario de confirmación si existe
    const formConfirmacion = document.getElementById('formConfirmacion');
    if (formConfirmacion) {
        console.log('📝 Formulario de confirmación encontrado, configurando AJAX...');
        
        formConfirmacion.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('🔄 Enviando formulario con AJAX...');
            
            const form = this;
            const formData = new FormData(form);
            const btnConfirmar = document.getElementById('btnConfirmar');
            
            // Obtener datos del formulario
            const idInvitado = formData.get('id_invitado');
            const cantidadConfirmada = formData.get('cantidad_confirmada');
            const token = formData.get('token');
            
            // Agregar manualmente el valor del botón (necesario para que PHP lo detecte)
            formData.append('confirmar_asistencia', 'Confirmar Asistencia');
            
            console.log('📊 Datos del formulario:', { idInvitado, cantidadConfirmada, token });
            
            // Deshabilitar botón y mostrar loading
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            
            // Enviar formulario con AJAX
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📨 Respuesta recibida:', response.status);
                return response.text();
            })
            .then(html => {
                console.log('📄 HTML recibido, analizando...');
                console.log('📄 HTML completo recibido:', html);
                
                // Crear un elemento temporal para parsear el HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                
                // Buscar el mensaje de éxito
                const successAlert = tempDiv.querySelector('.alert-success');
                if (successAlert) {
                    console.log('✅ Confirmación exitosa detectada');
                    
                    // Mostrar mensaje de éxito
                    const alertContainer = document.createElement('div');
                    alertContainer.innerHTML = successAlert.outerHTML;
                    form.parentNode.insertBefore(alertContainer, form);
                    
                    // Ocultar formulario
                    form.style.display = 'none';
                    
                    // Actualizar dashboard inmediatamente
                    console.log('🎉 Actualizando dashboard...');
                    actualizarTablaInvitados(idInvitado, cantidadConfirmada, token);
                    
                    // Notificar a otras pestañas que hubo una confirmación
                    if ('BroadcastChannel' in window) {
                        const channel = new BroadcastChannel('confirmaciones_boda');
                        channel.postMessage({
                            tipo: 'confirmacion',
                            idInvitado,
                            cantidadConfirmada,
                            token
                        });
                        channel.close();
                    }
                    
                    // Recargar la página después de 3 segundos para mostrar el QR
                    setTimeout(() => {
                        console.log('🔄 Recargando página para mostrar QR...');
                        window.location.reload();
                    }, 3000);
                    
                } else {
                    console.log('❌ No se encontró mensaje de éxito');
                    console.log('🔍 Buscando otros elementos en el HTML...');
                    
                    // Buscar cualquier alerta
                    const anyAlert = tempDiv.querySelector('.alert');
                    if (anyAlert) {
                        console.log('⚠️ Se encontró una alerta:', anyAlert.className, anyAlert.textContent);
                    }
                    
                    // Buscar mensajes de error
                    const errorAlert = tempDiv.querySelector('.alert-danger');
                    if (errorAlert) {
                        console.log('❌ Se encontró error:', errorAlert.textContent);
                        const alertContainer = document.createElement('div');
                        alertContainer.innerHTML = errorAlert.outerHTML;
                        form.parentNode.insertBefore(alertContainer, form);
                    } else {
                        // Mostrar error genérico
                        const alertContainer = document.createElement('div');
                        alertContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error al procesar la confirmación. Intenta nuevamente.
                            </div>
                        `;
                        form.parentNode.insertBefore(alertContainer, form);
                    }
                    
                    // Restaurar botón
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="fas fa-check me-2"></i>Confirmar Asistencia';
                }
            })
            .catch(error => {
                console.error('❌ Error en la petición AJAX:', error);
                
                // Mostrar error genérico
                const alertContainer = document.createElement('div');
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error al procesar la confirmación. Intenta nuevamente.
                    </div>
                `;
                form.parentNode.insertBefore(alertContainer, form);
                
                // Restaurar botón
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="fas fa-check me-2"></i>Confirmar Asistencia';
            });
        });
    } else {
        console.log('ℹ️ No se encontró formulario de confirmación');
    }
});
</script>

</body>
</html>