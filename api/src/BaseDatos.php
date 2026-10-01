<?php
declare(strict_types=1);

namespace Api;

use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

// Inicializo Eloquent fuera de Laravel ("standalone") con los mismos datos de conexion
// que usan las vistas PHP (config/entorno.php).
final class BaseDatos
{
    private static ?Capsule $capsula = null;

    public static function iniciar(): void
    {
        if (self::$capsula !== null) {
            return;
        }
        $capsula = new Capsule();
        $capsula->addConnection([
            'driver' => 'mysql',
            'host' => SERVIDOR,
            'port' => PUERTO,
            'database' => BD,
            'username' => USUARIO,
            'password' => PASSWORD,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_spanish_ci',
            'prefix' => '',
        ]);
        // Hago disponible la conexion de forma global y activo el ORM
        $capsula->setAsGlobal();
        $capsula->bootEloquent();
        self::$capsula = $capsula;
    }

    // Devuelvo el PDO de la conexion de Eloquent para reutilizar funciones existentes que reciben PDO
    public static function pdo(): PDO
    {
        return Capsule::connection()->getPdo();
    }

    // Ejecuto varias operaciones en una transaccion: si algo falla se hace rollback automaticamente
    public static function transaccion(callable $operaciones): mixed
    {
        return Capsule::connection()->transaction($operaciones);
    }
}
