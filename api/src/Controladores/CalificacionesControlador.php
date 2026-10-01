<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Calificacion;
use Api\Modelos\Tarea;
use Api\Modelos\Usuario;
use Api\Seguridad\Sesion;
use Illuminate\Database\Eloquent\Builder;

final class CalificacionesControlador
{
    // GET /calificaciones?id_tarea=5 (el estudiante solo ve sus propias notas)
    public function listar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        $calificaciones = $this->consulta($usuario, $peticion)
            ->with(['usuario', 'tarea.materia'])
            ->orderByDesc('fecha_calificacion')
            ->get();
        return Respuesta::ok($calificaciones->map(fn(Calificacion $c) => $c->paraApi())->values());
    }

    // POST /calificaciones { id_tarea, id_usuario, nota, observacion }
    // Si el estudiante ya tenia nota en esa tarea, la actualizo (una nota por tarea y estudiante)
    public function guardar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR', 'PROFESOR']);
        $validador = new Validador($peticion);
        $idTarea = $validador->entero('id_tarea', 'La tarea');
        $idUsuario = $validador->entero('id_usuario', 'El estudiante');
        $nota = $validador->decimal('nota', 'La nota', 0, 5);
        $observacion = $validador->texto('observacion', 'La observacion', false, 2000);
        $validador->verificar();

        $tarea = Tarea::find($idTarea) ?? throw ErrorHttp::noEncontrado('La tarea');

        // Verifico que el estudiante exista, este activo y matriculado en la materia de la tarea
        $matriculado = Usuario::activos()
            ->conRol('ESTUDIANTE')
            ->whereKey($idUsuario)
            ->whereHas('materias', fn(Builder $m) => $m->where('materias.id_materia', $tarea->id_materia))
            ->exists();
        if (!$matriculado) {
            throw ErrorHttp::solicitudInvalida('El estudiante indicado no esta matriculado en la materia de la tarea');
        }

        $calificacion = Calificacion::updateOrCreate(
            ['id_tarea' => $idTarea, 'id_usuario' => $idUsuario],
            ['nota' => $nota, 'observacion' => $observacion, 'fecha_calificacion' => date('Y-m-d H:i:s')]
        );
        return new Respuesta($calificacion->paraApi(), $calificacion->wasRecentlyCreated ? 201 : 200);
    }

    // GET /calificaciones/promedios?id_materia=3
    // El calculo lo hacen las funciones puras de lib/funciones_notas.php
    public function promedios(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        $filas = $this->consulta($usuario, $peticion)
            ->with('usuario')
            ->get()
            ->map(fn(Calificacion $c) => [
                'id_usuario' => $c->id_usuario,
                'nombres' => $c->usuario?->nombres ?? '',
                'nota' => $c->nota,
            ])
            ->all();

        $promedios = promediosPorEstudiante($filas);
        usort($promedios, fn(array $a, array $b) => strcmp($a['nombres'], $b['nombres']));
        return Respuesta::ok($promedios);
    }

    private function consulta(array $usuario, Peticion $peticion): Builder
    {
        $idTarea = (int) $peticion->query('id_tarea');
        $idMateria = (int) $peticion->query('id_materia');
        return Calificacion::query()
            ->when(Sesion::esEstudiante($usuario), fn(Builder $c) => $c->where('id_usuario', $usuario['id']))
            ->when($idTarea > 0, fn(Builder $c) => $c->where('id_tarea', $idTarea))
            ->when($idMateria > 0, fn(Builder $c) => $c->whereHas('tarea', fn(Builder $t) => $t->where('id_materia', $idMateria)));
    }
}
