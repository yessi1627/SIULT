<?php
// Exclusion mutua con los bloqueos con nombre de MySQL (GET_LOCK / RELEASE_LOCK).
//
// GET_LOCK('nombre', segundos) deja pasar a UNA sola conexion a la vez con ese nombre; las demas
// esperan hasta `segundos` y, si el bloqueo no se libera, reciben 0. Funciona entre procesos
// distintos de Apache porque el bloqueo vive en el servidor MySQL (es un mutex distribuido).
// Lo uso para que un mismo estudiante no suba dos entregas de la misma tarea al mismo tiempo.

final class BloqueoOcupado extends RuntimeException
{
}

/**
 * Ejecuto $operacion mientras tengo el bloqueo $nombre. El bloqueo se libera SIEMPRE (finally),
 * aunque la operacion lance una excepcion. Si no logro el bloqueo en $segundos lanzo BloqueoOcupado.
 */
function conBloqueo(PDO $pdo, string $nombre, int $segundos, callable $operacion): mixed
{
    $obtener = $pdo->prepare('SELECT GET_LOCK(:nombre, :segundos)');
    $obtener->execute([':nombre' => $nombre, ':segundos' => $segundos]);
    if ((int) $obtener->fetchColumn() !== 1) {
        throw new BloqueoOcupado('Hay otra operacion en curso con el mismo recurso, intente de nuevo');
    }
    try {
        return $operacion();
    } finally {
        $liberar = $pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
        $liberar->execute([':nombre' => $nombre]);
    }
}

// Nombre del bloqueo de la entrega de un estudiante en una tarea
function nombreBloqueoEntrega(int $idTarea, int $idUsuario): string
{
    return "entrega_{$idTarea}_{$idUsuario}";
}
