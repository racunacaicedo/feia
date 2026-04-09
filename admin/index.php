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

        // Crear artículo completo (genera HTML + registra en JSON)
        if ($action === 'crear_articulo') {
            $titulo    = trim($_POST['art_titulo']    ?? '');
            $categoria = trim($_POST['art_categoria'] ?? 'IA General');
            $autor     = trim($_POST['art_autor']     ?? 'FEIAAL');
            $fecha     = trim($_POST['art_fecha']     ?? date('Y-m-d'));
            $lectura   = trim($_POST['art_lectura']   ?? '5');
            $resumen   = trim($_POST['art_resumen']   ?? '');
            $imagen    = trim($_POST['art_imagen']    ?? '');
            $intro     = trim($_POST['art_intro']     ?? '');
            $cita      = trim($_POST['art_cita']      ?? '');
            $conclusion= trim($_POST['art_conclusion']?? '');
            $slug      = trim($_POST['art_slug']      ?? '');

            // Secciones dinámicas
            $sec_titulos   = $_POST['sec_titulo']   ?? [];
            $sec_contenidos= $_POST['sec_contenido'] ?? [];

            if ($titulo === '') {
                $msg = '⚠️ El título del artículo es obligatorio.';
                $msgType = 'error';
            } elseif ($slug === '') {
                $msg = '⚠️ El nombre de archivo (slug) es obligatorio.';
                $msgType = 'error';
            } else {
                // Sanitizar slug
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
                $slug = preg_replace('/-+/', '-', $slug);
                $filename = __DIR__ . '/../articulos/' . $slug . '.html';

                // Meses en español
                $meses = ['','enero','febrero','marzo','abril','mayo','junio',
                          'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                $fechaObj = date_create($fecha);
                $fechaFmt = $fechaObj
                    ? intval(date_format($fechaObj,'d')).' de '.$meses[intval(date_format($fechaObj,'m'))].' de '.date_format($fechaObj,'Y')
                    : $fecha;

                // Mapa de íconos por categoría
                $iconoCat = [
                    'IA General'   => 'fa-robot',
                    'Modelos de IA'=> 'fa-brain',
                    'Educación'    => 'fa-graduation-cap',
                    'Negocios'     => 'fa-briefcase',
                    'Investigación'=> 'fa-flask',
                    'América Latina'=>'fa-globe-americas',
                    'Tecnología'   => 'fa-microchip',
                    'Innovación'   => 'fa-lightbulb',
                ][$categoria] ?? 'fa-newspaper';

                // Construir TOC y secciones
                $tocHtml = '';
                $seccionesHtml = '';
                foreach ($sec_titulos as $i => $stit) {
                    $stit = trim($stit);
                    $scont= trim($sec_contenidos[$i] ?? '');
                    if ($stit === '') continue;
                    $sid = 'seccion-' . ($i + 1);
                    $tocHtml .= "<li><a href=\"#{$sid}\">" . ($i+1) . ". {$stit}</a></li>\n";
                    // Convertir saltos de línea en párrafos
                    $parrafos = '';
                    foreach (explode("\n\n", $scont) as $p) {
                        $p = trim($p);
                        if ($p !== '') $parrafos .= "<p>" . nl2br(htmlspecialchars($p)) . "</p>\n";
                    }
                    $seccionesHtml .= <<<SEC
        <div class="art-card">
            <h2 id="{$sid}">{$stit}</h2>
            {$parrafos}
        </div>
SEC;
                }
                if ($tocHtml) $tocHtml .= "<li><a href=\"#conclusion\">Conclusión</a></li>\n";

                // Imagen hero
                $imgHero = $imagen
                    ? "<img src=\"../articulos/imagenes/{$imagen}\" alt=\"{$titulo}\" style=\"width:100%;border-radius:12px;margin-top:28px;max-height:420px;object-fit:cover;\">"
                    : '';

                // Cita destacada
                $citaHtml = $cita
                    ? "<blockquote class=\"art-pullquote\"><p>" . htmlspecialchars($cita) . "</p></blockquote>"
                    : '';

                // Conclusión
                $conclusionParrafos = '';
                foreach (explode("\n\n", $conclusion) as $p) {
                    $p = trim($p);
                    if ($p !== '') $conclusionParrafos .= "<p>" . nl2br(htmlspecialchars($p)) . "</p>\n";
                }

                // Intro párrafos
                $introParrafos = '';
                foreach (explode("\n\n", $intro) as $p) {
                    $p = trim($p);
                    if ($p !== '') $introParrafos .= "<p>" . nl2br(htmlspecialchars($p)) . "</p>\n";
                }

                // Generar HTML completo
                $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$titulo} — FEIAAL</title>
<meta name="description" content="{$resumen}">
<meta name="author" content="{$autor}">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://feiaal.org/articulos/{$slug}.html">
<meta property="og:type" content="article">
<meta property="og:title" content="{$titulo} — FEIAAL">
<meta property="og:description" content="{$resumen}">
<meta property="og:url" content="https://feiaal.org/articulos/{$slug}.html">
<meta property="og:site_name" content="FEIAAL">
<link rel="stylesheet" href="../css/styles.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script defer src="../js/translations.js"></script>
<script defer src="../js/script.js"></script>
<style>
#art-progress-bar{position:fixed;top:0;left:0;height:4px;width:0%;background:linear-gradient(90deg,#c2185b,#ff9100);z-index:9999;transition:width .1s linear;border-radius:0 4px 4px 0}
.art-hero{background:linear-gradient(135deg,#4a0014,#8b0e3a,#c2185b);padding:72px 24px 56px;text-align:center;position:relative;overflow:hidden}
.art-hero-inner{max-width:780px;margin:0 auto;position:relative}
.art-breadcrumb{font-family:'Inter',sans-serif;font-size:.8rem;color:rgba(255,255,255,.6);margin-bottom:20px;letter-spacing:.04em}
.art-breadcrumb a{color:rgba(255,255,255,.75);text-decoration:none}.art-breadcrumb span{margin:0 6px}
.art-category-tag{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;font-family:'Inter',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:4px 14px;border-radius:999px;margin-bottom:20px}
.art-hero h1{font-family:'Playfair Display',Georgia,serif;font-size:clamp(1.8rem,4vw,2.8rem);font-weight:700;color:#fff;line-height:1.25;margin:0 0 28px;text-shadow:0 2px 20px rgba(0,0,0,.3)}
.art-meta{display:flex;flex-wrap:wrap;justify-content:center;gap:20px;font-family:'Inter',sans-serif;font-size:.85rem;color:rgba(255,255,255,.8)}
.art-meta-item{display:flex;align-items:center;gap:7px}.art-meta-item i{color:rgba(255,144,0,.9);font-size:.8rem}
#articulo-individual{background:#f0f4f8;padding:0 0 60px;display:block}
.art-layout{max-width:1160px;margin:0 auto;padding:48px 24px 0;display:grid;grid-template-columns:1fr 260px;gap:40px;align-items:start}
.art-toc{position:sticky;top:80px;background:#fff;border-radius:14px;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,.08);order:2}
.art-toc h4{font-family:'Inter',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8;margin:0 0 16px}
.art-toc ul{list-style:none;padding:0;margin:0}.art-toc li{margin-bottom:4px}
.art-toc a{display:block;font-family:'Inter',sans-serif;font-size:.875rem;color:#475569;text-decoration:none;padding:6px 10px;border-radius:8px;border-left:2px solid transparent;transition:all .2s;line-height:1.4}
.art-toc a:hover,.art-toc a.active{background:#fce4ec;color:#c2185b;border-left-color:#c2185b}
.art-toc-divider{border:none;border-top:1px solid #e2e8f0;margin:16px 0}
.art-toc-back{display:flex;align-items:center;gap:8px;font-family:'Inter',sans-serif;font-size:.82rem;font-weight:600;color:#c2185b;text-decoration:none;padding:8px 10px;border-radius:8px;background:#fce4ec;transition:background .2s}
.art-toc-back:hover{background:#f8bbd0}
.art-content{order:1;min-width:0}
.art-card{background:#fff;border-radius:16px;padding:40px 44px;box-shadow:0 4px 24px rgba(0,0,0,.07);margin-bottom:28px}
.art-content p{font-family:'Inter',sans-serif;font-size:1.05rem;line-height:1.85;color:#374151;margin:0 0 1.4em;text-align:justify}
.art-content h2{font-family:'Playfair Display',Georgia,serif;font-size:1.55rem;color:#4a0014;margin:40px 0 18px;padding-top:8px}
.art-content h2:first-child{margin-top:0}
.art-content h3{font-family:'Playfair Display',Georgia,serif;font-size:1.2rem;color:#8b0e3a;margin:32px 0 14px}
.art-content a{color:#c2185b;text-decoration:underline;text-decoration-color:rgba(194,24,91,.3);text-underline-offset:3px}
.art-content ul,.art-content ol{font-family:'Inter',sans-serif;font-size:1.02rem;line-height:1.85;color:#374151;padding-left:1.6rem;margin:0 0 1.4em}
.art-pullquote{border:none;margin:36px 0;padding:28px 36px;background:linear-gradient(135deg,#4a0014,#c2185b);border-radius:14px;position:relative;overflow:hidden}
.art-pullquote::before{content:'\201C';font-family:'Playfair Display',Georgia,serif;font-size:8rem;color:rgba(255,255,255,.12);position:absolute;top:-20px;left:16px;line-height:1}
.art-pullquote p{font-family:'Playfair Display',Georgia,serif;font-size:1.25rem;color:#fff;line-height:1.6;margin:0;position:relative;text-align:left}
@media(max-width:900px){.art-layout{grid-template-columns:1fr}.art-toc{position:static;order:-1}.art-content{order:1}.art-card{padding:28px 22px}}
</style>
</head>
<body>
<div id="art-progress-bar"></div>
<header>
<div class="container" style="position:relative;display:flex;align-items:center;gap:.5rem;margin:0;padding:0;width:100%">
<div id="language-selector"><button id="language-btn"><i class="fas fa-globe"></i><span>Idiomas</span></button>
<ul id="language-options" class="hidden"><li data-lang="es">Español</li><li data-lang="en">English</li><li data-lang="zh">中文</li></ul></div>
<img src="../imagenes/feiaal.png" alt="Logo de FEIAAL" class="logo">
<div style="display:flex;flex-direction:column;justify-content:center;align-items:center;flex:1;margin:0;padding:0">
<h1 class="main-header">Fundación para la Enseñanza de la Inteligencia Artificial en América Latina</h1>
<p class="subheader">Promoviendo el aprendizaje y el uso ético de la Inteligencia Artificial en América Latina.</p>
</div></div>
<hr style="border:2px solid #ccc;margin:1.5rem auto;width:80%">
<div class="container"><nav><ul>
<li><a href="../index.html">Inicio</a></li>
<li><a href="../nosotros.html">Sobre Nosotros</a></li>
<li><a href="../biblioteca.html">Biblioteca de IA</a></li>
<li><a href="../proyectos.html">Proyectos</a></li>
<li><a href="../socios.html">Socios</a></li>
<li><a href="../oportunidades.html">Oportunidades</a></li>
<li><a href="../tienda.html">Tienda</a></li>
<li><a href="../blog.html" style="color:#c2185b;font-weight:700">Blog</a></li>
<li><a href="../eventos.html">Eventos</a></li>
<li><a href="../incubadora.html">Incubadora</a></li>
</ul></nav>
<div class="menu-toggle"><span></span><span></span><span></span></div>
</div></header>
<main>
<div class="art-hero">
<div class="art-hero-inner">
<div class="art-breadcrumb"><a href="../blog.html">Blog</a><span>›</span><span>{$categoria}</span></div>
<span class="art-category-tag"><i class="fas {$iconoCat}"></i>&nbsp; {$categoria}</span>
<h1>{$titulo}</h1>
<div class="art-meta">
<div class="art-meta-item"><i class="fas fa-user-circle"></i><span>{$autor}</span></div>
<div class="art-meta-item"><i class="fas fa-calendar-alt"></i><span>{$fechaFmt}</span></div>
<div class="art-meta-item"><i class="fas fa-clock"></i><span>{$lectura} min de lectura</span></div>
</div>
{$imgHero}
</div></div>
<section id="articulo-individual">
<div class="art-layout">
<aside class="art-toc">
<h4><i class="fas fa-list-ul" style="margin-right:6px"></i>Contenido</h4>
<ul>{$tocHtml}</ul>
<hr class="art-toc-divider">
<a href="../blog.html" class="art-toc-back"><i class="fas fa-arrow-left"></i> Volver al blog</a>
</aside>
<div class="art-content">
<div class="art-card" id="introduccion">
{$introParrafos}
{$citaHtml}
</div>
{$seccionesHtml}
<div class="art-card">
<h2 id="conclusion">Conclusión</h2>
{$conclusionParrafos}
<p>¿Quieres saber más? Visita nuestra <a href="../biblioteca.html">Biblioteca de IA</a> o revisa los próximos <a href="../eventos.html">eventos y cursos de FEIAAL</a>.</p>
</div>
</div></div></section></main>
<footer><div class="container"><p>&copy; 2024 FEIAAL. Todos los derechos reservados.</p></div></footer>
<div class="social-icons hidden">
<a href="https://www.facebook.com/profile.php?id=61576129793383" class="icon facebook"><i class="fab fa-facebook-f"></i></a>
<a href="https://www.instagram.com/fundacion_feial/" class="icon instagram"><i class="fab fa-instagram"></i></a>
<a href="https://www.twitter.com/" class="icon twitter"><i class="fab fa-twitter"></i></a>
<a href="https://www.linkedin.com/" class="icon linkedin"><i class="fab fa-linkedin-in"></i></a>
<a href="https://youtu.be/5Tc2K1kH9BQ?si=AWYYhtYxUsoIVJI4" class="icon youtube"><i class="fab fa-youtube"></i></a>
<a href="https://www.tiktok.com/" class="icon tiktok"><i class="fab fa-tiktok"></i></a>
</div>
<button id="toggle-social-icons" class="toggle-button"><i class="fas fa-share-alt"></i></button>
<a href="https://wa.me/593987121170?text=Hola%2C%20me%20gustar%C3%ADa%20obtener%20informaci%C3%B3n%20sobre%20sus%20servicios" class="whatsapp-icon"><i class="fab fa-whatsapp"></i></a>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-PS34S9QRVV"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','G-PS34S9QRVV');</script>
<script>
window.addEventListener('scroll',()=>{const d=document.documentElement,s=d.scrollTop||document.body.scrollTop,h=d.scrollHeight-d.clientHeight;document.getElementById('art-progress-bar').style.width=(h>0?s/h*100:0)+'%'});
const hs=document.querySelectorAll('.art-content h2[id]'),ls=document.querySelectorAll('.art-toc a');
window.addEventListener('scroll',()=>{let c='';hs.forEach(h=>{if(window.scrollY>=h.offsetTop-120)c=h.id});ls.forEach(a=>{a.classList.toggle('active',a.getAttribute('href')==='#'+c)})});
</script>
</body></html>
HTML;

                // Guardar archivo HTML
                file_put_contents($filename, $html);

                // Registrar en noticias.json
                $noticias = leerNoticias();
                $nueva = [
                    'id'       => nextId($noticias),
                    'titulo'   => $titulo,
                    'resumen'  => $resumen,
                    'imagen'   => $imagen ? "articulos/imagenes/{$imagen}" : '',
                    'fecha'    => $fecha,
                    'categoria'=> $categoria,
                    'url'      => "articulos/{$slug}.html",
                    'autor'    => $autor,
                ];
                $noticias[] = $nueva;
                guardarNoticias($noticias);

                $msg = "✅ Artículo '<strong>{$titulo}</strong>' creado y publicado en el blog.";
            }
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

// Banco de temas sugeridos para publicar
$temasSugeridos = [
  ['cat' => 'IA General',    'icono' => 'fa-robot',         'titulo' => 'Qué es la inteligencia artificial generativa y cómo usarla en tu día a día'],
  ['cat' => 'IA General',    'icono' => 'fa-robot',         'titulo' => 'Los 10 agentes de IA más útiles en 2025'],
  ['cat' => 'IA General',    'icono' => 'fa-robot',         'titulo' => 'Diferencias entre IA débil, fuerte y superinteligencia'],
  ['cat' => 'Modelos de IA', 'icono' => 'fa-brain',         'titulo' => 'GPT-4o vs Claude 3.5 vs Gemini 1.5: comparativa actualizada'],
  ['cat' => 'Modelos de IA', 'icono' => 'fa-brain',         'titulo' => 'Cómo elegir el modelo de IA adecuado para tu empresa'],
  ['cat' => 'Modelos de IA', 'icono' => 'fa-brain',         'titulo' => 'DeepSeek R2: lo que debes saber del nuevo modelo chino de IA'],
  ['cat' => 'Educación',     'icono' => 'fa-graduation-cap','titulo' => 'Cómo integrar la inteligencia artificial en el aula sin perder el pensamiento crítico'],
  ['cat' => 'Educación',     'icono' => 'fa-graduation-cap','titulo' => 'Las mejores plataformas gratuitas para aprender IA en español'],
  ['cat' => 'Educación',     'icono' => 'fa-graduation-cap','titulo' => 'IA y educación en América Latina: desafíos y oportunidades'],
  ['cat' => 'Negocios',      'icono' => 'fa-briefcase',     'titulo' => 'Cómo automatizar procesos de tu negocio con herramientas de IA gratuitas'],
  ['cat' => 'Negocios',      'icono' => 'fa-briefcase',     'titulo' => '5 casos de éxito de empresas latinoamericanas usando inteligencia artificial'],
  ['cat' => 'Negocios',      'icono' => 'fa-briefcase',     'titulo' => 'IA para PyMEs: por dónde empezar sin gastar mucho'],
  ['cat' => 'Investigación', 'icono' => 'fa-flask',         'titulo' => 'Los papers de IA más importantes del año y qué significan'],
  ['cat' => 'Investigación', 'icono' => 'fa-flask',         'titulo' => 'Avances en IA cuántica: dónde estamos y qué viene'],
  ['cat' => 'Investigación', 'icono' => 'fa-flask',         'titulo' => 'Neurociencia e inteligencia artificial: una relación cada vez más cercana'],
  ['cat' => 'América Latina','icono' => 'fa-globe-americas','titulo' => 'El estado de la IA en América Latina: informe 2025'],
  ['cat' => 'América Latina','icono' => 'fa-globe-americas','titulo' => 'Startups latinoamericanas de IA que están cambiando el mercado'],
  ['cat' => 'América Latina','icono' => 'fa-globe-americas','titulo' => 'Políticas públicas de inteligencia artificial en la región: ¿qué países van adelante?'],
  ['cat' => 'Tecnología',    'icono' => 'fa-microchip',     'titulo' => 'Chips de IA: NVIDIA H100 vs la nueva generación de procesadores'],
  ['cat' => 'Tecnología',    'icono' => 'fa-microchip',     'titulo' => 'Cómo funciona un modelo de lenguaje grande (LLM) por dentro'],
  ['cat' => 'Tecnología',    'icono' => 'fa-microchip',     'titulo' => 'IA en el borde (Edge AI): procesamiento sin necesidad de la nube'],
  ['cat' => 'Innovación',    'icono' => 'fa-lightbulb',     'titulo' => 'Robótica e IA: el futuro de la automatización industrial'],
  ['cat' => 'Innovación',    'icono' => 'fa-lightbulb',     'titulo' => 'IA generativa en el arte, la música y la creatividad'],
  ['cat' => 'Innovación',    'icono' => 'fa-lightbulb',     'titulo' => 'Vehículos autónomos: el rol de la inteligencia artificial en la movilidad del futuro'],
];
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

<!-- ── Formulario CREAR ARTÍCULO COMPLETO ── -->
<div class="card">
  <h2><i class="fas fa-file-alt"></i> Crear artículo completo
    <span style="font-size:0.75rem;font-weight:400;color:rgba(255,255,255,0.4);margin-left:8px;">Genera el HTML y lo publica en el blog automáticamente</span>
  </h2>
  <form method="POST" id="form-articulo">
    <input type="hidden" name="action" value="crear_articulo">
    <div class="form-grid">
      <div class="full">
        <label>Título del artículo *</label>
        <input type="text" name="art_titulo" id="art_titulo" required
          placeholder="Ej: Cómo usar ChatGPT para mejorar tu negocio"
          oninput="generarSlug(this.value)">
      </div>
      <div>
        <label>Nombre de archivo (slug) *</label>
        <input type="text" name="art_slug" id="art_slug" required
          placeholder="como-usar-chatgpt-negocio"
          style="font-family:monospace">
        <small style="color:#666;font-size:0.72rem;">Solo letras minúsculas, números y guiones. Sin espacios.</small>
      </div>
      <div>
        <label>Categoría</label>
        <select name="art_categoria">
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Autor</label>
        <input type="text" name="art_autor" value="FEIAAL" placeholder="FEIAAL">
      </div>
      <div>
        <label>Fecha de publicación</label>
        <input type="date" name="art_fecha" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label>Tiempo de lectura (minutos)</label>
        <input type="number" name="art_lectura" value="5" min="1" max="60">
      </div>
      <div class="full">
        <label>Resumen corto (aparece en la tarjeta del blog)</label>
        <textarea name="art_resumen" placeholder="Breve descripción del artículo para la tarjeta del blog y buscadores..."></textarea>
      </div>
      <div class="full">
        <label>Imagen de portada <small style="font-weight:400;color:#666">(nombre del archivo en articulos/imagenes/)</small></label>
        <input type="text" name="art_imagen" placeholder="Ej: mi-imagen.jpg">
      </div>

      <div class="full"><hr style="border:none;border-top:1px solid #2a2a4a;margin:0.5rem 0"></div>

      <div class="full">
        <label>Introducción <small style="font-weight:400;color:#666">(separa párrafos con una línea en blanco)</small></label>
        <textarea name="art_intro" rows="5" placeholder="Escribe la introducción del artículo aquí. Puedes dejar una línea en blanco entre párrafos para separarlos."></textarea>
      </div>
      <div class="full">
        <label>Cita destacada <small style="font-weight:400;color:#666">(opcional)</small></label>
        <input type="text" name="art_cita" placeholder="Ej: La IA no reemplaza la inteligencia humana, la amplifica.">
      </div>
    </div>

    <!-- Secciones dinámicas -->
    <div style="margin:1.5rem 0 0.5rem">
      <label style="font-size:0.9rem;color:#fff;font-weight:700;text-transform:none;letter-spacing:0">
        <i class="fas fa-layer-group" style="color:#c2185b;margin-right:6px"></i>Secciones del artículo
      </label>
      <small style="display:block;color:#666;font-size:0.75rem;margin-top:3px">Agrega las secciones principales. Separa párrafos con una línea en blanco.</small>
    </div>
    <div id="secciones-wrap">
      <div class="seccion-bloque" style="background:#0a0a18;border:1px solid #2a2a4a;border-radius:10px;padding:1rem;margin-bottom:0.75rem">
        <label>Título de la sección</label>
        <input type="text" name="sec_titulo[]" placeholder="Ej: El impacto de la IA en la educación" style="margin-bottom:0.6rem">
        <label>Contenido</label>
        <textarea name="sec_contenido[]" rows="4" placeholder="Escribe el contenido de esta sección..."></textarea>
      </div>
    </div>
    <button type="button" onclick="agregarSeccion()"
      style="background:#1e1e3a;color:#aaa;border:1px dashed #2a2a4a;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:0.82rem;margin-bottom:1.5rem;transition:all 0.2s"
      onmouseover="this.style.borderColor='#c2185b';this.style.color='#c2185b'"
      onmouseout="this.style.borderColor='#2a2a4a';this.style.color='#aaa'">
      <i class="fas fa-plus"></i> Agregar sección
    </button>

    <div class="form-grid">
      <div class="full">
        <label>Conclusión <small style="font-weight:400;color:#666">(separa párrafos con línea en blanco)</small></label>
        <textarea name="art_conclusion" rows="4" placeholder="Escribe el párrafo de cierre del artículo..."></textarea>
      </div>
    </div>

    <button type="submit" class="btn-primary">
      <i class="fas fa-rocket"></i> Generar artículo y publicar en el blog
    </button>
  </form>
</div>

<script>
function generarSlug(titulo) {
  const slug = titulo.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim().replace(/\s+/g, '-')
    .replace(/-+/g, '-').substring(0, 60);
  document.getElementById('art_slug').value = slug;
}

function agregarSeccion() {
  const wrap = document.getElementById('secciones-wrap');
  const n = wrap.querySelectorAll('.seccion-bloque').length + 1;
  const div = document.createElement('div');
  div.className = 'seccion-bloque';
  div.style.cssText = 'background:#0a0a18;border:1px solid #2a2a4a;border-radius:10px;padding:1rem;margin-bottom:0.75rem;position:relative';
  div.innerHTML = `
    <button type="button" onclick="this.parentElement.remove()"
      style="position:absolute;top:8px;right:10px;background:none;border:none;color:#555;cursor:pointer;font-size:1rem"
      title="Eliminar sección">&#x2715;</button>
    <label>Título de la sección ${n}</label>
    <input type="text" name="sec_titulo[]" placeholder="Título de la sección" style="margin-bottom:0.6rem">
    <label>Contenido</label>
    <textarea name="sec_contenido[]" rows="4" placeholder="Contenido de esta sección..."></textarea>`;
  wrap.appendChild(div);
}
</script>

<!-- ── Formulario agregar / editar noticia ── -->
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

<!-- ── Banco de temas sugeridos ── -->
<div class="card">
  <h2><i class="fas fa-lightbulb"></i> Ideas de temas para publicar
    <span style="font-size:0.75rem;font-weight:400;color:rgba(255,255,255,0.4);margin-left:8px;">Haz clic en un tema para pre-cargarlo en el formulario</span>
  </h2>

  <style>
    .temas-filtros { display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:1.2rem; }
    .tema-filtro-btn { padding:4px 14px; border-radius:999px; border:1.5px solid #2a2a4a;
      background:transparent; color:#aaa; font-size:0.75rem; font-weight:600; cursor:pointer; transition:all 0.2s; }
    .tema-filtro-btn:hover, .tema-filtro-btn.active { background:#c2185b; border-color:#c2185b; color:#fff; }

    .temas-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem; }
    @media(max-width:800px){ .temas-grid { grid-template-columns:repeat(2,1fr); } }
    @media(max-width:520px){ .temas-grid { grid-template-columns:1fr; } }

    .tema-card {
      background:#0f0f1a;
      border:1px solid #1e1e3a;
      border-radius:10px;
      padding:0.85rem 1rem;
      cursor:pointer;
      transition:border-color 0.2s, background 0.2s, transform 0.2s;
      display:flex;
      gap:0.7rem;
      align-items:flex-start;
    }
    .tema-card:hover { border-color:#c2185b; background:#160816; transform:translateY(-2px); }
    .tema-card i { color:#c2185b; font-size:1rem; flex-shrink:0; margin-top:2px; }
    .tema-card-text { font-size:0.82rem; color:rgba(255,255,255,0.75); line-height:1.4; }
    .tema-card-cat  { font-size:0.68rem; color:#c2185b; font-weight:700; text-transform:uppercase;
      letter-spacing:0.05em; margin-bottom:3px; }
    .temas-hidden { display:none; }
  </style>

  <!-- Filtros de categoría -->
  <div class="temas-filtros">
    <button class="tema-filtro-btn active" onclick="filtrarTemas('todas',this)">Todas</button>
    <?php
    $cats = array_unique(array_column($temasSugeridos, 'cat'));
    foreach($cats as $c): ?>
      <button class="tema-filtro-btn" onclick="filtrarTemas('<?= h($c) ?>',this)"><?= h($c) ?></button>
    <?php endforeach; ?>
  </div>

  <!-- Grid de temas -->
  <div class="temas-grid" id="temas-grid">
    <?php foreach($temasSugeridos as $t): ?>
    <div class="tema-card" data-cat="<?= h($t['cat']) ?>"
         onclick="precargarTema('<?= addslashes(h($t['titulo'])) ?>', '<?= h($t['cat']) ?>')">
      <i class="fas <?= h($t['icono']) ?>"></i>
      <div>
        <div class="tema-card-cat"><?= h($t['cat']) ?></div>
        <div class="tema-card-text"><?= h($t['titulo']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function filtrarTemas(cat, btn) {
  document.querySelectorAll('.tema-filtro-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.tema-card').forEach(card => {
    card.classList.toggle('temas-hidden', cat !== 'todas' && card.dataset.cat !== cat);
  });
}

function precargarTema(titulo, cat) {
  // Llenar el campo título y categoría del formulario de publicación
  const tituloInput = document.querySelector('input[name="titulo"]');
  const catSelect   = document.querySelector('select[name="categoria"]');
  if (tituloInput) { tituloInput.value = titulo; tituloInput.focus(); }
  if (catSelect) {
    for (let opt of catSelect.options) {
      if (opt.value === cat) { opt.selected = true; break; }
    }
  }
  // Hacer scroll al formulario
  tituloInput && tituloInput.scrollIntoView({ behavior:'smooth', block:'center' });
}
</script>

<?php endif; ?>
</div><!-- /wrap -->
</body>
</html>
