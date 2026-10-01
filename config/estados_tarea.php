<?php
// Valores unicos para el estado de una tarea. Antes se mezclaban 'Pendiente', 'pendiente',
// 'completado', 'completada' y 'No entrego', y por eso las comparaciones fallaban.
const ESTADO_TAREA_PENDIENTE = 'Pendiente';
const ESTADO_TAREA_COMPLETADA = 'Completada';
const ESTADO_TAREA_VENCIDA = 'Vencida';
const ESTADOS_TAREA = [ESTADO_TAREA_PENDIENTE, ESTADO_TAREA_COMPLETADA, ESTADO_TAREA_VENCIDA];

// Estado de la entrega de un estudiante (se calcula, no se guarda en la tabla tareas)
const ESTADO_ENTREGA_ENTREGADA = 'Entregada';
const ESTADO_ENTREGA_PENDIENTE = 'Pendiente';
const ESTADO_ENTREGA_NO_ENTREGO = 'No entrego';

// Marco como vencidas todas las tareas pendientes cuya fecha y hora de entrega ya pasaron.
// Lo hago con un solo UPDATE en vez de recorrer las tareas y actualizar una por una.
function actualizarTareasVencidas(PDO $pdo): void
{
    $sentencia = $pdo->prepare("UPDATE tareas SET estado = :vencida
        WHERE estado = :pendiente
          AND (fecha_entrega < :fecha OR (fecha_entrega = :fecha_igual AND hora_entrega < :hora))");
    $sentencia->execute([
        ':vencida' => ESTADO_TAREA_VENCIDA,
        ':pendiente' => ESTADO_TAREA_PENDIENTE,
        ':fecha' => date('Y-m-d'),
        ':fecha_igual' => date('Y-m-d'),
        ':hora' => date('H:i:s'),
    ]);
}

// Verifico si todavia se puede entregar una tarea comparando fecha y hora juntas
function tareaAbiertaParaEntregas(array $tarea): bool
{
    $limite = $tarea['fecha_entrega'] . ' ' . $tarea['hora_entrega'];
    return date('Y-m-d H:i:s') <= $limite;
}

// Calculo el estado de la entrega de un estudiante a partir de si entrego y del plazo
function estadoEntregaEstudiante(array $tarea, ?string $rutaEntrega): string
{
    if ($rutaEntrega !== null && $rutaEntrega !== '') {
        return ESTADO_ENTREGA_ENTREGADA;
    }
    return tareaAbiertaParaEntregas($tarea) ? ESTADO_ENTREGA_PENDIENTE : ESTADO_ENTREGA_NO_ENTREGO;
}
