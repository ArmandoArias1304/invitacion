<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Acceso - Boda</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #d4af37;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --dark-color: #2c2c2c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 1rem;
        }
        
        .scanner-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary-color);
            color: white;
            text-align: center;
            padding: 1.5rem;
            border: none;
        }
        
        .card-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        #scanner-container {
            position: relative;
            width: 100%;
            height: 300px;
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            margin: 1rem 0;
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
            border-radius: 15px;
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
        }
        
        @keyframes scan {
            0% { transform: translateY(-150px); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(150px); opacity: 0; }
        }
        
        .result-card {
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .result-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .result-error {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }
        
        .result-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }
        
        .btn {
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }
        
        .stats-mini {
            display: flex;
            justify-content: space-around;
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 1rem;
            margin: 1rem 0;
            color: white;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
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
        
        .camera-controls {
            text-align: center;
            margin: 1rem 0;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }
        .status-warning { background: #ffc107; }
    </style>
</head>
<body>

<div class="container">
    <div class="scanner-container">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-qrcode me-2"></i>
                    Control de Acceso
                </h3>
                <small>Escanea los códigos QR de los invitados</small>
            </div>
            
            <div class="card-body">
                
                <!-- Estado de la cámara -->
                <div class="text-center mb-3">
                    <span class="status-indicator status-inactive" id="camera-status"></span>
                    <span id="camera-status-text">Cámara desactivada</span>
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
                        <i class="fas fa-camera me-2"></i>
                        Activar Cámara
                    </button>
                    <button class="btn btn-secondary" id="stop-camera" style="display:none;">
                        <i class="fas fa-stop me-2"></i>
                        Detener
                    </button>
                </div>
                
                <!-- Estadísticas mini -->
                <div class="stats-mini" id="stats-container">
                    <div class="stat-item">
                        <span class="stat-number" id="stat-presentes">-</span>
                        <span class="stat-label">Presentes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="stat-confirmados">-</span>
                        <span class="stat-label">Confirmados</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="stat-porcentaje">-%</span>
                        <span class="stat-label">Asistencia</span>
                    </div>
                </div>
                
                <!-- Resultado del escaneo -->
                <div id="scan-result" style="display:none;"></div>
                
                <!-- Instrucciones -->
                <div class="alert alert-info" id="instructions">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Instrucciones:</strong><br>
                    1. Activa la cámara<br>
                    2. Pide al invitado que muestre su QR<br>
                    3. Enfoca el código en el centro de la pantalla<br>
                    4. El sistema registrará automáticamente su entrada
                </div>
                
            </div>
        </div>
        
        <!-- Link para volver -->
        <div class="text-center mt-3">
            <a href="../../index.html" class="text-white text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i>
                Volver a la invitación
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
            
            // Enviar datos al servidor
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
            
            if (data.success) {
                this.showSuccess(data.invitado);
                this.loadStats(); // Actualizar estadísticas
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
    
    showSuccess(invitado) {
        this.resultDiv.className = 'result-card result-success';
        this.resultDiv.innerHTML = `
            <i class="fas fa-check-circle fa-2x mb-2"></i>
            <h5>${invitado.nombre}</h5>
            <p class="mb-1"><strong>Mesa:</strong> ${invitado.mesa}</p>
            <p class="mb-1"><strong>Personas:</strong> ${invitado.cantidad}</p>
            <p class="mb-0"><small>Entrada registrada: ${invitado.hora_entrada}</small></p>
        `;
        this.resultDiv.style.display = 'block';
        
        // Ocultar después de 5 segundos
        setTimeout(() => this.hideResult(), 5000);
        
        // Sonido de éxito (opcional)
        this.playSound('success');
    }
    
    showError(message, status = 'error') {
        let className = 'result-error';
        let icon = 'fas fa-times-circle';
        
        if (status === 'ya_usado') {
            className = 'result-warning';
            icon = 'fas fa-exclamation-triangle';
        }
        
        this.resultDiv.className = `result-card ${className}`;
        this.resultDiv.innerHTML = `
            <i class="${icon} fa-2x mb-2"></i>
            <h5>${status === 'ya_usado' ? 'QR Ya Utilizado' : 'Error'}</h5>
            <p class="mb-0">${message}</p>
        `;
        this.resultDiv.style.display = 'block';
        
        // Ocultar después de 4 segundos
        setTimeout(() => this.hideResult(), 4000);
        
        // Sonido de error (opcional)
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
        this.startBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Activar Cámara';
        this.startBtn.disabled = false;
    }
    
    async loadStats() {
        try {
            const response = await fetch('validar_qr.php?stats=true');
            const data = await response.json();
            
            if (data.estadisticas) {
                document.getElementById('stat-presentes').textContent = data.estadisticas.presentes.personas;
                document.getElementById('stat-confirmados').textContent = data.estadisticas.confirmados.personas;
                document.getElementById('stat-porcentaje').textContent = data.estadisticas.porcentaje_asistencia + '%';
            }
        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
        }
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
        // Crear sonidos con Web Audio API (opcional)
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        if (type === 'success') {
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
        } else {
            oscillator.frequency.setValueAtTime(300, audioContext.currentTime);
        }
        
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.2);
    }
}

// Inicializar cuando la página esté lista
document.addEventListener('DOMContentLoaded', () => {
    new WeddingQRScanner();
});
</script>

</body>
</html>