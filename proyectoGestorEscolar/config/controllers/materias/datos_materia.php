<?php
// Verifico si existe el id de materia
if (!isset($id_materia)) {
    // Muestro error y redirecciono
    session_start();
    $_SESSION['mensaje'] = "No se encontro la materia solicitada";
    $_SESSION['icono'] = "error";
    header('Location: ../materias/index.php');
    exit();
}

// Preparo consulta para datos de materia
$sql_materias = "SELECT * FROM materias where estado = '1' and id_materia = :id_materia";
$query_materias = $pdo->prepare($sql_materias);
$query_materias->bindParam(':id_materia', $id_materia);
$query_materias->execute();
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
    session_start();
    $_SESSION['mensaje'] = "La materia solicitada no existe o fue eliminada";
    $_SESSION['icono'] = "error";
    header('Location: index.php');
    exit();
}
