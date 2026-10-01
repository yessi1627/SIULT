<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\BaseDatos;
use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Materia;
use Api\Modelos\Tarea;
use Api\Modelos\Usuario;
use Api\Seguridad\Sesion;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use ColaNotificacionesObserver;
use NotificacionObserver;
use Subject;

require_once __DIR__ . '/../../../observers/Subject.php';
require_once __DIR__ . '/../../../observers/NotificacionObserver.php';
require_once __DIR__ . '/../../../observers/ColaNotificacionesObserver.php';

final class TareasControlador
{
    private const ORDENES = [
        'titulo' => 'titulo',
        'fecha_entrega' => 'fecha_entrega',
        'estado' => 'estado',
    ];

    // GET /tareas?q=texto&id_materia=3&orden=fecha_entrega
    public function listar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        actualizarTareasVencidas(BaseDatos::pdo());

        $consulta = $this->consultaBase($usuario)
            ->buscar($peticion->query('q'))
            ->when((int) $peticion->query('id_materia') > 0, fn(Builder $c) => $c->where('id_materia', (int) $peticion->query('id_materia')))
            ->orderBy(self::ORDENES[$peticion->query('orden', 'fecha_entrega')] ?? 'fecha_entrega')
            ->orderBy('id_tarea');

        $tareas = $consulta->get();
        $esEstudiante = Sesion::esEstudiante($usuario);
        return Respuesta::ok($tareas->map(fn(Tarea $t) => $esEstudiante ? $t->paraEstudiante() : $t->paraApi())->values());
    }

    // GET /tareas/{id}: al profesor le agrego la lista de estudiantes con su entrega y su nota
    public function ver(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        $tarea = $this->consultaBase($usuario)->find($peticion->idRuta()) ?? throw ErrorHttp::noEncontrado('La tarea');

        if (Sesion::esEstudiante($usuario)) {
            return Respuesta::ok($tarea->paraEstudiante());
        }

        // Cargo los estudiantes matriculados con su entrega y su calificacion de ESTA tarea en 3 consultas en total
        $estudiantes = Usuario::activos()
            ->conRol('ESTUDIANTE')
            ->whereHas('materias', fn(Builder $m) => $m->where('materias.id_materia', $tarea->id_materia))
            ->with([
                'entregas' => fn($e) => $e->where('id_tarea', $tarea->id_tarea),
                'calificaciones' => fn($c) => $c->where('id_tarea', $tarea->id_tarea),
            ])
            ->orderBy('nombres')
            ->get();

        $abierta = tareaAbiertaParaEntregas($tarea->getAttributes());
        return Respuesta::ok($tarea->paraApi() + [
            'estudiantes' => $estudiantes->map(function (Usuario $estudiante) use ($abierta) {
                $entrega = $estudiante->entregas->first();
                $calificacion = $estudiante->calificaciones->first();
                return [
                    'id' => $estudiante->id_usuario,
                    'nombres' => $estudiante->nombres,
                    'email' => $estudiante->email,
                    'entrega' => $entrega?->paraApi(),
                    'calificacion' => $calificacion?->paraApi(),
                    'estado_entrega' => $entrega ? ESTADO_ENTREGA_ENTREGADA : ($abierta ? ESTADO_ENTREGA_PENDIENTE : ESTADO_ENTREGA_NO_ENTREGO),
                ];
            })->values(),
        ]);
    }

    // POST /tareas { id_materia, titulo, descripcion, fecha_entrega, hora_entrega }
    public function crear(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR', 'PROFESOR']);
        $datos = $this->validar($peticion, false);
        $tarea = Tarea::create($datos + ['estado' => ESTADO_TAREA_PENDIENTE]);
        $tarea->load('materia');

        $this->notificarCreacion($tarea);
        return Respuesta::creado($tarea->paraApi());
    }

    // PUT /tareas/{id} { ..., estado }
    public function actualizar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirRol(['ADMINISTRADOR', 'PROFESOR']);
        $tarea = $this->consultaBase($usuario)->find($peticion->idRuta()) ?? throw ErrorHttp::noEncontrado('La tarea');
        $tarea->update($this->validar($peticion, true));
        return Respuesta::ok($tarea->load('materia')->paraApi());
    }

    // DELETE /tareas/{id}: borro en una transaccion y despues borro los archivos fisicos
    public function eliminar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirRol(['ADMINISTRADOR', 'PROFESOR']);
        $tarea = $this->consultaBase($usuario)->find($peticion->idRuta()) ?? throw ErrorHttp::noEncontrado('La tarea');

        $rutas = [
            ...$tarea->archivos->pluck('ruta_archivo')->all(),
            ...$tarea->entregas()->pluck('ruta_archivo')->all(),
        ];
        BaseDatos::transaccion(function () use ($tarea) {
            // Las entregas, calificaciones y notificaciones se borran en cascada por las llaves foraneas
            $tarea->archivos()->delete();
            $tarea->delete();
        });
        array_map('borrarArchivoSubido', $rutas);

        return Respuesta::ok(null);
    }

    // Consulta con las relaciones que necesita la respuesta (eager loading para evitar N+1).
    // Si es estudiante, limito a sus materias y cargo solo SU entrega y SU calificacion.
    private function consultaBase(array $usuario): Builder
    {
        $consulta = Tarea::query()->with(['materia', 'archivos']);
        if (Sesion::esEstudiante($usuario)) {
            $consulta->delEstudiante($usuario['id'])->with([
                'entregas' => fn($e) => $e->where('id_usuario', $usuario['id']),
                'calificaciones' => fn($c) => $c->where('id_usuario', $usuario['id']),
            ]);
        }
        return $consulta;
    }

    private function validar(Peticion $peticion, bool $conEstado): array
    {
        $validador = new Validador($peticion);
        $idMateria = $validador->entero('id_materia', 'La materia');
        $datos = [
            'id_materia' => $idMateria,
            'titulo' => $validador->texto('titulo', 'El titulo'),
            'descripcion' => $validador->texto('descripcion', 'La descripcion', true, 5000),
            'fecha_entrega' => $validador->fecha('fecha_entrega', 'La fecha de entrega'),
            'hora_entrega' => $validador->hora('hora_entrega', 'La hora de entrega'),
        ];
        if ($conEstado) {
            $datos['estado'] = $validador->enLista('estado', 'El estado', ESTADOS_TAREA);
        }
        if ($idMateria !== null && !Materia::activas()->whereKey($idMateria)->exists()) {
            $validador->agregarError('id_materia', 'La materia no existe');
        }
        $validador->verificar();
        return $datos;
    }

    // Reutilizo el patron Observer de las vistas PHP (observers/): un observer guarda el aviso en MySQL
    // y otro lo publica en la cola de Redis para el microservicio de notificaciones (mensajeria asincrona)
    private function notificarCreacion(Tarea $tarea): void
    {
        // Los observers usan la variable global $pdo; les paso la conexion de Eloquent
        $GLOBALS['pdo'] = BaseDatos::pdo();
        $sujeto = new Subject();
        $sujeto->addObserver(new NotificacionObserver());
        $sujeto->addObserver(new ColaNotificacionesObserver());

        $nombreMateria = $tarea->materia?->nombre_materia ?? '';
        $sujeto->notifyObservers([
            'tipo' => 'tarea_creada',
            'mensaje' => "Se ha creado una nueva tarea en la materia $nombreMateria: {$tarea->titulo}",
            'id_tarea' => $tarea->id_tarea,
            'id_materia' => $tarea->id_materia,
        ]);

        $intervalo = (new DateTime())->diff(new DateTime($tarea->fecha_entrega));
        if ($intervalo->days <= 2 && $intervalo->invert === 0) {
            $sujeto->notifyObservers([
                'tipo' => 'tarea_por_vencer',
                'mensaje' => "La tarea '{$tarea->titulo}' de la materia '$nombreMateria' esta proxima a vencer",
                'id_tarea' => $tarea->id_tarea,
                'id_materia' => $tarea->id_materia,
            ]);
        }
    }
}
