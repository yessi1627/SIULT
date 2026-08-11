<?php
include('../../config.php');
include('../../../observers/Subject.php');
include('../../../observers/NotificacionObserver.php');

// PATRON OBSERVER
$subject = new Subject();
$notificacionObserver = new NotificacionObserver();
$subject->addObserver($notificacionObserver);

// Obtengo los datos del formulario
$id_materia = $_POST['id_materia'];
$titulo = $_POST['titulo'];
$descripcion = $_POST['descripcion'];
$fecha_entrega = $_POST['fecha_entrega'];
$hora_entrega = $_POST['hora_entrega'];

// Obtengo el nombre de la materia
$sentencia = $pdo->prepare("SELECT nombre_materia FROM materias WHERE id_materia = ?");
$sentencia->execute([$id_materia]);
$materia = $sentencia->fetch(PDO::FETCH_ASSOC);
$nombre_materia = $materia['nombre_materia'];

// Inserto la nueva tarea en la base de datos
$sentencia = $pdo->prepare("INSERT INTO tareas (id_materia, titulo, descripcion, fecha_entrega, hora_entrega, estado) VALUES (?, ?, ?, ?, ?, 'Pendiente')");
$sentencia->execute([$id_materia, $titulo, $descripcion, $fecha_entrega, $hora_entrega]);

// Obtengo el ID de la tarea recien creada
$id_tarea = $pdo->lastInsertId();

// Notifico la creacion de la tarea
$mensaje = "Se ha creado una nueva tarea en la materia $nombre_materia: $titulo";
$subject->notifyObservers(['mensaje' => $mensaje, 'id_tarea' => $id_tarea]);

// Verifico si la tarea esta proxima a vencer
$fecha_actual = new DateTime();
$fecha_entrega_dt = new DateTime($fecha_entrega);
$intervalo = $fecha_actual->diff($fecha_entrega_dt);

if ($intervalo->days <= 2 && $intervalo->invert == 0) {
    $mensaje_vencimiento = "La tarea '$titulo' de la materia '$nombre_materia' esta proxima a vencer";
    $subject->notifyObservers(['mensaje' => $mensaje_vencimiento, 'id_tarea' => $id_tarea]);
}

// Inicio sesion y establezco mensaje de exito
session_start();
$_SESSION['mensaje'] = "Se ha creado la tarea con exito";
$_SESSION['icono'] = "success";

// Redirijo al usuario al listado de tareas
header('Location: ../../../admin/tareas/index.php');
exit();
