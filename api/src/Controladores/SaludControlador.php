<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\BaseDatos;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Throwable;

final class SaludControlador
{
    // GET /salud: indica si la API, la base de datos y Redis responden. No requiere sesion.
    // El gateway y el monitoreo lo usan para saber si el servicio esta vivo.
    // Si Redis cae el servicio sigue "ok" (degradacion controlada): los mensajes van al respaldo de MySQL.
    public function revisar(Peticion $peticion): Respuesta
    {
        $inicio = microtime(true);
        $baseDatos = 'ok';
        $pendientes = null;
        try {
            BaseDatos::pdo()->query('SELECT 1');
            $pendientes = mensajesEnRespaldo(BaseDatos::pdo());
        } catch (Throwable $e) {
            error_log('Salud: la base de datos no responde: ' . $e->getMessage());
            $baseDatos = 'sin conexion';
        }

        $redis = clienteRedis();
        $colaRedis = null;
        if ($redis !== null) {
            try {
                $colaRedis = (int) $redis->llen(COLA_NOTIFICACIONES);
            } catch (Throwable) {
                $redis = null;
            }
        }

        $estado = $baseDatos === 'ok' ? 'ok' : 'degradado';
        return new Respuesta([
            'servicio' => 'api-php',
            'estado' => $estado,
            'base_datos' => $baseDatos,
            'redis' => $redis !== null ? 'ok' : 'sin conexion',
            'cola_notificaciones' => $colaRedis,
            'mensajes_en_respaldo' => $pendientes,
            'tiempo_ms' => round((microtime(true) - $inicio) * 1000, 1),
            'fecha' => date('Y-m-d H:i:s'),
        ], $estado === 'ok' ? 200 : 503);
    }
}
