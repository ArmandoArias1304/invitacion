<?php
/**
 * SSE PARA ESTADÍSTICAS DE ACCESO EN TIEMPO REAL
 * Monitorea los accesos registrados y envía actualizaciones al dashboard
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

// Permitir que el cliente envíe el último id recibido
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

set_time_limit(0);
ignore_user_abort(true);

while (true) {
    try {
        $db = getDB();
        $conn = $db->getConnection();
        
        // Buscar accesos nuevos (solo los registrados por el escáner)
        $stmt = $conn->prepare("
            SELECT 
                ae.id_acceso,
                ae.id_invitado,
                ae.cantidad_ingresada,
                ae.timestamp_escaneo,
                i.nombre_completo,
                i.mesa
            FROM accesos_evento ae
            JOIN invitados i ON ae.id_invitado = i.id_invitado
            WHERE ae.id_acceso > ? 
            ORDER BY ae.id_acceso ASC
        ");
        $stmt->execute([$lastId]);
        $nuevosAccesos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($nuevosAccesos) {
            foreach ($nuevosAccesos as $acceso) {
                // Obtener estadísticas actualizadas (solo accesos del escáner)
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(DISTINCT ae.id_invitado) as invitados_presentes,
                        SUM(ae.cantidad_ingresada) as personas_presentes,
                        COUNT(*) as total_accesos
                    FROM accesos_evento ae
                ");
                $stmt->execute();
                $statsAcceso = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Obtener total de confirmados para calcular porcentaje
                $stmt = $conn->prepare("SELECT SUM(cantidad_confirmada) as total_confirmados FROM confirmaciones");
                $stmt->execute();
                $totalConfirmados = $stmt->fetchColumn() ?: 0;
                
                $porcentajeAsistencia = $totalConfirmados > 0 ? 
                    round(($statsAcceso['personas_presentes'] / $totalConfirmados) * 100, 1) : 0;
                
                $datos = [
                    'acceso' => $acceso,
                    'stats' => [
                        'invitados_presentes' => (int)$statsAcceso['invitados_presentes'],
                        'personas_presentes' => (int)$statsAcceso['personas_presentes'],
                        'total_cupos_confirmados' => (int)$totalConfirmados,
                        'porcentaje_asistencia' => $porcentajeAsistencia
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                echo "id: {$acceso['id_acceso']}\n";
                echo "data: " . json_encode($datos) . "\n\n";
                ob_flush();
                flush();
                $lastId = $acceso['id_acceso'];
            }
        }
        
        // Enviar heartbeat cada 30 segundos para mantener la conexión
        if (time() % 30 == 0) {
            echo "data: {\"type\": \"heartbeat\", \"timestamp\": \"" . date('Y-m-d H:i:s') . "\"}\n\n";
            ob_flush();
            flush();
        }
        
    } catch (Exception $e) {
        error_log('Error en SSE accesos: ' . $e->getMessage());
        echo "data: {\"error\": \"Error en el servidor\"}\n\n";
        ob_flush();
        flush();
    }
    
    // Esperar 2 segundos antes de la siguiente consulta
    sleep(2);
}
?> 