<?php
declare(strict_types=1);

namespace Api\Http;

// Enrutador sencillo: asocia metodo + patron de ruta con el metodo de un controlador.
// Los patrones aceptan parametros numericos con la forma {nombre}, ej. /materias/{id}.
final class Enrutador
{
    private array $rutas = [];

    public function get(string $patron, array $accion): void
    {
        $this->agregar('GET', $patron, $accion);
    }

    public function post(string $patron, array $accion): void
    {
        $this->agregar('POST', $patron, $accion);
    }

    public function put(string $patron, array $accion): void
    {
        $this->agregar('PUT', $patron, $accion);
    }

    public function delete(string $patron, array $accion): void
    {
        $this->agregar('DELETE', $patron, $accion);
    }

    private function agregar(string $metodo, string $patron, array $accion): void
    {
        // Convierto /materias/{id} en la expresion regular #^/materias/(?P<id>\d+)$#
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $patron) . '$#';
        $this->rutas[] = ['metodo' => $metodo, 'regex' => $regex, 'accion' => $accion];
    }

    public function despachar(Peticion $peticion): Respuesta
    {
        $metodosPermitidos = [];
        foreach ($this->rutas as $ruta) {
            if (!preg_match($ruta['regex'], $peticion->ruta, $coincidencias)) {
                continue;
            }
            if ($ruta['metodo'] !== $peticion->metodo) {
                $metodosPermitidos[] = $ruta['metodo'];
                continue;
            }
            $peticion->parametrosRuta = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);
            [$clase, $metodo] = $ruta['accion'];
            return (new $clase())->$metodo($peticion);
        }
        if ($metodosPermitidos !== []) {
            header('Allow: ' . implode(', ', array_unique($metodosPermitidos)));
            throw new ErrorHttp(405, 'Metodo no permitido para esta ruta');
        }
        throw ErrorHttp::noEncontrado('La ruta ' . $peticion->ruta);
    }
}
