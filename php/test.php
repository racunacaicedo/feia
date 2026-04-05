<?php
// Incluir conexión a la base de datos
require 'db.php';

// Verificar si la conexión a la base de datos está activa
if (!$pdo) {
    echo json_encode(['error' => 'Error al conectar con la base de datos.']);
    exit;
}

// Configurar encabezados para JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Asegurar que siempre devuelva JSON, incluso en caso de error
try {
    // Tu lógica aquí...
    echo json_encode(['status' => 'success', 'message' => '¡Correo registrado correctamente!']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}


// Guardar datos en debug.log para depuración
file_put_contents(
    'debug.log',
    "[" . date('Y-m-d H:i:s') . "] Datos recibidos: " . print_r($_POST, true) . "\n",
    FILE_APPEND
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    file_put_contents('debug.log', 'Email recibido: ' . $email . PHP_EOL, FILE_APPEND);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "El correo electrónico ingresado no es válido.";
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO blog (blog_email) VALUES (:email)");

    try {
        $stmt->execute(['email' => $email]);
        echo "¡Correo registrado correctamente!";
    } catch (PDOException $ex) {
        file_put_contents('debug.log', 'Error en insert: ' . $ex->getMessage() . PHP_EOL, FILE_APPEND);
        if ($ex->getCode() == 23000) {
            echo "El correo ya se encuentra registrado.";
        } else {
            echo "Error al registrar el correo: " . $ex->getMessage();
        }
    }
} else {
    file_put_contents('debug.log', 'No se recibió POST o email' . PHP_EOL, FILE_APPEND);
    echo "No se recibió ningún correo electrónico.";
}
?>