<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar QR - Control de Acceso</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Importar fuente moderna */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #6366f1;
            --primary-light: #818cf8;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--gray-50) 0%, #e0e7ff 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--gray-700);
            min-height: 100vh;
            padding: 1rem;
        }

        .main-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
            padding: 1.5rem;
            text-align: center;
            border: none;
        }

        .card-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        .card-body {
            padding: 2rem;
        }

        /* Scanner Container */
        .scanner-container {
            position: relative;
            width: 100%;
            height: 300px;
            background: var(--gray-800);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 1.5rem;
            display: none;
        }

        .scanner-container.active {
            display: block;
        }

        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 3px solid var(--primary-color);
            border-radius: var(--border-radius);
            pointer-events: none;
        }

        .scanner-line {
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 2px;
            background: var(--primary-color);
            animation: scan 2s linear infinite;
            box-shadow: 0 0 10px var(--primary-color);
        }

        @keyframes scan {
            0% { transform: translateY(-150px); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(150px); opacity: 0; }
        }

        /* Botones */
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

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            box-shadow: 0 4px 14px 0 rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px 0 rgba(99, 102, 241, 0.4);
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1rem;
        }

        /* Estados de resultado */
        .result-card {
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            margin: 1.5rem 0;
            animation: slideInUp 0.5s ease-out;
            display: none;
        }

        .result-card.show {
            display: block;
        }

        .result-success {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: var(--white);
        }

        .result-warning {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: var(--white);
        }

        .result-error {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: var(--white);
        }

        .result-info {
            background: linear-gradient(135deg, var(--info-color), #2563eb);
            color: var(--white);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Estadísticas */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-lg);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            display: block;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Estados de cámara */
        .camera-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-active { background: var(--success-color); }
        .status-inactive { background: var(--danger-color); }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Loading */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Historial */
        .historial-container {
            max-height: 200px;
            overflow-y: auto;
        }

        .historial-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .historial-item:last-child {
            margin-bottom: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 0.5rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
        }

        /* Back link */
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

        /* Countdown timer */
        .countdown-timer {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="main-container">
    
    <!-- Link para volver -->
    <a href="../../index.html" class="back-link">
        <i class="fas fa-arrow-left me-2"></i>
        Volver a la invitación
    </a>
    
    <!-- Card principal -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-qrcode me-2"></i>
                Control de Acceso
            </h2>
            <p class="mb-0">Escanea los códigos QR de los invitados</p>
        </div>
        
        <div class="card-body">
            
            <!-- Estado de la cámara -->
            <div class="camera-status">
                <span class="status-indicator status-inactive" id="camera-status"></span>
                <span id="camera-status-text">Cámara desactivada</span>
            </div>
            
            <!-- Contenedor del escáner -->
            <div class="scanner-container" id="scanner-container">
                <video id="video" playsinline></video>
                <div class="scanner-overlay">
                    <div class="scanner-line"></div>
                    <div class="countdown-timer" id="countdown-timer" style="display: none;"></div>
                </div>
            </div>
            
            <!-- Botón de control de cámara -->
            <div class="text-center mb-3">
                <button class="btn btn-primary btn-lg" id="toggle-camera">
                    <i class="fas fa-camera me-2"></i>
                    Activar Cámara
                </button>
            </div>
            
            <!-- Resultado del escaneo -->
            <div id="scan-result"></div>
            
            <!-- Instrucciones iniciales -->
            <div class="alert alert-info" id="instructions">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Instrucciones:</strong><br>
                1. Activa la cámara<br>
                2. Enfoca el código QR del invitado<br>
                3. El sistema registrará automáticamente su entrada
            </div>
            
        </div>
    </div>
    
    <!-- Estadísticas -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                Estadísticas del Evento
            </h5>
        </div>
        <div class="card-body">
            <div class="stats-container">
                <div class="stat-card">
                    <span class="stat-number" id="stat-presentes">-</span>
                    <span class="stat-label">Presentes</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number" id="stat-confirmados">-</span>
                    <span class="stat-label">Confirmados</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number" id="stat-porcentaje">-%</span>
                    <span class="stat-label">Asistencia</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number" id="stat-ultimos">-</span>
                    <span class="stat-label">Últimos 10min</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Historial de entradas -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>
                Últimas Entradas
            </h5>
        </div>
        <div class="card-body">
            <div class="historial-container" id="historial-container">
                <p class="text-muted text-center">No hay entradas registradas aún</p>
            </div>
        </div>
    </div>
    
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<!-- QR Scanner Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.umd.min.js"></script>

<script>
class VerificadorQR {
    constructor() {
        this.scanner = null;
        this.video = document.getElementById('video');
        this.toggleBtn = document.getElementById('toggle-camera');
        this.scannerContainer = document.getElementById('scanner-container');
        this.resultDiv = document.getElementById('scan-result');
        this.statusIndicator = document.getElementById('camera-status');
        this.statusText = document.getElementById('camera-status-text');
        this.instructions = document.getElementById('instructions');
        this.countdownTimer = document.getElementById('countdown-timer');
        
        this.isScanning = false;
        this.lastScanTime = 0;
        this.cameraActive = false;
        this.cooldownTimer = null;
        this.autoHideTimer = null;
        
        this.init();
    }
    
    init() {
        this.toggleBtn.addEventListener('click', () => this.toggleCamera());
        
        // Cargar estadísticas iniciales
        this.loadStats();
        this.loadHistorial();
        
        // Actualizar estadísticas cada 30 segundos
        setInterval(() => {
            this.loadStats();
            this.loadHistorial();
        }, 30000);
    }
    
    async toggleCamera() {
        if (this.cameraActive) {
            this.stopCamera();
        } else {
            await this.startCamera();
        }
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
            
            this.cameraActive = true;
            this.updateCameraStatus(true);
            this.scannerContainer.classList.add('active');
            this.toggleBtn.innerHTML = '<i class="fas fa-stop me-2"></i>Detener Cámara';
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
        
        this.clearTimers();
        
        this.cameraActive = false;
        this.updateCameraStatus(false);
        this.scannerContainer.classList.remove('active');
        this.toggleBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Activar Cámara';
        this.instructions.style.display = 'block';
        this.hideResult();
        this.countdownTimer.style.display = 'none';
    }
    
    async handleScan(result) {
        // Evitar escaneos múltiples durante el cooldown
        const now = Date.now();
        if (now - this.lastScanTime < 2000 || this.isScanning) {
            return;
        }
        
        this.lastScanTime = now;
        this.isScanning = true;
        
        try {
            this.showLoading(true);
            
            // Simular llamada al backend (reemplaza con tu endpoint real)
            await this.simulateBackendCall(result.data);
            
        } catch (error) {
            console.error('Error al procesar QR:', error);
            this.showError('Error de conexión. Intenta nuevamente.');
        } finally {
            this.hideLoading();
            this.startCooldown();
        }
    }
    
    // Función para llamar a tu backend real
    async simulateBackendCall(qrData) {
        try {
            const response = await fetch('procesar_verificacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    qr_data: qrData,
                    timestamp: new Date().toISOString()
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.status === 'registrado') {
                    this.showSuccess(data.invitado);
                } else if (data.status === 'ya_usado') {
                    this.showWarning(data.invitado, data.message);
                }
                
                // Actualizar estadísticas
                this.loadStats();
                this.loadHistorial();
                
            } else {
                this.showError(data.message || data.error, data.status);
            }
            
        } catch (error) {
            console.error('Error de conexión:', error);
            this.showError('Error de conexión con el servidor. Intenta nuevamente.');
        }
    }
    
    startCooldown() {
        this.isScanning = false;
        
        // Mostrar countdown
        this.countdownTimer.style.display = 'block';
        let timeLeft = 3;
        
        this.cooldownTimer = setInterval(() => {
            this.countdownTimer.textContent = `Listo en ${timeLeft}s`;
            timeLeft--;
            
            if (timeLeft < 0) {
                this.endCooldown();
            }
        }, 1000);
        
        this.countdownTimer.textContent = `Listo en ${timeLeft}s`;
    }
    
    endCooldown() {
        if (this.cooldownTimer) {
            clearInterval(this.cooldownTimer);
            this.cooldownTimer = null;
        }
        
        this.countdownTimer.style.display = 'none';
        this.isScanning = false;
        
        // Ocultar resultado si aún está visible
        this.hideResult();
        
        console.log('✅ Listo para escanear nuevamente');
    }
    
    clearTimers() {
        if (this.cooldownTimer) {
            clearInterval(this.cooldownTimer);
            this.cooldownTimer = null;
        }
        
        if (this.autoHideTimer) {
            clearTimeout(this.autoHideTimer);
            this.autoHideTimer = null;
        }
    }
    
    showSuccess(invitado) {
        this.resultDiv.className = 'result-card result-success show';
        this.resultDiv.innerHTML = `
            <i class="fas fa-check-circle fa-3x mb-3"></i>
            <h4>✅ ENTRADA REGISTRADA</h4>
            <hr style="border-color: rgba(255,255,255,0.3);">
            <h5>${invitado.nombre}</h5>
            <p class="mb-1"><i class="fas fa-chair me-2"></i><strong>Mesa:</strong> ${invitado.mesa}</p>
            <p class="mb-1"><i class="fas fa-users me-2"></i><strong>Personas:</strong> ${invitado.cantidad}</p>
            <p class="mb-3"><i class="fas fa-clock me-2"></i><strong>Hora:</strong> ${invitado.hora_entrada}</p>
            <button class="btn btn-light btn-sm" onclick="verificador.hideResult()">
                <i class="fas fa-qrcode me-1"></i>Continuar
            </button>
        `;
        
        this.playSound('success');
        this.autoHideResult(4000);
    }
    
    showWarning(invitado, message) {
        this.resultDiv.className = 'result-card result-warning show';
        this.resultDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <h4>⚠️ QR YA UTILIZADO</h4>
            <hr style="border-color: rgba(255,255,255,0.3);">
            <h5>${invitado.nombre}</h5>
            <p class="mb-3">${message}</p>
            <button class="btn btn-light btn-sm" onclick="verificador.hideResult()">
                <i class="fas fa-qrcode me-1"></i>Continuar
            </button>
        `;
        
        this.playSound('warning');
        this.autoHideResult(4000);
    }
    
    showError(message, status = 'error') {
        this.resultDiv.className = 'result-card result-error show';
        this.resultDiv.innerHTML = `
            <i class="fas fa-times-circle fa-3x mb-3"></i>
            <h4>❌ QR INVÁLIDO</h4>
            <hr style="border-color: rgba(255,255,255,0.3);">
            <p class="mb-3">${message}</p>
            <button class="btn btn-light btn-sm" onclick="verificador.hideResult()">
                <i class="fas fa-qrcode me-1"></i>Continuar
            </button>
        `;
        
        this.playSound('error');
        this.autoHideResult(4000);
    }
    
    hideResult() {
        this.resultDiv.className = 'result-card';
        this.resultDiv.style.display = 'none';
        
        // Limpiar timer de auto-hide
        if (this.autoHideTimer) {
            clearTimeout(this.autoHideTimer);
            this.autoHideTimer = null;
        }
    }
    
    autoHideResult(delay) {
        if (this.autoHideTimer) {
            clearTimeout(this.autoHideTimer);
        }
        
        this.autoHideTimer = setTimeout(() => {
            if (this.resultDiv.classList.contains('show')) {
                this.hideResult();
            }
        }, delay);
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
    
    showLoading(scanning = false) {
        if (scanning) {
            this.toggleBtn.innerHTML = '<span class="loading"></span> Procesando...';
        } else {
            this.toggleBtn.innerHTML = '<span class="loading"></span> Activando...';
        }
        this.toggleBtn.disabled = true;
    }
    
    hideLoading() {
        this.toggleBtn.disabled = false;
        if (this.cameraActive) {
            this.toggleBtn.innerHTML = '<i class="fas fa-stop me-2"></i>Detener Cámara';
        } else {
            this.toggleBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Activar Cámara';
        }
    }
    
    async loadStats() {
        try {
            const response = await fetch('../scanner/validar_qr.php?stats=true');
            const data = await response.json();
            
            if (data.estadisticas) {
                document.getElementById('stat-presentes').textContent = data.estadisticas.presentes.personas;
                document.getElementById('stat-confirmados').textContent = data.estadisticas.confirmados.personas;
                document.getElementById('stat-porcentaje').textContent = data.estadisticas.porcentaje_asistencia + '%';
                
                // Calcular entradas en últimos 10 minutos
                const ultimosMinutos = data.ultimos_ingresos ? 
                    data.ultimos_ingresos.filter(ingreso => {
                        const hace = ingreso.hace;
                        return hace.includes('minutos') || hace.includes('segundos');
                    }).length : 0;
                
                document.getElementById('stat-ultimos').textContent = ultimosMinutos;
            }
        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
            // Fallback con datos simulados en caso de error
            const stats = {
                presentes: '-',
                confirmados: '-',
                porcentaje: '-',
                ultimos: '-'
            };
            
            document.getElementById('stat-presentes').textContent = stats.presentes;
            document.getElementById('stat-confirmados').textContent = stats.confirmados;
            document.getElementById('stat-porcentaje').textContent = stats.porcentaje;
            document.getElementById('stat-ultimos').textContent = stats.ultimos;
        }
    }
    
    async loadHistorial() {
        try {
            const response = await fetch('../scanner/validar_qr.php?stats=true');
            const data = await response.json();
            
            const container = document.getElementById('historial-container');
            
            if (data.ultimos_ingresos && data.ultimos_ingresos.length > 0) {
                container.innerHTML = data.ultimos_ingresos.slice(0, 5).map(ingreso => `
                    <div class="historial-item">
                        <div>
                            <strong>${ingreso.nombre}</strong><br>
                            <small class="text-muted">Mesa ${ingreso.mesa} • ${ingreso.cantidad} personas</small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">${ingreso.hora}</small><br>
                            <small class="text-success">${ingreso.hace}</small>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted text-center">No hay entradas registradas aún</p>';
            }
        } catch (error) {
            console.error('Error al cargar historial:', error);
            // Fallback en caso de error
            const container = document.getElementById('historial-container');
            container.innerHTML = '<p class="text-muted text-center">Error al cargar historial</p>';
        }
    }
    
    playSound(type) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            if (type === 'success') {
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
            } else if (type === 'warning') {
                oscillator.frequency.setValueAtTime(600, audioContext.currentTime);
                oscillator.frequency.setValueAtTime(400, audioContext.currentTime + 0.1);
            } else {
                oscillator.frequency.setValueAtTime(300, audioContext.currentTime);
            }
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (error) {
            // Silenciar errores de audio
        }
    }
}

// Inicializar cuando la página esté lista
let verificador;
document.addEventListener('DOMContentLoaded', () => {
    verificador = new VerificadorQR();
});
</script>

</body>
</html>