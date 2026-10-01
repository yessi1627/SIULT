<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
exigirRol(['ADMINISTRADOR']);
verificarCsrf('admin/roles/index.php');
$id_rol = $_POST['id_rol'];

$sentencia = $pdo->prepare("DELETE FROM roles WHERE id_rol=:id_rol");
$sentencia->bindParam('id_rol', $id_rol);

if ($sentencia->execute()) {
    $_SESSION['mensaje'] = "Se elimino el rol de manera correcta";
    $_SESSION['icono'] = "success";
    header('Location: ../../../admin/roles/index.php');
} else {
    $_SESSION['mensaje'] = "Error al eliminar el rol";
    $_SESSION['icono'] = "error";
    header('Location: ../../../admin/roles/create.php');
}
