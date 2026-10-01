<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
exigirRol(['ADMINISTRADOR']);
$id_usuario = $_POST['id_usuario'];

$sentencia = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario=:id_usuario");
$sentencia->bindParam('id_usuario', $id_usuario);

if ($sentencia->execute()) {
    $_SESSION['mensaje'] = "Se elimino el usuario de manera correcta";
    $_SESSION['icono'] = "success";
    header('Location: ../../../admin/usuarios/index.php');
} else {
    $_SESSION['mensaje'] = "Error al eliminar el usuario";
    $_SESSION['icono'] = "error";
    header('Location: ../../../admin/usuarios/create.php');
}
