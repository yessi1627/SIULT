<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
require_once __DIR__ . '/../../estados_tarea.php';
require_once __DIR__ . '/../../archivos.php';
require_once __DIR__ . '/../../bloqueos.php';
exigirRol(['ADMINISTRADOR', 'PROFESOR', 'ESTUDIANTE']);

function rechazarArchivo($mensaje)
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['icono'] = 'error';
    header('Location: ../../../admin/tareas/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verificarCsrf('admin/tareas/index.php');

    $id_tarea = filter_input(INPUT_POST, 'id_tarea', FILTER_VALIDATE_INT);
    $archivo = $_FILES['archivo'] ?? null;
    $es_estudiante = ($_SESSION['role'] ?? '') === 'ESTUDIANTE';

    if (!$id_tarea) {
        rechazarArchivo('El archivo no pudo ser recibido');
    }
    // Las reglas de tamaño, extension y tipo MIME estan en config/archivos.php
    $error_archivo = errorArchivoSubido($archivo);
    if ($error_archivo !== null) {
        rechazarArchivo($error_archivo);
    }
    $nombre_original = $archivo['name'];

    $sql_tarea = "SELECT * FROM tareas WHERE id_tarea = :id_tarea";
    $parametros_tarea = [':id_tarea' => $id_tarea];
    if ($es_estudiante) {
        $sql_tarea .= " AND id_materia IN (SELECT id_materia FROM matriculas WHERE id_usuario = :id_usuario)";
        $parametros_tarea[':id_usuario'] = $_SESSION['id_usuario'];
    }
    $sentencia = $pdo->prepare($sql_tarea);
    $sentencia->execute($parametros_tarea);
    $tarea = $sentencia->fetch(PDO::FETCH_ASSOC);

    if (!$tarea) {
        header('Location: ../../../admin/tareas/index.php');
        exit();
    }

    // El plazo solo aplica a la entrega del estudiante; el profesor puede adjuntar material cuando quiera
    if ($es_estudiante && !tareaAbiertaParaEntregas($tarea)) {
        $_SESSION['mensaje'] = "La fecha y hora de entrega han pasado No puedes subir archivos";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/tareas/show.php?id=' . $id_tarea);
        exit();
    }

    $nombre_guardado = guardarArchivoSubido($archivo);
    if ($nombre_guardado === null) {
        $_SESSION['mensaje'] = "Hubo un error al subir el archivo Por favor intentalo de nuevo";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/tareas/show.php?id=' . $id_tarea);
        exit();
    }

    if ($es_estudiante) {
        // Exclusion mutua con GET_LOCK: una sola subida a la vez por estudiante y tarea.
        // Dentro, una transaccion: si algo falla se deshace todo y borro el archivo nuevo.
        try {
            $ruta_anterior = conBloqueo($pdo, nombreBloqueoEntrega($id_tarea, (int) $_SESSION['id_usuario']), 5, function () use ($pdo, $id_tarea, $nombre_guardado, $nombre_original, $fechaHora) {
                $pdo->beginTransaction();
                try {
                    // Busco si el estudiante ya tenia una entrega para borrar el archivo anterior al reemplazarla
                    $sentencia = $pdo->prepare("SELECT ruta_archivo FROM entregas WHERE id_tarea = :id_tarea AND id_usuario = :id_usuario FOR UPDATE");
                    $sentencia->execute([':id_tarea' => $id_tarea, ':id_usuario' => $_SESSION['id_usuario']]);
                    $anterior = $sentencia->fetchColumn();

                    // Registro SOLO la entrega de este estudiante; si vuelve a subir, reemplazo su entrega
                    $sentencia = $pdo->prepare("INSERT INTO entregas (id_tarea, id_usuario, ruta_archivo, nombre_original, fecha_entrega)
                        VALUES (:id_tarea, :id_usuario, :ruta_archivo, :nombre_original, :fecha_entrega)
                        ON DUPLICATE KEY UPDATE ruta_archivo = VALUES(ruta_archivo), nombre_original = VALUES(nombre_original),
                            fecha_entrega = VALUES(fecha_entrega)");
                    $sentencia->execute([
                        ':id_tarea' => $id_tarea,
                        ':id_usuario' => $_SESSION['id_usuario'],
                        ':ruta_archivo' => $nombre_guardado,
                        ':nombre_original' => mb_substr($nombre_original, 0, 255),
                        ':fecha_entrega' => $fechaHora,
                    ]);
                    $pdo->commit();
                    return $anterior;
                } catch (Throwable $error) {
                    $pdo->rollBack();
                    throw $error;
                }
            });
        } catch (Throwable $error) {
            borrarArchivoSubido($nombre_guardado);
            $_SESSION['mensaje'] = $error instanceof BloqueoOcupado
                ? 'Ya hay una subida de esta entrega en curso, espere un momento'
                : 'No se pudo registrar la entrega, intente de nuevo';
            $_SESSION['icono'] = 'error';
            header('Location: ../../../admin/tareas/show.php?id=' . $id_tarea);
            exit();
        }

        borrarArchivoSubido($ruta_anterior ?: null);

        $_SESSION['mensaje'] = $ruta_anterior
            ? "Tu entrega fue reemplazada correctamente"
            : "Tu entrega fue registrada correctamente";
    } else {
        // El profesor o el administrador adjuntan material a la tarea
        $sentencia = $pdo->prepare("INSERT INTO archivos (id_tarea, ruta_archivo) VALUES (?, ?)");
        $sentencia->execute([$id_tarea, $nombre_guardado]);
        $_SESSION['mensaje'] = "El archivo de la tarea fue subido correctamente";
    }

    $_SESSION['icono'] = "success";
    header('Location: ../../../admin/tareas/show.php?id=' . $id_tarea);
    exit();
}
