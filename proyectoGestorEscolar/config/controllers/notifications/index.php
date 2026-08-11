<?php
include('../../config.php');

// Obtengo todas las notificaciones
$sql = "SELECT * FROM notificaciones";
$query = $pdo->prepare($sql);
$query->execute();
$notifications = $query->fetchAll(PDO::FETCH_ASSOC);

// Devuelvo las notificaciones en formato JSON
header('Content-Type: application/json');
echo json_encode($notifications);
