<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/db.php';

$data   = json_decode(file_get_contents('php://input'), true);
$accion = $data['accion'] ?? '';
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

function registrarAcceso($pdo, $accion, $detalle = null, $id = null, $email = null, $ip = '0.0.0.0') {
    $stmt = $pdo->prepare("INSERT INTO curso_accesos (estudiante_id, email, ip, accion, detalle) VALUES (?,?,?,?,?)");
    $stmt->execute([$id, $email, $ip, $accion, $detalle]);
}

// ── REGISTRO ──
if ($accion === 'registro') {
    $nombre = trim($data['nombre'] ?? '');
    $email  = strtolower(trim($data['email'] ?? ''));
    $pass   = $data['password'] ?? '';

    if (!$nombre || !$email || !$pass) {
        echo json_encode(['ok' => false, 'msg' => 'Completa todos los campos.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => 'Correo no válido.']); exit;
    }
    if (strlen($pass) < 6) {
        echo json_encode(['ok' => false, 'msg' => 'La contraseña debe tener al menos 6 caracteres.']); exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM curso_estudiantes WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Ya existe una cuenta con ese correo.']); exit;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO curso_estudiantes (nombre, email, password_hash) VALUES (?,?,?)");
    $stmt->execute([$nombre, $email, $hash]);
    $nuevoId = $pdo->lastInsertId();

    registrarAcceso($pdo, 'registro', 'Nuevo estudiante registrado', $nuevoId, $email, $ip);

    echo json_encode(['ok' => true, 'nombre' => $nombre, 'id' => $nuevoId]);
    exit;
}

// ── LOGIN ──
if ($accion === 'login') {
    $email = strtolower(trim($data['email'] ?? ''));
    $pass  = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, nombre, password_hash, activo FROM curso_estudiantes WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['ok' => false, 'msg' => 'No encontramos una cuenta con ese correo.']); exit;
    }
    if (!$user['activo']) {
        echo json_encode(['ok' => false, 'msg' => 'Tu cuenta está desactivada. Contacta al instructor.']); exit;
    }
    if (!password_verify($pass, $user['password_hash'])) {
        registrarAcceso($pdo, 'login_fallido', 'Contraseña incorrecta', null, $email, $ip);
        echo json_encode(['ok' => false, 'msg' => 'Contraseña incorrecta.']); exit;
    }

    registrarAcceso($pdo, 'login', 'Inicio de sesión exitoso', $user['id'], $email, $ip);
    echo json_encode(['ok' => true, 'nombre' => $user['nombre'], 'id' => $user['id']]);
    exit;
}

// ── REGISTRAR ACTIVIDAD ──
if ($accion === 'actividad') {
    $id      = intval($data['id'] ?? 0);
    $email   = $data['email'] ?? null;
    $detalle = $data['detalle'] ?? null;
    registrarAcceso($pdo, 'vista_clase', $detalle, $id ?: null, $email, $ip);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']);
?>
