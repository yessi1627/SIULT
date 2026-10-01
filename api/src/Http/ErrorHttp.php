<?php
declare(strict_types=1);

namespace Api\Http;

use RuntimeException;

// Error que los controladores lanzan para cortar la peticion con un codigo HTTP concreto.
// El front controller lo atrapa y responde { "data": null, "error": "...", "detalles": {...} }.
final class ErrorHttp extends RuntimeException
{
    public function __construct(
        public readonly int $codigoHttp,
        string $mensaje,
        public readonly array $detalles = []
    ) {
        parent::__construct($mensaje);
    }

    public static function solicitudInvalida(string $mensaje, array $detalles = []): self
    {
        return new self(422, $mensaje, $detalles);
    }

    public static function noAutenticado(): self
    {
        return new self(401, 'Debe iniciar sesion');
    }

    public static function prohibido(): self
    {
        return new self(403, 'No tiene permisos para realizar esta accion');
    }

    public static function noEncontrado(string $recurso = 'El recurso'): self
    {
        return new self(404, $recurso . ' no existe');
    }

    public static function conflicto(string $mensaje): self
    {
        return new self(409, $mensaje);
    }
}
