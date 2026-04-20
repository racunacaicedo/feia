<?php
// Ejecutar este archivo UNA VEZ para crear las tablas del curso
// Acceder a: http://localhost/feiaal/php/setup_curso.php

require_once __DIR__ . '/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS curso_estudiantes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(150)  NOT NULL,
    email         VARCHAR(200)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    fecha_registro DATETIME     DEFAULT CURRENT_TIMESTAMP,
    activo        TINYINT(1)    DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS curso_accesos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id  INT           NULL,
    email          VARCHAR(200)  NULL,
    ip             VARCHAR(45)   NOT NULL,
    accion         VARCHAR(100)  NOT NULL,
    detalle        VARCHAR(255)  NULL,
    fecha          DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES curso_estudiantes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo '<p style="font-family:monospace;color:green;">✔ Tablas creadas correctamente:<br>
          &nbsp;&nbsp;— curso_estudiantes<br>
          &nbsp;&nbsp;— curso_accesos<br><br>
          Puedes eliminar este archivo del servidor.</p>';
} catch (PDOException $e) {
    echo '<p style="color:red;">Error: ' . $e->getMessage() . '</p>';
}
?>
