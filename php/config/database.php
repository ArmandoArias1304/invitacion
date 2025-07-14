<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'invitacion');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Clase para manejo de base de datos
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Función para generar token único
    public static function generateToken($length = 12) {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Sin caracteres confusos
        $token = '';
        
        for ($i = 0; $i < $length; $i++) {
            if ($i > 0 && $i % 4 == 0) {
                $token .= '-';
            }
            $token .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $token;
    }
    
    // Función para verificar si un token existe
    public function tokenExists($token) {
        $stmt = $this->connection->prepare("SELECT id_invitado FROM invitados WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch() !== false;
    }
    
    // Función para obtener invitado por token
    public function getInvitadoPorToken($token) {
        $stmt = $this->connection->prepare("
            SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion 
            FROM invitados i 
            LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado 
            WHERE i.token = ?
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    
    // Función para confirmar asistencia
    public function confirmarAsistencia($id_invitado, $cantidad_confirmada, $observaciones = '') {
        try {
            $this->connection->beginTransaction();
            
            // Verificar si ya existe una confirmación
            $stmt = $this->connection->prepare("SELECT id_confirmacion FROM confirmaciones WHERE id_invitado = ?");
            $stmt->execute([$id_invitado]);
            $existeConfirmacion = $stmt->fetch();
            
            if ($existeConfirmacion) {
                // Actualizar confirmación existente
                $stmt = $this->connection->prepare("
                    UPDATE confirmaciones 
                    SET cantidad_confirmada = ?, fecha_confirmacion = NOW(), observaciones = ?, ip_confirmacion = ?
                    WHERE id_invitado = ?
                ");
                $stmt->execute([$cantidad_confirmada, $observaciones, $_SERVER['REMOTE_ADDR'], $id_invitado]);
            } else {
                // Crear nueva confirmación
                $stmt = $this->connection->prepare("
                    INSERT INTO confirmaciones (id_invitado, cantidad_confirmada, fecha_confirmacion, ip_confirmacion, observaciones)
                    VALUES (?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([$id_invitado, $cantidad_confirmada, $_SERVER['REMOTE_ADDR'], $observaciones]);
            }
            
            // Generar o actualizar token QR
            $this->generarTokenQR($id_invitado);
            
            $this->connection->commit();
            return true;
            
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
    
    // Función para generar token QR
    private function generarTokenQR($id_invitado) {
        // Verificar si ya existe un token QR activo
        $stmt = $this->connection->prepare("SELECT token_unico FROM tokens_qr WHERE id_invitado = ? AND activo = 1");
        $stmt->execute([$id_invitado]);
        $tokenExistente = $stmt->fetch();
        
        if (!$tokenExistente) {
            // Generar nuevo token QR
            $tokenQR = self::generateToken(16); // Token más largo para QR
            $fechaExpiracion = date('Y-m-d H:i:s', strtotime('+30 days')); // Expira en 30 días
            
            $stmt = $this->connection->prepare("
                INSERT INTO tokens_qr (token_unico, id_invitado, fecha_generacion, fecha_expiracion, usado, activo)
                VALUES (?, ?, NOW(), ?, 0, 1)
            ");
            $stmt->execute([$tokenQR, $id_invitado, $fechaExpiracion]);
        }
    }
    
    // Función para obtener token QR del invitado
    public function getTokenQR($id_invitado) {
        $stmt = $this->connection->prepare("
            SELECT token_unico, fecha_expiracion 
            FROM tokens_qr 
            WHERE id_invitado = ? AND activo = 1
        ");
        $stmt->execute([$id_invitado]);
        return $stmt->fetch();
    }
    
    // Función para validar token QR
    public function validarTokenQR($token_qr) {
        $stmt = $this->connection->prepare("
            SELECT tq.*, i.nombre_completo, i.mesa, c.cantidad_confirmada
            FROM tokens_qr tq
            JOIN invitados i ON tq.id_invitado = i.id_invitado
            LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
            WHERE tq.token_unico = ? AND tq.activo = 1 AND tq.usado = 0 AND tq.fecha_expiracion > NOW()
        ");
        $stmt->execute([$token_qr]);
        return $stmt->fetch();
    }
    
    // Función para marcar QR como usado
    public function marcarQRUsado($token_qr) {
        $stmt = $this->connection->prepare("UPDATE tokens_qr SET usado = 1 WHERE token_unico = ?");
        return $stmt->execute([$token_qr]);
    }
    
    // Función para registrar acceso al evento
    public function registrarAccesoEvento($id_invitado, $token_usado, $ubicacion_gps = '') {
        $stmt = $this->connection->prepare("
            INSERT INTO accesos_evento (id_invitado, token_usado, timestamp_escaneo, ubicacion_gps, status_entrada)
            VALUES (?, ?, NOW(), ?, 'ingreso')
        ");
        return $stmt->execute([$id_invitado, $token_usado, $ubicacion_gps]);
    }
    
    // ===== NUEVAS FUNCIONES PARA EL SISTEMA DE LOGIN =====
    
    // Función para autenticar usuario admin
    public function autenticarAdmin($usuario, $password) {
        $stmt = $this->connection->prepare("
            SELECT id_usuario, usuario, password, nombre_completo, activo 
            FROM usuarios_admin 
            WHERE usuario = ? AND activo = 1
        ");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Actualizar último acceso
            $stmt = $this->connection->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id_usuario = ?");
            $stmt->execute([$user['id_usuario']]);
            
            return $user;
        }
        
        return false;
    }
    
    // Función para crear nuevo usuario admin
    public function crearUsuarioAdmin($usuario, $password, $nombre_completo) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->connection->prepare("
            INSERT INTO usuarios_admin (usuario, password, nombre_completo, activo)
            VALUES (?, ?, ?, 1)
        ");
        
        return $stmt->execute([$usuario, $password_hash, $nombre_completo]);
    }
    
    // Función para obtener estadísticas generales
    public function getEstadisticasGenerales() {
        $stats = [];
        
        // Total de invitados
        $stmt = $this->connection->prepare("SELECT COUNT(*) as total FROM invitados");
        $stmt->execute();
        $stats['total_invitados'] = $stmt->fetchColumn();
        
        // Total confirmados
        $stmt = $this->connection->prepare("SELECT COUNT(*) as total FROM confirmaciones");
        $stmt->execute();
        $stats['total_confirmados'] = $stmt->fetchColumn();
        
        // Total de cupos disponibles
        $stmt = $this->connection->prepare("SELECT SUM(cupos_disponibles) as total FROM invitados");
        $stmt->execute();
        $stats['total_cupos'] = $stmt->fetchColumn() ?: 0;
        
        // Total de cupos confirmados
        $stmt = $this->connection->prepare("SELECT SUM(cantidad_confirmada) as total FROM confirmaciones");
        $stmt->execute();
        $stats['total_cupos_confirmados'] = $stmt->fetchColumn() ?: 0;
        
        // Porcentaje de confirmación
        $stats['porcentaje_confirmacion'] = $stats['total_invitados'] > 0 ? 
            round(($stats['total_confirmados'] / $stats['total_invitados']) * 100, 1) : 0;
        
        return $stats;
    }
    
    // Función para obtener invitados con filtros
    public function getInvitadosConFiltros($filtros = []) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filtros['mesa'])) {
            $where_conditions[] = "i.mesa = ?";
            $params[] = $filtros['mesa'];
        }
        
        if (!empty($filtros['tipo'])) {
            $where_conditions[] = "i.tipo_invitado = ?";
            $params[] = $filtros['tipo'];
        }
        
        if (!empty($filtros['buscar'])) {
            $where_conditions[] = "(i.nombre_completo LIKE ? OR i.telefono LIKE ?)";
            $params[] = "%{$filtros['buscar']}%";
            $params[] = "%{$filtros['buscar']}%";
        }
        
        if (isset($filtros['confirmado'])) {
            if ($filtros['confirmado'] == '1') {
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
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

// Función helper para obtener la instancia de la base de datos
function getDB() {
    return Database::getInstance();
}

// Función helper para la conexión PDO directa
function getConnection() {
    return Database::getInstance()->getConnection();
}

// Función para mostrar respuesta JSON
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Función para sanitizar input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Función para validar token format
function isValidTokenFormat($token) {
    // Formato: XXXX-XXXX-XXXX o XXXX-XXXX-XXXX-XXXX
    return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}(-[A-Z0-9]{4})?$/', $token);
}

// ===== FUNCIONES AUXILIARES NUEVAS =====

// Función para validar formato de teléfono
function validarTelefono($telefono) {
    // Remover espacios y caracteres especiales
    $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
    
    // Verificar que tenga al menos 10 dígitos
    return strlen($telefono_limpio) >= 10 ? $telefono_limpio : false;
}

// Función para formatear números
function formatearNumero($numero) {
    return number_format($numero, 0, '.', ',');
}

// Función para generar URL de WhatsApp
function generarUrlWhatsApp($telefono, $mensaje) {
    $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
    $mensaje_encoded = urlencode($mensaje);
    return "https://wa.me/$telefono_limpio?text=$mensaje_encoded";
}

// Función para generar mensaje de WhatsApp personalizado
function generarMensajeWhatsApp($invitado) {
    $mensaje = "🎉 ¡Estás invitado/a! 🎉\n\n";
    $mensaje .= "Hola " . $invitado['nombre_completo'] . ",\n\n";
    $mensaje .= "Nos complace invitarte a nuestro evento especial.\n\n";
    $mensaje .= "🎫 Tu código de invitación: *" . $invitado['token'] . "*\n";
    
    if ($invitado['cupos_disponibles'] > 1) {
        $mensaje .= "👥 Cupos disponibles: " . $invitado['cupos_disponibles'] . " personas\n";
    }
    
    if ($invitado['mesa']) {
        $mensaje .= "🪑 Mesa asignada: " . $invitado['mesa'] . "\n";
    }
    
    $mensaje .= "\n📱 Para confirmar tu asistencia, ingresa a:\n";
    $mensaje .= "https://tudominio.com/rsvp/confirmar.php?token=" . $invitado['token'] . "\n\n";
    $mensaje .= "¡Esperamos verte pronto! 🌟";
    
    return $mensaje;
}

// Función para log de actividades (opcional)
function logActividad($accion, $detalles = '', $id_usuario = null) {
    if (!$id_usuario && isset($_SESSION['admin_id'])) {
        $id_usuario = $_SESSION['admin_id'];
    }
    
    $db = getDB();
    try {
        $stmt = $db->getConnection()->prepare("
            INSERT INTO log_actividades (id_usuario, accion, detalles, ip_address, timestamp)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$id_usuario, $accion, $detalles, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        // Error en log no debe afectar la funcionalidad principal
        error_log("Error en log de actividad: " . $e->getMessage());
    }
}
?>