<?php
require_once 'php/config/database.php';

try {
    $db = getDB();
    echo "✅ Conexión exitosa a la base de datos!<br>";
    echo "📊 Base de datos: " . DB_NAME . "<br>";
    echo "🖥️ Servidor: " . DB_HOST;
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>