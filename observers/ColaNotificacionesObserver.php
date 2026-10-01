<?php
require_once __DIR__ . '/Observer.php';
require_once __DIR__ . '/../config/redis.php';

/**
 * Observer que publica cada evento en la cola de Redis `cola:notificaciones` (mensajeria asincrona).
 * El microservicio de notificaciones (servicios/notificaciones) consume la cola y guarda los avisos
 * en MongoDB. Si el microservicio esta caido, los mensajes esperan en la cola; si Redis esta caido,
 * quedan en la tabla cola_respaldo de MySQL hasta que Redis vuelva.
 */
class ColaNotificacionesObserver implements Observer
{
    public function update($eventData)
    {
        global $pdo;
        $mensaje = [
            'id_evento' => bin2hex(random_bytes(8)),
            'tipo' => $eventData['tipo'] ?? 'tarea_creada',
            'mensaje' => $eventData['mensaje'],
            'id_tarea' => (int) $eventData['id_tarea'],
            'id_materia' => isset($eventData['id_materia']) ? (int) $eventData['id_materia'] : null,
            'fecha' => date('Y-m-d H:i:s'),
        ];
        publicarEnCola($pdo, COLA_NOTIFICACIONES, $mensaje);
    }
}
