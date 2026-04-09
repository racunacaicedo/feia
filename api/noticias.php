<?php
/**
 * api/noticias.php — Endpoint público de lectura de noticias FEIAAL
 * GET  → devuelve todas las noticias ordenadas por fecha desc
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$file = __DIR__ . '/../data/noticias.json';

if (!file_exists($file)) {
    echo json_encode([]);
    exit;
}

$raw  = file_get_contents($file);
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([]);
    exit;
}

// Ordenar por fecha descendente (más reciente primero)
usort($data, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
