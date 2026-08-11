<?php
$ruta_actual = $_SERVER['PHP_SELF'];
$en_subcarpeta = (strpos($ruta_actual, '/admin/') !== false &&
  substr_count($ruta_actual, '/', strpos($ruta_actual, '/admin/')) > 2);

$base_path = $en_subcarpeta ? "../../" : "../";
?>

<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
  <div class="p-3">
    <h5>Title</h5>
    <p>Sidebar content</p>
  </div>
</aside>
<!-- /.control-sidebar -->

<!-- Main Footer -->
<footer class="main-footer">
  <div class="float-right d-none d-sm-inline">
    Anything you want
  </div>
  <strong>Copyright @<?= $anioActual; ?><a href=""> Marlly Pineda</a>.</strong> All rights reserved.
</footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="<?= $base_path ?>public/plugins/jquery/jquery.min.js"></script>
<script src="<?= $base_path ?>public/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?= $base_path ?>public/plugins/jszip/jszip.min.js"></script>
<script src="<?= $base_path ?>public/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?= $base_path ?>public/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?= $base_path ?>public/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="<?= $base_path ?>public/dist/js/adminlte.min.js"></script>
</body>

</html>