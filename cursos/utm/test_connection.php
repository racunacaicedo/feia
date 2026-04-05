<?php
/**
 * ARCHIVO DE PRUEBA DE CONEXIÓN
 * Sitio: https://feiaal.org/cursos/utm/
 * Archivo: test_connection.php
 * Verifica que todo esté funcionando correctamente
 */

require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Conexión - FEIAAL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; color: #333; }
        .test-item { margin: 20px 0; padding: 15px; border-radius: 8px; border-left: 5px solid #ddd; }
        .success { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Test de Conexión</h1>
            <h2>Curso IA para Emprendedores - FEIAAL</h2>
            <p><strong>Sitio:</strong> https://feiaal.org/cursos/utm/</p>
            <p><strong>Fecha de prueba:</strong> <?= date('d/m/Y H:i:s') ?></p>
        </div>

        <?php
        $tests = [];
        $allPassed = true;

        // Test 1: Conexión a base de datos
        try {
            $db = getDbConnection();
            $tests[] = [
                'name' => '🔗 Conexión a Base de Datos',
                'status' => 'success',
                'message' => 'Conexión establecida exitosamente',
                'details' => 'Host: ' . DB_HOST . ' | DB: ' . DB_NAME . ' | Usuario: ' . DB_USER
            ];
        } catch (Exception $e) {
            $allPassed = false;
            $tests[] = [
                'name' => '🔗 Conexión a Base de Datos',
                'status' => 'error',
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'details' => 'Verifica los datos en config.php'
            ];
        }

        // Test 2: Verificar tablas
        if (isset($db)) {
            try {
                $tables = ['inscripciones', 'facultades_utm', 'configuracion_curso'];
                $missingTables = [];

                foreach ($tables as $table) {
                    $stmt = $db->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$table]);
                    if ($stmt->rowCount() == 0) {
                        $missingTables[] = $table;
                    }
                }

                if (empty($missingTables)) {
                    $tests[] = [
                        'name' => '📊 Estructura de Tablas',
                        'status' => 'success',
                        'message' => 'Todas las tablas necesarias existen',
                        'details' => 'Tablas verificadas: ' . implode(', ', $tables)
                    ];
                } else {
                    $allPassed = false;
                    $tests[] = [
                        'name' => '📊 Estructura de Tablas',
                        'status' => 'error',
                        'message' => 'Faltan tablas: ' . implode(', ', $missingTables),
                        'details' => 'Ejecuta el script SQL completo en phpMyAdmin'
                    ];
                }
            } catch (Exception $e) {
                $allPassed = false;
                $tests[] = [
                    'name' => '📊 Estructura de Tablas',
                    'status' => 'error',
                    'message' => 'Error verificando tablas: ' . $e->getMessage(),
                    'details' => 'Verifica permisos del usuario de BD'
                ];
            }
        }

        // Test 3: Verificar archivos necesarios
        $requiredFiles = ['config.php', 'save_inscription.php'];
        $missingFiles = [];

        foreach ($requiredFiles as $file) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                $missingFiles[] = $file;
            }
        }

        if (empty($missingFiles)) {
            $tests[] = [
                'name' => '📄 Archivos del Sistema',
                'status' => 'success',
                'message' => 'Todos los archivos necesarios están presentes',
                'details' => 'Archivos verificados: ' . implode(', ', $requiredFiles)
            ];
        } else {
            $allPassed = false;
            $tests[] = [
                'name' => '📄 Archivos del Sistema',
                'status' => 'error',
                'message' => 'Faltan archivos: ' . implode(', ', $missingFiles),
                'details' => 'Sube todos los archivos PHP a la carpeta'
            ];
        }

        // Test 4: Verificar permisos de escritura
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            if (mkdir($logDir, 0755, true)) {
                $tests[] = [
                    'name' => '📁 Permisos de Escritura',
                    'status' => 'success',
                    'message' => 'Directorio logs creado exitosamente',
                    'details' => 'El sistema puede escribir logs de errores'
                ];
            } else {
                $tests[] = [
                    'name' => '📁 Permisos de Escritura',
                    'status' => 'error',
                    'message' => 'No se pudo crear directorio logs',
                    'details' => 'Algunos hosting no permiten crear directorios'
                ];
            }
        } else {
            $tests[] = [
                'name' => '📁 Permisos de Escritura',
                'status' => 'success',
                'message' => 'Directorio logs existe',
                'details' => 'El sistema puede escribir logs de errores'
            ];
        }
        ?>

        <!-- Mostrar resultados de las pruebas -->
        <?php foreach ($tests as $test): ?>
            <div class="test-item <?= $test['status'] ?>">
                <h3><?= $test['name'] ?></h3>
                <p><strong><?= $test['message'] ?></strong></p>
                <small><?= $test['details'] ?></small>
            </div>
        <?php endforeach; ?>

        <!-- Resumen general -->
        <div class="test-item <?= $allPassed ? 'success' : 'error' ?>">
            <h2>📋 Resumen General</h2>
            <?php if ($allPassed): ?>
                <p><strong>✅ ¡Sistema funcionando correctamente!</strong></p>
                <p>La conexión a la base de datos está activa y el sistema está listo para recibir inscripciones.</p>
            <?php else: ?>
                <p><strong>❌ Se encontraron problemas</strong></p>
                <p>Revisa los errores mostrados arriba y corrige los problemas antes de usar el sistema.</p>
            <?php endif; ?>
        </div>

        <?php if (isset($db) && $allPassed): ?>
        <!-- Estadísticas de la base de datos -->
        <div class="test-item info">
            <h3>📊 Estadísticas Actuales</h3>
            <?php
            try {
                // Estadísticas generales
                $statsStmt = $db->query("
                    SELECT
                        COUNT(*) as total_inscripciones,
                        COUNT(CASE WHEN estado = 'activa' THEN 1 END) as activas,
                        COUNT(CASE WHEN tiene_emprendimiento = 'si' THEN 1 END) as emprendedores,
                        COUNT(CASE WHEN perfil_participante = 'graduado_utm' THEN 1 END) as graduados_utm
                    FROM inscripciones
                ");
                $stats = $statsStmt->fetch();
                ?>

                <table>
                    <tr>
                        <th>Métrica</th>
                        <th>Valor</th>
                    </tr>
                    <tr>
                        <td>Total Inscripciones</td>
                        <td><strong><?= $stats['total_inscripciones'] ?></strong></td>
                    </tr>
                    <tr>
                        <td>Inscripciones Activas</td>
                        <td><strong><?= $stats['activas'] ?></strong></td>
                    </tr>
                    <tr>
                        <td>Emprendedores</td>
                        <td><strong><?= $stats['emprendedores'] ?></strong></td>
                    </tr>
                    <tr>
                        <td>Graduados UTM</td>
                        <td><strong><?= $stats['graduados_utm'] ?></strong></td>
                    </tr>
                </table>

            <?php
            } catch (Exception $e) {
                echo "<p>Error obteniendo estadísticas: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

        <!-- Configuración del sistema -->
        <div class="test-item info">
            <h3>⚙️ Configuración del Sistema</h3>
            <?php
            try {
                $configStmt = $db->query("SELECT clave, valor FROM configuracion_curso ORDER BY clave");
                $configs = $configStmt->fetchAll();
            ?>
                <table>
                    <tr>
                        <th>Configuración</th>
                        <th>Valor</th>
                    </tr>
                    <?php foreach ($configs as $config): ?>
                    <tr>
                        <td><?= htmlspecialchars($config['clave']) ?></td>
                        <td><?= htmlspecialchars($config['valor']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php
            } catch (Exception $e) {
                echo "<p>Error obteniendo configuración: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>
        <?php endif; ?>

        <!-- Enlaces útiles -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="emprendimiento.html" class="btn">🏠 Volver al Sitio</a>
            <?php if (file_exists('admin.php')): ?>
                <a href="admin.php" class="btn">🛠️ Panel Admin</a>
            <?php endif; ?>
        </div>

        <!-- Información técnica -->
        <div class="test-item info">
            <h3>ℹ️ Información Técnica</h3>
            <table>
                <tr>
                    <th>Configuración</th>
                    <th>Valor</th>
                </tr>
                <tr>
                    <td>Versión PHP</td>
                    <td><?= PHP_VERSION ?></td>
                </tr>
                <tr>
                    <td>Servidor</td>
                    <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'No disponible' ?></td>
                </tr>
                <tr>
                    <td>Directorio</td>
                    <td><?= __DIR__ ?></td>
                </tr>
                <tr>
                    <td>URL Base</td>
                    <td><?= SITE_URL ?></td>
                </tr>
                <tr>
                    <td>Modo Debug</td>
                    <td><?= DEBUG_MODE ? '🟡 Activado' : '🟢 Desactivado' ?></td>
                </tr>
            </table>
        </div>

        <!-- Próximos pasos -->
        <div class="test-item info">
            <h3>📋 Próximos Pasos</h3>
            <ol>
                <li><strong>Modificar el HTML:</strong> Cambiar la acción del formulario para enviar a save_inscription.php</li>
                <li><strong>Probar inscripción:</strong> Llenar el formulario en emprendimiento.html</li>
                <li><strong>Verificar datos:</strong> Comprobar que se guarden en la base de datos</li>
                <li><strong>Configurar emails:</strong> Opcional para notificaciones automáticas</li>
                <li><strong>Panel admin:</strong> Subir admin.php para gestionar inscripciones</li>
            </ol>
        </div>
    </div>
</body>
</html>