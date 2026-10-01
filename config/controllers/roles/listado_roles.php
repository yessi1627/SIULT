<?php
// Este archivo solo se incluye desde una vista; no se puede abrir directamente
require_once __DIR__ . '/../../seguridad.php';
impedirAccesoDirecto(__FILE__);
$sql_roles = "SELECT * FROM roles WHERE estado = '1'";
$query_roles = $pdo->prepare($sql_roles);
$query_roles->execute();
$roles = $query_roles->fetchAll(PDO::FETCH_ASSOC);
