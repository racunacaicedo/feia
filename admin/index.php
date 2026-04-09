<?php
/**
 * admin/index.php — Panel de administración de noticias FEIAAL
 * Contraseña: feiaal2025
 */

session_start();

define('ADMIN_PASSWORD', 'feiaal2025');
define('DATA_FILE', __DIR__ . '/../data/noticias.json');

/* ── Helpers ───────────────────────────────────────────────── */
function leerNoticias(): array {
    if (!file_exists(DATA_FILE)) return [];
    $data = json_decode(file_get_contents(DATA_FILE), true);
    return is_array($data) ? $data : [];
}

function guardarNoticias(array $data): void {
    usort($data, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    file_put_contents(DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function nextId(array $data): int {
    if (empty($data)) return 1;
    return max(array_column($data, 'id')) + 1;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$msg = '';
$msgType = 'ok';
$editItem = null;

/* ── Acciones POST ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Login
    if ($action === 'login') {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_feiaal'] = true;
        } else {
            $msg = '❌ Contraseña incorrecta.';
            $msgType = 'error';
        }
    }

    // Logout
    if ($action === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if (isset($_SESSION['admin_feiaal'])) {

        // Agregar noticia
        if ($action === 'add') {
            $noticias = leerNoticias();
            $nueva = [
                'id'       => nextId($noticias),
                'titulo'   => trim($_POST['titulo']   ?? ''),
                'resumen'  => trim($_POST['resumen']  ?? ''),
                'imagen'   => trim($_POST['imagen']   ?? ''),
                'fecha'    => trim($_POST['fecha']    ?? date('Y-m-d')),
                'categoria'=> trim($_POST['categoria']?? 'IA General'),
                'url'      => trim($_POST['url']      ?? '#'),
                'autor'    => trim($_POST['autor']    ?? 'FEIAAL'),
            ];
            if ($nueva['titulo'] === '') {
                $msg = '⚠️ El título es obligatorio.';
                $msgType = 'error';
            } else {
                $noticias[] = $nueva;
                guardarNoticias($noticias);
                $msg = '✅ Noticia publicada correctamente.';
            }
        }

        // Eliminar noticia
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $noticias = leerNoticias();
            $noticias = array_values(array_filter($noticias, fn($n) => $n['id'] !== $id));
            guardarNoticias($noticias);
            $msg = '🗑️ Noticia eliminada.';
        }

        // Guardar edición
        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $noticias = leerNoticias();
            foreach ($noticias as &$n) {
                if ($n['id'] === $id) {
                    $n['titulo']    = trim($_POST['titulo']    ?? $n['titulo']);
                    $n['resumen']   = trim($_POST['resumen']   ?? $n['resumen']);
                    $n['imagen']    = trim($_POST['imagen']    ?? $n['imagen']);
                    $n['fecha']     = trim($_POST['fecha']     ?? $n['fecha']);
                    $n['categoria'] = trim($_POST['categoria'] ?? $n['categoria']);
                    $n['url']       = trim($_POST['url']       ?? $n['url']);
                    $n['autor']     = trim($_POST['autor']     ?? $n['autor']);
                }
            }
            guardarNoticias($noticias);
            $msg = '✅ Noticia actualizada.';
        }
    }
}

// Cargar ítem a editar (GET)
if (isset($_SESSION['admin_feiaal']) && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $noticias = leerNoticias();
    foreach ($noticias as $n) {
        if ($n['id'] === $editId) { $editItem = $n; break; }
    }
}

$noticias = leerNoticias();
$loggedIn = isset($_SESSION['admin_feiaal']);

$categorias = ['IA General','Modelos de IA','Educación','Negocios','Investigación','América Latina','Tecnología','Innovación'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Noticias — FEIAAL</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
  body { font-family: 'Segoe UI', sans-serif; background:#0f0f1a; color:#e0e0e0; min-height:100vh; }

  /* ── Topbar ── */
  .topbar {
    background: linear-gradient(135deg,#4a0014,#c2185b);
    padding: 1rem 2rem;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 2px 12px rgba(0,0,0,0.4);
  }
  .topbar .brand { display:flex; align-items:center; gap:0.75rem; }
  .topbar .brand img { height:42px; }
  .topbar .brand span { font-size:1.1rem; font-weight:700; color:#fff; }
  .topbar .brand small { color:rgba(255,255,255,0.7); font-size:0.78rem; display:block; }
  .btn-logout { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3);
    color:#fff; padding:7px 18px; border-radius:999px; cursor:pointer; font-size:0.85rem;
    transition: background 0.2s; }
  .btn-logout:hover { background:rgba(255,255,255,0.25); }

  /* ── Layout ── */
  .wrap { max-width:1100px; margin:0 auto; padding:2rem 1.5rem; }

  /* ── Mensaje ── */
  .msg { padding:0.85rem 1.2rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; font-weight:600; }
  .msg.ok    { background:#1a3a1a; border:1px solid #2e7d32; color:#81c784; }
  .msg.error { background:#3a1a1a; border:1px solid #c62828; color:#ef9a9a; }

  /* ── Login ── */
  .login-box { max-width:400px; margin:5rem auto; background:#1a1a2e; border-radius:16px;
    padding:2.5rem; box-shadow:0 8px 32px rgba(0,0,0,0.4); text-align:center; }
  .login-box h2 { margin-bottom:1.5rem; color:#fff; font-size:1.5rem; }
  .login-box input { width:100%; padding:10px 14px; border-radius:8px; border:1px solid #333;
    background:#0f0f1a; color:#fff; font-size:1rem; margin-bottom:1rem; }
  .login-box button { width:100%; padding:11px; border-radius:999px; border:none;
    background:linear-gradient(135deg,#c2185b,#ff9100); color:#fff; font-weight:700;
    font-size:1rem; cursor:pointer; transition:opacity 0.2s; }
  .login-box button:hover { opacity:0.88; }

  /* ── Card ── */
  .card { background:#1a1a2e; border-radius:14px; padding:1.8rem; margin-bottom:2rem;
    box-shadow:0 4px 18px rgba(0,0,0,0.25); }
  .card h2 { font-size:1.15rem; color:#fff; margin-bottom:1.4rem; display:flex; align-items:center; gap:0.5rem; }
  .card h2 i { color:#c2185b; }

  /* ── Formulario ── */
  .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
  .form-grid .full { grid-column:1/-1; }
  label { display:block; font-size:0.78rem; color:rgba(255,255,255,0.6); margin-bottom:4px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; }
  input[type=text], input[type=date], input[type=url], textarea, select {
    width:100%; padding:9px 12px; border-radius:8px; border:1px solid #2a2a4a;
    background:#0f0f1a; color:#fff; font-size:0.9rem; transition:border-color 0.2s; }
  input:focus, textarea:focus, select:focus { outline:none; border-color:#c2185b; }
  textarea { min-height:90px; resize:vertical; font-family:inherit; line-height:1.5; }
  select option { background:#1a1a2e; }

  .btn-primary { background:linear-gradient(135deg,#c2185b,#ff9100); color:#fff; border:none;
    padding:9px 24px; border-radius:999px; font-weight:700; font-size:0.88rem; cursor:pointer;
    transition:opacity 0.2s, transform 0.2s; margin-top:1rem; }
  .btn-primary:hover { opacity:0.88; transform:translateY(-1px); }
  .btn-cancel { background:#2a2a4a; color:#aaa; border:none; padding:9px 20px;
    border-radius:999px; font-weight:600; font-size:0.88rem; cursor:pointer; margin-top:1rem; margin-left:0.5rem; }
  .btn-cancel:hover { background:#333; color:#fff; }

  /* ── Tabla de noticias ── */
  .news-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
  .news-table th { background:#0f0f1a; color:rgba(255,255,255,0.5); text-align:left;
    padding:10px 14px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; }
  .news-table td { padding:12px 14px; border-top:1px solid rgba(255,255,255,0.06); vertical-align:middle; }
  .news-table tr:hover td { background:rgba(255,255,255,0.03); }
  .news-table .news-title { font-weight:600; color:#fff; max-width:300px; }
  .news-table .news-title small { display:block; color:rgba(255,255,255,0.45); font-weight:400; font-size:0.78rem; margin-top:2px; }

  .badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:0.72rem; font-weight:700; }
  .badge-ia    { background:rgba(33,150,243,0.2); color:#64b5f6; }
  .badge-mod   { background:rgba(156,39,176,0.2); color:#ce93d8; }
  .badge-edu   { background:rgba(76,175,80,0.2);  color:#a5d6a7; }
  .badge-neg   { background:rgba(255,152,0,0.2);  color:#ffcc02; }
  .badge-inv   { background:rgba(0,188,212,0.2);  color:#80deea; }
  .badge-lat   { background:rgba(194,24,91,0.2);  color:#f48fb1; }
  .badge-tec   { background:rgba(255,87,34,0.2);  color:#ffab91; }
  .badge-inn   { background:rgba(105,240,174,0.2);color:#69f0ae; }

  .btn-edit { background:#1565c0; color:#fff; border:none; padding:5px 14px;
    border-radius:999px; font-size:0.75rem; cursor:pointer; transition:background 0.2s; }
  .btn-edit:hover { background:#1976d2; }
  .btn-del  { background:#b71c1c; color:#fff; border:none; padding:5px 14px;
    border-radius:999px; font-size:0.75rem; cursor:pointer; transition:background 0.2s; margin-left:4px; }
  .btn-del:hover { background:#c62828; }

  .empty-state { text-align:center; padding:3rem; color:rgba(255,255,255,0.3); font-size:0.9rem; }
  .empty-state i { font-size:3rem; display:block; margin-bottom:1rem; }

  .counter { background:#c2185b; color:#fff; font-size:0.72rem; font-weight:700;
    padding:2px 8px; border-radius:999px; margin-left:8px; }

  @media(max-width:700px) {
    .form-grid { grid-template-columns:1fr; }
    .news-table th:nth-child(3), .news-table td:nth-child(3),
    .news-table th:nth-child(4), .news-table td:nth-child(4) { display:none; }
    .topbar { padding:0.8rem 1rem; }
  }
</style>
</head>
<body>

<!-- ── Topbar ── -->
<div class="topbar">
  <div class="brand">
    <img src="../imagenes/feiaal.png" alt="FEIAAL" onerror="this.style.display='none'">
    <div>
      <span>FEIAAL Admin</span>
      <small>Panel de noticias</small>
    </div>
  </div>
  <?php if ($loggedIn): ?>
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="logout">
    <button class="btn-logout"><i class="fas fa-sign-out-alt"></i> Salir</button>
  </form>
  <?php endif; ?>
</div>

<div class="wrap">

<?php if ($msg): ?>
  <div class="msg <?= $msgType ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- ════════════════ LOGIN ════════════════ -->
<?php if (!$loggedIn): ?>
<div class="login-box">
  <h2><i class="fas fa-lock" style="color:#c2185b;margin-right:8px"></i>Acceso Admin</h2>
  <form method="POST">
    <input type="hidden" name="action" value="login">
    <input type="password" name="password" placeholder="Contraseña" autofocus required>
    <button type="submit">Ingresar</button>
  </form>
</div>

<!-- ════════════════ PANEL ════════════════ -->
<?php else: ?>

<!-- ── Formulario agregar / editar ── -->
<div class="card">
  <?php if ($editItem): ?>
    <h2><i class="fas fa-edit"></i> Editar noticia</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
  <?php else: ?>
    <h2><i class="fas fa-plus-circle"></i> Publicar nueva noticia</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
  <?php endif; ?>

    <div class="form-grid">
      <div class="full">
        <label>Título *</label>
        <input type="text" name="titulo" required
          value="<?= h($editItem['titulo'] ?? '') ?>"
          placeholder="Ej: ChatGPT-5 llega con capacidades multimodales avanzadas">
      </div>
      <div class="full">
        <label>Resumen / Extracto</label>
        <textarea name="resumen" placeholder="Breve descripción que aparecerá en la tarjeta..."><?= h($editItem['resumen'] ?? '') ?></textarea>
      </div>
      <div>
        <label>URL del artículo o enlace externo</label>
        <input type="text" name="url"
          value="<?= h($editItem['url'] ?? '') ?>"
          placeholder="articulos/articulo3.html  o  https://...">
      </div>
      <div>
        <label>Imagen (ruta local o URL)</label>
        <input type="text" name="imagen"
          value="<?= h($editItem['imagen'] ?? '') ?>"
          placeholder="articulos/imagenes/foto.jpg  o  https://...">
      </div>
      <div>
        <label>Categoría</label>
        <select name="categoria">
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= h($cat) ?>" <?= ($editItem['categoria'] ?? '') === $cat ? 'selected' : '' ?>>
              <?= h($cat) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Fecha de publicación</label>
        <input type="date" name="fecha"
          value="<?= h($editItem['fecha'] ?? date('Y-m-d')) ?>">
      </div>
      <div>
        <label>Autor</label>
        <input type="text" name="autor"
          value="<?= h($editItem['autor'] ?? 'FEIAAL') ?>"
          placeholder="FEIAAL">
      </div>
    </div>

    <button type="submit" class="btn-primary">
      <i class="fas <?= $editItem ? 'fa-save' : 'fa-paper-plane' ?>"></i>
      <?= $editItem ? ' Guardar cambios' : ' Publicar noticia' ?>
    </button>
    <?php if ($editItem): ?>
      <a href="index.php"><button type="button" class="btn-cancel">Cancelar</button></a>
    <?php endif; ?>
  </form>
</div>

<!-- ── Lista de noticias ── -->
<div class="card">
  <h2>
    <i class="fas fa-newspaper"></i> Noticias publicadas
    <span class="counter"><?= count($noticias) ?></span>
  </h2>

  <?php if (empty($noticias)): ?>
    <div class="empty-state">
      <i class="fas fa-inbox"></i>
      No hay noticias publicadas aún. ¡Agrega la primera!
    </div>
  <?php else: ?>
  <?php
    // Ordenar por fecha desc para mostrar
    usort($noticias, fn($a,$b) => strcmp($b['fecha'], $a['fecha']));
  ?>
  <table class="news-table">
    <thead>
      <tr>
        <th>Noticia</th>
        <th>Categoría</th>
        <th>Autor</th>
        <th>Fecha</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($noticias as $n): ?>
      <?php
        $badgeMap = [
          'IA General'   => 'badge-ia',
          'Modelos de IA'=> 'badge-mod',
          'Educación'    => 'badge-edu',
          'Negocios'     => 'badge-neg',
          'Investigación'=> 'badge-inv',
          'América Latina'=> 'badge-lat',
          'Tecnología'   => 'badge-tec',
          'Innovación'   => 'badge-inn',
        ];
        $badgeClass = $badgeMap[$n['categoria']] ?? 'badge-ia';
        $fechaFmt = date('d/m/Y', strtotime($n['fecha']));
      ?>
      <tr>
        <td class="news-title">
          <?= h($n['titulo']) ?>
          <small><?= h(mb_strimwidth($n['resumen'], 0, 80, '...')) ?></small>
        </td>
        <td><span class="badge <?= $badgeClass ?>"><?= h($n['categoria']) ?></span></td>
        <td><?= h($n['autor']) ?></td>
        <td><?= $fechaFmt ?></td>
        <td>
          <a href="index.php?edit=<?= $n['id'] ?>">
            <button class="btn-edit"><i class="fas fa-pencil"></i> Editar</button>
          </a>
          <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta noticia?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $n['id'] ?>">
            <button type="submit" class="btn-del"><i class="fas fa-trash"></i> Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php endif; ?>
</div><!-- /wrap -->
</body>
</html>
