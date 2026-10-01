<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
require_once __DIR__ . '/../../estados_tarea.php';
exigirRol(['ADMINISTRADOR', 'PROFESOR']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verificarCsrf('admin/tareas/index.php');

    $id_tarea = $_POST['id_tarea'];
    $id_materia = $_POST['id_materia'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha_entrega = $_POST['fecha_entrega'];
    $hora_entrega = $_POST['hora_entrega'];
    $estado = $_POST['estado'];

    // Verifico que el estado sea uno de los valores unificados
    if (!in_array($estado, ESTADOS_TAREA, true)) {
        $_SESSION['mensaje'] = "El estado de la tarea no es valido";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/tareas/edit.php?id=' . urlencode($id_tarea));
        exit();
    }

    $sentencia = $pdo->prepare("UPDATE tareas SET id_materia = ?, titulo = ?, descripcion = ?, fecha_entrega = ?, hora_entrega = ?, estado = ? WHERE id_tarea = ?");

    if ($sentencia->execute([$id_materia, $titulo, $descripcion, $fecha_entrega, $hora_entrega, $estado, $id_tarea])) {
        $_SESSION['mensaje'] = "La tarea se ha actualizado correctamente";
        $_SESSION['icono'] = "success";
    } else {
        $_SESSION['mensaje'] = "Error al actualizar la tarea";
        $_SESSION['icono'] = "error";
    }

    header('Location: ../../../admin/tareas/index.php');
    exit();
}
