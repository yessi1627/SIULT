<?php
include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
exigirRol(['ADMINISTRADOR', 'PROFESOR', 'ESTUDIANTE']);

const TAMANO_MAXIMO_ARCHIVO = 5242880;
const EXTENSIONES_PERMITIDAS = ['pdf', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
const TIPOS_MIME_PERMITIDOS = [
    'pdf' => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'zip' => ['application/zip', 'application/x-zip-compressed'],
];

function rechazarArchivo($mensaje)
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['icono'] = 'error';
    header('Location: ../../../admin/tareas/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_tarea = filter_input(INPUT_POST, 'id_tarea', FILTER_VALIDATE_INT);
    $archivo = $_FILES['archivo'] ?? null;

    if (!$id_tarea || !$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
        rechazarArchivo('El archivo no pudo ser recibido');
    }
    if ($archivo['size'] > TAMANO_MAXIMO_ARCHIVO) {
        rechazarArchivo('El archivo supera el tamaño máximo permitido de 5 MB');
    }

    $nombre_original = $archivo['name'];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    if (!in_array($extension, EXTENSIONES_PERMITIDAS, true)) {
        rechazarArchivo('Tipo de archivo no permitido');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipo_mime = $finfo->file($archivo['tmp_name']);
    if (!in_array($tipo_mime, TIPOS_MIME_PERMITIDOS[$extension], true)) {
        rechazarArchivo('El contenido del archivo no coincide con su extensión');
    }

    $sql_tarea = "SELECT * FROM tareas WHERE id_tarea = :id_tarea";
    $parametros_tarea = [':id_tarea' => $id_tarea];
    if (($_SESSION['role'] ?? '') === 'ESTUDIANTE') {
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

    $fecha_actual = date('Y-m-d');
    $hora_actual = date('H:i:s');

    if ($fecha_actual > $tarea['fecha_entrega'] || ($fecha_actual == $tarea['fecha_entrega'] && $hora_actual > $tarea['hora_entrega'])) {
        $_SESSION['mensaje'] = "La fecha y hora de entrega han pasado No puedes subir archivos";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/tareas/show.php?id=' . $id_tarea);
        exit();
    }

    $directorio = '../../uploads/';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }
    $nombre_guardado = bin2hex(random_bytes(16)) . '.' . $extension;
    $ruta_archivo = $directorio . $nombre_guardado;

    if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
        $sentencia = $pdo->prepare("INSERT INTO archivos (id_tarea, ruta_archivo) VALUES (?, ?)");
        $sentencia->execute([$id_tarea, $nombre_guardado]);

        $sentencia = $pdo->prepare("UPDATE tareas SET estado = 'completado' WHERE id_tarea = ?");
        $sentencia->execute([$id_tarea]);

        $_SESSION['mensaje'] = "El archivo fue subido correctamente La tarea ha sido marcada como completada";
        $_SESSION['icono'] = "success";
        header('Location: ../../../admin/tareas/index.php');
        exit();
    } else {
        $_SESSION['mensaje'] = "Hubo un error al subir el archivo Por favor intentalo de nuevo";
        $_SESSION['icono'] = "error";
        header('Location: ../../../admin/tareas/show.php?id=' . $id_tarea);
        exit();
    }
}
