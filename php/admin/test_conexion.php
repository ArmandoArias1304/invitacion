<?php
require_once '../config/database.php';

echo "<h2>🔍 Prueba de Conexión a Base de Datos</h2>";

try {
    echo "<p>🔄 Intentando conectar...</p>";
    echo "<p>📍 Host: " . DB_HOST . "</p>";
    echo "<p>🗄️ Base de datos: " . DB_NAME . "</p>";
    echo "<p>👤 Usuario: " . DB_USER . "</p>";
    
    $db = getDB();
    echo "<p style='color: green;'>✅ ¡Conexión exitosa a la base de datos!</p>";
    
    // Probar una consulta simple
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM invitados");
    $result = $stmt->fetch();
    echo "<p>📊 Total de invitados en la base de datos: " . $result['total'] . "</p>";
    
    echo "<p style='color: green; font-weight: bold;'>🎉 ¡Todo funciona correctamente!</p>";
    echo "<p><a href='dashboard.php'>➡️ Ir al Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    
    echo "<h3>🔧 Posibles soluciones:</h3>";
    echo "<ol>";
    echo "<li>Verificar que MySQL/MariaDB esté encendido en XAMPP</li>";
    echo "<li>Cambiar 'localhost' por '127.0.0.1' en database.php</li>";
    echo "<li>Verificar usuario y contraseña de la base de datos</li>";
    echo "</ol>";
}
?>