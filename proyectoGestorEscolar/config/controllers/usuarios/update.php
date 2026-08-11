<?php
include('../../config.php');

$id_usuario = $_POST['id_usuario'];
$nombres = $_POST['nombres'];
$rol_id = $_POST['rol_id'];
$email = $_POST['email'];
$password = $_POST['password'];
$password_repeat = $_POST['password_repeat'];
$fechaHora = date('Y-m-d H:i:s');

if ($password == '') {
    $sentencia = $pdo->prepare("UPDATE usuarios SET nombres=:nombres, rol_id=:rol_id, email=:email, hora_actualizacion=:hora_actualizacion WHERE id_usuario=:id_usuario");

    $sentencia->bindParam(':nombres', $nombres);
    $sentencia->bindParam(':rol_id', $rol_id);
    $sentencia->bindParam(':email', $email);
    $sentencia->bindParam(':hora_actualizacion', $fechaHora);
    $sentencia->bindParam(':id_usuario', $id_usuario);

    try {
        if ($sentencia->execute()) {
            session_start();
            $_SESSION['mensaje'] = "El usuario se actualizo correctamente";
            $_SESSION['icono'] = "success";
            header('Location: ../../../admin/usuarios/index.php');
        } else {
            session_start();
            $_SESSION['mensaje'] = "El usuario no se pudo actualizar en la base de datos";
            $_SESSION['icono'] = "error";
            header('Location: ../../../admin/usuarios/index.php');
        }
    } catch (PDOException $e) {
        session_start();
        $_SESSION['mensaje'] = "El email del usuario ya existe en la base de datos";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/usuarios/index.php');
    }
} else {
    if ($password == $password_repeat) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

        $sentencia = $pdo->prepare('UPDATE usuarios SET nombres=:nombres, rol_id=:rol_id, email=:email, password=:password, hora_actualizacion=:hora_actualizacion WHERE id_usuario=:id_usuario');

        $sentencia->bindParam(':nombres', $nombres);
        $sentencia->bindParam(':rol_id', $rol_id);
        $sentencia->bindParam(':email', $email);
        $sentencia->bindParam(':password', $password_hashed);
        $sentencia->bindParam(':hora_actualizacion', $fechaHora);
        $sentencia->bindParam(':id_usuario', $id_usuario);

        try {
            if ($sentencia->execute()) {
                session_start();
                $_SESSION['mensaje'] = "Usuario actualizado correctamente";
                $_SESSION['icono'] = "success";
                header('Location: ../../../admin/usuarios/index.php');
            } else {
                session_start();
                $_SESSION['mensaje'] = "El usuario no se pudo actualizar en la base de datos";
                $_SESSION['icono'] = "error";
                header('Location: ../../../admin/usuarios/index.php');
            }
        } catch (PDOException $e) {
            session_start();
            $_SESSION['mensaje'] = "El email del usuario ya existe en la base de datos";
            $_SESSION['icono'] = "error";
            header('Location: ../../../admin/usuarios/index.php');
            exit();
        }
    } else {
        session_start();
        $_SESSION['mensaje'] = "Las contraseñas no coinciden";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/usuarios/index.php');
    }
}
