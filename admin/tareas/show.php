<?php
include('../../config/config.php');
include('../../config/autenticacion_rol.php');
require_once __DIR__ . '/../../config/estados_tarea.php';
include('../layout/parte1.php');

$id_tarea = (int) ($_GET['id'] ?? 0);
$es_estudiante = ($_SESSION['role'] ?? '') === 'ESTUDIANTE';
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

$tarea_abierta = tareaAbiertaParaEntregas($tarea);

// Si es estudiante busco SU entrega (solo puede tener una por tarea)
$mi_entrega = null;
if ($es_estudiante) {
    $sentencia = $pdo->prepare("SELECT ruta_archivo, nombre_original, fecha_entrega FROM entregas WHERE id_tarea = :id_tarea AND id_usuario = :id_usuario");
    $sentencia->execute([':id_tarea' => $id_tarea, ':id_usuario' => $_SESSION['id_usuario']]);
    $mi_entrega = $sentencia->fetch(PDO::FETCH_ASSOC) ?: null;
}

$estudiantes_calificacion = [];
if (in_array($_SESSION['role'] ?? '', ['ADMINISTRADOR', 'PROFESOR'], true)) {
    $sql_estudiantes = "SELECT u.id_usuario, u.nombres, u.email, c.nota, c.observacion, c.version,
            e.ruta_archivo AS ruta_entrega, e.fecha_entrega AS fecha_entrega_estudiante
        FROM usuarios u
        INNER JOIN roles r ON r.id_rol = u.rol_id
        INNER JOIN matriculas m ON m.id_usuario = u.id_usuario
        LEFT JOIN calificaciones c ON c.id_usuario = u.id_usuario AND c.id_tarea = :id_tarea
        LEFT JOIN entregas e ON e.id_usuario = u.id_usuario AND e.id_tarea = :id_tarea_entrega
        WHERE m.id_materia = :id_materia AND u.estado = '1' AND r.nombre_rol = 'ESTUDIANTE'
        ORDER BY u.nombres";
    $sentencia_estudiantes = $pdo->prepare($sql_estudiantes);
    $sentencia_estudiantes->execute([
        ':id_tarea' => $id_tarea,
        ':id_tarea_entrega' => $id_tarea,
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
                            <h3 class="card-title">Detalles de la tarea: <?= htmlspecialchars($tarea['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Descripcion:</strong> <?= htmlspecialchars($tarea['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p><strong>Fecha de Entrega:</strong> <?= $tarea['fecha_entrega'] ?></p>
                            <p><strong>Hora de Entrega:</strong> <?= $tarea['hora_entrega'] ?></p>
                            <p><strong>Materia:</strong> <?= $tarea['id_materia'] ?></p>
                            <p><strong>Estado:</strong> <?= htmlspecialchars($tarea['estado'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($es_estudiante && $mi_entrega): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-success">
                            Entregaste esta tarea el <?= htmlspecialchars($mi_entrega['fecha_entrega'], ENT_QUOTES, 'UTF-8') ?>:
                            <a href="../../config/uploads/<?= rawurlencode($mi_entrega['ruta_archivo']) ?>" target="_blank">
                                <?= htmlspecialchars($mi_entrega['nombre_original'] ?? 'Ver archivo', ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php // Solo el estudiante entrega aqui; el profesor adjunta material desde la edicion de la tarea ?>
            <?php if ($es_estudiante && $tarea_abierta): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title"><?= $mi_entrega ? 'Reemplazar mi entrega' : 'Entregar la tarea' ?></h3>
                            </div>
                            <div class="card-body">
                                <form action="../../config/controllers/tareas/upload.php" method="POST"
                                    enctype="multipart/form-data"><?= campoCsrf() ?>
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
            <?php elseif ($es_estudiante): ?>
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
                                                <th>Entrega</th>
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
                                                    <td>
                                                        <?php if ($estudiante['ruta_entrega']): ?>
                                                            <a href="../../config/uploads/<?= rawurlencode($estudiante['ruta_entrega']) ?>" target="_blank">Ver entrega</a>
                                                            <br><small><?= htmlspecialchars($estudiante['fecha_entrega_estudiante'], ENT_QUOTES, 'UTF-8') ?></small>
                                                        <?php else: ?>
                                                            <?= $tarea_abierta ? ESTADO_ENTREGA_PENDIENTE : ESTADO_ENTREGA_NO_ENTREGO ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= $estudiante['nota'] !== null ? htmlspecialchars($estudiante['nota'], ENT_QUOTES, 'UTF-8') : 'Sin calificar'; ?></td>
                                                    <td><?= $estudiante['observacion'] !== null ? htmlspecialchars($estudiante['observacion'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                                    <td>
                                                        <form action="../../config/controllers/calificaciones/create.php" method="POST" class="form-inline"><?= campoCsrf() ?>
                                                            <input type="hidden" name="id_tarea" value="<?= $id_tarea ?>">
                                                            <input type="hidden" name="id_usuario" value="<?= (int) $estudiante['id_usuario']; ?>">
                                                            <input type="hidden" name="version" value="<?= (int) ($estudiante['version'] ?? 0); ?>">
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