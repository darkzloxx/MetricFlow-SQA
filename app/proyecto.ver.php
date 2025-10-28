<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_PROYECTOS);
include_once '../modelo/Permiso.php';

// ID del proyecto recibido por GET
$idProyecto = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$urlDashboard = 'dashboard.php?proyecto=' . $idProyecto;
?>


<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
       <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades del Proyecto</title>

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Propiedades del Proyecto</h3>
                </div>
                <div class="card-body">
                    <h5 class="card-text mb-3">Ver tablero del proyecto</h5>
                    <a class="btn btn-primary" href="<?= $urlDashboard; ?>">
                        <span class="oi oi-graph"></span> Abrir Dashboard
                    </a>
                    <a class="btn btn-outline-secondary ml-2" href="<?= $urlDashboard; ?>" target="_blank" rel="noopener">
                        Abrir en otra pestaña
                    </a>
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
