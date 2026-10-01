<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
exigirRol(['ADMINISTRADOR']);
$id_materia = $_POST['id_materia'];
$nombre_materia = $_POST['nombre_materia'];

$sentencia = $pdo->prepare('UPDATE materias
 SET nombre_materia=:nombre_materia,
     hora_actualizacion=:hora_actualizacion
WHERE id_materia=:id_materia ');

$sentencia->bindParam(':nombre_materia', $nombre_materia);
$sentencia->bindParam(':hora_actualizacion', $fechaHora);
$sentencia->bindParam(':id_materia', $id_materia);

if ($sentencia->execute()) {
    $_SESSION['mensaje'] = "Se actualizo la materia de la manera correcta en la base de datos";
    $_SESSION['icono'] = "success";
    header('Location: ../../../admin/materias/index.php');
} else {
    $_SESSION['mensaje'] = "Error no se pudo actualizar en la base datos comuniquese con el administrador";
    $_SESSION['icono'] = "error";
?><script>
        window.history.back();
    </script><?php
            }
