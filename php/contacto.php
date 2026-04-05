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

// Guardar datos en debug.log para depuración
file_put_contents(
    'debug.log',
    "[" . date('Y-m-d H:i:s') . "] Datos recibidos: " . print_r($_POST, true) . "\n",
    FILE_APPEND
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capturar y sanitizar los datos del formulario
        $nombre = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $celular = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $pais = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING);
        $mensaje = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

        // Validar campos obligatorios
        if (empty($nombre) || empty($email) || empty($mensaje)) {
            echo json_encode(['error' => 'Todos los campos son obligatorios.']);
            exit;
        }

        // Validar el formato del email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'El email no es válido.']);
            exit;
        }

        // Verificar si el usuario ya está registrado en la base de datos
        $check_sql = "SELECT cont_email FROM contacto WHERE cont_email = :email LIMIT 1";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $check_stmt->execute();
        $existingUser = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            echo json_encode(['error' => 'Usuario ya registrado.', 'email_verificado' => $email]);
            exit;
        }

        // Insertar los datos en la base de datos
        $sql = "INSERT INTO contacto (cont_nom, cont_email, cont_cel, cont_pais, cont_men, fecha_creacion)
                VALUES (:nombre, :email, :celular, :pais, :mensaje, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':celular', $celular);
        $stmt->bindParam(':pais', $pais);
        $stmt->bindParam(':mensaje', $mensaje);
        $stmt->execute();

        // Respuesta exitosa
        echo json_encode(['success' => true, 'message' => "Gracias por contactarnos, $nombre. Hemos recibido tu mensaje."]);
    } catch (PDOException $e) {
        // Manejo de errores
        echo json_encode(['error' => 'Error al guardar los datos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Acceso no válido.']);
}
?>