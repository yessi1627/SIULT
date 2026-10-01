<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Usuario;
use Api\Seguridad\Sesion;

final class AuthControlador
{
    // POST /auth/login { email, password }
    public function login(Peticion $peticion): Respuesta
    {
        $validador = new Validador($peticion);
        $email = $validador->email('email');
        $password = $validador->texto('password', 'La contraseña');
        $validador->verificar();

        // Cargo el rol en la misma consulta (eager loading) porque lo necesito para la sesion
        $usuario = Usuario::with('rol')->activos()->where('email', $email)->first();

        // Uso el mismo mensaje si el email no existe o la clave es incorrecta para no revelar que emails existen
        if (!$usuario || !password_verify((string) $password, $usuario->password)) {
            throw new ErrorHttp(401, 'Los datos son incorrectos, verifiquelos y vuelva a intentarlo');
        }

        Sesion::abrir($usuario);
        return Respuesta::ok($usuario->paraApi());
    }

    // POST /auth/logout
    public function logout(Peticion $peticion): Respuesta
    {
        Sesion::cerrar();
        return Respuesta::ok(null);
    }

    // GET /auth/me: datos del usuario autenticado.
    // Tambien lo usa el API Gateway para validar la sesion antes de llamar a los microservicios;
    // por eso incluyo las materias del estudiante (el microservicio de notificaciones filtra con ellas).
    public function yo(Peticion $peticion): Respuesta
    {
        $sesion = Sesion::exigirSesion();
        $usuario = Usuario::with(['rol', 'materias'])->activos()->find($sesion['id']);
        if (!$usuario) {
            Sesion::cerrar();
            throw ErrorHttp::noAutenticado();
        }
        return Respuesta::ok($usuario->paraApi() + [
            'materias' => $usuario->materias->pluck('id_materia')->map(fn($id) => (int) $id)->values()->all(),
        ]);
    }
}
