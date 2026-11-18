<?php
declare(strict_types=1);

namespace Tests\Unitarias;

use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Validador;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Pruebas del validador de la API: cada campo invalido debe terminar en un 422 con su mensaje
final class ValidadorTest extends TestCase
{
    private function validadorCon(array $campos): Validador
    {
        // Simulo un formulario enviado por POST
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = $campos;
        return new Validador(new Peticion('POST', '/prueba'));
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    #[Test]
    public function aceptaDatosCorrectosYQuitaEspacios(): void
    {
        $validador = $this->validadorCon(['nombre' => '  Taller 1 ', 'email' => 'ana@correo.com', 'nota' => '4.5', 'fecha' => '2026-11-13']);

        $this->assertSame('Taller 1', $validador->texto('nombre', 'El nombre'));
        $this->assertSame('ana@correo.com', $validador->email('email'));
        $this->assertSame(4.5, $validador->decimal('nota', 'La nota', 0, 5));
        $this->assertSame('2026-11-13', $validador->fecha('fecha', 'La fecha'));
        $validador->verificar(); // no lanza error
    }

    #[Test]
    public function acumulaLosErroresYRespondeCon422(): void
    {
        $validador = $this->validadorCon(['email' => 'no-es-correo', 'nota' => '7', 'fecha' => '2026-02-30', 'id' => '-3']);
        $validador->texto('nombre', 'El nombre');
        $validador->email('email');
        $validador->decimal('nota', 'La nota', 0, 5);
        $validador->fecha('fecha', 'La fecha');
        $validador->entero('id', 'El id');

        try {
            $validador->verificar();
            $this->fail('Se esperaba un ErrorHttp 422');
        } catch (ErrorHttp $error) {
            $this->assertSame(422, $error->codigoHttp);
            $this->assertSame(['nombre', 'email', 'nota', 'fecha', 'id'], array_keys($error->detalles));
            $this->assertSame('El nombre es obligatorio', $error->detalles['nombre']);
        }
    }

    #[Test]
    public function validaHorasYValoresDeUnaLista(): void
    {
        $validador = $this->validadorCon(['hora' => '25:00', 'estado' => 'Borrador']);
        $validador->hora('hora', 'La hora');
        $validador->enLista('estado', 'El estado', ['Pendiente', 'Completada', 'Vencida']);

        $this->expectException(ErrorHttp::class);
        $validador->verificar();
    }
}
