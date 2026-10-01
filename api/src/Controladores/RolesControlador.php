<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Rol;
use Api\Seguridad\Sesion;

// CRUD de roles: exclusivo del administrador
final class RolesControlador
{
    public function listar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $roles = Rol::activos()->withCount('usuarios')->orderBy('nombre_rol')->get();
        return Respuesta::ok($roles->map(fn(Rol $r) => $r->paraApi() + ['cantidad_usuarios' => (int) $r->usuarios_count])->values());
    }

    public function ver(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        return Respuesta::ok($this->buscar($peticion->idRuta())->paraApi());
    }

    public function crear(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $nombre = $this->validarNombre($peticion);
        $rol = Rol::create(['nombre_rol' => $nombre, 'estado' => '1']);
        return Respuesta::creado($rol->paraApi());
    }

    public function actualizar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $rol = $this->buscar($peticion->idRuta());
        $rol->update(['nombre_rol' => $this->validarNombre($peticion, $rol->id_rol)]);
        return Respuesta::ok($rol->paraApi());
    }

    public function eliminar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $rol = $this->buscar($peticion->idRuta());
        if ($rol->usuarios()->exists()) {
            throw ErrorHttp::conflicto('No se puede eliminar el rol porque tiene usuarios asignados');
        }
        $rol->delete();
        return Respuesta::ok(null);
    }

    private function buscar(int $id): Rol
    {
        return Rol::activos()->find($id) ?? throw ErrorHttp::noEncontrado('El rol');
    }

    // Guardo el nombre en mayusculas, igual que el formulario PHP
    private function validarNombre(Peticion $peticion, ?int $idActual = null): string
    {
        $validador = new Validador($peticion);
        $nombre = $validador->texto('nombre_rol', 'El nombre del rol');
        $validador->verificar();
        $nombre = mb_strtoupper((string) $nombre, 'UTF-8');

        $repetido = Rol::where('nombre_rol', $nombre)
            ->when($idActual, fn($c) => $c->where('id_rol', '!=', $idActual))
            ->exists();
        if ($repetido) {
            throw ErrorHttp::conflicto('Este rol ya existe');
        }
        return $nombre;
    }
}
