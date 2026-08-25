<?php
if (getenv('MYSQLHOST')) {
    // Si estoy en Railway uso las variables de entorno
    define('SERVIDOR', getenv('MYSQLHOST'));
    define('USUARIO', getenv('MYSQLUSER'));
    define('PASSWORD', getenv('MYSQLPASSWORD'));
    define('BD', getenv('MYSQLDATABASE') ?: 'sistemaescolar');
    define('PUERTO', getenv('MYSQLPORT'));
} else {
    // Si estoy en local uso la configuración de XAMPP
    define('SERVIDOR', 'localhost');
    define('USUARIO', 'root');
    define('PASSWORD', '');
    define('BD', 'sistemaescolar');
    define('PUERTO', '3306');
}

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

// Configuro la zona horaria 
date_default_timezone_set('America/Bogota');
$fechaHora = date('Y-m-d H:i:s');
$fechaActual = date('Y-m-d');
$diaActual = date('d');
$mesActual = date('m');
$anioActual = date('Y');
$estadoRegistro = 1;
