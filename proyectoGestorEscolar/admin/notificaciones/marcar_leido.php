<?php
include('../../config/config.php');
include('../../config/autenticacion_rol.php');

// Obtengo el ID de notificacion
$id_notificacion = $_GET['id'];

// Marco como leida la notificacion
$sentencia = $pdo->prepare("UPDATE notificaciones SET leido = TRUE WHERE id = ?");
$sentencia->execute([$id_notificacion]);

// Verifico si hay una URL de retorno
if (isset($_GET['return_url'])) {
    $return_url = $_GET['return_url'];
    header('Location: ' . $return_url);
} else {
    // Si no hay URL de retorno vuelvo al panel de admin
    header('Location: ../index.php');
}
exit();
