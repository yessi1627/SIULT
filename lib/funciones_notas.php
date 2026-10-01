<?php
// Funciones puras para calcular estadisticas de notas.
//
// Una funcion pura:
//   1. Siempre devuelve el mismo resultado para los mismos datos de entrada.
//   2. No tiene efectos secundarios: no consulta la base de datos, no modifica variables globales,
//      no escribe archivos ni cambia el arreglo que recibe (PHP pasa los arreglos por copia).
// Por eso se pueden probar de forma aislada y reutilizar en cualquier controlador.
// Uso array_map, array_filter y array_reduce en lugar de ciclos que acumulan en variables externas.

const NOTA_MINIMA_APROBACION = 3.0;

// Convierto cada valor a float y descarto los que no son notas validas (fuera de 0 a 5 o vacios)
function notasValidas(array $notas): array
{
    $numericas = array_map(fn($nota) => is_numeric($nota) ? (float) $nota : null, $notas);
    return array_values(array_filter($numericas, fn($nota) => $nota !== null && $nota >= 0 && $nota <= 5));
}

// Sumo las notas con array_reduce
function sumaNotas(array $notas): float
{
    return array_reduce(notasValidas($notas), fn(float $acumulado, float $nota) => $acumulado + $nota, 0.0);
}

// Promedio redondeado a 2 decimales; null si no hay notas
function promedioNotas(array $notas): ?float
{
    $validas = notasValidas($notas);
    return count($validas) === 0 ? null : round(sumaNotas($validas) / count($validas), 2);
}

function notaMaxima(array $notas): ?float
{
    $validas = notasValidas($notas);
    return count($validas) === 0 ? null : max($validas);
}

function notaMinima(array $notas): ?float
{
    $validas = notasValidas($notas);
    return count($validas) === 0 ? null : min($validas);
}

// Devuelvo solo las notas aprobadas (mayores o iguales al minimo)
function notasAprobadas(array $notas, float $minimo = NOTA_MINIMA_APROBACION): array
{
    return array_values(array_filter(notasValidas($notas), fn(float $nota) => $nota >= $minimo));
}

// Resumen completo de una lista de notas
function resumenNotas(array $notas, float $minimo = NOTA_MINIMA_APROBACION): array
{
    $promedio = promedioNotas($notas);
    return [
        'cantidad' => count(notasValidas($notas)),
        'promedio' => $promedio,
        'nota_maxima' => notaMaxima($notas),
        'nota_minima' => notaMinima($notas),
        'aprobadas' => count(notasAprobadas($notas, $minimo)),
        'aprueba' => $promedio !== null && $promedio >= $minimo,
    ];
}

// Agrupo calificaciones por estudiante sin modificar el arreglo original.
// Recibo filas con 'id_usuario', 'nombres' y 'nota' y devuelvo un resumen por estudiante.
function promediosPorEstudiante(array $calificaciones, float $minimo = NOTA_MINIMA_APROBACION): array
{
    $agrupadas = array_reduce($calificaciones, function (array $grupos, array $fila) {
        $id = (int) $fila['id_usuario'];
        $actual = $grupos[$id] ?? ['id_usuario' => $id, 'nombres' => $fila['nombres'] ?? '', 'notas' => []];
        // Creo un arreglo nuevo en vez de modificar el anterior (inmutabilidad)
        return array_replace($grupos, [$id => array_merge($actual, ['notas' => [...$actual['notas'], $fila['nota']]])]);
    }, []);

    return array_values(array_map(
        fn(array $grupo) => [
            'id_usuario' => $grupo['id_usuario'],
            'nombres' => $grupo['nombres'],
        ] + resumenNotas($grupo['notas'], $minimo),
        $agrupadas
    ));
}
