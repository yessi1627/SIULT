<?php
include('../config/config.php');

session_start();

// Verifico que el usuario este logueado
if (!isset($_SESSION['admin']) && !isset($_SESSION['sesion email'])) {
  // Redirijo al login
  header('Location: ../index.php');
  exit;
}

// Si es admin, lo mando a su pagina
if ($_SESSION['role'] === 'ADMINISTRADOR') {
  header('Location: index.php');
  exit;
}

// Incluyo el layout y los controladores necesarios
include('layout/parte1.php');
include('../config/controllers/roles/listado_roles.php');
include('../config/controllers/usuarios/listado_usuarios.php');
include('../config/controllers/materias/listado_de_materias.php');
include('../config/controllers/tareas/index.php');
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <br>
  <div class="container">
    <div class="container">
      <div class="row">
        <h1><?= APP_NAME; ?></h1>
      </div>
      <br>
      <div class="row">

        <div class="col-lg-3 col-6">
          <div class="small-box bg-danger">
            <div class="inner">
              <?php
              $contador_materias = 0;
              foreach ($materias as $materia) {
                $contador_materias++;
              }
              ?>
              <h3><?= $contador_materias; ?></h3>
              <p>Materias registradas</p>
            </div>
            <div class="icon">
              <i class="fas"><i class="bi bi-bookshelf"></i></i>
            </div>
            <a href="materias/index.php" class="small-box-footer">
              Más información <i class="fas fa-arrow-circle-right"></i>
            </a>
          </div>
        </div>

        <div class="col-lg-3 col-6">
          <div class="small-box bg-success">
            <div class="inner">
              <?php
              $contador_tareas = 0;
              foreach ($tareas as $tarea) {
                $contador_tareas++;
              }
              ?>
              <h3><?= $contador_tareas; ?></h3>
              <p>Tareas registradas</p>
            </div>
            <div class="icon">
              <i class="fas"><i class="bi bi-plus-slash-minus"></i></i>
            </div>
            <a href="tareas/index.php" class="small-box-footer">
              Más información <i class="fas fa-arrow-circle-right"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
    <!-- /.row -->
  </div><!-- /.container-fluid -->
</div>
<!-- /.content-wrapper -->

<?php
include('layout/parte2.php');
include('../layout/mostrarMensajes.php');
?>