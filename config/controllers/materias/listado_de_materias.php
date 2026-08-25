<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$sql_materias = "SELECT m.* FROM materias m";
$parametros_materias = [];

if (($_SESSION['role'] ?? '') === 'ESTUDIANTE') {
  $sql_materias .= " INNER JOIN matriculas ma ON ma.id_materia = m.id_materia
		WHERE m.estado = '1' AND ma.id_usuario = :id_usuario";
  $parametros_materias[':id_usuario'] = $_SESSION['id_usuario'];
} else {
  $sql_materias .= " WHERE m.estado = '1'";
}

$query_materias = $pdo->prepare($sql_materias);
$query_materias->execute($parametros_materias);
$materias = $query_materias->fetchAll(PDO::FETCH_ASSOC);
