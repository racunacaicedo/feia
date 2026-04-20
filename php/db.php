<?php
$host     = 'localhost';
$dbname   = 'creixuue_feiaal';

// En local XAMPP usa root sin contraseña; en producción usa las credenciales reales
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    $username = 'root';
    $password = '';
} else {
    $username = 'creixuue_user';
    $password = 'Rwac197401';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
