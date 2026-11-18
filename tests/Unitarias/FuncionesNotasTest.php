<?php
declare(strict_types=1);

namespace Tests\Unitarias;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Pruebas unitarias de las funciones puras de lib/funciones_notas.php (Unidad 1)
final class FuncionesNotasTest extends TestCase
{
    #[Test]
    public function descartaLosValoresQueNoSonNotas(): void
    {
        $this->assertSame([4.5, 3.0, 0.0], notasValidas([4.5, '3', 'abc', 7, -1, null, 0]));
    }

    #[Test]
    public function calculaElPromedioRedondeadoADosDecimales(): void
    {
        $this->assertSame(3.67, promedioNotas([3, 4, 4]));
        $this->assertNull(promedioNotas([]));
    }

    #[Test]
    public function calculaMaximaMinimaYAprobadas(): void
    {
        $notas = [2.5, 4.8, 3.0, 1.0];
        $this->assertSame(4.8, notaMaxima($notas));
        $this->assertSame(1.0, notaMinima($notas));
        $this->assertSame([4.8, 3.0], notasAprobadas($notas));
    }

    #[Test]
    public function esPuraMismaEntradaMismaSalidaYNoModificaLaEntrada(): void
    {
        $notas = [4.0, 2.0, 5.0];
        $copia = $notas;
        $this->assertSame(resumenNotas($notas), resumenNotas($notas));
        $this->assertSame($copia, $notas);
    }

    #[Test]
    public function agrupaLasCalificacionesPorEstudiante(): void
    {
        $filas = [
            ['id_usuario' => 7, 'nombres' => 'Ana', 'nota' => 4.0],
            ['id_usuario' => 9, 'nombres' => 'Luis', 'nota' => 2.0],
            ['id_usuario' => 7, 'nombres' => 'Ana', 'nota' => 5.0],
        ];
        $resultado = promediosPorEstudiante($filas);

        $this->assertCount(2, $resultado);
        $this->assertSame(['id_usuario' => 7, 'nombres' => 'Ana', 'cantidad' => 2, 'promedio' => 4.5], array_slice($resultado[0], 0, 4));
        $this->assertTrue($resultado[0]['aprueba']);
        $this->assertFalse($resultado[1]['aprueba']);
        $this->assertSame('Bajo', $resultado[1]['desempeno']);
    }
}
