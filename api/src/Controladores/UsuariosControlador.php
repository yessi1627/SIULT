<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Rol;
use Api\Modelos\Usuario;
use Api\Seguridad\Sesion;

// CRUD de usuarios: exclusivo del administrador, igual que en las vistas PHP
final class UsuariosControlador
{
    // GET /usuarios?rol=ESTUDIANTE
    public function listar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        // with('rol') trae los roles de todos los usuarios en UNA consulta adicional,
        // en vez de una consulta por usuario (evito el problema N+1)
        $consulta = Usuario::with('rol')->activos()->orderBy('nombres');
        $rol = $peticion->query('rol');
        if ($rol) {
            $consulta->conRol(mb_strtoupper($rol, 'UTF-8'));
        }
        return Respuesta::ok($consulta->get()->map(fn(Usuario $u) => $u->paraApi())->values());
    }

    // GET /usuarios/{id}
    public function ver(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        return Respuesta::ok($this->buscar($peticion->idRuta())->paraApi());
    }

    // POST /usuarios { nombres, email, rol_id, password, password_confirmacion }
    public function crear(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $datos = $this->validar($peticion, true);
        $usuario = Usuario::create($datos + ['estado' => '1']);
        // Cambia la cantidad de usuarios por rol: borro la cache de roles
        invalidarCache(PREFIJO_CACHE_ROLES);
        return Respuesta::creado($usuario->load('rol')->paraApi());
    }

    // PUT /usuarios/{id} (la contraseña es opcional: si llega vacia no se cambia)
    public function actualizar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $usuario = $this->buscar($peticion->idRuta());
        $usuario->update($this->validar($peticion, false, $usuario->id_usuario));
        // Cambia la cantidad de usuarios por rol: borro la cache de roles
        invalidarCache(PREFIJO_CACHE_ROLES);
        return Respuesta::ok($usuario->load('rol')->paraApi());
    }

    // DELETE /usuarios/{id}
    public function eliminar(Peticion $peticion): Respuesta
    {
        $sesion = Sesion::exigirRol(['ADMINISTRADOR']);
        $usuario = $this->buscar($peticion->idRuta());
        if ($usuario->id_usuario === $sesion['id']) {
            throw ErrorHttp::conflicto('No puede eliminar su propio usuario');
        }
        $usuario->delete();
        // Cambia la cantidad de usuarios por rol: borro la cache de roles
        invalidarCache(PREFIJO_CACHE_ROLES);
        return Respuesta::ok(null);
    }

    private function buscar(int $id): Usuario
    {
        return Usuario::with('rol')->activos()->find($id) ?? throw ErrorHttp::noEncontrado('El usuario');
    }

    private function validar(Peticion $peticion, bool $esNuevo, ?int $idActual = null): array
    {
        $validador = new Validador($peticion);
        $nombres = $validador->texto('nombres', 'El nombre');
        $email = $validador->email('email');
        $rolId = $validador->entero('rol_id', 'El rol');
        $password = $validador->texto('password', 'La contraseña', $esNuevo);
        $confirmacion = $peticion->campo('password_confirmacion');

        if ($password !== null && $password !== $confirmacion) {
            $validador->agregarError('password_confirmacion', 'Las contraseñas no coinciden');
        }
        if ($rolId !== null && !Rol::activos()->whereKey($rolId)->exists()) {
            $validador->agregarError('rol_id', 'El rol no existe');
        }
        $validador->verificar();

        $emailRepetido = Usuario::where('email', $email)
            ->when($idActual, fn($c) => $c->where('id_usuario', '!=', $idActual))
            ->exists();
        if ($emailRepetido) {
            throw ErrorHttp::conflicto('El email del usuario ya existe');
        }

        $datos = ['nombres' => $nombres, 'email' => $email, 'rol_id' => $rolId];
        if ($password !== null) {
            $datos['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        return $datos;
    }
}
