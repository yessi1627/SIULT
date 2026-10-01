<?php
// Este archivo solo se incluye desde una vista; no se puede abrir directamente
require_once __DIR__ . '/../../seguridad.php';
impedirAccesoDirecto(__FILE__);
// Uso sentencia preparada; antes el id se concatenaba en el SQL y permitia inyeccion
$sql_roles = "SELECT * FROM roles WHERE estado = '1' AND id_rol = :id_rol";
$query_roles = $pdo->prepare($sql_roles);
$query_roles->execute([':id_rol' => $id_rol]);
$datos_roles = $query_roles->fetchAll(PDO::FETCH_ASSOC);
foreach ($datos_roles as $datos_role) {
    $nombre_rol = $datos_role['nombre_rol'];
}
