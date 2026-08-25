<?php
include('../../config/config.php');
include('../../config/autenticacion_rol.php');

// Verifico si el rol es valido para crear tareas
if (!in_array($_SESSION['role'], ['ADMINISTRADOR', 'PROFESOR'])) {
    header('Location: index.php');
    exit();
}

include('../layout/parte1.php');
include('../../config/controllers/materias/listado_de_materias.php');
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <br>
    <div class="content">
        <div class="container">
            <div class="row">
                <h1>Crear Tarea</h1>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Digite los datos</h3>
                        </div>
                        <div class="card-body">
                            <form action="../../config/controllers/tareas/create.php" method="POST">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="">Titulo</label>
                                            <input type="text" class="form-control" name="titulo" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="">Descripcion</label>
                                            <textarea class="form-control" name="descripcion" required></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Fecha de Entrega</label>
                                            <input type="date" class="form-control" name="fecha_entrega" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Hora de Entrega</label>
                                            <input type="time" class="form-control" name="hora_entrega" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="">Materia</label>
                                            <select class="form-control" name="id_materia" required>
                                                <?php foreach ($materias as $materia): ?>
                                                    <option value="<?= $materia['id_materia'] ?>"><?= $materia['nombre_materia'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Guardar Tarea</button>
                                            <a href="index.php" class="btn btn-secondary">Cancelar</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include('../layout/parte2.php');
include('../../layout/mostrarMensajes.php');
?>