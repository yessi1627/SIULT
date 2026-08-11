<?php
include('../../config/config.php');
include('../../config/autenticacion_rol.php');
include('../layout/parte1.php');
?>

<!-- Formulario para crear un nuevo rol -->
<div class="content-wrapper">
    <br>
    <div class="content">
        <div class="container">
            <div class="row">
                <h1>Crear nuevo rol</h1>
            </div>
            <br>
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Digite los datos</h3>
                        </div>
                        <div class="card-body">
                            <form action="../../config/controllers/roles/create.php" method="POST">
                                <div class="form-group">
                                    <label for="nombre_rol">Nombre del rol</label>
                                    <input type="text" name="nombre_rol" id="nombre_rol" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                    <a href="index.php" class="btn btn-secondary">Volver</a>
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