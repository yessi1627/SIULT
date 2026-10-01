<?php
// Este archivo solo se incluye desde una vista; no se puede abrir directamente
require_once __DIR__ . '/../../seguridad.php';
impedirAccesoDirecto(__FILE__);

$id_tarea = $_GET['id'];
$sentencia = $pdo->prepare("SELECT * FROM tareas WHERE id_tarea = :id_tarea");
$sentencia->bindParam(':id_tarea', $id_tarea);
$sentencia->execute();
$tarea = $sentencia->fetch(PDO::FETCH_ASSOC);
