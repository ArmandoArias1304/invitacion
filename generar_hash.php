<?php
require_once 'php/config/database.php';

echo "<h2>🔑 Generador de Hash de Contraseña</h2>";

$password = 'admin123';
echo "<p><strong>Contraseña:</strong> $password</p>";

// Generar nuevo hash
$nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
echo "<p><strong>Nuevo hash generado:</strong><br><code>$nuevo_hash</code></p>";

// Verificar que el hash funciona
$verificacion = password_verify($password, $nuevo_hash);
echo "<p><strong>Verificación:</strong> " . ($verificacion ? '✅ Correcto' : '❌ Error') . "</p>";

// Actualizar en la base de datos
echo "<h3>Actualizando en la base de datos...</h3>";

try {
    $db = getDB();
    $stmt = $db->getConnection()->prepare("
        UPDATE usuarios_admin 
        SET password = ? 
        WHERE usuario = 'admin'
    ");
    
    $result = $stmt->execute([$nuevo_hash]);
    
    if ($result) {
        echo "<p>✅ <strong>Hash actualizado exitosamente en la base de datos!</strong></p>";
        
        // Verificar con la función autenticarAdmin
        echo "<h3>Probando autenticación...</h3>";
        $auth_result = $db->autenticarAdmin('admin', $password);
        
        if ($auth_result) {
            echo "<p>✅ <strong>¡Autenticación exitosa!</strong></p>";
            echo "<p>Usuario: " . $auth_result['usuario'] . "</p>";
            echo "<p>Nombre: " . $auth_result['nombre_completo'] . "</p>";
            
            echo "<hr>";
            echo "<h3>🎉 ¡Listo para usar!</h3>";
            echo "<p><strong>Credenciales:</strong></p>";
            echo "<p>Usuario: <code>admin</code></p>";
            echo "<p>Contraseña: <code>admin123</code></p>";
            echo "<p><a href='login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Login</a></p>";
            
        } else {
            echo "<p>❌ Autenticación aún falla</p>";
        }
    } else {
        echo "<p>❌ Error al actualizar en la base de datos</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Elimina este archivo después de usarlo por seguridad.</em></p>";
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 700px; 
    margin: 20px auto; 
    padding: 20px;
    background: #f5f5f5;
}
h2, h3 { color: #333; }
p { background: white; padding: 10px; margin: 10px 0; border-radius: 5px; }
code { 
    background: #f8f9fa; 
    padding: 2px 5px; 
    border-radius: 3px; 
    font-family: monospace;
    word-break: break-all;
}
a { color: #667eea; }
</style>