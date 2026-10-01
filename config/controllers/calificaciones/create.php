<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
exigirRol(['ADMINISTRADOR', 'PROFESOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../admin/tareas/index.php');
    exit();
}
verificarCsrf('admin/tareas/index.php');

function rechazarCalificacion($mensaje, $id_tarea = null)
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['icono'] = 'error';
    $destino = $id_tarea ? '../../../admin/tareas/show.php?id=' . $id_tarea : '../../../admin/tareas/index.php';
    header('Location: ' . $destino);
    exit();
}

$id_tarea = filter_input(INPUT_POST, 'id_tarea', FILTER_VALIDATE_INT);
$id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
$nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_FLOAT);
$observacion = trim($_POST['observacion'] ?? '');

if (!$id_tarea || !$id_usuario) {
    rechazarCalificacion('Debe indicar la tarea y el estudiante a calificar');
}

if ($nota === false || $nota === null || $nota < 0 || $nota > 5) {
    rechazarCalificacion('La nota debe estar entre 0 y 5', $id_tarea);
}

// Verifico que la tarea exista
$sentencia = $pdo->prepare("SELECT id_tarea FROM tareas WHERE id_tarea = :id_tarea");
$sentencia->execute([':id_tarea' => $id_tarea]);
if (!$sentencia->fetchColumn()) {
    rechazarCalificacion('La tarea indicada no existe');
}

// Verifico que el estudiante exista y este matriculado en la materia de la tarea
$sentencia = $pdo->prepare("SELECT u.id_usuario
    FROM usuarios u
    INNER JOIN roles r ON r.id_rol = u.rol_id
    INNER JOIN matriculas m ON m.id_usuario = u.id_usuario
    INNER JOIN tareas t ON t.id_materia = m.id_materia
    WHERE u.id_usuario = :id_usuario AND t.id_tarea = :id_tarea
        AND u.estado = '1' AND r.nombre_rol = 'ESTUDIANTE'");
$sentencia->execute([':id_usuario' => $id_usuario, ':id_tarea' => $id_tarea]);
if (!$sentencia->fetchColumn()) {
    rechazarCalificacion('El estudiante indicado no esta matriculado en la materia de la tarea', $id_tarea);
}

// Bloqueo optimista: el formulario envia la version de la nota que mostro.
// Si otro profesor la cambio despues, la version ya no coincide y no piso su nota.
$version = filter_input(INPUT_POST, 'version', FILTER_VALIDATE_INT);
$parametros = [
    ':id_tarea' => $id_tarea,
    ':id_usuario' => $id_usuario,
    ':nota' => $nota,
    ':observacion' => $observacion !== '' ? $observacion : null,
    ':fecha_calificacion' => $fechaHora,
];

try {
    if ($version) {
        $sentencia = $pdo->prepare("UPDATE calificaciones
            SET nota = :nota, observacion = :observacion, fecha_calificacion = :fecha_calificacion, version = version + 1
            WHERE id_tarea = :id_tarea AND id_usuario = :id_usuario AND version = :version");
        $sentencia->execute($parametros + [':version' => $version]);
        if ($sentencia->rowCount() === 0) {
            rechazarCalificacion('Otro usuario modificó esta nota mientras usted la editaba. Revise el valor actual y vuelva a guardar.', $id_tarea);
        }
    } else {
        // Nota nueva; si otro profesor la creo al mismo tiempo, la llave unica lanza un error 23000
        $sentencia = $pdo->prepare("INSERT INTO calificaciones (id_tarea, id_usuario, nota, observacion, fecha_calificacion, version)
            VALUES (:id_tarea, :id_usuario, :nota, :observacion, :fecha_calificacion, 1)");
        $sentencia->execute($parametros);
    }

    $_SESSION['mensaje'] = 'La calificación fue guardada correctamente';
    $_SESSION['icono'] = 'success';
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        rechazarCalificacion('Otro usuario ya registró una nota para este estudiante. Revise el valor actual.', $id_tarea);
    }
    $_SESSION['mensaje'] = 'No se pudo guardar la calificación';
    $_SESSION['icono'] = 'error';
}

header('Location: ../../../admin/tareas/show.php?id=' . $id_tarea);
exit();
