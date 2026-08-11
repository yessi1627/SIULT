<?php
include('../../config.php');

// Recibo datos del formulario
$nombre_materia = $_POST['nombre_materia'];

// Preparo sentencia SQL para insertar
$sentencia = $pdo->prepare('INSERT INTO materias
(nombre_materia,hora_creacion, estado)
VALUES ( :nombre_materia,:hora_creacion,:estado)');

// Asigno valores a parametros
$sentencia->bindParam(':nombre_materia', $nombre_materia);
$sentencia->bindParam('hora_creacion', $fechaHora);
$sentencia->bindParam('estado', $estadoRegistro);

// Ejecuto consulta y verifico resultado
if ($sentencia->execute()) {
    // Registro exitoso
    session_start();
    $_SESSION['mensaje'] = "Se registro la materia de la manera correcta en la base de datos";
    $_SESSION['icono'] = "success";
    header('Location: ../../../admin/materias/index.php');
} else {
    // Error al registrar
    session_start();
    $_SESSION['mensaje'] = "Error no se pudo registrar en la base datos comuniquese con el administrador";
    $_SESSION['icono'] = "error";
?><script>
        window.history.back();
    </script><?php
            }
