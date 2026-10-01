<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Modelos\Notificacion;
use Api\Seguridad\Sesion;
use Illuminate\Database\Eloquent\Builder;

final class NotificacionesControlador
{
    // GET /notificaciones?limite=20
    // El estudiante solo recibe avisos de tareas de sus materias
    public function listar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        $limite = min(max((int) $peticion->query('limite', '20'), 1), 100);

        $notificaciones = Notificacion::query()
            ->when(Sesion::esEstudiante($usuario), fn(Builder $c) => $c->whereHas(
                'tarea',
                fn(Builder $t) => $t->delEstudiante($usuario['id'])
            ))
            ->orderByDesc('fecha_creacion')
            ->orderByDesc('id_notificacion')
            ->limit($limite)
            ->get();

        return Respuesta::ok($notificaciones->map(fn(Notificacion $n) => $n->paraApi())->values());
    }
}
