<?php
// Cache en Redis para consultas que cambian poco (materias y roles).
// Patron "cache-aside": primero busco en Redis; si no esta, consulto MySQL y guardo el resultado.
// Cuando se crea, edita o elimina un dato, borro (invalido) las claves relacionadas para no
// mostrar informacion vieja. Si Redis no responde, consulto MySQL directamente (sin cache).

require_once __DIR__ . '/redis.php';

const PREFIJO_CACHE_MATERIAS = 'cache:materias:';
const PREFIJO_CACHE_ROLES = 'cache:roles';
const TTL_CACHE_SEGUNDOS = 3600;

/**
 * Devuelvo [valor, 'HIT' | 'MISS' | 'SIN-CACHE'].
 * $calcular solo se ejecuta si el valor no esta en Redis.
 */
function recordarEnCache(string $clave, int $ttlSegundos, callable $calcular): array
{
    $redis = clienteRedis();
    if ($redis !== null) {
        try {
            $guardado = $redis->get($clave);
            if ($guardado !== null) {
                return [json_decode($guardado, true), 'HIT'];
            }
        } catch (Throwable $e) {
            error_log('Cache: no se pudo leer ' . $clave . ': ' . $e->getMessage());
            $redis = null;
        }
    }

    $valor = $calcular();
    if ($redis === null) {
        return [$valor, 'SIN-CACHE'];
    }
    try {
        $redis->setex($clave, $ttlSegundos, json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    } catch (Throwable $e) {
        error_log('Cache: no se pudo guardar ' . $clave . ': ' . $e->getMessage());
    }
    return [$valor, 'MISS'];
}

// Borro todas las claves que empiezan con $prefijo. Uso SCAN (no KEYS) para no bloquear Redis.
function invalidarCache(string $prefijo): void
{
    $redis = clienteRedis();
    if ($redis === null) {
        return;
    }
    try {
        $cursor = '0';
        do {
            [$cursor, $claves] = $redis->scan($cursor, ['MATCH' => $prefijo . '*', 'COUNT' => 100]);
            if ($claves) {
                $redis->del($claves);
            }
        } while ($cursor !== '0' && $cursor !== 0);
    } catch (Throwable $e) {
        error_log('Cache: no se pudo invalidar ' . $prefijo . ': ' . $e->getMessage());
    }
}
