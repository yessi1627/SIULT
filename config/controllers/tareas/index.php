<?php
// Este archivo solo se incluye desde una vista; no se puede abrir directamente
require_once __DIR__ . '/../../seguridad.php';
require_once __DIR__ . '/../../estados_tarea.php';
impedirAccesoDirecto(__FILE__);

// Antes de listar actualizo en una sola consulta las tareas que ya vencieron
actualizarTareasVencidas($pdo);

// Tomo solo el ultimo archivo de cada tarea con una subconsulta; con LEFT JOIN archivos
// una tarea con varios archivos aparecia repetida en el listado
$sql_tareas = "
    SELECT t.*, m.nombre_materia AS materia,
        (SELECT a.ruta_archivo FROM archivos a WHERE a.id_tarea = t.id_tarea ORDER BY a.id DESC LIMIT 1) AS ruta_archivo
    FROM tareas t
    LEFT JOIN materias m ON t.id_materia = m.id_materia";
$parametros_tareas = [];

if (($_SESSION['role'] ?? '') === 'ESTUDIANTE') {
    $sql_tareas .= " INNER JOIN matriculas mat ON mat.id_materia = t.id_materia
        WHERE mat.id_usuario = :id_usuario";
    $parametros_tareas[':id_usuario'] = $_SESSION['id_usuario'];
}

$sentencia = $pdo->prepare($sql_tareas);
$sentencia->execute($parametros_tareas);
$tareas = $sentencia->fetchAll(PDO::FETCH_ASSOC);

$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i:s');
