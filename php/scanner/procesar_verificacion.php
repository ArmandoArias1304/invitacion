<?php
/**
 * PROCESADOR DE VERIFICACIÓN QR
 * Backend para el sistema de control de acceso con registro de entrada
 */

require_once '../config/database.php';

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Solo permitir POST para verificación
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['error' => 'Datos JSON inválidos'], 400);
}

// Validar que venga el código QR
if (!isset($input['qr_data']) || empty($input['qr_data'])) {
    jsonResponse(['error' => 'Código QR requerido'], 400);
}

// Validar que venga la cantidad_ingresada
if (!isset($input['cantidad_ingresada']) || !is_numeric($input['cantidad_ingresada'])) {
    jsonResponse(['error' => 'Debes indicar cuántas personas están ingresando.'], 400);
}
$cantidadIngresada = (int)$input['cantidad_ingresada'];
if ($cantidadIngresada < 1) {
    jsonResponse(['error' => 'La cantidad ingresada debe ser al menos 1.'], 400);
}

try {
    $db = getDB();
    $connection = $db->getConnection();
    
    // Decodificar datos del QR
    $qrData = json_decode($input['qr_data'], true);
    
    if (!$qrData || !isset($qrData['token'])) {
        jsonResponse(['error' => 'Formato de QR inválido'], 400);
    }
    
    $tokenQR = $qrData['token'];
    
    // Validar formato del token
    if (!preg_match('/^[A-Z0-9-]{12,20}$/', $tokenQR)) {
        jsonResponse(['error' => 'Formato de token inválido'], 400);
    }
    
    // PASO 1: Verificar el estado actual del token QR
    $stmt = $connection->prepare("
        SELECT tq.*, i.nombre_completo, i.mesa, i.tipo_invitado, c.cantidad_confirmada
        FROM tokens_qr tq
        JOIN invitados i ON tq.id_invitado = i.id_invitado
        LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
        WHERE tq.token_unico = ? AND tq.activo = 1
    ");
    $stmt->execute([$tokenQR]);
    $tokenInfo = $stmt->fetch();
    
    if (!$tokenInfo) {
        jsonResponse([
            'success' => false,
            'error' => 'QR no encontrado o inactivo',
            'status' => 'no_encontrado'
        ], 404);
    }
    
    // PASO 2: Verificar si el token ha expirado
    if (strtotime($tokenInfo['fecha_expiracion']) < time()) {
        jsonResponse([
            'success' => false,
            'error' => 'QR expirado',
            'message' => 'Este código QR ha expirado. Contacta a los organizadores.',
            'status' => 'expirado'
        ], 410);
    }
    
    // PASO 3: Verificar si el invitado confirmó asistencia
    if (!$tokenInfo['cantidad_confirmada'] || $tokenInfo['cantidad_confirmada'] <= 0) {
        jsonResponse([
            'success' => false,
            'error' => 'Asistencia no confirmada',
            'message' => $tokenInfo['nombre_completo'] . ' no ha confirmado su asistencia.',
            'status' => 'no_confirmado',
            'invitado' => [
                'nombre' => $tokenInfo['nombre_completo'],
                'mesa' => $tokenInfo['mesa'],
                'tipo' => $tokenInfo['tipo_invitado']
            ]
        ], 409);
    }

    // PASO 4: Calcular cuántos ya han ingresado
    $stmt = $connection->prepare("SELECT SUM(cantidad_ingresada) as total_ingresados FROM accesos_evento WHERE token_usado = ? AND status_entrada = 'ingreso'");
    $stmt->execute([$tokenQR]);
    $totalIngresados = (int)($stmt->fetchColumn() ?? 0);
    $cuposRestantes = $tokenInfo['cantidad_confirmada'] - $totalIngresados;
    if ($cuposRestantes <= 0) {
        jsonResponse([
            'success' => false,
            'error' => 'Todos los cupos ya han ingresado con este QR.',
            'status' => 'ya_usado',
            'invitado' => [
                'nombre' => $tokenInfo['nombre_completo'],
                'mesa' => $tokenInfo['mesa'],
                'cantidad' => $tokenInfo['cantidad_confirmada'],
                'tipo' => $tokenInfo['tipo_invitado'],
                'total_ingresados' => $totalIngresados
            ]
        ]);
    }
    if ($cantidadIngresada > $cuposRestantes) {
        jsonResponse([
            'success' => false,
            'error' => 'No puedes ingresar más personas de las que quedan disponibles.',
            'cupos_restantes' => $cuposRestantes,
            'status' => 'exceso',
            'invitado' => [
                'nombre' => $tokenInfo['nombre_completo'],
                'mesa' => $tokenInfo['mesa'],
                'cantidad' => $tokenInfo['cantidad_confirmada'],
                'tipo' => $tokenInfo['tipo_invitado'],
                'total_ingresados' => $totalIngresados
            ]
        ], 400);
    }

    // PASO 5: Registrar el acceso parcial
    $connection->beginTransaction();
    try {
        // Obtener ubicación GPS si está disponible
        $ubicacionGPS = '';
        if (isset($input['ubicacion']) && is_array($input['ubicacion'])) {
            $ubicacionGPS = $input['ubicacion']['lat'] . ',' . $input['ubicacion']['lng'];
        }
        // Registrar el acceso al evento
        $stmt = $connection->prepare("
            INSERT INTO accesos_evento (id_invitado, token_usado, timestamp_escaneo, cantidad_ingresada, ubicacion_gps, status_entrada)
            VALUES (?, ?, NOW(), ?, ?, 'ingreso')
        ");
        $stmt->execute([
            $tokenInfo['id_invitado'],
            $tokenQR,
            $cantidadIngresada,
            $ubicacionGPS
        ]);
        // Si se completan los cupos, marcar QR como usado
        if (($totalIngresados + $cantidadIngresada) >= $tokenInfo['cantidad_confirmada']) {
            $stmt = $connection->prepare("UPDATE tokens_qr SET usado = 1 WHERE token_unico = ?");
            $stmt->execute([$tokenQR]);
        }
        $connection->commit();
        // Log para auditoría
        error_log("ENTRADA REGISTRADA: " . $tokenInfo['nombre_completo'] . " - Mesa " . $tokenInfo['mesa'] . " - " . date('Y-m-d H:i:s'));
        // RESPUESTA EXITOSA
        jsonResponse([
            'success' => true,
            'message' => 'Entrada registrada exitosamente',
            'status' => 'registrado',
            'invitado' => [
                'nombre' => $tokenInfo['nombre_completo'],
                'mesa' => $tokenInfo['mesa'],
                'cantidad' => $tokenInfo['cantidad_confirmada'],
                'tipo' => $tokenInfo['tipo_invitado'],
                'total_ingresados' => $totalIngresados + $cantidadIngresada,
                'cupos_restantes' => max(0, $tokenInfo['cantidad_confirmada'] - ($totalIngresados + $cantidadIngresada)),
                'hora_entrada' => date('H:i'),
                'fecha_entrada' => date('d/m/Y'),
                'timestamp_entrada' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        $connection->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Error en procesar_verificacion.php: ' . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => 'Por favor, intenta nuevamente. Si el problema persiste, contacta al administrador.',
        'status' => 'error_servidor'
    ], 500);
}

/**
 * Función para obtener estadísticas rápidas (endpoint adicional)
 */
if (isset($_GET['stats_rapidas']) && $_GET['stats_rapidas'] === 'true') {
    try {
        $db = getDB();
        $connection = $db->getConnection();
        
        // Contar presentes hoy
        $stmt = $connection->prepare("
            SELECT COUNT(DISTINCT ae.id_invitado) as presentes_hoy,
                   SUM(c.cantidad_confirmada) as personas_presentes
            FROM accesos_evento ae
            JOIN confirmaciones c ON ae.id_invitado = c.id_invitado
            WHERE DATE(ae.timestamp_escaneo) = CURDATE() AND ae.status_entrada = 'ingreso'
        ");
        $stmt->execute();
        $presentes = $stmt->fetch();
        
        // Total confirmados
        $stmt = $connection->prepare("
            SELECT COUNT(*) as total_confirmados,
                   SUM(cantidad_confirmada) as total_personas
            FROM confirmaciones
            WHERE cantidad_confirmada > 0
        ");
        $stmt->execute();
        $confirmados = $stmt->fetch();
        
        // Entradas en los últimos 10 minutos
        $stmt = $connection->prepare("
            SELECT COUNT(*) as recientes
            FROM accesos_evento
            WHERE timestamp_escaneo >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND status_entrada = 'ingreso'
        ");
        $stmt->execute();
        $recientes = $stmt->fetch();
        
        jsonResponse([
            'estadisticas' => [
                'presentes' => (int)($presentes['presentes_hoy'] ?? 0),
                'personas_presentes' => (int)($presentes['personas_presentes'] ?? 0),
                'confirmados' => (int)($confirmados['total_confirmados'] ?? 0),
                'total_personas' => (int)($confirmados['total_personas'] ?? 0),
                'recientes' => (int)($recientes['recientes'] ?? 0),
                'porcentaje' => ($confirmados['total_personas'] ?? 0) > 0 ? 
                    round((($presentes['personas_presentes'] ?? 0) / $confirmados['total_personas']) * 100, 1) : 0
            ]
        ]);
        
    } catch (Exception $e) {
        error_log('Error en estadísticas rápidas: ' . $e->getMessage());
        jsonResponse(['error' => 'Error al obtener estadísticas'], 500);
    }
    exit;
}
?> 