<?php
include('../../config.php');
$id_rol = $_POST['id_rol'];

$sentencia = $pdo->prepare("DELETE FROM roles WHERE id_rol=:id_rol");
$sentencia->bindParam('id_rol', $id_rol);

if ($sentencia->execute()) {
    session_start();
    $_SESSION['mensaje'] = "Se elimino el rol de manera correcta";
    $_SESSION['icono'] = "success";
    header('Location: ../../../admin/roles/index.php');
} else {
    session_start();
    $_SESSION['mensaje'] = "Error al eliminar el rol";
    $_SESSION['icono'] = "error";
    header('Location: ../../../admin/roles/create.php');
}
