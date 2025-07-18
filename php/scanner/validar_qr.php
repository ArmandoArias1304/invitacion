<?php
/**
 * API DE VALIDACIÓN DE CÓDIGOS QR
 * Invitación Digital de Boda
 */

require_once '../config/database.php';

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ENDPOINT PARA ESTADÍSTICAS (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats']) && $_GET['stats'] === 'true') {
    try {
        $db = getDB();
        $connection = $db->getConnection();
        
        // Total de invitados confirmados
        $stmt = $connection->prepare("
            SELECT COUNT(*) as total_confirmados,
                   SUM(cantidad_confirmada) as total_personas_confirmadas
            FROM confirmaciones
            WHERE cantidad_confirmada > 0
        ");
        $stmt->execute();
        $confirmados = $stmt->fetch();
        
        // Total de personas que ya llegaron
        $stmt = $connection->prepare("
            SELECT COUNT(DISTINCT ae.id_invitado) as invitados_presentes,
                   SUM(ae.cantidad_ingresada) as personas_presentes
            FROM accesos_evento ae
            JOIN confirmaciones c ON ae.id_invitado = c.id_invitado
            WHERE ae.status_entrada = 'ingreso'
        ");
        $stmt->execute();
        $presentes = $stmt->fetch();
        
        // Últimos ingresos
        $stmt = $connection->prepare("
            SELECT i.nombre_completo, ae.timestamp_escaneo, i.mesa, c.cantidad_confirmada
            FROM accesos_evento ae
            JOIN invitados i ON ae.id_invitado = i.id_invitado
            JOIN confirmaciones c ON ae.id_invitado = c.id_invitado
            WHERE ae.status_entrada = 'ingreso'
            ORDER BY ae.timestamp_escaneo DESC
            LIMIT 10
        ");
        $stmt->execute();
        $ultimosIngresos = $stmt->fetchAll();
        
        jsonResponse([
            'estadisticas' => [
                'confirmados' => [
                    'invitados' => (int)($confirmados['total_confirmados'] ?? 0),
                    'personas' => (int)($confirmados['total_personas_confirmadas'] ?? 0)
                ],
                'presentes' => [
                    'invitados' => (int)($presentes['invitados_presentes'] ?? 0),
                    'personas' => (int)($presentes['personas_presentes'] ?? 0)
                ],
                'pendientes' => [
                    'invitados' => (int)($confirmados['total_confirmados'] ?? 0) - (int)($presentes['invitados_presentes'] ?? 0),
                    'personas' => (int)($confirmados['total_personas_confirmadas'] ?? 0) - (int)($presentes['personas_presentes'] ?? 0)
                ],
                'porcentaje_asistencia' => ($confirmados['total_personas_confirmadas'] ?? 0) > 0 ? 
                    round((($presentes['personas_presentes'] ?? 0) / $confirmados['total_personas_confirmadas']) * 100, 1) : 0
            ],
            'ultimos_ingresos' => array_map(function($ingreso) {
                return [
                    'nombre' => $ingreso['nombre_completo'],
                    'mesa' => $ingreso['mesa'],
                    'cantidad' => $ingreso['cantidad_confirmada'],
                    'hora' => date('H:i', strtotime($ingreso['timestamp_escaneo'])),
                    'hace' => tiempoTranscurrido($ingreso['timestamp_escaneo'])
                ];
            }, $ultimosIngresos)
        ]);
        
    } catch (Exception $e) {
        error_log('Error en estadísticas: ' . $e->getMessage());
        jsonResponse(['error' => 'Error al obtener estadísticas'], 500);
    }
    exit;
}

// VALIDACIÓN DE QR (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido para validación QR'], 405);
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['error' => 'Datos JSON inválidos'], 400);
}

// Validar que venga el token QR
if (!isset($input['qr_data']) || empty($input['qr_data'])) {
    jsonResponse(['error' => 'Código QR requerido'], 400);
}

try {
    $db = getDB();
    
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
    
    // Validar token QR en la base de datos
    $datosQR = $db->validarTokenQR($tokenQR);
    if (!$datosQR) {
        // Verificar si el QR ya fue usado
        $stmt = $db->getConnection()->prepare("
            SELECT tq.usado, i.nombre_completo
            FROM tokens_qr tq
            LEFT JOIN invitados i ON tq.id_invitado = i.id_invitado
            WHERE tq.token_unico = ?
        ");
        $stmt->execute([$tokenQR]);
        $tokenInfo = $stmt->fetch();
        if ($tokenInfo) {
            if ($tokenInfo['usado']) {
                jsonResponse([
                    'error' => 'QR ya utilizado',
                    'message' => $tokenInfo['nombre_completo'] . ' ya utilizó todos sus cupos.',
                    'status' => 'ya_usado'
                ], 409);
            } else {
                jsonResponse([
                    'error' => 'QR expirado o inactivo',
                    'status' => 'expirado'
                ], 410);
            }
        } else {
            jsonResponse([
                'error' => 'QR no encontrado',
                'status' => 'no_encontrado'
            ], 404);
        }
    }

    // NUEVO: Sumar accesos previos y calcular cupos restantes
    $stmt = $db->getConnection()->prepare("SELECT SUM(cantidad_ingresada) as total_ingresados FROM accesos_evento WHERE token_usado = ? AND status_entrada = 'ingreso'");
    $stmt->execute([$tokenQR]);
    $totalIngresados = (int)($stmt->fetchColumn() ?? 0);
    $cuposRestantes = $datosQR['cantidad_confirmada'] - $totalIngresados;
    if ($cuposRestantes <= 0) {
        jsonResponse([
            'error' => 'QR ya utilizado',
            'message' => $datosQR['nombre_completo'] . ' ya utilizó todos sus cupos.',
            'status' => 'ya_usado',
            'invitado' => [
                'nombre' => $datosQR['nombre_completo'],
                'mesa' => $datosQR['mesa'],
                'cantidad' => $datosQR['cantidad_confirmada'],
                'total_ingresados' => $totalIngresados,
                'cupos_restantes' => 0
            ]
        ], 409);
    }

    // Devolver datos para el frontend
    jsonResponse([
        'success' => true,
        'invitado' => [
            'nombre' => $datosQR['nombre_completo'],
            'mesa' => $datosQR['mesa'],
            'cantidad' => $datosQR['cantidad_confirmada'],
            'total_ingresados' => $totalIngresados,
            'cupos_restantes' => $cuposRestantes
        ],
        'status' => 'parcial'
    ]);
    
    // Obtener ubicación GPS si está disponible
    $ubicacionGPS = isset($input['ubicacion']) ? 
        $input['ubicacion']['lat'] . ',' . $input['ubicacion']['lng'] : '';
    
    // Registrar el acceso
    $connection = $db->getConnection();
    $connection->beginTransaction();
    
    try {
        // Marcar QR como usado
        $db->marcarQRUsado($tokenQR);
        
        // Registrar acceso al evento
        $db->registrarAccesoEvento(
            $datosQR['id_invitado'],
            $tokenQR,
            $ubicacionGPS
        );
        
        $connection->commit();
        
        // Respuesta exitosa
        jsonResponse([
            'success' => true,
            'message' => 'Acceso registrado exitosamente',
            'invitado' => [
                'nombre' => $datosQR['nombre_completo'],
                'mesa' => $datosQR['mesa'],
                'cantidad' => $datosQR['cantidad_confirmada'],
                'hora_entrada' => date('H:i'),
                'fecha_entrada' => date('d/m/Y')
            ],
            'status' => 'registrado'
        ]);
        
    } catch (Exception $e) {
        $connection->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Error en validar_qr.php: ' . $e->getMessage());
    jsonResponse([
        'error' => 'Error interno del servidor',
        'message' => 'Por favor, intenta nuevamente'
    ], 500);
}

/**
 * Función para calcular tiempo transcurrido
 */
function tiempoTranscurrido($datetime) {
    $tiempo = time() - strtotime($datetime);
    
    if ($tiempo < 60) {
        return 'Hace ' . $tiempo . ' segundos';
    } elseif ($tiempo < 3600) {
        return 'Hace ' . floor($tiempo / 60) . ' minutos';
    } else {
        return 'Hace ' . floor($tiempo / 3600) . ' horas';
    }
}
?>