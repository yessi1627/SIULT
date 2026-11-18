<?php
declare(strict_types=1);

namespace Tests\Unitarias;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TDD de desempenoNota(). Estas pruebas se escribieron ANTES que la funcion:
 *  1. ROJO: fallaban porque desempenoNota() no existia.
 *  2. VERDE: escribi el codigo minimo (una cadena de if) para que pasaran.
 *  3. REFACTOR: cambie los if por la tabla RANGOS_DESEMPENO sin tocar las pruebas, y siguieron en verde.
 * Los limites de cada rango (2.99 / 3.0, 3.99 / 4.0, 4.59 / 4.6) son los casos que mas errores dan.
 */
final class DesempenoTest extends TestCase
{
    public static function notasYNiveles(): array
    {
        return [
            'cero es Bajo' => [0, 'Bajo'],
            'justo debajo de aprobar' => [2.99, 'Bajo'],
            'nota minima aprobatoria' => [3.0, 'Básico'],
            'limite superior de Basico' => [3.99, 'Básico'],
            'inicio de Alto' => [4.0, 'Alto'],
            'limite superior de Alto' => [4.59, 'Alto'],
            'inicio de Superior' => [4.6, 'Superior'],
            'nota maxima' => [5, 'Superior'],
            'nota que llega como texto' => ['4.2', 'Alto'],
        ];
    }

    #[Test]
    #[DataProvider('notasYNiveles')]
    public function clasificaLaNotaSegunLaEscalaDelDecreto1290(mixed $nota, string $esperado): void
    {
        $this->assertSame($esperado, desempenoNota($nota));
    }

    #[Test]
    public function devuelveNullSiNoEsUnaNotaValida(): void
    {
        $this->assertNull(desempenoNota(null));
        $this->assertNull(desempenoNota(5.5));
        $this->assertNull(desempenoNota(-1));
        $this->assertNull(desempenoNota('abc'));
    }

    #[Test]
    public function elResumenIncluyeElDesempenoDelPromedio(): void
    {
        $this->assertSame('Alto', resumenNotas([4.0, 4.5, 4.2])['desempeno']);
        $this->assertNull(resumenNotas([])['desempeno']);
    }
}
