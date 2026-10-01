<?php
// Cargo los datos de conexion y la zona horaria (compartidos con la API)
require_once __DIR__ . '/entorno.php';

// Defino el nombre de mi aplicacion
define('APP_NAME', 'SISTEMA DE GESTION ESCOLAR');

// Esta ruta base la uso para algunas operaciones internas
define('BASE_PATH', dirname(__DIR__) . '/');

// CONEXION A LA BASE DE DATOS
$servidor = "mysql:dbname=" . BD . ";host=" . SERVIDOR . ";port=" . PUERTO;

try {
    $pdo = new PDO($servidor, USUARIO, PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
} catch (PDOException $e) {
    // Muestro errores de forma diferente segun el entorno
    if (getenv('RAILWAY_ENVIRONMENT_NAME') === 'production') {
        error_log("Error al conectar a la base de datos: " . $e->getMessage());
        echo "No pude conectar a la base de datos. Intenta mas tarde.";
    } else {
        print_r("Error: " . $e->getMessage());
        echo "No pude conectar a la base de datos";
    }
}

$fechaHora = date('Y-m-d H:i:s');
$fechaActual = date('Y-m-d');
$diaActual = date('d');
$mesActual = date('m');
$anioActual = date('Y');
$estadoRegistro = 1;
