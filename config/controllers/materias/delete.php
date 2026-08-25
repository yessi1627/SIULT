<?php
include('../../config.php');
$id_materia = $_POST['id_materia'];

try {
    // Verifico que no haya tareas asociadas a la materia
    $verificar = $pdo->prepare("SELECT COUNT(*) as total FROM tareas WHERE id_materia = :id_materia");
    $verificar->bindParam(':id_materia', $id_materia);
    $verificar->execute();
    $resultado = $verificar->fetch(PDO::FETCH_ASSOC);

    if ($resultado['total'] > 0) {
        // Hay tareas asociadas no se puede eliminar
        session_start();
        $_SESSION['mensaje'] = "No se puede eliminar esta materia porque tiene " . $resultado['total'] . " tarea(s) asociada(s)";
        $_SESSION['icono'] = "error";
    } else {
        // No hay tareas relacionadas por lo que se puede eliminar la materia
        $sentencia = $pdo->prepare("DELETE FROM materias WHERE id_materia = :id_materia");
        $sentencia->bindParam(':id_materia', $id_materia);

        if ($sentencia->execute()) {
            session_start();
            $_SESSION['mensaje'] = "Se elimino la materia de manera correcta";
            $_SESSION['icono'] = "success";
        } else {
            session_start();
            $_SESSION['mensaje'] = "Error al eliminar la materia";
            $_SESSION['icono'] = "error";
        }
    }
} catch (PDOException $e) {
    // Manejo el error para que muestre un mensaje especifico
    if ($e->getCode() == '23000') {
        session_start();
        $_SESSION['mensaje'] = "No se puede eliminar esta materia porque tiene tareas asociadas";
        $_SESSION['icono'] = "error";
    } else {
        session_start();
        $_SESSION['mensaje'] = "Error: " . $e->getMessage();
        $_SESSION['icono'] = "error";
    }
}

// Redirecciono al listado de materias
header('Location: ../../../admin/materias/index.php');
exit();
