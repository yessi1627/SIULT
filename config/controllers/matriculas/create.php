<?php
include('../../config.php');
include('../../autenticacion_rol.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../../../admin/matriculas/index.php');
  exit();
}

$id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
$id_materias = $_POST['id_materias'] ?? [];
$id_materias = array_values(array_filter(array_map('intval', (array) $id_materias), function ($id_materia) {
  return $id_materia > 0;
}));

if (!$id_usuario || count($id_materias) === 0) {
  session_start();
  $_SESSION['mensaje'] = 'Seleccione un estudiante y al menos una materia';
  $_SESSION['icono'] = 'error';
  header('Location: ../../../admin/matriculas/index.php');
  exit();
}

$estudiante = $pdo->prepare("SELECT u.id_usuario
    FROM usuarios u
    INNER JOIN roles r ON r.id_rol = u.rol_id
    WHERE u.id_usuario = :id_usuario AND u.estado = '1' AND r.nombre_rol = 'ESTUDIANTE'");
$estudiante->execute([':id_usuario' => $id_usuario]);

if (!$estudiante->fetchColumn()) {
  session_start();
  $_SESSION['mensaje'] = 'El usuario seleccionado no es un estudiante activo';
  $_SESSION['icono'] = 'error';
  header('Location: ../../../admin/matriculas/index.php');
  exit();
}

try {
  $pdo->beginTransaction();
  $materia = $pdo->prepare("INSERT IGNORE INTO matriculas (id_usuario, id_materia, fecha_matricula)
        SELECT :id_usuario, id_materia, :fecha_matricula
        FROM materias
        WHERE id_materia = :id_materia AND estado = '1'");

  foreach ($id_materias as $id_materia) {
    $materia->execute([
      ':id_usuario' => $id_usuario,
      ':id_materia' => $id_materia,
      ':fecha_matricula' => $fechaHora,
    ]);
  }
  $pdo->commit();

  session_start();
  $_SESSION['mensaje'] = 'Matrícula(s) registrada(s) correctamente';
  $_SESSION['icono'] = 'success';
} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  session_start();
  $_SESSION['mensaje'] = 'No se pudieron registrar las matrículas';
  $_SESSION['icono'] = 'error';
}

header('Location: ../../../admin/matriculas/index.php');
exit();
