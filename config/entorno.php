<?php
// Datos del entorno compartidos por las vistas PHP y por la API.
// Los separo de config.php porque la API necesita estos valores sin abrir la conexion PDO de las vistas.

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

// Configuro la zona horaria
date_default_timezone_set('America/Bogota');
