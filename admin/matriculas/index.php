<?php
include('../../config/config.php');
include('../../config/autenticacion_rol.php');

$consulta_estudiantes = $pdo->prepare("SELECT u.id_usuario, u.nombres, u.email
    FROM usuarios u
    INNER JOIN roles r ON r.id_rol = u.rol_id
    WHERE u.estado = '1' AND r.nombre_rol = 'ESTUDIANTE'
    ORDER BY u.nombres");
$consulta_estudiantes->execute();
$estudiantes = $consulta_estudiantes->fetchAll(PDO::FETCH_ASSOC);

$consulta_materias = $pdo->prepare("SELECT id_materia, nombre_materia
    FROM materias WHERE estado = '1' ORDER BY nombre_materia");
$consulta_materias->execute();
$materias = $consulta_materias->fetchAll(PDO::FETCH_ASSOC);

$consulta_matriculas = $pdo->prepare("SELECT m.nombre_materia, u.nombres, u.email, ma.fecha_matricula
    FROM matriculas ma
    INNER JOIN materias m ON m.id_materia = ma.id_materia
    INNER JOIN usuarios u ON u.id_usuario = ma.id_usuario
    ORDER BY m.nombre_materia, u.nombres");
$consulta_matriculas->execute();
$matriculas = $consulta_matriculas->fetchAll(PDO::FETCH_ASSOC);

include('../layout/parte1.php');
?>

<div class="content-wrapper">
  <br>
  <div class="content">
    <div class="container">
      <h1>Módulo de Matrículas</h1>
      <div class="row">
        <div class="col-lg-5">
          <div class="card card-outline card-primary">
            <div class="card-header">
              <h3 class="card-title">Matricular estudiante</h3>
            </div>
            <div class="card-body">
              <form action="../../config/controllers/matriculas/create.php" method="POST">
                <div class="form-group">
                  <label for="id_usuario">Estudiante</label>
                  <select id="id_usuario" name="id_usuario" class="form-control" required>
                    <option value="">Seleccione un estudiante</option>
                    <?php foreach ($estudiantes as $estudiante): ?>
                      <option value="<?= (int) $estudiante['id_usuario']; ?>">
                        <?= htmlspecialchars($estudiante['nombres'] . ' - ' . $estudiante['email'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Materias</label>
                  <?php foreach ($materias as $materia): ?>
                    <div class="custom-control custom-checkbox">
                      <input class="custom-control-input" type="checkbox"
                        id="materia_<?= (int) $materia['id_materia']; ?>" name="id_materias[]"
                        value="<?= (int) $materia['id_materia']; ?>">
                      <label class="custom-control-label" for="materia_<?= (int) $materia['id_materia']; ?>">
                        <?= htmlspecialchars($materia['nombre_materia'], ENT_QUOTES, 'UTF-8'); ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Registrar
                  matrícula</button>
              </form>
            </div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card card-outline card-success">
            <div class="card-header">
              <h3 class="card-title">Estudiantes por materia</h3>
            </div>
            <div class="card-body">
              <table id="example1" class="table table-striped table-bordered">
                <thead>
                  <tr>
                    <th>Materia</th>
                    <th>Estudiante</th>
                    <th>Email</th>
                    <th>Fecha de matrícula</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($matriculas as $matricula): ?>
                    <tr>
                      <td><?= htmlspecialchars($matricula['nombre_materia'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?= htmlspecialchars($matricula['nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?= htmlspecialchars($matricula['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?= htmlspecialchars($matricula['fecha_matricula'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('../layout/parte2.php'); ?>
<script>
  $(function () {
    $('#example1').DataTable({ responsive: true, lengthChange: false, autoWidth: false });
  });
</script>
<?php include('../../layout/mostrarMensajes.php'); ?>