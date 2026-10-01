<?php
// Este archivo solo se incluye desde una vista; no se puede abrir directamente
require_once __DIR__ . '/../../seguridad.php';
impedirAccesoDirecto(__FILE__);
// Verifico si existe el id de materia
if (!isset($id_materia)) {
    // Muestro error y redirecciono
    $_SESSION['mensaje'] = "No se encontro la materia solicitada";
    $_SESSION['icono'] = "error";
    header('Location: ../materias/index.php');
    exit();
}

// Preparo consulta para datos de materia
$sql_materias = "SELECT m.* FROM materias m WHERE m.estado = '1' AND m.id_materia = :id_materia";
$parametros_materia = [':id_materia' => $id_materia];
if (($_SESSION['role'] ?? '') === 'ESTUDIANTE') {
    $sql_materias .= " AND EXISTS (
        SELECT 1 FROM matriculas ma
        WHERE ma.id_materia = m.id_materia AND ma.id_usuario = :id_usuario
    )";
    $parametros_materia[':id_usuario'] = $_SESSION['id_usuario'];
}
$query_materias = $pdo->prepare($sql_materias);
$query_materias->execute($parametros_materia);
$materias = $query_materias->fetchAll(PDO::FETCH_ASSOC);

// Verifico si encontre la materia
if (count($materias) > 0) {
    // Extraigo los datos
    foreach ($materias as $materia) {
        $nombre_materia = $materia['nombre_materia'];
        $hora_creacion = $materia['hora_creacion'];
        $estado = $materia['estado'];
    }
} else {
    // No encontre la materia
    $_SESSION['mensaje'] = "La materia solicitada no existe o fue eliminada";
    $_SESSION['icono'] = "error";
    header('Location: index.php');
    exit();
}
