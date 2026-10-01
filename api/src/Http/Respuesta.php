<?php
declare(strict_types=1);

namespace Api\Http;

// Respuesta JSON uniforme de toda la API: { "data": ..., "error": null }
final class Respuesta
{
    public function __construct(
        public readonly mixed $data = null,
        public readonly int $codigoHttp = 200
    ) {
    }

    public static function ok(mixed $data): self
    {
        return new self($data, 200);
    }

    public static function creado(mixed $data): self
    {
        return new self($data, 201);
    }

    public function enviar(): void
    {
        http_response_code($this->codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $this->data, 'error' => null], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function enviarError(int $codigoHttp, string $mensaje, array $detalles = []): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        $cuerpo = ['data' => null, 'error' => $mensaje];
        if ($detalles !== []) {
            $cuerpo['detalles'] = $detalles;
        }
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
