<?php
include('../../config/config.php');
include('../../config/autenticacion_rol.php');
include('../layout/parte1.php');

$id_tarea = $_GET['id'];
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
    header('Location: index.php');
    exit();
}

$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i:s');

$estudiantes_calificacion = [];
if (in_array($_SESSION['role'] ?? '', ['ADMINISTRADOR', 'PROFESOR'], true)) {
    $sql_estudiantes = "SELECT u.id_usuario, u.nombres, u.email, c.nota, c.observacion
        FROM usuarios u
        INNER JOIN roles r ON r.id_rol = u.rol_id
        INNER JOIN matriculas m ON m.id_usuario = u.id_usuario
        LEFT JOIN calificaciones c ON c.id_usuario = u.id_usuario AND c.id_tarea = :id_tarea
        WHERE m.id_materia = :id_materia AND u.estado = '1' AND r.nombre_rol = 'ESTUDIANTE'
        ORDER BY u.nombres";
    $sentencia_estudiantes = $pdo->prepare($sql_estudiantes);
    $sentencia_estudiantes->execute([
        ':id_tarea' => $id_tarea,
        ':id_materia' => $tarea['id_materia'],
    ]);
    $estudiantes_calificacion = $sentencia_estudiantes->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <br>
    <div class="content">
        <div class="container">
            <div class="row">
                <h1>Detalles de la Tarea</h1>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Detalles de la tarea: <?= $tarea['titulo'] ?></h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Descripcion:</strong> <?= $tarea['descripcion'] ?></p>
                            <p><strong>Fecha de Entrega:</strong> <?= $tarea['fecha_entrega'] ?></p>
                            <p><strong>Hora de Entrega:</strong> <?= $tarea['hora_entrega'] ?></p>
                            <p><strong>Materia:</strong> <?= $tarea['id_materia'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($fecha_actual <= $tarea['fecha_entrega'] && $hora_actual <= $tarea['hora_entrega']): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Subir archivo para la tarea</h3>
                            </div>
                            <div class="card-body">
                                <form action="../../config/controllers/tareas/upload.php" method="POST"
                                    enctype="multipart/form-data">
                                    <input type="hidden" name="id_tarea" value="<?= $id_tarea ?>">
                                    <div class="form-group">
                                        <label for="archivo">Archivo</label>
                                        <input type="file" class="form-control" name="archivo"
                                            accept=".pdf,.docx,.jpg,.jpeg,.png,.zip" required>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Subir Archivo</button>
                                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-danger">
                            La fecha y hora de entrega han pasado No puedes subir archivos
                        </div>
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'] ?? '', ['ADMINISTRADOR', 'PROFESOR'], true)): ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Calificar estudiantes</h3>
                            </div>
                            <div class="card-body">
                                <?php if (count($estudiantes_calificacion) === 0): ?>
                                    <p>No hay estudiantes matriculados en la materia de esta tarea.</p>
                                <?php else: ?>
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Estudiante</th>
                                                <th>Email</th>
                                                <th>Nota</th>
                                                <th>Observación</th>
                                                <th>Calificar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($estudiantes_calificacion as $estudiante): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($estudiante['nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?= htmlspecialchars($estudiante['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?= $estudiante['nota'] !== null ? htmlspecialchars($estudiante['nota'], ENT_QUOTES, 'UTF-8') : 'Sin calificar'; ?></td>
                                                    <td><?= $estudiante['observacion'] !== null ? htmlspecialchars($estudiante['observacion'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                                    <td>
                                                        <form action="../../config/controllers/calificaciones/create.php" method="POST" class="form-inline">
                                                            <input type="hidden" name="id_tarea" value="<?= $id_tarea ?>">
                                                            <input type="hidden" name="id_usuario" value="<?= (int) $estudiante['id_usuario']; ?>">
                                                            <input type="number" step="0.01" min="0" max="5" name="nota" class="form-control form-control-sm mr-1" style="width: 80px;"
                                                                value="<?= $estudiante['nota'] !== null ? htmlspecialchars($estudiante['nota'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                                                            <input type="text" name="observacion" class="form-control form-control-sm mr-1" placeholder="Observación"
                                                                value="<?= $estudiante['observacion'] !== null ? htmlspecialchars($estudiante['observacion'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                                            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
include('../layout/parte2.php');
include('../../layout/mostrarMensajes.php');
?>