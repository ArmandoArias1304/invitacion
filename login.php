<?php
session_start();
require_once 'php/config/database.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: php/admin/dashboard.php');
    exit;
}

$error = '';

if ($_POST) {
    $usuario = sanitizeInput($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $db = getDB();
        $stmt = $db->getConnection()->prepare("
            SELECT id_usuario, usuario, password, nombre_completo, activo 
            FROM usuarios_admin 
            WHERE usuario = ? AND activo = 1
        ");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login exitoso
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id_usuario'];
            $_SESSION['admin_usuario'] = $user['usuario'];
            $_SESSION['admin_nombre'] = $user['nombre_completo'];
            
            // Actualizar último acceso
            $stmt = $db->getConnection()->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id_usuario = ?");
            $stmt->execute([$user['id_usuario']]);
            
            header('Location: php/admin/dashboard.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FastInvite - Iniciar Sesión</title>
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Lobster&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            --glass-bg: rgba(30, 30, 50, 0.8);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-dark: #e2e8f0;
            --text-light: rgba(255, 255, 255, 0.9);
            --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-strong: 0 15px 35px rgba(0, 0, 0, 0.3);
            --input-bg: rgba(30, 30, 50, 0.9);
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --accent-color: #6366f1;
            --accent-secondary: #8b5cf6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Partículas flotantes de fondo - Sin corazones */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: linear-gradient(45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .particle:nth-child(1) { width: 80px; height: 80px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 60px; height: 60px; left: 20%; animation-delay: 1s; }
        .particle:nth-child(3) { width: 100px; height: 100px; left: 35%; animation-delay: 2s; }
        .particle:nth-child(4) { width: 40px; height: 40px; left: 60%; animation-delay: 3s; }
        .particle:nth-child(5) { width: 70px; height: 70px; left: 75%; animation-delay: 4s; }
        .particle:nth-child(6) { width: 50px; height: 50px; left: 85%; animation-delay: 5s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.7; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 1; }
        }

        /* Container principal */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .login-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            animation: slideUp 0.8s ease-out;
            position: relative;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Layout desktop */
        .login-content {
            display: flex;
            min-height: 600px;
        }

        /* Lado izquierdo - Branding */
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.95));
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 60px 40px;
            position: relative;
            overflow: hidden;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-brand::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(99, 102, 241, 0.03) 50%, transparent 70%);
            animation: drift 25s linear infinite;
            opacity: 0.6;
        }

        @keyframes drift {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-20px, -20px) rotate(360deg); }
        }

        .brand-logo {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--accent-color), var(--accent-secondary));
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
        }

        .brand-logo:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.4);
        }

        .brand-logo i {
            font-size: 3.5rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .brand-title {
            font-family: 'Lobster', cursive;
            font-size: 2.8rem;
            font-weight: 400;
            color: white;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
            letter-spacing: 1px;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 40px;
            font-weight: 300;
            position: relative;
            z-index: 2;
        }

        .brand-features {
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
            z-index: 2;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent-color), var(--accent-secondary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        /* Lado derecho - Formulario */
        .login-form-section {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(20, 20, 35, 0.95);
            backdrop-filter: blur(10px);
            border-left: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-title {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .form-subtitle {
            color: #64748b;
            font-size: 1rem;
            font-weight: 400;
        }

        /* Formulario */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-floating {
            position: relative;
        }

        .form-control {
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px 50px 15px 15px;
            font-size: 1rem;
            background: var(--input-bg);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            height: 58px;
            color: var(--text-dark);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-control:focus {
            border-color: var(--accent-color);
            background: rgba(30, 30, 50, 1);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.15);
            outline: none;
            color: white;
        }

        .input-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            font-size: 1.2rem;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .form-control:focus + .input-icon {
            color: var(--accent-color);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        /* Botón de login */
        .btn-login {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-secondary) 100%);
            border: none;
            border-radius: 12px;
            padding: 16px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Loading state simplificado */
        .btn-login:disabled {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Mensajes de estado */
        .alert-custom {
            border: none;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            backdrop-filter: blur(10px);
            animation: fadeInDown 0.5s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            font-weight: 400;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }

            .login-content {
                flex-direction: column;
                min-height: 100vh;
            }

            .login-brand {
                min-height: 300px;
                padding: 40px 30px;
            }

            .brand-logo {
                width: 80px;
                height: 80px;
                margin-bottom: 20px;
            }

            .brand-logo i {
                font-size: 2.5rem;
            }

            .brand-title {
                font-size: 2.2rem;
                margin-bottom: 10px;
                letter-spacing: 0.5px;
            }

            .brand-subtitle {
                font-size: 1rem;
                margin-bottom: 30px;
            }

            .brand-features {
                display: none;
            }

            .login-form-section {
                padding: 40px 30px;
                flex: 1;
            }

            .form-title {
                font-size: 1.75rem;
            }

            .particles {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .login-wrapper {
                padding: 0;
            }

            .login-form-section {
                padding: 30px 20px;
            }

            .brand-title {
                font-size: 2rem;
                letter-spacing: 0.5px;
            }

            .form-title {
                font-size: 1.5rem;
            }
        }

        /* Animaciones adicionales */
        .form-control, .btn-login, .alert-custom {
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: backwards;
        }

        .form-control:nth-child(1) { animation-delay: 0.1s; }
        .form-control:nth-child(2) { animation-delay: 0.2s; }
        .btn-login { animation-delay: 0.3s; }

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

        /* Focus visible para accesibilidad */
        .form-control:focus-visible,
        .btn-login:focus-visible {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- Partículas de fondo -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-content">
                <!-- Lado Izquierdo - Branding -->
                <div class="login-brand">
                    <div class="brand-logo">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h1 class="brand-title">Fastnvite</h1>
                    <p class="brand-subtitle">Creando momentos inolvidables</p>
                    
                    <div class="brand-features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-gem"></i>
                            </div>
                            <span>Invitaciones elegantes para tu boda</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <span>Toda tu logística en un mismo lugar</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <span>Gestión completa de invitados y confirmaciones</span>
                        </div>
                    </div>
                </div>

                <!-- Lado Derecho - Formulario -->
                <div class="login-form-section">
                    <div class="form-header">
                        <h2 class="form-title">Iniciar Sesión</h2>
                        <p class="form-subtitle">Accede a tu panel de control</p>
                    </div>

                    <!-- Mostrar error PHP si existe -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-custom alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="usuario" class="form-label">Usuario</label>
                            <div class="position-relative">
                                <input type="text" 
                                       class="form-control" 
                                       id="usuario" 
                                       name="usuario" 
                                       required 
                                       autocomplete="username"
                                       value="<?php echo htmlspecialchars($usuario ?? ''); ?>"
                                       placeholder="Ingresa tu usuario">
                                <i class="bi bi-person input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="position-relative">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required 
                                       autocomplete="current-password"
                                       placeholder="Ingresa tu contraseña">
                                <i class="bi bi-lock input-icon"></i>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Ingresar al Sistema
                        </button>
                    </form>

                    <div class="login-footer">
                        <p class="footer-text">
                            <i class="bi bi-shield-check me-1"></i>
                            Sistema de Invitaciones v2.0
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Solo efectos visuales - NO interceptar el formulario
        
        // Efectos de focus en los inputs
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
        
        // Animación de entrada de partículas
        function animateParticles() {
            const particles = document.querySelectorAll('.particle');
            particles.forEach((particle, index) => {
                particle.style.animationDelay = (index * 0.5) + 's';
            });
        }
        
        // Inicializar animaciones
        document.addEventListener('DOMContentLoaded', function() {
            animateParticles();
            
            // Focus automático en el primer campo
            setTimeout(() => {
                document.getElementById('usuario').focus();
            }, 800);
        });
        
        // Efecto de loading al enviar el formulario (opcional)
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.querySelector('.btn-login');
            btn.style.opacity = '0.7';
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';
        });
    </script>
</body>
</html>