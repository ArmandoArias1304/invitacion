<?php
// sse_confirmaciones.php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require_once '../config/database.php';

// Permitir que el cliente envíe el último id recibido (por query string)
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

set_time_limit(0);
ignore_user_abort(true);

while (true) {
    $db = getDB();
    $conn = $db->getConnection();
    // Buscar confirmaciones nuevas
    $stmt = $conn->prepare("SELECT id_confirmacion, id_invitado, cantidad_confirmada, fecha_confirmacion FROM confirmaciones WHERE id_confirmacion > ? ORDER BY id_confirmacion ASC");
    $stmt->execute([$lastId]);
    $nuevas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($nuevas) {
        foreach ($nuevas as $conf) {
            echo "id: {$conf['id_confirmacion']}\n";
            echo "data: " . json_encode($conf) . "\n\n";
            ob_flush();
            flush();
            $lastId = $conf['id_confirmacion'];
        }
    }
    // Espera 3 segundos antes de volver a consultar
    sleep(3);
} 