<?php
// Acceso básico con contraseña
$clave = 'feiaal2025';
session_start();
if (isset($_POST['clave'])) {
    if ($_POST['clave'] === $clave) $_SESSION['admin_curso'] = true;
    else $error = 'Clave incorrecta.';
}
if (isset($_GET['salir'])) { session_destroy(); header('Location: curso-admin.php'); exit; }

if (!isset($_SESSION['admin_curso'])): ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Curso IA</title>
<style>
    body { font-family: Arial, sans-serif; background: #f0f4ff; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .box { background: #fff; border-radius: 12px; padding: 2.5rem 2rem; width: 100%; max-width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,0.12); text-align: center; }
    .box h2 { color: #1a3a8f; margin-bottom: 1.5rem; }
    input[type=password] { width: 100%; padding: 0.7rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 1rem; margin-bottom: 1rem; box-sizing: border-box; }
    button { width: 100%; padding: 0.75rem; background: #1a3a8f; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; }
    .error { color: #dc2626; margin-bottom: 0.8rem; font-size: 0.9rem; }
</style>
</head>
<body>
<div class="box">
    <h2>🔒 Admin — Curso IA</h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
        <input type="password" name="clave" placeholder="Contraseña de administrador" required autofocus>
        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>
<?php exit; endif;

require_once dirname(__DIR__) . '/php/db.php';

// Filtro de fechas
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Totales
$totEst    = $pdo->query("SELECT COUNT(*) FROM curso_estudiantes")->fetchColumn();
$totAcceso = $pdo->query("SELECT COUNT(*) FROM curso_accesos")->fetchColumn();
$hoy       = $pdo->query("SELECT COUNT(*) FROM curso_accesos WHERE DATE(fecha) = CURDATE()")->fetchColumn();

// Estudiantes
$estudiantes = $pdo->query("SELECT id, nombre, email, fecha_registro, activo FROM curso_estudiantes ORDER BY fecha_registro DESC")->fetchAll(PDO::FETCH_ASSOC);

// Accesos filtrados
$stmt = $pdo->prepare("SELECT a.id, e.nombre, a.email, a.ip, a.accion, a.detalle, a.fecha
    FROM curso_accesos a
    LEFT JOIN curso_estudiantes e ON e.id = a.estudiante_id
    WHERE DATE(a.fecha) BETWEEN ? AND ?
    ORDER BY a.fecha DESC LIMIT 500");
$stmt->execute([$desde, $hasta]);
$accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Actividad por día (últimos 14 días)
$grafico = $pdo->query("SELECT DATE(fecha) as dia, COUNT(*) as total FROM curso_accesos WHERE fecha >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY dia ORDER BY dia")->fetchAll(PDO::FETCH_ASSOC);

// Toggle activo
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $pdo->prepare("UPDATE curso_estudiantes SET activo = 1 - activo WHERE id = ?")->execute([$id]);
    header('Location: curso-admin.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Curso IA · FEIAAL</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #f0f4ff; color: #333; }

    .topbar { background: linear-gradient(135deg, #0d1b4b, #1a3a8f); color: #fff; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
    .topbar h1 { font-size: 1.15rem; }
    .topbar a { color: #93c5fd; font-size: 0.85rem; text-decoration: none; }
    .topbar a:hover { text-decoration: underline; }

    .main { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem; }

    /* Tarjetas de resumen */
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: #fff; border-radius: 12px; padding: 1.2rem 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.07); border-left: 4px solid #2563eb; }
    .stat-card .num { font-size: 2rem; font-weight: 800; color: #1a3a8f; }
    .stat-card .lbl { font-size: 0.82rem; color: #6b7280; margin-top: 2px; }
    .stat-card.verde { border-color: #16a34a; } .stat-card.verde .num { color: #16a34a; }
    .stat-card.naranja { border-color: #f59e0b; } .stat-card.naranja .num { color: #d97706; }

    /* Gráfico de barras simple */
    .chart-wrap { background: #fff; border-radius: 12px; padding: 1.2rem 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
    .chart-wrap h3 { font-size: 0.95rem; color: #1a3a8f; margin-bottom: 1rem; }
    .bar-chart { display: flex; align-items: flex-end; gap: 6px; height: 80px; }
    .bar-col { display: flex; flex-direction: column; align-items: center; flex: 1; }
    .bar { background: #2563eb; border-radius: 4px 4px 0 0; width: 100%; min-height: 2px; transition: opacity 0.2s; }
    .bar:hover { opacity: 0.75; }
    .bar-lbl { font-size: 0.6rem; color: #9ca3af; margin-top: 3px; writing-mode: vertical-lr; transform: rotate(180deg); }

    /* Sección */
    .section { background: #fff; border-radius: 12px; padding: 1.2rem 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
    .section h3 { font-size: 1rem; color: #1a3a8f; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

    /* Tabla */
    .tbl-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    th { background: #f0f4ff; color: #1a3a8f; font-weight: 700; padding: 0.6rem 0.8rem; text-align: left; white-space: nowrap; }
    td { padding: 0.55rem 0.8rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #f9fafb; }

    .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    .badge.activo   { background: #dcfce7; color: #16a34a; }
    .badge.inactivo { background: #fee2e2; color: #dc2626; }
    .badge.login    { background: #eff6ff; color: #2563eb; }
    .badge.registro { background: #f0fdf4; color: #16a34a; }
    .badge.vista    { background: #faf5ff; color: #7c3aed; }
    .badge.fallido  { background: #fef2f2; color: #dc2626; }

    .btn-sm { padding: 3px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: none; text-decoration: none; }
    .btn-sm.des { background: #fee2e2; color: #dc2626; }
    .btn-sm.act { background: #dcfce7; color: #16a34a; }

    /* Filtro */
    .filter-row { display: flex; flex-wrap: wrap; gap: 0.7rem; align-items: center; margin-bottom: 1rem; }
    .filter-row label { font-size: 0.85rem; font-weight: 600; color: #374151; }
    .filter-row input { padding: 0.4rem 0.7rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; }
    .filter-row button { padding: 0.4rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 700; cursor: pointer; }

    @media (max-width: 600px) { .bar-lbl { font-size: 0.5rem; } }
</style>
</head>
<body>

<div class="topbar">
    <h1><i class="fas fa-chart-bar"></i> &nbsp;Admin — Curso de Inteligencia Artificial · FEIAAL</h1>
    <a href="?salir=1"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
</div>

<div class="main">

    <!-- Resumen -->
    <div class="stats">
        <div class="stat-card">
            <div class="num"><?= $totEst ?></div>
            <div class="lbl"><i class="fas fa-users"></i> Estudiantes registrados</div>
        </div>
        <div class="stat-card verde">
            <div class="num"><?= $totAcceso ?></div>
            <div class="lbl"><i class="fas fa-door-open"></i> Total de accesos</div>
        </div>
        <div class="stat-card naranja">
            <div class="num"><?= $hoy ?></div>
            <div class="lbl"><i class="fas fa-calendar-day"></i> Accesos hoy</div>
        </div>
    </div>

    <!-- Gráfico últimos 14 días -->
    <?php if (!empty($grafico)):
        $maxVal = max(array_column($grafico, 'total')) ?: 1;
    ?>
    <div class="chart-wrap">
        <h3><i class="fas fa-chart-column"></i> Actividad — últimos 14 días</h3>
        <div class="bar-chart">
        <?php foreach ($grafico as $g):
            $pct = round(($g['total'] / $maxVal) * 100);
        ?>
            <div class="bar-col">
                <div class="bar" style="height:<?= $pct ?>%" title="<?= $g['dia'] ?>: <?= $g['total'] ?> accesos"></div>
                <div class="bar-lbl"><?= substr($g['dia'], 5) ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Estudiantes -->
    <div class="section">
        <h3><i class="fas fa-user-graduate"></i> Estudiantes registrados (<?= $totEst ?>)</h3>
        <div class="tbl-wrap">
            <table>
                <thead><tr>
                    <th>#</th><th>Nombre</th><th>Correo</th><th>Registro</th><th>Estado</th><th>Acción</th>
                </tr></thead>
                <tbody>
                <?php foreach ($estudiantes as $e): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td><?= htmlspecialchars($e['nombre']) ?></td>
                    <td><?= htmlspecialchars($e['email']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($e['fecha_registro'])) ?></td>
                    <td><span class="badge <?= $e['activo'] ? 'activo' : 'inactivo' ?>"><?= $e['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td>
                        <a href="?toggle=<?= $e['id'] ?>" class="btn-sm <?= $e['activo'] ? 'des' : 'act' ?>"
                           onclick="return confirm('¿Cambiar estado de este estudiante?')">
                            <?= $e['activo'] ? 'Desactivar' : 'Activar' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($estudiantes)): ?>
                <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:2rem;">Aún no hay estudiantes registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Accesos -->
    <div class="section">
        <h3><i class="fas fa-door-open"></i> Registro de accesos</h3>
        <form method="GET" class="filter-row">
            <label>Desde:</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>">
            <label>Hasta:</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
            <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
        </form>
        <div class="tbl-wrap">
            <table>
                <thead><tr>
                    <th>Fecha</th><th>Estudiante</th><th>Correo</th><th>Acción</th><th>Detalle</th><th>IP</th>
                </tr></thead>
                <tbody>
                <?php foreach ($accesos as $a):
                    $accionClass = match(true) {
                        str_contains($a['accion'], 'registro')  => 'registro',
                        str_contains($a['accion'], 'fallido')   => 'fallido',
                        str_contains($a['accion'], 'login')     => 'login',
                        str_contains($a['accion'], 'vista')     => 'vista',
                        default => 'login'
                    };
                ?>
                <tr>
                    <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></td>
                    <td><?= htmlspecialchars($a['nombre'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['email'] ?? '—') ?></td>
                    <td><span class="badge <?= $accionClass ?>"><?= htmlspecialchars($a['accion']) ?></span></td>
                    <td><?= htmlspecialchars($a['detalle'] ?? '—') ?></td>
                    <td style="color:#9ca3af;font-size:0.78rem"><?= htmlspecialchars($a['ip']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($accesos)): ?>
                <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:2rem;">No hay accesos en este rango de fechas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
