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
?>