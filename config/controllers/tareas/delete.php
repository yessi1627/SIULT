<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
exigirRol(['ADMINISTRADOR', 'PROFESOR']);
verificarCsrf('admin/tareas/index.php');

$id_tarea = $_POST['id_tarea'];

// Guardo las rutas de los archivos de la tarea y de las entregas para borrarlos del disco al final
$sentencia = $pdo->prepare("SELECT ruta_archivo FROM archivos WHERE id_tarea = :id_tarea
    UNION ALL SELECT ruta_archivo FROM entregas WHERE id_tarea = :id_tarea_entregas");
$sentencia->execute([':id_tarea' => $id_tarea, ':id_tarea_entregas' => $id_tarea]);
$rutas_archivos = $sentencia->fetchAll(PDO::FETCH_COLUMN);

try {
    // Elimino archivos y tarea juntos; las entregas, calificaciones y notificaciones se borran en cascada
    $pdo->beginTransaction();

    $sentencia = $pdo->prepare("DELETE FROM archivos WHERE id_tarea = :id_tarea");
    $sentencia->execute([':id_tarea' => $id_tarea]);

    $sentencia = $pdo->prepare("DELETE FROM tareas WHERE id_tarea = :id_tarea");
    $sentencia->execute([':id_tarea' => $id_tarea]);

    $pdo->commit();

    // Solo despues de confirmar en la base de datos borro los archivos fisicos
    foreach ($rutas_archivos as $ruta) {
        $ruta_completa = __DIR__ . '/../../uploads/' . basename($ruta);
        if (is_file($ruta_completa)) {
            unlink($ruta_completa);
        }
    }

    $_SESSION['mensaje'] = "Tarea eliminada correctamente";
    $_SESSION['icono'] = "success";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensaje'] = "Error al eliminar la tarea";
    $_SESSION['icono'] = "error";
}

header('Location: ../../../admin/tareas/index.php');
exit();
