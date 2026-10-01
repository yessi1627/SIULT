<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\BaseDatos;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Throwable;

final class SaludControlador
{
    // GET /salud: indica si la API y la base de datos responden. No requiere sesion.
    // El gateway y el monitoreo lo usan para saber si el servicio esta vivo.
    public function revisar(Peticion $peticion): Respuesta
    {
        $inicio = microtime(true);
        try {
            BaseDatos::pdo()->query('SELECT 1');
            $baseDatos = 'ok';
        } catch (Throwable $e) {
            error_log('Salud: la base de datos no responde: ' . $e->getMessage());
            $baseDatos = 'sin conexion';
        }
        $estado = $baseDatos === 'ok' ? 'ok' : 'degradado';
        return new Respuesta([
            'servicio' => 'api-php',
            'estado' => $estado,
            'base_datos' => $baseDatos,
            'tiempo_ms' => round((microtime(true) - $inicio) * 1000, 1),
            'fecha' => date('Y-m-d H:i:s'),
        ], $estado === 'ok' ? 200 : 503);
    }
}
