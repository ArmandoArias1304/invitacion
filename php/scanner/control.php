<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Acceso - FastInvite</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* ===== PALETA DE COLORES MODO OSCURO ELEGANTE ===== */
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-gray: rgba(30, 30, 50, 0.9);
            --dark-gray: #64748b;
            --text-dark: #e2e8f0;
            --border-color: rgba(255, 255, 255, 0.1);
            --body-background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            --card-background: rgba(30, 30, 50, 0.8);
            --scanner-background: rgba(20, 20, 35, 0.95);
            --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            --card-header-background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.2));
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--body-background);
            color: var(--text-dark);
            min-height: 100vh;
            padding: 1rem;
        }
        
        .scanner-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            background: var(--card-background);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1.5rem;
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.3),
                0 0 25px rgba(99, 102, 241, 0.3),
                0 0 50px rgba(139, 92, 246, 0.2);
        }
        
        .card-header {
            background: var(--card-header-background);
            backdrop-filter: blur(10px);
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            padding: 2rem 1.5rem;
            border: none;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-title {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .card-subtitle {
            margin-top: 0.5rem;
            font-size: 0.95rem;
            opacity: 0.8;
            color: var(--dark-gray);
        }
        
        #scanner-container {
            position: relative;
            width: 100%;
            height: 350px;
            background: var(--scanner-background);
            border-radius: 1rem;
            overflow: hidden;
            margin: 1.5rem 0;
            border: 2px solid var(--border-color);
        }
        
        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 1rem;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 3px solid var(--primary-color);
            border-radius: 1rem;
            pointer-events: none;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
        }
        
        .scanner-overlay:hover {
            box-shadow: 
                0 0 20px rgba(99, 102, 241, 0.4),
                0 0 40px rgba(139, 92, 246, 0.3);
        }
        
        .scanner-line {
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
            box-shadow: 0 0 10px var(--primary-color);
            animation: scan 2s linear infinite;
        }
        
        @keyframes scan {
            0% { transform: translateY(-175px); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(175px); opacity: 0; }
        }
        
        .result-card {
            background: var(--card-background);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin: 1.5rem 0;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
            animation: fadeInUp 0.4s ease-out;
        }
        
        .result-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);
        }
        
        .result-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.2);
        }
        
        .result-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.2));
            border-color: rgba(245, 158, 11, 0.3);
            box-shadow: 0 8px 32px rgba(245, 158, 11, 0.2);
        }
        
        .btn {
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary-color));
            color: white;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-outline-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: rgba(255, 255, 255, 0.8);
        }
        
        .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        
        .btn-outline-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger-color);
        }
        
        .btn-outline-danger:hover {
            background: rgba(239, 68, 68, 0.2);
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.3);
        }
        
        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            display: block;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--dark-gray);
            font-weight: 500;
        }
        
        .stat-card.presentes .stat-number { color: var(--success-color); }
        .stat-card.confirmados .stat-number { color: var(--primary-color); }
        .stat-card.porcentaje .stat-number { color: var(--warning-color); }
        .stat-card.faltan .stat-number { color: var(--danger-color); }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .camera-controls {
            text-align: center;
            margin: 1.5rem 0;
        }
        
        .status-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .status-active { 
            background: var(--success-color);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }
        .status-inactive { 
            background: var(--danger-color);
            animation: none;
        }
        
        .status-text {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .alert {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 1rem;
            color: rgba(255, 255, 255, 0.9);
            padding: 1.5rem;
        }
        
        .alert i {
            color: var(--primary-color);
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            color: white;
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin-bottom: 0.75rem;
        }
        
        .input-group {
            max-width: 200px;
            margin: 0 auto;
        }
        
        .back-link {
            text-align: center;
            margin-top: 2rem;
        }
        
        .back-link a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link a:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            border-color: rgba(99, 102, 241, 0.5);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .scanner-container {
                padding: 0 0.5rem;
            }
            
            #scanner-container {
                height: 280px;
                margin: 1rem 0;
            }
            
            .card-header {
                padding: 1.5rem 1rem;
            }
            
            .card-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 1rem 0.75rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .result-card {
                padding: 1.5rem;
                margin: 1rem 0;
            }
        }
        
        /* ===== ANIMACIONES ===== */
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
        
        .card {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="scanner-container">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-qr-code-scan me-2"></i>
                    Control de Acceso
                </h3>
                <div class="card-subtitle">Sistema FastInvite - Escanea los códigos QR de los invitados</div>
            </div>
            
            <div class="card-body" style="padding: 2rem;">
                
                <!-- Estado de la cámara -->
                <div class="status-section">
                    <span class="status-indicator status-inactive" id="camera-status"></span>
                    <span class="status-text" id="camera-status-text">Cámara desactivada</span>
                </div>
                
                <!-- Contenedor del escáner -->
                <div id="scanner-container">
                    <video id="video" playsinline></video>
                    <div class="scanner-overlay">
                        <div class="scanner-line"></div>
                    </div>
                </div>
                
                <!-- Controles de cámara -->
                <div class="camera-controls">
                    <button class="btn btn-primary" id="start-camera">
                        <i class="bi bi-camera me-2"></i>
                        Activar Cámara
                    </button>
                    <button class="btn btn-secondary" id="stop-camera" style="display:none;">
                        <i class="bi bi-stop-circle me-2"></i>
                        Detener Cámara
                    </button>
                </div>
                
                <!-- Estadísticas mejoradas -->
                <div class="stats-grid" id="stats-container">
                    <div class="stat-card presentes">
                        <span class="stat-number" id="stat-presentes">-</span>
                        <span class="stat-label">Ya Ingresaron</span>
                    </div>
                    <div class="stat-card confirmados">
                        <span class="stat-number" id="stat-confirmados">-</span>
                        <span class="stat-label">Confirmados</span>
                    </div>
                    <div class="stat-card faltan">
                        <span class="stat-number" id="stat-faltan">-</span>
                        <span class="stat-label">Faltan</span>
                    </div>
                    <div class="stat-card porcentaje">
                        <span class="stat-number" id="stat-porcentaje">-%</span>
                        <span class="stat-label">% Asistencia</span>
                    </div>
                </div>
                
                <!-- Resultado del escaneo -->
                <div id="scan-result" style="display:none;"></div>
                
                <!-- Instrucciones mejoradas -->
                <div class="alert" id="instructions">
                    <div style="text-align: center;">
                        <i class="bi bi-info-circle fs-4 mb-3 d-block" style="color: var(--primary-color);"></i>
                        <strong style="font-size: 1.1rem;">Instrucciones de Uso</strong>
                    </div>
                    <div style="margin-top: 1rem; text-align: left;">
                        <div style="margin-bottom: 0.5rem;"><i class="bi bi-1-circle me-2" style="color: var(--primary-color);"></i>Activa la cámara haciendo clic en el botón</div>
                        <div style="margin-bottom: 0.5rem;"><i class="bi bi-2-circle me-2" style="color: var(--primary-color);"></i>Pide al invitado que muestre su código QR</div>
                        <div style="margin-bottom: 0.5rem;"><i class="bi bi-3-circle me-2" style="color: var(--primary-color);"></i>Enfoca el código en el centro de la pantalla</div>
                        <div><i class="bi bi-4-circle me-2" style="color: var(--primary-color);"></i>El sistema registrará automáticamente la entrada</div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Link para volver mejorado -->
        <div class="back-link">
    <a href="../admin/dashboard.php">
        <i class="bi bi-arrow-left"></i>
        Volver al Dashboard
    </a>
</div>
        
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<!-- QR Scanner Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.umd.min.js"></script>

<script>
class WeddingQRScanner {
    constructor() {
        this.scanner = null;
        this.video = document.getElementById('video');
        this.startBtn = document.getElementById('start-camera');
        this.stopBtn = document.getElementById('stop-camera');
        this.resultDiv = document.getElementById('scan-result');
        this.statusIndicator = document.getElementById('camera-status');
        this.statusText = document.getElementById('camera-status-text');
        this.instructions = document.getElementById('instructions');
        
        this.isScanning = false;
        this.lastScanTime = 0;
        
        this.init();
    }
    
    init() {
        this.startBtn.addEventListener('click', () => this.startCamera());
        this.stopBtn.addEventListener('click', () => this.stopCamera());
        
        // Cargar estadísticas iniciales
        this.loadStats();
        
        // Actualizar estadísticas cada 30 segundos
        setInterval(() => this.loadStats(), 30000);
    }
    
    async startCamera() {
        try {
            this.showLoading();
            
            this.scanner = new QrScanner(
                this.video,
                result => this.handleScan(result),
                {
                    returnDetailedScanResult: true,
                    highlightScanRegion: true,
                    highlightCodeOutline: true,
                }
            );
            
            await this.scanner.start();
            
            this.updateCameraStatus(true);
            this.startBtn.style.display = 'none';
            this.stopBtn.style.display = 'inline-block';
            this.instructions.style.display = 'none';
            
            this.hideLoading();
            
        } catch (error) {
            console.error('Error al iniciar cámara:', error);
            this.showError('Error al acceder a la cámara. Verifica los permisos.');
            this.hideLoading();
        }
    }
    
    stopCamera() {
        if (this.scanner) {
            this.scanner.stop();
            this.scanner.destroy();
            this.scanner = null;
        }
        
        this.updateCameraStatus(false);
        this.startBtn.style.display = 'inline-block';
        this.stopBtn.style.display = 'none';
        this.instructions.style.display = 'block';
        this.hideResult();
    }
    
    async handleScan(result) {
        // Evitar escaneos múltiples del mismo código
        const now = Date.now();
        if (now - this.lastScanTime < 3000) {
            return;
        }
        this.lastScanTime = now;
        if (this.isScanning) return;
        this.isScanning = true;
        
        try {
            this.showLoading();
            
            // Obtener ubicación si está disponible
            const ubicacion = await this.getLocation();
            
            // Consultar estado del QR
            const response = await fetch('validar_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    qr_data: result.data,
                    ubicacion: ubicacion
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.invitado) {
                // Mostrar formulario para ingresar cantidad
                this.showCuposForm(data.invitado, result.data, ubicacion);
            } else {
                this.showError(data.message || data.error, data.status);
            }
        } catch (error) {
            console.error('Error al validar QR:', error);
            this.showError('Error de conexión. Intenta nuevamente.');
        } finally {
            this.hideLoading();
            this.isScanning = false;
        }
    }

    showCuposForm(invitado, qrRawData, ubicacion) {
        const div = document.createElement('div');
        div.className = 'result-card result-success';
        div.innerHTML = `
           <h4 style="color: white;"><i class="bi bi-person-check me-2"></i> ${invitado.nombre}</h4>
<p style="color: white;"><strong>Cupos confirmados:</strong> ${invitado.cantidad}</p>
<p style="color: white;"><strong>Ya ingresaron:</strong> ${invitado.total_ingresados || 0}</p>
<p style="color: white;"><strong>Cupos restantes:</strong> <span id="cupos-restantes">${invitado.cupos_restantes}</span></p>
            <div class="mb-3">
                <label for="cantidad-ingresada" class="form-label">¿Cuántos están ingresando?</label>
                <div class="input-group">
                    <button class="btn btn-outline-secondary" type="button" id="btn-menos">-</button>
                    <input type="number" min="1" max="${invitado.cupos_restantes}" value="1" class="form-control" id="cantidad-ingresada">
                    <button class="btn btn-outline-secondary" type="button" id="btn-mas">+</button>
                </div>
            </div>
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-primary" id="registrar-acceso">
                    <i class="bi bi-check-circle me-2"></i>Registrar Acceso
                </button>
                <button class="btn btn-outline-danger" id="cancelar-acceso" type="button">
                    <i class="bi bi-x-circle me-2"></i>Cancelar
                </button>
            </div>
        `;
        
        this.resultDiv.innerHTML = '';
        this.resultDiv.appendChild(div);
        this.resultDiv.style.display = 'block';
        
        // Lógica para los botones + y -
        const inputCantidad = document.getElementById('cantidad-ingresada');
        document.getElementById('btn-menos').onclick = () => {
            let val = parseInt(inputCantidad.value, 10) || 1;
            if (val > 1) inputCantidad.value = val - 1;
        };
        document.getElementById('btn-mas').onclick = () => {
            let val = parseInt(inputCantidad.value, 10) || 1;
            if (val < invitado.cupos_restantes) inputCantidad.value = val + 1;
        };
        
        // Evento para el botón Cancelar
        document.getElementById('cancelar-acceso').onclick = () => {
            this.hideResult();
            this.isScanning = false;
        };
        
        // Evento para el botón Registrar
        document.getElementById('registrar-acceso').onclick = async () => {
            const cantidad = parseInt(inputCantidad.value, 10);
            if (isNaN(cantidad) || cantidad < 1 || cantidad > invitado.cupos_restantes) {
                alert('Cantidad inválida.');
                return;
            }
            
            this.showLoading();
            
            try {
                const resp = await fetch('procesar_verificacion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        qr_data: qrRawData,
                        cantidad_ingresada: cantidad,
                        ubicacion: ubicacion
                    })
                });
                
                const resData = await resp.json();
                
                if (resData.success && resData.invitado) {
                    this.showSuccess(resData.invitado);
                    this.loadStats();
                } else {
                    this.showError(resData.message || resData.error, resData.status);
                }
            } catch (e) {
                this.showError('Error de conexión al registrar acceso.');
            } finally {
                this.hideLoading();
            }
        };
    }
    
    showSuccess(invitado) {
        this.resultDiv.className = 'result-card result-success';
        this.resultDiv.innerHTML = `
            <i class="bi bi-check-circle-fill" style="font-size: 3rem; color: var(--success-color); margin-bottom: 1rem;"></i>
            <h5 style="color: white;">${invitado.nombre}</h5>
            <div style="margin: 1rem 0;">
                <p class="mb-1" style="color: white;"><strong><i class="bi bi-table me-2"></i>Mesa:</strong> ${invitado.mesa}</p>
<p class="mb-1" style="color: white;"><strong><i class="bi bi-people me-2"></i>Personas:</strong> ${invitado.cantidad}</p>
<p class="mb-0" style="color: white;"><strong><i class="bi bi-clock me-2"></i>Entrada:</strong> ${invitado.hora_entrada}</p>
            </div>
            <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                <i class="bi bi-shield-check me-2" style="color: var(--success-color);"></i>
                <strong style="color: var(--success-color);">Acceso Autorizado</strong>
            </div>
        `;
        this.resultDiv.style.display = 'block';
        
        // Ocultar después de 5 segundos
        setTimeout(() => this.hideResult(), 5000);
        
        // Sonido de éxito
        this.playSound('success');
    }
    
    showError(message, status = 'error') {
        let className = 'result-error';
        let icon = 'bi bi-x-circle-fill';
        let iconColor = 'var(--danger-color)';
        let title = 'Error de Acceso';
        
        if (status === 'ya_usado') {
            className = 'result-warning';
            icon = 'bi bi-exclamation-triangle-fill';
            iconColor = 'var(--warning-color)';
            title = 'QR Ya Utilizado';
        }
        
        this.resultDiv.className = `result-card ${className}`;
        this.resultDiv.innerHTML = `
            <i class="${icon}" style="font-size: 3rem; color: ${iconColor}; margin-bottom: 1rem;"></i>
            <h5 style="color: white;">${title}</h5>
<p class="mb-0" style="margin-top: 1rem; color: white;">${message}</p>
            <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                <i class="bi bi-shield-x me-2" style="color: var(--danger-color);"></i>
                <strong style="color: var(--danger-color);">Acceso Denegado</strong>
            </div>
        `;
        this.resultDiv.style.display = 'block';
        
        // Ocultar después de 4 segundos
        setTimeout(() => this.hideResult(), 4000);
        
        // Sonido de error
        this.playSound('error');
    }
    
    hideResult() {
        this.resultDiv.style.display = 'none';
    }
    
    updateCameraStatus(active) {
        if (active) {
            this.statusIndicator.className = 'status-indicator status-active';
            this.statusText.textContent = 'Cámara activa - Listo para escanear';
        } else {
            this.statusIndicator.className = 'status-indicator status-inactive';
            this.statusText.textContent = 'Cámara desactivada';
        }
    }
    
    showLoading() {
        this.startBtn.innerHTML = '<span class="loading"></span> Cargando...';
        this.startBtn.disabled = true;
    }
    
    hideLoading() {
        this.startBtn.innerHTML = '<i class="bi bi-camera me-2"></i>Activar Cámara';
        this.startBtn.disabled = false;
    }
    
    async loadStats() {
        try {
            const response = await fetch('validar_qr.php?stats=true');
            const data = await response.json();
            
            if (data.estadisticas) {
                // Actualizar estadísticas con animación
                this.animateNumber('stat-presentes', data.estadisticas.presentes.personas);
                this.animateNumber('stat-confirmados', data.estadisticas.confirmados.personas);
                this.animateNumber('stat-porcentaje', data.estadisticas.porcentaje_asistencia, '%');
                
                // Calcular cuántos faltan (confirmados - presentes)
                const faltan = data.estadisticas.confirmados.personas - data.estadisticas.presentes.personas;
                this.animateNumber('stat-faltan', Math.max(0, faltan));
            }
        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
        }
    }
    
    animateNumber(elementId, targetValue, suffix = '') {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const currentValue = parseInt(element.textContent) || 0;
        const difference = targetValue - currentValue;
        const duration = 1000; // 1 segundo
        const steps = 20;
        const stepValue = difference / steps;
        const stepDuration = duration / steps;
        
        let currentStep = 0;
        const timer = setInterval(() => {
            currentStep++;
            const newValue = Math.round(currentValue + (stepValue * currentStep));
            element.textContent = newValue + suffix;
            
            if (currentStep >= steps) {
                element.textContent = targetValue + suffix;
                clearInterval(timer);
            }
        }, stepDuration);
    }
    
    async getLocation() {
        return new Promise((resolve) => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        resolve({
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        });
                    },
                    () => resolve(null),
                    { timeout: 5000 }
                );
            } else {
                resolve(null);
            }
        });
    }
    
    playSound(type) {
        try {
            // Crear sonidos con Web Audio API
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            if (type === 'success') {
                // Sonido de éxito - dos tonos ascendentes
                oscillator.frequency.setValueAtTime(600, audioContext.currentTime);
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.1);
                oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.2);
            } else {
                // Sonido de error - tono grave
                oscillator.frequency.setValueAtTime(200, audioContext.currentTime);
            }
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (error) {
            // Silenciar errores de audio para no interrumpir la funcionalidad
            console.log('Audio no disponible');
        }
    }
}

// Inicializar cuando la página esté lista
document.addEventListener('DOMContentLoaded', () => {
    new WeddingQRScanner();
});
</script>

</body>
</html>