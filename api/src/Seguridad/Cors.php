<?php
declare(strict_types=1);

namespace Api\Seguridad;

use Api\Http\ErrorHttp;

// CORS y proteccion contra peticiones de otros sitios (CSRF) para una API que usa la cookie de sesion.
final class Cors
{
    // Origenes del frontend que pueden llamar a la API enviando la cookie de sesion
    private const ORIGENES_PERMITIDOS = ['http://localhost:4200', 'http://127.0.0.1:4200'];

    public static function aplicar(): void
    {
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origen, self::ORIGENES_PERMITIDOS, true)) {
            header('Access-Control-Allow-Origin: ' . $origen);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    }

    // Las peticiones que modifican datos deben traer la cabecera X-Requested-With.
    // Un formulario HTML de otro sitio no puede enviar cabeceras personalizadas, y si lo intenta
    // con fetch el navegador hace una verificacion previa (preflight) que este CORS rechaza.
    // Asi la cookie de sesion no se puede usar desde otro sitio para crear o borrar datos.
    public static function verificarOrigenEscritura(string $metodo): void
    {
        if (in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
            throw new ErrorHttp(403, 'Falta la cabecera X-Requested-With');
        }
    }
}
