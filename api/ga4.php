<?php
/**
 * api/ga4.php — Proxy para Google Analytics Data API v1beta
 *
 * REQUISITOS:
 *   1. Coloca tu archivo de Service Account en: api/service-account.json
 *   2. Define tu GA4 Property ID numérico abajo (NO el G-XXXXXXXXXX)
 *      Para encontrarlo: GA4 → Admin → Configuración de la propiedad → Property ID
 *
 * CACHE: Los datos se cachean 1 hora en api/cache/ga4-cache.json
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── CONFIGURACIÓN ──────────────────────────────────────────────────────────
define('GA4_PROPERTY_ID', '320456789'); // Ej: 320456789
define('CREDENTIALS_FILE', __DIR__ . '/service-account.json');
define('CACHE_FILE',       __DIR__ . '/cache/ga4-cache.json');
define('CACHE_TTL',        3600); // segundos (1 hora)
// ──────────────────────────────────────────────────────────────────────────

// Servir caché si es válido
if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL) {
    header('X-Cache: HIT');
    echo file_get_contents(CACHE_FILE);
    exit;
}

// Validar credenciales
if (!file_exists(CREDENTIALS_FILE)) {
    http_response_code(503);
    echo json_encode([
        'error'   => 'no_credentials',
        'message' => 'Coloca el archivo service-account.json en la carpeta api/'
    ]);
    exit;
}

$credentials = json_decode(file_get_contents(CREDENTIALS_FILE), true);
if (!$credentials || ($credentials['type'] ?? '') !== 'service_account') {
    http_response_code(500);
    echo json_encode([
        'error'   => 'invalid_credentials',
        'message' => 'El archivo service-account.json no es válido'
    ]);
    exit;
}

if (GA4_PROPERTY_ID === 'TU_PROPERTY_ID_NUMERICO') {
    http_response_code(503);
    echo json_encode([
        'error'   => 'no_property_id',
        'message' => 'Define GA4_PROPERTY_ID en api/ga4.php con tu Property ID numérico'
    ]);
    exit;
}

// ── FUNCIONES JWT / OAUTH ──────────────────────────────────────────────────
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function getAccessToken(array $creds): string {
    $now     = time();
    $header  = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'iss'   => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $unsigned = "$header.$payload";
    $key      = openssl_pkey_get_private($creds['private_key']);
    if (!$key) {
        throw new RuntimeException('Clave privada inválida en service-account.json');
    }
    openssl_sign($unsigned, $sig, $key, OPENSSL_ALGO_SHA256);
    $jwt = $unsigned . '.' . base64url_encode($sig);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($res['access_token'])) {
        throw new RuntimeException('OAuth falló: ' . json_encode($res));
    }
    return $res['access_token'];
}

function ga4Report(string $token, string $propId, array $body): array {
    $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propId}:runReport";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) throw new RuntimeException('cURL error: ' . $err);
    return json_decode($raw, true) ?? [];
}

// ── HELPER: formatear segundos ─────────────────────────────────────────────
function fmtTime(float $secs): string {
    $m = floor($secs / 60);
    $s = round($secs % 60);
    return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
}

// ── MAIN ───────────────────────────────────────────────────────────────────
try {
    $token = getAccessToken($credentials);
    $prop  = GA4_PROPERTY_ID;

    // ── Reporte 1: KPIs globales (7 días actuales vs 7 anteriores) ──────────
    $rKpi = ga4Report($token, $prop, [
        'metrics'    => [
            ['name' => 'activeUsers'],
            ['name' => 'screenPageViews'],
            ['name' => 'eventCount'],
            ['name' => 'sessions'],
        ],
        'dateRanges' => [
            ['startDate' => '7daysAgo',  'endDate' => 'today',    'name' => 'current'],
            ['startDate' => '14daysAgo', 'endDate' => '8daysAgo', 'name' => 'previous'],
        ],
    ]);

    $kpiCurrent  = [];
    $kpiPrevious = [];
    if (!empty($rKpi['rows'])) {
        foreach ($rKpi['rows'] as $row) {
            $rangeName = $row['dimensionValues'][0]['value'] ?? '';
            $vals = array_map(fn($v) => (float)$v['value'], $row['metricValues']);
            if ($rangeName === 'current')  $kpiCurrent  = $vals;
            if ($rangeName === 'previous') $kpiPrevious = $vals;
        }
    }
    // Si no hay dimensión de rango (GA4 devuelve rows sin dimensiones)
    if (empty($kpiCurrent) && !empty($rKpi['rows'][0]['metricValues'])) {
        $kpiCurrent  = array_map(fn($v) => (float)$v['value'], $rKpi['rows'][0]['metricValues']);
        $kpiPrevious = array_map(fn($v) => (float)$v['value'], $rKpi['rows'][1]['metricValues'] ?? []);
    }

    $kpi = [];
    $kpiKeys = ['users', 'views', 'events', 'sessions'];
    foreach ($kpiKeys as $i => $key) {
        $cur  = $kpiCurrent[$i]  ?? 0;
        $prev = $kpiPrevious[$i] ?? 0;
        $pct  = $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : null;
        $kpi[$key] = ['value' => (int)$cur, 'change' => $pct];
    }

    // ── Reporte 2: País en tiempo real (aproximado — últimas 24h) ─────────
    $rCountry = ga4Report($token, $prop, [
        'dimensions'  => [['name' => 'country']],
        'metrics'     => [['name' => 'activeUsers']],
        'dateRanges'  => [['startDate' => '1daysAgo', 'endDate' => 'today']],
        'orderBys'    => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
        'limit'       => 1,
    ]);
    $topCountry = $rCountry['rows'][0]['dimensionValues'][0]['value'] ?? 'N/A';

    // ── Reporte 3: por página (últimos 30 días) ───────────────────────────
    $rPages = ga4Report($token, $prop, [
        'dimensions'  => [['name' => 'pagePath']],
        'metrics'     => [
            ['name' => 'screenPageViews'],
            ['name' => 'activeUsers'],
            ['name' => 'averageSessionDuration'],
            ['name' => 'bounceRate'],
            ['name' => 'sessions'],
        ],
        'dateRanges'  => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'orderBys'    => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
        'limit'       => 100,
    ]);

    $pages = [];
    if (!empty($rPages['rows'])) {
        foreach ($rPages['rows'] as $row) {
            $pages[] = [
                'path'     => $row['dimensionValues'][0]['value'],
                'views'    => (int)  $row['metricValues'][0]['value'],
                'users'    => (int)  $row['metricValues'][1]['value'],
                'avgTime'  => round((float)$row['metricValues'][2]['value']),
                'avgTimeFmt' => fmtTime((float)$row['metricValues'][2]['value']),
                'bounce'   => round((float)$row['metricValues'][3]['value'] * 100, 1),
                'sessions' => (int)  $row['metricValues'][4]['value'],
            ];
        }
    }

    // ── Reporte 4: por mes + página (últimos 6 meses) ─────────────────────
    $rMonthly = ga4Report($token, $prop, [
        'dimensions'  => [
            ['name' => 'yearMonth'],
            ['name' => 'pagePath'],
            ['name' => 'pageTitle'],
        ],
        'metrics'     => [
            ['name' => 'screenPageViews'],
            ['name' => 'activeUsers'],
            ['name' => 'averageSessionDuration'],
            ['name' => 'bounceRate'],
            ['name' => 'sessions'],
            ['name' => 'newUsers'],
        ],
        'dateRanges'  => [['startDate' => '180daysAgo', 'endDate' => 'today']],
        'orderBys'    => [
            ['dimension' => ['dimensionName' => 'yearMonth'], 'desc' => true],
            ['metric'    => ['metricName'    => 'screenPageViews'], 'desc' => true],
        ],
        'limit'       => 1000,
        // Filtrar solo las páginas del sitio (excluir rutas internas)
        'dimensionFilter' => [
            'filter' => [
                'fieldName'    => 'pagePath',
                'stringFilter' => ['matchType' => 'PARTIAL_REGEXP', 'value' => '\.html'],
            ],
        ],
    ]);

    $monthly = [];
    if (!empty($rMonthly['rows'])) {
        foreach ($rMonthly['rows'] as $row) {
            $ym   = $row['dimensionValues'][0]['value']; // "202503"
            $path = $row['dimensionValues'][1]['value'];
            $title = $row['dimensionValues'][2]['value'];
            $secs = (float)$row['metricValues'][2]['value'];
            $monthly[] = [
                'yearMonth'  => $ym,
                'month'      => substr($ym, 0, 4) . '-' . substr($ym, 4, 2),
                'monthLabel' => monthLabel($ym),
                'path'       => $path,
                'title'      => $title,
                'views'      => (int)  $row['metricValues'][0]['value'],
                'users'      => (int)  $row['metricValues'][1]['value'],
                'avgTime'    => round($secs),
                'avgTimeFmt' => fmtTime($secs),
                'bounce'     => round((float)$row['metricValues'][3]['value'] * 100, 1),
                'sessions'   => (int)  $row['metricValues'][4]['value'],
                'newUsers'   => (int)  $row['metricValues'][5]['value'],
            ];
        }
    }

    // ── Armar respuesta ────────────────────────────────────────────────────
    $result = [
        'generated'  => date('c'),
        'period'     => '30 días / 6 meses',
        'topCountry' => $topCountry,
        'kpi'        => $kpi,
        'pages'      => $pages,
        'monthly'    => $monthly,
    ];

    // Guardar caché
    file_put_contents(CACHE_FILE, json_encode($result));

    header('X-Cache: MISS');
    echo json_encode($result);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function monthLabel(string $ym): string {
    $meses = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr',
              '05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago',
              '09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
    $y = substr($ym, 0, 4);
    $m = substr($ym, 4, 2);
    return ($meses[$m] ?? $m) . ' ' . $y;
}
