<?php
/**
 * ENDPOINT PARA NOTIFICACIONES DE CONFIRMACIÓN EN TIEMPO REAL
 * Recibe notificaciones cuando un invitado confirma su asistencia
 * y actualiza la base de datos correspondiente
 */

require_once '../config/database.php';

// Configurar headers para AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos JSON del body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validar datos requeridos
    if (!$data || !isset($data['id_invitado']) || !isset($data['cantidad_confirmada'])) {
        throw new Exception('Datos incompletos o inválidos');
    }
    
    $id_invitado = (int)$data['id_invitado'];
    $cantidad_confirmada = (int)$data['cantidad_confirmada'];
    $token = $data['token'] ?? '';
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    // Validar que el invitado existe
    $db = getDB();
    $invitado = $db->getInvitadoPorId($id_invitado);
    
    if (!$invitado) {
        throw new Exception('Invitado no encontrado');
    }
    
    // Verificar que no haya confirmado previamente
    $confirmacionExistente = $db->getConfirmacionPorInvitado($id_invitado);
    
    if ($confirmacionExistente) {
        throw new Exception('El invitado ya confirmó su asistencia previamente');
    }
    
    // Procesar la confirmación
    $observaciones = 'Confirmación automática vía web - ' . date('Y-m-d H:i:s');
    $db->confirmarAsistencia($id_invitado, $cantidad_confirmada, $observaciones);
    
    // Generar token QR si no existe
    $tokenQR = $db->getTokenQR($id_invitado);
    if (!$tokenQR) {
        $db->generarTokenQR($id_invitado);
    }
    
    // Obtener estadísticas actualizadas
    $stats = [
        'total_invitados' => $db->getTotalInvitados(),
        'total_confirmados' => $db->getTotalConfirmados(),
        'total_cupos' => $db->getTotalCupos(),
        'total_cupos_confirmados' => $db->getTotalCuposConfirmados()
    ];
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Confirmación procesada exitosamente',
        'data' => [
            'id_invitado' => $id_invitado,
            'cantidad_confirmada' => $cantidad_confirmada,
            'fecha_confirmacion' => $timestamp,
            'stats' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log('Error en notificar_confirmacion.php: ' . $e->getMessage());
    
    // Respuesta de error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?> 