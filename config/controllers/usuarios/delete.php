<?php
include('../../config.php');
$id_usuario = $_POST['id_usuario'];

$sentencia = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario=:id_usuario");
$sentencia->bindParam('id_usuario', $id_usuario);

if ($sentencia->execute()) {
    session_start();
    $_SESSION['mensaje'] = "Se elimino el usuario de manera correcta";
    $_SESSION['icono'] = "success";
    header('Location: ../../../admin/usuarios/index.php');
} else {
    session_start();
    $_SESSION['mensaje'] = "Error al eliminar el usuario";
    $_SESSION['icono'] = "error";
    header('Location: ../../../admin/usuarios/create.php');
}
