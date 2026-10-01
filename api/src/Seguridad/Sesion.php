<?php
declare(strict_types=1);

namespace Api\Seguridad;

use Api\Http\ErrorHttp;
use Api\Modelos\Usuario;

// Manejo la sesion PHP de la API. Uso las mismas claves de $_SESSION que el login de las vistas
// (controler_login.php) para que un usuario que entra por Angular tambien quede autenticado en las vistas PHP.
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    // Devuelvo los datos del usuario autenticado o null
    public static function usuario(): ?array
    {
        if (!isset($_SESSION['sesion email'], $_SESSION['id_usuario'])) {
            return null;
        }
        return [
            'id' => (int) $_SESSION['id_usuario'],
            'email' => $_SESSION['sesion email'],
            'nombres' => $_SESSION['name'] ?? '',
            'rol' => $_SESSION['role'] ?? '',
        ];
    }

    public static function exigirSesion(): array
    {
        return self::usuario() ?? throw ErrorHttp::noAutenticado();
    }

    public static function exigirRol(array $rolesPermitidos): array
    {
        $usuario = self::exigirSesion();
        if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
            throw ErrorHttp::prohibido();
        }
        return $usuario;
    }

    public static function esEstudiante(array $usuario): bool
    {
        return $usuario['rol'] === 'ESTUDIANTE';
    }

    public static function abrir(Usuario $usuario): void
    {
        // Regenero el id de sesion al iniciar sesion para evitar fijacion de sesion
        session_regenerate_id(true);
        $_SESSION['sesion email'] = $usuario->email;
        $_SESSION['name'] = $usuario->nombres;
        $_SESSION['id_usuario'] = $usuario->id_usuario;
        $_SESSION['role'] = $usuario->rol?->nombre_rol ?? '';
    }

    public static function cerrar(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
        }
        session_destroy();
    }
}
