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

    /**
     * POST /calificaciones { id_tarea, id_usuario, nota, observacion, version }
     *
     * CONCURRENCIA CON BLOQUEO OPTIMISTA: no bloqueo la fila mientras el profesor escribe la nota.
     * El cliente envia la `version` que leyo; al guardar ejecuto
     *     UPDATE ... SET version = version + 1 WHERE id_tarea = ? AND id_usuario = ? AND version = ?
     * Si otro profesor guardo antes, la version ya cambio, el UPDATE afecta 0 filas y respondo 409
     * en lugar de pisar su nota (se evita la "actualizacion perdida").
     * Sin `version` se entiende que el cliente cree que la nota es nueva; si ya existe tambien es 409.
     */
    public function guardar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR', 'PROFESOR']);
        $validador = new Validador($peticion);
        $idTarea = $validador->entero('id_tarea', 'La tarea');
        $idUsuario = $validador->entero('id_usuario', 'El estudiante');
        $nota = $validador->decimal('nota', 'La nota', 0, 5);
        $observacion = $validador->texto('observacion', 'La observacion', false, 2000);
        $version = $validador->entero('version', 'La version', false);
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

        $datos = ['nota' => $nota, 'observacion' => $observacion, 'fecha_calificacion' => date('Y-m-d H:i:s')];
        $clave = ['id_tarea' => $idTarea, 'id_usuario' => $idUsuario];

        if ($version === null) {
            // Nota nueva: la llave unica (id_tarea, id_usuario) impide que dos profesores la creen a la vez
            if (Calificacion::where($clave)->exists()) {
                throw ErrorHttp::conflicto('Otro usuario ya registró una nota para este estudiante. Recargue para verla.');
            }
            $calificacion = Calificacion::create($clave + $datos + ['version' => 1]);
            return Respuesta::creado($calificacion->paraApi());
        }

        // increment() genera: UPDATE ... SET version = version + 1, nota = ?, ... WHERE ... AND version = ?
        $filas = Calificacion::where($clave)->where('version', $version)->increment('version', 1, $datos);
        if ($filas === 0) {
            throw ErrorHttp::conflicto('Otro usuario modificó esta nota mientras usted la editaba. Recargue para ver el valor actual.');
        }
        return Respuesta::ok(Calificacion::where($clave)->firstOrFail()->paraApi());
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
