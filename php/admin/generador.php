<?php
require_once 'auth_check.php';

$mensaje = '';
$error = '';

// Función para verificar si el teléfono ya existe
function telefonoExiste($telefono, $conn) {
    if (empty($telefono)) return false;
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM invitados WHERE telefono = ?");
    $stmt->execute([$telefono]);
    return $stmt->fetchColumn() > 0;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $conn = $db->getConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generar_individual':
                $nombre_completo = sanitizeInput($_POST['nombre_completo']);
                $telefono = sanitizeInput($_POST['telefono']);
                $cupos_disponibles = (int)$_POST['cupos_disponibles'];
                $mesa = $_POST['mesa'] ? (int)$_POST['mesa'] : null;
                $tipo_invitado = sanitizeInput($_POST['tipo_invitado']);
                
                // Validaciones
                if (empty($nombre_completo)) {
                    $error = 'El nombre completo es requerido';
                } elseif (!empty($telefono) && strlen($telefono) !== 10) {
                    $error = 'El teléfono debe tener exactamente 10 dígitos';
                } elseif (!empty($telefono) && !ctype_digit($telefono)) {
                    $error = 'El teléfono solo debe contener números';
                } elseif (!empty($telefono) && telefonoExiste($telefono, $conn)) {
                    $error = 'Ya existe un invitado con este número de teléfono. Por favor usa otro número.';
                } else {
                    try {
                        // Generar token único
                        do {
                            $token = Database::generateToken();
                        } while ($db->tokenExists($token));
                        
                        $stmt = $conn->prepare("
                            INSERT INTO invitados (nombre_completo, telefono, cupos_disponibles, mesa, tipo_invitado, token)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([$nombre_completo, $telefono, $cupos_disponibles, $mesa, $tipo_invitado, $token]);
                        $id_insertado = $conn->lastInsertId();
                        $mensaje = "✅ Invitado generado exitosamente!<br><strong>ID:</strong> $id_insertado<br><strong>Token:</strong> $token<br><br><a href='dashboard.php' class='btn btn-success'>Ver en Dashboard</a>";
                        
                    } catch (Exception $e) {
                        $error = 'Error al generar invitado: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'generar_masivo':
                $lista_invitados = $_POST['lista_invitados'];
                $lineas = explode("\n", $lista_invitados);
                $generados = 0;
                $errores = [];
                $telefonos_procesados = [];
                
                foreach ($lineas as $num_linea => $linea) {
                    $linea = trim($linea);
                    if (empty($linea)) continue;
                    
                    $datos = explode(',', $linea);
                    if (count($datos) < 1) {
                        $errores[] = "Línea " . ($num_linea + 1) . ": Formato inválido";
                        continue;
                    }
                    
                    $nombre_completo = trim($datos[0]);
                    $telefono = isset($datos[1]) ? trim($datos[1]) : '';
                    $cupos_disponibles = isset($datos[2]) ? (int)trim($datos[2]) : 1;
                    $mesa = isset($datos[3]) && !empty(trim($datos[3])) ? (int)trim($datos[3]) : null;
                    $tipo_invitado = isset($datos[4]) ? trim($datos[4]) : 'general';
                    
                    // Validaciones por línea
                    if (empty($nombre_completo)) {
                        $errores[] = "Línea " . ($num_linea + 1) . ": Nombre vacío";
                        continue;
                    }
                    
                    if (!empty($telefono)) {
                        if (strlen($telefono) !== 10 || !ctype_digit($telefono)) {
                            $errores[] = "Línea " . ($num_linea + 1) . ": Teléfono '$telefono' debe tener exactamente 10 dígitos";
                            continue;
                        }
                        
                        if (telefonoExiste($telefono, $conn)) {
                            $errores[] = "Línea " . ($num_linea + 1) . ": Teléfono '$telefono' ya existe en la base de datos";
                            continue;
                        }
                        
                        if (in_array($telefono, $telefonos_procesados)) {
                            $errores[] = "Línea " . ($num_linea + 1) . ": Teléfono '$telefono' duplicado en la lista";
                            continue;
                        }
                        
                        $telefonos_procesados[] = $telefono;
                    }
                    
                    try {
                        do {
                            $token = Database::generateToken();
                        } while ($db->tokenExists($token));
                        
                        $stmt = $conn->prepare("
                            INSERT INTO invitados (nombre_completo, telefono, cupos_disponibles, mesa, tipo_invitado, token)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([$nombre_completo, $telefono, $cupos_disponibles, $mesa, $tipo_invitado, $token]);
                        $generados++;
                        
                    } catch (Exception $e) {
                        $errores[] = "Línea " . ($num_linea + 1) . " ('$nombre_completo'): " . $e->getMessage();
                    }
                }
                
                $mensaje = "Se generaron $generados invitados exitosamente.";
                if (!empty($errores)) {
                    $error = implode('<br>', $errores);
                }
                break;
                
            case 'verificar_telefono':
                header('Content-Type: application/json');
                $telefono = sanitizeInput($_POST['telefono']);
                
                $response = ['existe' => false, 'valido' => true, 'mensaje' => ''];
                
                if (!empty($telefono)) {
                    if (strlen($telefono) !== 10 || !ctype_digit($telefono)) {
                        $response['valido'] = false;
                        $response['mensaje'] = 'El teléfono debe tener exactamente 10 dígitos';
                    } elseif (telefonoExiste($telefono, $conn)) {
                        $response['existe'] = true;
                        $response['mensaje'] = 'Este número ya está registrado';
                    } else {
                        $response['mensaje'] = 'Número disponible';
                    }
                }
                
                echo json_encode($response);
                exit;
        }
    }
}

// Obtener estadísticas
$db = getDB();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados");
$stmt->execute();
$total_invitados = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados WHERE mesa IS NOT NULL");
$stmt->execute();
$con_mesa = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitados WHERE telefono IS NOT NULL AND telefono != ''");
$stmt->execute();
$con_telefono = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Invitados - Sistema de Invitaciones</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            background: rgba(255,255,255,0.1);
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #667eea;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h3 {
            color: #333;
            font-size: 1.2rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-grid-full {
            grid-column: 1 / -1;
        }
        
        .help-text {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .validation-message {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            display: none;
        }
        
        .validation-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .validation-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .validation-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-validated {
            border-color: #28a745 !important;
        }
        
        .input-error {
            border-color: #dc3545 !important;
        }
        
        .spinner {
            display: none;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>👥 Generador de Invitados</h1>
            <div class="nav-links">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="envios.php">📱 Envíos</a>
                <a href="../../logout.php">🚪 Salir</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($total_invitados); ?></h3>
                <p>Total de Invitados</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($con_mesa); ?></h3>
                <p>Con Mesa Asignada</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($con_telefono); ?></h3>
                <p>Con Teléfono</p>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Contenido principal -->
        <div class="content-grid">
            <!-- Generación individual -->
            <div class="card">
                <div class="card-header">
                    <h3>🧑 Generar Invitado Individual</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="formIndividual">
                        <input type="hidden" name="action" value="generar_individual">
                        
                        <div class="form-group">
                            <label for="nombre_completo">Nombre Completo *</label>
                            <input type="text" id="nombre_completo" name="nombre_completo" required>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <div class="input-group">
                                    <input type="tel" id="telefono" name="telefono" placeholder="1234567890" maxlength="10">
                                    <div class="spinner" id="telefonoSpinner"></div>
                                </div>
                                <div class="help-text">Exactamente 10 dígitos, solo números</div>
                                <div class="validation-message" id="telefonoValidation"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="cupos_disponibles">Cupos Disponibles</label>
                                <input type="number" id="cupos_disponibles" name="cupos_disponibles" value="1" min="1" max="10">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="mesa">Mesa</label>
                                <input type="number" id="mesa" name="mesa" min="1" placeholder="Opcional">
                            </div>
                            
                            <div class="form-group">
                                <label for="tipo_invitado">Tipo de Invitado</label>
                                <select id="tipo_invitado" name="tipo_invitado">
                                    <option value="general">General</option>
                                    <option value="vip">VIP</option>
                                    <option value="familia">Familia</option>
                                    <option value="amigo">Amigo</option>
                                    <option value="trabajo">Trabajo</option>
                                    <option value="especial">Especial</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="btnGenerar">
                            ➕ Generar Invitado
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Generación masiva -->
            <div class="card">
                <div class="card-header">
                    <h3>👥 Generación Masiva</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generar_masivo">
                        
                        <div class="form-group">
                            <label for="lista_invitados">Lista de Invitados</label>
                            <textarea id="lista_invitados" name="lista_invitados" placeholder="Formato: Nombre Completo, Teléfono, Cupos, Mesa, Tipo&#10;Juan Pérez, 1234567890, 2, 1, familia&#10;María García, 0987654321, 1, 2, amigo" required></textarea>
                            <div class="help-text">
                                Formato por línea: Nombre, Teléfono (10 dígitos), Cupos, Mesa, Tipo<br>
                                Solo el nombre es obligatorio. Separar con comas.
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            📋 Generar Masivamente
                        </button>
                    </form>
                    
                    <hr style="margin: 1.5rem 0;">
                    
                    <div>
                        <h4>📋 Plantilla de Ejemplo</h4>
                        <textarea readonly style="margin-top: 0.5rem; font-family: monospace; font-size: 0.9rem;">Juan Pérez González, 1234567890, 2, 1, familia
María García López, 0987654321, 1, 2, amigo
Carlos Rodríguez, , 1, 3, trabajo
Ana Martínez, 5555555555, 3, , vip</textarea>
                        <div class="help-text">
                            Copia este formato y modifica con tus datos. Los teléfonos deben tener exactamente 10 dígitos.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3>ℹ️ Información Importante</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <h4>🔑 Tokens</h4>
                        <p>Cada invitado recibe un token único de 12 caracteres que servirá para acceder a su invitación y confirmar asistencia.</p>
                    </div>
                    <div>
                        <h4>📱 Teléfonos</h4>
                        <p><strong>Nuevo:</strong> Los números de teléfono deben tener exactamente 10 dígitos y ser únicos. No se permiten duplicados.</p>
                    </div>
                    <div>
                        <h4>🪑 Mesas</h4>
                        <p>La asignación de mesas es opcional pero recomendada para eventos grandes. Puedes asignarlas después.</p>
                    </div>
                    <div>
                        <h4>👥 Cupos</h4>
                        <p>Indica cuántas personas puede traer cada invitado. Por defecto es 1 (solo el invitado principal).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let validacionTimeout;
        
        // Validación en tiempo real del teléfono
        document.getElementById('telefono').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Solo números
            e.target.value = value;
            
            const validationDiv = document.getElementById('telefonoValidation');
            const spinner = document.getElementById('telefonoSpinner');
            const btnGenerar = document.getElementById('btnGenerar');
            
            // Limpiar timeout anterior
            clearTimeout(validacionTimeout);
            
            if (value === '') {
                validationDiv.style.display = 'none';
                e.target.classList.remove('input-validated', 'input-error');
                btnGenerar.disabled = false;
                return;
            }
            
            if (value.length !== 10) {
                validationDiv.className = 'validation-message validation-error';
                validationDiv.textContent = `Faltan ${10 - value.length} dígitos`;
                validationDiv.style.display = 'block';
                e.target.classList.remove('input-validated');
                e.target.classList.add('input-error');
                btnGenerar.disabled = true;
                return;
            }
            
            // Mostrar spinner y validar con el servidor
            spinner.style.display = 'block';
            validationDiv.style.display = 'none';
            e.target.classList.remove('input-validated', 'input-error');
            
            validacionTimeout = setTimeout(() => {
                fetch('generador.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verificar_telefono&telefono=${value}`
                })
                .then(response => response.json())
                .then(data => {
                    spinner.style.display = 'none';
                    
                    if (!data.valido) {
                        validationDiv.className = 'validation-message validation-error';
                        validationDiv.textContent = data.mensaje;
                        e.target.classList.add('input-error');
                        btnGenerar.disabled = true;
                    } else if (data.existe) {
                        validationDiv.className = 'validation-message validation-error';
                        validationDiv.textContent = '❌ ' + data.mensaje + '. Usa otro número.';
                        e.target.classList.add('input-error');
                        btnGenerar.disabled = true;
                    } else {
                        validationDiv.className = 'validation-message validation-success';
                        validationDiv.textContent = '✅ ' + data.mensaje;
                        e.target.classList.add('input-validated');
                        btnGenerar.disabled = false;
                    }
                    
                    validationDiv.style.display = 'block';
                })
                .catch(error => {
                    spinner.style.display = 'none';
                    console.error('Error:', error);
                    validationDiv.className = 'validation-message validation-warning';
                    validationDiv.textContent = 'Error al verificar el teléfono';
                    validationDiv.style.display = 'block';
                });
            }, 500); // Esperar 500ms antes de validar
        });
        
        // Validación del formulario antes de enviar
        document.getElementById('formIndividual').addEventListener('submit', function(e) {
            const telefono = document.getElementById('telefono').value;
            const validationDiv = document.getElementById('telefonoValidation');
            
            if (telefono && telefono.length !== 10) {
                e.preventDefault();
                alert('El teléfono debe tener exactamente 10 dígitos');
                return;
            }
            
            if (validationDiv.classList.contains('validation-error') && validationDiv.style.display === 'block') {
                e.preventDefault();
                alert('Por favor corrige el error en el teléfono antes de continuar');
                return;
            }
        });
        
        // Validación de formulario masivo
        document.querySelector('form[action*="generar_masivo"]').addEventListener('submit', function(e) {
            const textarea = document.getElementById('lista_invitados');
            const lineas = textarea.value.trim().split('\n');
            let errores = [];
            let telefonos = [];
            
            lineas.forEach((linea, index) => {
                linea = linea.trim();
                if (!linea) return;
                
                const datos = linea.split(',');
                if (datos.length < 1 || !datos[0].trim()) {
                    errores.push(`Línea ${index + 1}: Falta el nombre`);
                    return;
                }
                
                if (datos.length > 1) {
                    const telefono = datos[1].trim();
                    if (telefono) {
                        if (telefono.length !== 10 || !/^\d+$/.test(telefono)) {
                            errores.push(`Línea ${index + 1}: Teléfono '${telefono}' debe tener exactamente 10 dígitos`);
                        } else if (telefonos.includes(telefono)) {
                            errores.push(`Línea ${index + 1}: Teléfono '${telefono}' duplicado en la lista`);
                        } else {
                            telefonos.push(telefono);
                        }
                    }
                }
            });
            
            if (errores.length > 0) {
                e.preventDefault();
                alert('Errores encontrados:\n' + errores.slice(0, 10).join('\n') + 
                     (errores.length > 10 ? '\n... y ' + (errores.length - 10) + ' errores más' : ''));
            }
        });
    </script>
</body>
</html>