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
    // GET /materias: el estudiante solo ve las materias en las que esta matriculado.
    // CACHE: el listado casi no cambia, asi que lo guardo en Redis una hora. La cabecera X-Cache
    // indica si vino de Redis (HIT) o de MySQL (MISS).
    public function listar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        $esEstudiante = Sesion::esEstudiante($usuario);
        $clave = PREFIJO_CACHE_MATERIAS . ($esEstudiante ? 'estudiante:' . $usuario['id'] : 'todas');

        [$materias, $estado] = recordarEnCache($clave, TTL_CACHE_SEGUNDOS, function () use ($usuario, $esEstudiante) {
            // withCount agrega la cantidad de tareas con una subconsulta, sin cargar las tareas
            $consulta = Materia::activas()->withCount('tareas')->orderBy('nombre_materia');
            if ($esEstudiante) {
                $consulta->delEstudiante($usuario['id']);
            }
            return $consulta->get()->map(fn(Materia $m) => $m->paraApi())->values()->all();
        });
        header('X-Cache: ' . $estado);
        return Respuesta::ok($materias);
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
        // La lista de materias cambio: borro su cache
        invalidarCache(PREFIJO_CACHE_MATERIAS);
        return Respuesta::creado($materia->paraApi());
    }

    public function actualizar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $materia = $this->buscar($peticion->idRuta());
        $materia->update(['nombre_materia' => $this->validarNombre($peticion)]);
        // La lista de materias cambio: borro su cache
        invalidarCache(PREFIJO_CACHE_MATERIAS);
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
        // La lista de materias cambio: borro su cache
        invalidarCache(PREFIJO_CACHE_MATERIAS);
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
