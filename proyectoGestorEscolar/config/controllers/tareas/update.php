<?php
include('../../config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_tarea = $_POST['id_tarea'];
    $id_materia = $_POST['id_materia'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha_entrega = $_POST['fecha_entrega'];
    $hora_entrega = $_POST['hora_entrega'];
    $estado = $_POST['estado'];

    $sentencia = $pdo->prepare("UPDATE tareas SET id_materia = ?, titulo = ?, descripcion = ?, fecha_entrega = ?, hora_entrega = ?, estado = ? WHERE id_tarea = ?");

    if ($sentencia->execute([$id_materia, $titulo, $descripcion, $fecha_entrega, $hora_entrega, $estado, $id_tarea])) {
        session_start();
        $_SESSION['mensaje'] = "La tarea se ha actualizado correctamente";
        $_SESSION['icono'] = "success";
    } else {
        session_start();
        $_SESSION['mensaje'] = "Error al actualizar la tarea";
        $_SESSION['icono'] = "error";
    }

    header('Location: ../../../admin/tareas/index.php');
    exit();
}
