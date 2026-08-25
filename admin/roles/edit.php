<?php
$id_rol = $_GET['id'];
include('../../config/config.php');
include('../../config/autenticacion_rol.php');
include('../layout/parte1.php');
include('../../config/controllers/roles/datos_rol.php');
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <br>
  <div class="content">
    <div class="container">
      <div class="row">
        <h1>Modificar rol: <?= $nombre_rol; ?></h1>
      </div>
      <br>
      <div class="row">
        <div class="col-md-6">
          <div class="card card-outline card-success">
            <div class="card-header">
              <h3 class="card-title">Llene los datos</h3>
            </div>
            <div class="card-body">
              <form action="../../config/controllers/roles/update.php" method="post">
                <div class="row">
                  <div class="col-md-12">
                    <div class="form-group">
                      <input type="hidden" name="id_rol" value="<?= $id_rol; ?>">
                      <label for="">Rol</label>
                      <input type="text" value="<?= $nombre_rol; ?>" name="nombre_rol" class="form-control" required>
                    </div>
                  </div>
                </div>
                <hr>
                <div class="row">
                  <div class="col-md-12">
                    <div class="form-group">
                      <button type="submit" class="btn btn-success">Actualizar</button>
                      <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <!-- /.row -->
    </div><!-- /.container-fluid -->
  </div>
  <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php
include('../layout/parte2.php');
include('../../layout/mostrarMensajes.php');
?>