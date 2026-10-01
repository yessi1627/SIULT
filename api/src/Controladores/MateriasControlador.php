<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Materia;
use Api\Seguridad\Sesion;

final class MateriasControlador
{
    // GET /materias: el estudiante solo ve las materias en las que esta matriculado
    public function listar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        // withCount agrega la cantidad de tareas con una subconsulta, sin cargar las tareas
        $consulta = Materia::activas()->withCount('tareas')->orderBy('nombre_materia');
        if (Sesion::esEstudiante($usuario)) {
            $consulta->delEstudiante($usuario['id']);
        }
        return Respuesta::ok($consulta->get()->map(fn(Materia $m) => $m->paraApi())->values());
    }

    public function ver(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        $consulta = Materia::activas()->withCount('tareas');
        if (Sesion::esEstudiante($usuario)) {
            $consulta->delEstudiante($usuario['id']);
        }
        $materia = $consulta->find($peticion->idRuta()) ?? throw ErrorHttp::noEncontrado('La materia');
        return Respuesta::ok($materia->paraApi());
    }

    public function crear(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $materia = Materia::create(['nombre_materia' => $this->validarNombre($peticion), 'estado' => '1']);
        return Respuesta::creado($materia->paraApi());
    }

    public function actualizar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $materia = $this->buscar($peticion->idRuta());
        $materia->update(['nombre_materia' => $this->validarNombre($peticion)]);
        return Respuesta::ok($materia->paraApi());
    }

    // No permito eliminar una materia con tareas, igual que el controlador PHP
    public function eliminar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $materia = $this->buscar($peticion->idRuta());
        $totalTareas = $materia->tareas()->count();
        if ($totalTareas > 0) {
            throw ErrorHttp::conflicto("No se puede eliminar esta materia porque tiene $totalTareas tarea(s) asociada(s)");
        }
        $materia->delete();
        return Respuesta::ok(null);
    }

    private function buscar(int $id): Materia
    {
        return Materia::activas()->find($id) ?? throw ErrorHttp::noEncontrado('La materia');
    }

    private function validarNombre(Peticion $peticion): string
    {
        $validador = new Validador($peticion);
        $nombre = $validador->texto('nombre_materia', 'El nombre de la materia');
        $validador->verificar();
        return (string) $nombre;
    }
}
