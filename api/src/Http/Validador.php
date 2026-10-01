<?php
declare(strict_types=1);

namespace Api\Http;

// Valido los campos de una peticion y acumulo los errores por campo.
// Al final, si hubo errores, respondo 422 con { "detalles": { "campo": "mensaje" } }.
final class Validador
{
    private array $errores = [];

    public function __construct(private readonly Peticion $peticion)
    {
    }

    public function texto(string $campo, string $etiqueta, bool $requerido = true, int $maximo = 255): ?string
    {
        $valor = $this->peticion->campo($campo);
        if ($valor === null || $valor === '') {
            if ($requerido) {
                $this->errores[$campo] = "$etiqueta es obligatorio";
            }
            return null;
        }
        if (!is_string($valor)) {
            $this->errores[$campo] = "$etiqueta debe ser texto";
            return null;
        }
        if (mb_strlen($valor) > $maximo) {
            $this->errores[$campo] = "$etiqueta no puede tener mas de $maximo caracteres";
        }
        return $valor;
    }

    public function email(string $campo): ?string
    {
        $valor = $this->texto($campo, 'El email');
        if ($valor !== null && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $this->errores[$campo] = 'El email no es valido';
        }
        return $valor;
    }

    public function entero(string $campo, string $etiqueta, bool $requerido = true): ?int
    {
        $valor = $this->peticion->campo($campo);
        if ($valor === null || $valor === '') {
            if ($requerido) {
                $this->errores[$campo] = "$etiqueta es obligatorio";
            }
            return null;
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            $this->errores[$campo] = "$etiqueta no es valido";
            return null;
        }
        return $entero;
    }

    public function decimal(string $campo, string $etiqueta, float $minimo, float $maximo): ?float
    {
        $valor = $this->peticion->campo($campo);
        $numero = filter_var($valor, FILTER_VALIDATE_FLOAT);
        if ($valor === null || $valor === '' || $numero === false || $numero < $minimo || $numero > $maximo) {
            $this->errores[$campo] = "$etiqueta debe estar entre $minimo y $maximo";
            return null;
        }
        return $numero;
    }

    public function fecha(string $campo, string $etiqueta): ?string
    {
        $valor = $this->texto($campo, $etiqueta);
        if ($valor !== null && \DateTime::createFromFormat('Y-m-d', $valor)?->format('Y-m-d') !== $valor) {
            $this->errores[$campo] = "$etiqueta debe tener el formato AAAA-MM-DD";
        }
        return $valor;
    }

    public function hora(string $campo, string $etiqueta): ?string
    {
        $valor = $this->texto($campo, $etiqueta);
        if ($valor !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $valor)) {
            $this->errores[$campo] = "$etiqueta debe tener el formato HH:MM";
        }
        return $valor;
    }

    public function enLista(string $campo, string $etiqueta, array $permitidos): ?string
    {
        $valor = $this->texto($campo, $etiqueta);
        if ($valor !== null && !in_array($valor, $permitidos, true)) {
            $this->errores[$campo] = "$etiqueta debe ser uno de: " . implode(', ', $permitidos);
        }
        return $valor;
    }

    public function agregarError(string $campo, string $mensaje): void
    {
        $this->errores[$campo] = $mensaje;
    }

    public function verificar(): void
    {
        if ($this->errores !== []) {
            throw ErrorHttp::solicitudInvalida('Hay datos invalidos en la peticion', $this->errores);
        }
    }
}
