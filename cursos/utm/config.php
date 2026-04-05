<?php
/**
 * CONFIGURACIÓN DE BASE DE DATOS
 * Sitio: https://feiaal.org/cursos/utm/emprendimiento.html
 * Curso: Inteligencia Artificial para Emprendedores - UTM
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'creixuue_curso_ia_utm');
define('DB_USER', 'creixuue_utm');
define('DB_PASS', 'Rwac1974@');

// Configuración del sitio
define('SITE_URL', 'https://feiaal.org');
define('ADMIN_EMAIL', 'admin@feiaal.org');
define('COURSE_NAME', 'Inteligencia Artificial para Emprendedores');

// Configuración de errores (cambiar a false en producción)
define('DEBUG_MODE', true);

/**
 * Clase para manejar la conexión a la base de datos
 */
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Error de conexión: " . $e->getMessage());
            } else {
                die("Error de conexión a la base de datos.");
            }
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}

/**
 * Función para obtener la conexión PDO
 */
function getDbConnection() {
    return Database::getInstance()->getConnection();
}

/**
 * Función para registrar errores
 */
function logError($message, $context = []) {
    $logFile = __DIR__ . '/logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;

    // Crear directorio logs si no existe
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Función para validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Función para limpiar datos de entrada
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Función para obtener IP del cliente
 */
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Función para respuestas JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Configurar zona horaria
date_default_timezone_set('America/Guayaquil');

// Configurar headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

?>