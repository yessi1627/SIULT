<?php
// Conexion a Redis (cola de mensajes y, en la Fase 4, cache y registro de servicios).
// Si Redis no responde, las funciones devuelven null / false y el sistema sigue funcionando
// (tolerancia a fallos): nunca dejo que una caida de Redis tumbe una pagina.

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

const COLA_NOTIFICACIONES = 'cola:notificaciones';

// Devuelvo un cliente conectado o null si Redis no esta disponible.
// Guardo el resultado para no intentar conectar varias veces en la misma peticion.
function clienteRedis(): ?Client
{
    static $cliente = false;
    if ($cliente !== false) {
        return $cliente;
    }
    try {
        $intento = new Client([
            'scheme' => 'tcp',
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'database' => (int) (getenv('REDIS_DB') ?: 0),
            // Tiempos cortos: si Redis esta caido prefiero responder rapido usando el respaldo
            'timeout' => 0.5,
            'read_write_timeout' => 1.0,
        ]);
        $intento->ping();
        $cliente = $intento;
    } catch (Throwable $e) {
        error_log('Redis no disponible: ' . $e->getMessage());
        $cliente = null;
    }
    return $cliente;
}

/**
 * Publico un mensaje en una cola de Redis (LPUSH). El consumidor lo saca con BRPOP,
 * asi que la cola funciona en orden FIFO: el primero en entrar es el primero en procesarse.
 * Si Redis no responde, guardo el mensaje en la tabla cola_respaldo de MySQL (patron outbox)
 * para reenviarlo despues. Devuelvo 'redis' o 'respaldo' segun donde quedo el mensaje.
 */
function publicarEnCola(PDO $pdo, string $cola, array $mensaje): string
{
    $contenido = json_encode($mensaje, JSON_UNESCAPED_UNICODE);
    $redis = clienteRedis();
    if ($redis !== null) {
        try {
            $redis->lpush($cola, [$contenido]);
            // Aprovecho que Redis respondio para vaciar lo que quedo pendiente en el respaldo
            reenviarRespaldo($pdo, $redis);
            return 'redis';
        } catch (Throwable $e) {
            error_log('No se pudo publicar en Redis: ' . $e->getMessage());
        }
    }
    $sentencia = $pdo->prepare('INSERT INTO cola_respaldo (cola, contenido) VALUES (:cola, :contenido)');
    $sentencia->execute([':cola' => $cola, ':contenido' => $contenido]);
    return 'respaldo';
}

// Reenvio a Redis los mensajes guardados en el respaldo, en el mismo orden en que se crearon.
// Devuelvo cuantos mensajes se reenviaron.
function reenviarRespaldo(PDO $pdo, Client $redis, int $limite = 100): int
{
    $pendientes = $pdo->query("SELECT id_mensaje, cola, contenido FROM cola_respaldo ORDER BY id_mensaje LIMIT $limite")
        ->fetchAll(PDO::FETCH_ASSOC);
    $borrar = $pdo->prepare('DELETE FROM cola_respaldo WHERE id_mensaje = :id');
    $reenviados = 0;
    foreach ($pendientes as $pendiente) {
        $redis->lpush($pendiente['cola'], [$pendiente['contenido']]);
        $borrar->execute([':id' => $pendiente['id_mensaje']]);
        $reenviados++;
    }
    return $reenviados;
}

function mensajesEnRespaldo(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM cola_respaldo')->fetchColumn();
}
