<?php
declare(strict_types=1);

namespace Api\Http;

// Datos de la peticion actual: metodo, ruta, parametros de la URL y cuerpo JSON o de formulario
final class Peticion
{
    private ?array $cuerpo = null;

    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public array $parametrosRuta = []
    ) {
    }

    public static function desdeGlobales(string $rutaBase): self
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Quito la parte de la URL donde vive la API (ej. /proyectoGestorEscolar/api)
        if ($rutaBase !== '' && str_starts_with($ruta, $rutaBase)) {
            $ruta = substr($ruta, strlen($rutaBase));
        }
        // Si Apache no tiene mod_rewrite, acepto la ruta como ?r=materias
        if (($ruta === '' || $ruta === '/' || $ruta === '/index.php') && isset($_GET['r'])) {
            $ruta = '/' . ltrim((string) $_GET['r'], '/');
        }
        $ruta = '/' . trim($ruta, '/');
        return new self($metodo, $ruta);
    }

    // Parametro de la query string (?q=...)
    public function query(string $nombre, ?string $porDefecto = null): ?string
    {
        $valor = $_GET[$nombre] ?? $porDefecto;
        return is_string($valor) ? trim($valor) : $porDefecto;
    }

    // Cuerpo de la peticion: JSON si llega application/json, si no los campos del formulario
    public function cuerpo(): array
    {
        if ($this->cuerpo !== null) {
            return $this->cuerpo;
        }
        $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipo, 'application/json')) {
            $datos = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($datos)) {
                throw ErrorHttp::solicitudInvalida('El cuerpo de la peticion no es un JSON valido');
            }
            return $this->cuerpo = $datos;
        }
        return $this->cuerpo = $_POST;
    }

    public function campo(string $nombre, mixed $porDefecto = null): mixed
    {
        $valor = $this->cuerpo()[$nombre] ?? $porDefecto;
        return is_string($valor) ? trim($valor) : $valor;
    }

    public function archivo(string $nombre): ?array
    {
        return $_FILES[$nombre] ?? null;
    }

    // Parametro de la ruta (ej. {id}) convertido a entero
    public function idRuta(string $nombre = 'id'): int
    {
        return (int) ($this->parametrosRuta[$nombre] ?? 0);
    }
}
