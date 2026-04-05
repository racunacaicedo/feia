<?php
require 'db.php';

if ($pdo) {
    echo "Conexión exitosa a la base de datos.";
} else {
    echo "Error al conectar a la base de datos.";
}
?>
