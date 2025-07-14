<?php
require_once 'auth_check.php';

$mensaje = '';
$error = '';

// Obtener invitados
$db = getDB();
$conn = $db->getConnection();

// Filtros
$filtro_mesa = $_GET['mesa'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_confirmado = $_GET['confirmado'] ?? '';
$buscar = $_GET['buscar'] ?? '';

// Construir consulta
$where_conditions = [];
$params = [];

if (!empty($filtro_mesa)) {
    $where_conditions[] = "i.mesa = ?";
    $params[] = $filtro_mesa;
}

if (!empty($filtro_tipo)) {
    $where_conditions[] = "i.tipo_invitado = ?";
    $params[] = $filtro_tipo;
}

if (!empty($buscar)) {
    $where_conditions[] = "(i.nombre_completo LIKE ? OR i.telefono LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($filtro_confirmado !== '') {
    if ($filtro_confirmado == '1') {
        $where_conditions[] = "c.id_confirmacion IS NOT NULL";
    } else {
        $where_conditions[] = "c.id_confirmacion IS NULL";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT i.*, c.cantidad_confirmada, c.fecha_confirmacion
    FROM invitados i
    LEFT JOIN confirmaciones c ON i.id_invitado = c.id_invitado
    $where_clause
    ORDER BY i.nombre_completo
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$invitados = $stmt->fetchAll();

// Obtener opciones para filtros
$stmt = $conn->prepare("SELECT DISTINCT mesa FROM invitados WHERE mesa IS NOT NULL ORDER BY mesa");
$stmt->execute();
$mesas_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("SELECT DISTINCT tipo_invitado FROM invitados WHERE tipo_invitado IS NOT NULL ORDER BY tipo_invitado");
$stmt->execute();
$tipos_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Procesar envíos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'enviar_individual':
            $id_invitado = (int)$_POST['id_invitado'];
            
            // Obtener datos del invitado
            $stmt = $conn->prepare("SELECT * FROM invitados WHERE id_invitado = ?");
            $stmt->execute([$id_invitado]);
            $invitado = $stmt->fetch();
            
            if ($invitado && !empty($invitado['telefono'])) {
                $mensaje_whatsapp = generarMensajeWhatsApp($invitado);
                $url_whatsapp = generarUrlWhatsApp($invitado['telefono'], $mensaje_whatsapp);
                
                echo "<script>window.open('$url_whatsapp', '_blank');</script>";
                $mensaje = "Enviando invitación a " . htmlspecialchars($invitado['nombre_completo']);
            } else {
                $error = "El invitado no tiene teléfono registrado";
            }
            break;
            
        case 'enviar_masivo':
            $invitados_seleccionados = $_POST['invitados_seleccionados'] ?? [];
            $enviados = 0;
            $errores = [];
            
            foreach ($invitados_seleccionados as $id_invitado) {
                $stmt = $conn->prepare("SELECT * FROM invitados WHERE id_invitado = ?");
                $stmt->execute([$id_invitado]);
                $invitado = $stmt->fetch();
                
                if ($invitado && !empty($invitado['telefono'])) {
                    $enviados++;
                } else {
                    $errores[] = $invitado['nombre_completo'] . " (sin teléfono)";
                }
            }
            
            if ($enviados > 0) {
                $mensaje = "Preparados $enviados envíos. Haz clic en 'Enviar Seleccionados' para abrir WhatsApp.";
            }
            
            if (!empty($errores)) {
                $error = "Errores: " . implode(', ', $errores);
            }
            break;
    }
}

// Funciones auxiliares movidas a database.php
// generarMensajeWhatsApp() y generarUrlWhatsApp() están ahora en database.php
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envío de Invitaciones - Sistema de Invitaciones</title>
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
        
        .filters-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
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
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            position: sticky;
            top: 0;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .stats-bar {
            display: flex;
            gap: 2rem;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-item .label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .actions-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .checkbox-column {
            width: 40px;
            text-align: center;
        }
        
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .whatsapp-btn {
            background: #25D366;
            color: white;
        }
        
        .whatsapp-btn:hover {
            background: #128C7E;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📱 Envío de Invitaciones</h1>
            <div class="nav-links">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="generador.php">👥 Generador</a>
                <a href="../logout.php">🚪 Salir</a>
            </div>
        </div>
    </div>
    
    <div class="container">
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
        
        <!-- Filtros -->
        <div class="filters-card">
            <h3 style="margin-bottom: 1rem;">🔍 Filtros de Búsqueda</h3>
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label for="buscar">Buscar por nombre/teléfono</label>
                        <input type="text" id="buscar" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar...">
                    </div>
                    
                    <div class="form-group">
                        <label for="filtro_mesa">Mesa</label>
                        <select id="filtro_mesa" name="mesa">
                            <option value="">Todas las mesas</option>
                            <?php foreach ($mesas_disponibles as $mesa): ?>
                                <option value="<?php echo $mesa; ?>" <?php echo $filtro_mesa == $mesa ? 'selected' : ''; ?>>
                                    Mesa <?php echo $mesa; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="filtro_tipo">Tipo</label>
                        <select id="filtro_tipo" name="tipo">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_disponibles as $tipo): ?>
                                <option value="<?php echo $tipo; ?>" <?php echo $filtro_tipo == $tipo ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($tipo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="filtro_confirmado">Estado</label>
                        <select id="filtro_confirmado" name="confirmado">
                            <option value="">Todos</option>
                            <option value="1" <?php echo $filtro_confirmado === '1' ? 'selected' : ''; ?>>Confirmados</option>
                            <option value="0" <?php echo $filtro_confirmado === '0' ? 'selected' : ''; ?>>Sin confirmar</option>
                        </select>
                    </div>
                </div>
                
                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    <a href="envios.php" class="btn btn-warning">🔄 Limpiar filtros</a>
                </div>
            </form>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="number"><?php echo count($invitados); ?></div>
                <div class="label">Invitados Mostrados</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo count(array_filter($invitados, function($inv) { return !empty($inv['telefono']); })); ?></div>
                <div class="label">Con Teléfono</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo count(array_filter($invitados, function($inv) { return $inv['cantidad_confirmada']; })); ?></div>
                <div class="label">Confirmados</div>
            </div>
        </div>
        
        <!-- Lista de invitados -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Lista de Invitados</h3>
                <div class="actions-bar">
                    <button type="button" class="btn btn-success" onclick="seleccionarTodos()">
                        ☑️ Seleccionar Todos
                    </button>
                    <button type="button" class="btn btn-warning" onclick="deseleccionarTodos()">
                        ❌ Deseleccionar Todos
                    </button>
                    <button type="button" class="btn whatsapp-btn" onclick="enviarSeleccionados()">
                        📱 Enviar Seleccionados
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <form id="formEnvioMasivo" method="POST">
                        <input type="hidden" name="action" value="enviar_masivo">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <input type="checkbox" id="selectAll" onchange="toggleTodos()">
                                    </th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Mesa</th>
                                    <th>Cupos</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Token</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invitados as $invitado): ?>
                                <tr>
                                    <td class="checkbox-column">
                                        <?php if (!empty($invitado['telefono'])): ?>
                                            <input type="checkbox" name="invitados_seleccionados[]" value="<?php echo $invitado['id_invitado']; ?>" class="invitado-checkbox">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($invitado['nombre_completo']); ?></strong>
                                        <?php if ($invitado['tipo_invitado']): ?>
                                            <br><span class="badge badge-info"><?php echo ucfirst($invitado['tipo_invitado']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invitado['telefono']): ?>
                                            <?php echo htmlspecialchars($invitado['telefono']); ?>
                                        <?php else: ?>
                                            <span style="color: #dc3545;">Sin teléfono</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $invitado['mesa'] ? 'Mesa ' . $invitado['mesa'] : 'Sin asignar'; ?>
                                    </td>
                                    <td>
                                        <?php echo $invitado['cupos_disponibles']; ?>
                                        <?php if ($invitado['cantidad_confirmada']): ?>
                                            <br><small>(Confirmó: <?php echo $invitado['cantidad_confirmada']; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo ucfirst($invitado['tipo_invitado'] ?? 'general'); ?></td>
                                    <td>
                                        <?php if ($invitado['cantidad_confirmada']): ?>
                                            <span class="badge badge-success">
                                                Confirmado
                                                <br><small><?php echo date('d/m/Y', strtotime($invitado['fecha_confirmacion'])); ?></small>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo $invitado['token']; ?></code>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="copiarToken('<?php echo $invitado['token']; ?>')">
                                            📋
                                        </button>
                                    </td>
                                    <td>
                                        <?php if (!empty($invitado['telefono'])): ?>
                                            <button type="button" class="btn btn-sm whatsapp-btn" onclick="enviarIndividual(<?php echo $invitado['id_invitado']; ?>)">
                                                📱 Enviar
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="previsualizarMensaje(<?php echo $invitado['id_invitado']; ?>)">
                                                👁️ Ver
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #dc3545; font-size: 0.8rem;">Sin teléfono</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if (empty($invitados)): ?>
            <div class="alert alert-warning" style="margin-top: 2rem;">
                <h4>No se encontraron invitados</h4>
                <p>No hay invitados que coincidan con los filtros seleccionados. <a href="generador.php">¿Quieres generar algunos invitados?</a></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para previsualizar mensajes -->
    <div id="modalPreview" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
            <h3>📱 Previsualización del Mensaje</h3>
            <div id="contenidoMensaje" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin: 1rem 0; white-space: pre-line; font-family: monospace;"></div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-warning" onclick="cerrarModal()">Cerrar</button>
                <button type="button" class="btn whatsapp-btn" onclick="enviarDesdeModal()">📱 Enviar por WhatsApp</button>
            </div>
        </div>
    </div>
    
    <script>
        let invitadoActual = null;
        
        // Funciones de selección
        function seleccionarTodos() {
            const checkboxes = document.querySelectorAll('.invitado-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            document.getElementById('selectAll').checked = true;
        }
        
        function deseleccionarTodos() {
            const checkboxes = document.querySelectorAll('.invitado-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
        }
        
        function toggleTodos() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.invitado-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        // Función para enviar seleccionados
        function enviarSeleccionados() {
            const seleccionados = document.querySelectorAll('.invitado-checkbox:checked');
            
            if (seleccionados.length === 0) {
                alert('Por favor selecciona al menos un invitado');
                return;
            }
            
            if (confirm(`¿Enviar invitaciones a ${seleccionados.length} invitado(s)?`)) {
                // Abrir múltiples pestañas de WhatsApp
                seleccionados.forEach((checkbox, index) => {
                    setTimeout(() => {
                        enviarIndividual(checkbox.value);
                    }, index * 1000); // Retraso de 1 segundo entre envíos
                });
            }
        }
        
        // Función para envío individual
        function enviarIndividual(idInvitado) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'enviar_individual';
            form.appendChild(actionInput);
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id_invitado';
            idInput.value = idInvitado;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Función para copiar token
        function copiarToken(token) {
            navigator.clipboard.writeText(token).then(() => {
                alert('Token copiado: ' + token);
            });
        }
        
        // Función para previsualizar mensaje
        function previsualizarMensaje(idInvitado) {
            invitadoActual = idInvitado;
            
            // Buscar datos del invitado en la tabla
            const fila = document.querySelector(`input[value="${idInvitado}"]`).closest('tr');
            const nombre = fila.cells[1].textContent.trim().split('\n')[0];
            const telefono = fila.cells[2].textContent.trim();
            const mesa = fila.cells[3].textContent.trim();
            const cupos = fila.cells[4].textContent.trim().split('\n')[0];
            const token = fila.querySelector('code').textContent;
            
            // Generar mensaje
            let mensaje = "🎉 ¡Estás invitado/a! 🎉\n\n";
            mensaje += `Hola ${nombre},\n\n`;
            mensaje += "Nos complace invitarte a nuestro evento especial.\n\n";
            mensaje += `🎫 Tu código de invitación: *${token}*\n`;
            
            if (cupos > 1) {
                mensaje += `👥 Cupos disponibles: ${cupos} personas\n`;
            }
            
            if (mesa && mesa !== 'Sin asignar') {
                mensaje += `🪑 ${mesa}\n`;
            }
            
            mensaje += "\n📱 Para confirmar tu asistencia, ingresa a:\n";
            mensaje += `https://tudominio.com/rsvp/confirmar.php?token=${token}\n\n`;
            mensaje += "¡Esperamos verte pronto! 🌟";
            
            document.getElementById('contenidoMensaje').textContent = mensaje;
            document.getElementById('modalPreview').style.display = 'block';
        }
        
        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalPreview').style.display = 'none';
            invitadoActual = null;
        }
        
        // Función para enviar desde modal
        function enviarDesdeModal() {
            if (invitadoActual) {
                enviarIndividual(invitadoActual);
                cerrarModal();
            }
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalPreview').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Función para filtrado en tiempo real
        document.getElementById('buscar').addEventListener('input', function() {
            // Aquí puedes implementar filtrado en tiempo real sin recargar la página
        });
    </script>
</body>
</html>