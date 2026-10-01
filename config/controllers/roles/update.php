<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
exigirRol(['ADMINISTRADOR']);
verificarCsrf('admin/roles/index.php');
$id_rol = $_POST['id_rol'];
$nombre_rol = $_POST['nombre_rol'];
$nombre_rol = mb_strtoupper($nombre_rol, 'UTF-8');

if ($nombre_rol == "") {
    $_SESSION['mensaje'] = "El nombre del rol es requerido";
    $_SESSION['icono'] = "error";
    header('Location: ../../../admin/roles/edit.php?id=' . $id_rol);
    exit();
} else {
    $sentencia = $pdo->prepare("UPDATE roles SET nombre_rol=:nombre_rol, hora_actualizacion=:hora_actualizacion WHERE id_rol = :id_rol");
    $sentencia->bindParam('nombre_rol', $nombre_rol);
    $sentencia->bindParam('hora_actualizacion', $fechaHora);
    $sentencia->bindParam('id_rol', $id_rol);

    try {
        if ($sentencia->execute()) {
            $_SESSION['mensaje'] = "Se actualiza el rol de manera correcta";
            $_SESSION['icono'] = "success";
            header('Location: ../../../admin/roles/index.php');
        } else {
            $_SESSION['mensaje'] = "Error al actualizar el rol";
            $_SESSION['icono'] = "error";
            header('Location: ../../../admin/roles/edit.php?id=' . $id_rol);
        }
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Este rol ya existe";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/roles/edit.php?id=' . $id_rol);
    }
}
