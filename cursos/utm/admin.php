<?php
/**
 * PANEL DE ADMINISTRACIÓN COMPLETO
 * Sitio: https://feiaal.org/cursos/utm/
 * Archivo: admin.php
 * Panel para gestionar inscripciones y generar reportes
 */

session_start();
require_once 'config.php';

// ====== AUTENTICACIÓN SIMPLE ======
$admin_password = 'feiaal2024admin'; // CAMBIAR ESTA CONTRASEÑA
$admin_user = 'admin';

// Procesar login
if (isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    if ($user === $admin_user && $pass === $admin_password) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_user'] = $user;
        $_SESSION['login_time'] = time();
    } else {
        $error_login = 'Usuario o contraseña incorrectos';
    }
}

// Verificar sesión
if (!isset($_SESSION['admin_logged']) || !$_SESSION['admin_logged']) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Administrativo - Curso IA</title>
        <style>
            body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); margin: 0; padding: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); max-width: 400px; width: 100%; }
            .login-header { text-align: center; margin-bottom: 30px; }
            .login-header h1 { color: #333; margin-bottom: 10px; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
            input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; }
            input:focus { outline: none; border-color: #667eea; }
            .btn { width: 100%; padding: 15px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; }
            .btn:hover { background: #5a6fd8; }
            .error { color: #e74c3c; margin: 15px 0; text-align: center; padding: 10px; background: #f8d7da; border-radius: 5px; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>🔐 Panel Administrativo</h1>
                <p>Curso IA para Emprendedores</p>
                <small style="color: #666;">UTM + FEIAAL</small>
            </div>

            <?php if (isset($error_login)): ?>
                <div class="error">❌ <?= htmlspecialchars($error_login) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Usuario:</label>
                    <input type="text" name="username" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label>Contraseña:</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit" name="login" class="btn">Acceder</button>
            </form>

            <div class="info">
                <strong>💡 Credenciales por defecto:</strong><br>
                Usuario: <code>admin</code><br>
                Contraseña: <code>feiaal2024admin</code><br>
                <small style="color: #856404;">⚠️ Cambia estas credenciales en la línea 11 del archivo admin.php</small>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ====== FUNCIONES DEL PANEL ======

// Obtener estadísticas
function getStatistics($db) {
    $stmt = $db->query("
        SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN estado = 'activa' THEN 1 END) as activas,
            COUNT(CASE WHEN tiene_emprendimiento = 'si' THEN 1 END) as emprendedores,
            COUNT(CASE WHEN perfil_participante = 'graduado_utm' THEN 1 END) as graduados_utm,
            COUNT(CASE WHEN DATE(fecha_inscripcion) = CURDATE() THEN 1 END) as hoy,
            COUNT(CASE WHEN fecha_inscripcion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as esta_semana,
            MIN(fecha_inscripcion) as primera_inscripcion,
            MAX(fecha_inscripcion) as ultima_inscripcion
        FROM inscripciones
    ");
    return $stmt->fetch();
}

// Obtener inscripciones con filtros
function getInscriptions($db, $filters = []) {
    $where = ['1=1'];
    $params = [];

    // Filtros
    if (!empty($filters['search'])) {
        $where[] = "(nombres LIKE ? OR apellidos LIKE ? OR correo LIKE ? OR ciudad_domicilio LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if (!empty($filters['perfil'])) {
        $where[] = "perfil_participante = ?";
        $params[] = $filters['perfil'];
    }

    if (!empty($filters['emprendimiento'])) {
        $where[] = "tiene_emprendimiento = ?";
        $params[] = $filters['emprendimiento'];
    }

    if (!empty($filters['facultad'])) {
        $where[] = "facultad_utm = ?";
        $params[] = $filters['facultad'];
    }

    if (!empty($filters['fecha_desde'])) {
        $where[] = "DATE(fecha_inscripcion) >= ?";
        $params[] = $filters['fecha_desde'];
    }

    if (!empty($filters['fecha_hasta'])) {
        $where[] = "DATE(fecha_inscripcion) <= ?";
        $params[] = $filters['fecha_hasta'];
    }

    $sql = "SELECT * FROM inscripciones WHERE " . implode(' AND ', $where) . " ORDER BY fecha_inscripcion DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

// Procesar acciones
$message = '';
$error = '';

try {
    $db = getDbConnection();

    // Procesar exportaciones
    if (isset($_GET['export'])) {
        $format = $_GET['export'];
        $filters = [
            'search' => $_GET['search'] ?? '',
            'perfil' => $_GET['perfil'] ?? '',
            'emprendimiento' => $_GET['emprendimiento'] ?? '',
            'facultad' => $_GET['facultad'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
        ];

        $inscriptions = getInscriptions($db, $filters);

        if ($format === 'csv') {
            exportCSV($inscriptions);
        } elseif ($format === 'excel') {
            exportExcel($inscriptions);
        }
    }

    // Obtener datos para mostrar
    $filters = [
        'search' => $_GET['search'] ?? '',
        'perfil' => $_GET['perfil'] ?? '',
        'emprendimiento' => $_GET['emprendimiento'] ?? '',
        'facultad' => $_GET['facultad'] ?? '',
        'fecha_desde' => $_GET['fecha_desde'] ?? '',
        'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
    ];

    $stats = getStatistics($db);
    $inscriptions = getInscriptions($db, $filters);

    // Obtener listas para filtros
    $perfiles = $db->query("SELECT DISTINCT perfil_participante FROM inscripciones ORDER BY perfil_participante")->fetchAll();
    $facultades = $db->query("SELECT DISTINCT facultad_utm FROM inscripciones WHERE facultad_utm IS NOT NULL ORDER BY facultad_utm")->fetchAll();

} catch (Exception $e) {
    $error = "Error de conexión: " . $e->getMessage();
}

// Funciones de exportación
function exportCSV($inscriptions) {
    $filename = 'inscripciones_curso_ia_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    $output = fopen('php://output', 'w');

    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Encabezados
    fputcsv($output, [
        'ID', 'Correo', 'Apellidos', 'Nombres', 'Ciudad', 'Perfil',
        'Facultad UTM', 'Tiene Emprendimiento', 'Descripción Emprendimiento',
        'Sitio Web', 'Acepta Términos', 'Fecha Inscripción', 'Estado', 'IP'
    ]);

    // Datos
    foreach ($inscriptions as $ins) {
        fputcsv($output, [
            $ins['id'],
            $ins['correo'],
            $ins['apellidos'],
            $ins['nombres'],
            $ins['ciudad_domicilio'],
            ucfirst(str_replace('_', ' ', $ins['perfil_participante'])),
            $ins['facultad_utm'] ?? '',
            $ins['tiene_emprendimiento'] === 'si' ? 'Sí' : 'No',
            $ins['descripcion_emprendimiento'] ?? '',
            $ins['sitio_web_fanpage'] ?? '',
            $ins['acepta_terminos'] ? 'Sí' : 'No',
            date('d/m/Y H:i', strtotime($ins['fecha_inscripcion'])),
            ucfirst($ins['estado']),
            $ins['ip_inscripcion'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

function exportExcel($inscriptions) {
    // Crear archivo Excel simple (HTML con formato Excel)
    $filename = 'inscripciones_curso_ia_' . date('Y-m-d_H-i-s') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    echo "\xEF\xBB\xBF"; // BOM para UTF-8

    echo "<table border='1'>\n";
    echo "<tr style='background-color: #667eea; color: white; font-weight: bold;'>\n";
    echo "<td>ID</td><td>Correo</td><td>Apellidos</td><td>Nombres</td><td>Ciudad</td>";
    echo "<td>Perfil</td><td>Facultad UTM</td><td>Tiene Emprendimiento</td>";
    echo "<td>Descripción Emprendimiento</td><td>Sitio Web</td><td>Fecha Inscripción</td><td>Estado</td>\n";
    echo "</tr>\n";

    foreach ($inscriptions as $ins) {
        echo "<tr>\n";
        echo "<td>" . htmlspecialchars($ins['id']) . "</td>";
        echo "<td>" . htmlspecialchars($ins['correo']) . "</td>";
        echo "<td>" . htmlspecialchars($ins['apellidos']) . "</td>";
        echo "<td>" . htmlspecialchars($ins['nombres']) . "</td>";
        echo "<td>" . htmlspecialchars($ins['ciudad_domicilio']) . "</td>";
        echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $ins['perfil_participante']))) . "</td>";
        echo "<td>" . htmlspecialchars($ins['facultad_utm'] ?? '') . "</td>";
        echo "<td>" . ($ins['tiene_emprendimiento'] === 'si' ? 'Sí' : 'No') . "</td>";
        echo "<td>" . htmlspecialchars($ins['descripcion_emprendimiento'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($ins['sitio_web_fanpage'] ?? '') . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($ins['fecha_inscripcion'])) . "</td>";
        echo "<td>" . htmlspecialchars(ucfirst($ins['estado'])) . "</td>";
        echo "</tr>\n";
    }

    echo "</table>\n";
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Curso IA para Emprendedores</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 1.8rem; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }

        /* Container */
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        .stat-label { color: #666; font-size: 0.9rem; }

        /* Filters */
        .filters {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .filters h3 { color: #333; margin-bottom: 20px; }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .filter-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn:hover { transform: translateY(-2px); }

        /* Export Section */
        .export-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .export-buttons { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .export-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            color: #1565c0;
            margin-left: auto;
            font-size: 0.9rem;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            position: sticky;
            top: 0;
        }
        tr:hover { background: #f8f9fa; }
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-warning { background: #fff3cd; color: #856404; }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 15px; text-align: center; }
            .filter-grid { grid-template-columns: 1fr; }
            .export-buttons { flex-direction: column; align-items: stretch; }
            .export-info { margin-left: 0; margin-top: 15px; }
            table { font-size: 0.9rem; }
            th, td { padding: 10px 8px; }
        }

        /* Loading */
        .loading { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; }
        .loading-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Generando archivo...</p>
        </div>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🛠️ Panel Administrativo</h1>
                <p style="opacity: 0.9; font-size: 0.9rem;">Curso: Inteligencia Artificial para Emprendedores</p>
            </div>
            <div class="user-info">
                <span>👤 <?= htmlspecialchars($_SESSION['admin_user']) ?></span>
                <span style="opacity: 0.8; font-size: 0.8rem;">
                    Sesión: <?= date('d/m/Y H:i', $_SESSION['login_time']) ?>
                </span>
                <a href="?logout=1" class="logout-btn">🚪 Salir</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php else: ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Inscripciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['activas'] ?></div>
                <div class="stat-label">Inscripciones Activas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['emprendedores'] ?></div>
                <div class="stat-label">Emprendedores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['graduados_utm'] ?></div>
                <div class="stat-label">Graduados UTM</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['hoy'] ?></div>
                <div class="stat-label">Inscripciones Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['esta_semana'] ?></div>
                <div class="stat-label">Esta Semana</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3>🔍 Filtros de Búsqueda</h3>
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Buscar:</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Nombre, correo, ciudad...">
                    </div>

                    <div class="filter-group">
                        <label>Perfil:</label>
                        <select name="perfil">
                            <option value="">Todos los perfiles</option>
                            <?php foreach ($perfiles as $perfil): ?>
                                <option value="<?= $perfil['perfil_participante'] ?>" <?= $filters['perfil'] === $perfil['perfil_participante'] ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $perfil['perfil_participante'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Emprendimiento:</label>
                        <select name="emprendimiento">
                            <option value="">Todos</option>
                            <option value="si" <?= $filters['emprendimiento'] === 'si' ? 'selected' : '' ?>>Sí</option>
                            <option value="no" <?= $filters['emprendimiento'] === 'no' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Facultad UTM:</label>
                        <select name="facultad">
                            <option value="">Todas las facultades</option>
                            <?php foreach ($facultades as $facultad): ?>
                                <option value="<?= $facultad['facultad_utm'] ?>" <?= $filters['facultad'] === $facultad['facultad_utm'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($facultad['facultad_utm']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Fecha Desde:</label>
                        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filters['fecha_desde']) ?>">
                    </div>

                    <div class="filter-group">
                        <label>Fecha Hasta:</label>
                        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filters['fecha_hasta']) ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    <a href="admin.php" class="btn btn-warning">🔄 Limpiar Filtros</a>
                </div>
            </form>
        </div>

        <!-- Export Section -->
        <div class="export-section">
            <h3 style="margin-bottom: 20px;">📥 Descargar Listados</h3>
            <div class="export-buttons">
                <a href="?export=csv<?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>"
                   class="btn btn-success" onclick="showLoading()">
                    📊 Descargar CSV
                </a>
                <a href="?export=excel<?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>"
                   class="btn btn-info" onclick="showLoading()">
                    📈 Descargar Excel
                </a>
                <div class="export-info">
                    📋 Se exportarán <strong><?= count($inscriptions) ?></strong> registros
                    <?php if (array_filter($filters)): ?>
                        con los filtros aplicados
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <h3>👥 Listado de Inscritos (<?= count($inscriptions) ?> registros)</h3>
                <div>
                    <?php if ($stats['primera_inscripcion']): ?>
                        <small style="opacity: 0.9;">
                            Primera: <?= date('d/m/Y', strtotime($stats['primera_inscripcion'])) ?> |
                            Última: <?= date('d/m/Y', strtotime($stats['ultima_inscripcion'])) ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($inscriptions)): ?>
                <div class="no-results">
                    <h4>😔 No se encontraron inscripciones</h4>
                    <p>Intenta ajustar los filtros de búsqueda o verifica que haya inscripciones en la base de datos.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>Correo</th>
                                <th>Ciudad</th>
                                <th>Perfil</th>
                                <th>Facultad UTM</th>
                                <th>Emprendimiento</th>
                                <th>Descripción</th>
                                <th>Sitio Web</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscriptions as $ins): ?>
                                <tr>
                                    <td><strong><?= $ins['id'] ?></strong></td>
                                    <td>
                                        <div style="font-weight: bold;">
                                            <?= htmlspecialchars($ins['nombres'] . ' ' . $ins['apellidos']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($ins['correo']) ?>"
                                           style="color: #667eea; text-decoration: none;">
                                            <?= htmlspecialchars($ins['correo']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($ins['ciudad_domicilio']) ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?= ucfirst(str_replace('_', ' ', $ins['perfil_participante'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ins['facultad_utm']): ?>
                                            <small><?= htmlspecialchars($ins['facultad_utm']) ?></small>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $ins['tiene_emprendimiento'] === 'si' ? 'badge-success' : 'badge-warning' ?>">
                                            <?= $ins['tiene_emprendimiento'] === 'si' ? 'Sí' : 'No' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ins['descripcion_emprendimiento']): ?>
                                            <small title="<?= htmlspecialchars($ins['descripcion_emprendimiento']) ?>">
                                                <?= htmlspecialchars(substr($ins['descripcion_emprendimiento'], 0, 50)) ?>...
                                            </small>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ins['sitio_web_fanpage']): ?>
                                            <a href="<?= htmlspecialchars($ins['sitio_web_fanpage']) ?>"
                                               target="_blank" style="color: #667eea;">🌐</a>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($ins['fecha_inscripcion'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?= ucfirst($ins['estado']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="margin-top: 30px; text-align: center; padding: 20px; background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 15px;">🔗 Enlaces Útiles</h3>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="emprendimiento.html" class="btn btn-primary">🏠 Ver Sitio Web</a>
                <a href="test_connection.php" class="btn btn-info">🔧 Test de Conexión</a>
                <a href="?refresh=1" class="btn btn-warning">🔄 Actualizar Datos</a>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 0.9rem;">
                <p><strong>📊 Última actualización:</strong> <?= date('d/m/Y H:i:s') ?></p>
                <p><strong>🌐 Sitio:</strong> https://feiaal.org/cursos/utm/</p>
                <p><strong>🏛️ Organizado por:</strong> Universidad Técnica de Manabí + FEIAAL</p>
            </div>
        </div>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'block';
            setTimeout(() => {
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 3000);
        }

        // Auto-refresh cada 5 minutos
        setTimeout(() => {
            if (confirm('¿Actualizar datos para ver nuevas inscripciones?')) {
                location.reload();
            }
        }, 300000);

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                document.querySelector('a[href*="export=csv"]').click();
            }
        });
    </script>
</body>
</html>