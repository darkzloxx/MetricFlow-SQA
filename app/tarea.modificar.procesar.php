<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_PERMISOS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;


$query = "UPDATE tarea "
        . "SET nombre = '{$DatosFormulario["nombre"]}',  descripcion = '{$DatosFormulario["descripcion"]}'  "
        . "WHERE id_tarea = {$DatosFormulario["id"]}";
$consulta = BDConexion::getInstancia()->query($query);


$query = "DELETE FROM metrica_tarea "
        . "WHERE id_tarea = {$DatosFormulario["id"]}";
$consulta = BDConexion::getInstancia()->query($query);

foreach ($DatosFormulario["permiso"] as $idPermiso) {
    $query = "INSERT INTO metrica_tarea "
            . "VALUES ({$idPermiso},{$DatosFormulario["id"]} )";
    $consulta = BDConexion::getInstancia()->query($query);
    if (!$consulta) {
        BDConexion::getInstancia()->rollback();
        //arrojar una excepcion
        die(BDConexion::getInstancia()->errno);
    }
}

$query = "DELETE FROM iteracion_tarea "
        . "WHERE id_tarea = {$DatosFormulario["id"]}";
$consulta = BDConexion::getInstancia()->query($query);

$query = "INSERT INTO iteracion_tarea "
            . "VALUES ({$DatosFormulario["iteracion"]},{$DatosFormulario["id"]} )";
    $consulta = BDConexion::getInstancia()->query($query);
    if (!$consulta) {
        BDConexion::getInstancia()->rollback();
        //arrojar una excepcion
        die(BDConexion::getInstancia()->errno);
    }
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Actualizar Tarea</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Actualizar Tarea</h3>
                </div>
                <div class="card-body">
                    <?php if ($consulta) { ?>
                        <div class="alert alert-success" role="alert">
                            Operaci&oacute;n realizada con &eacute;xito.
                        </div>
                    <?php } ?>   
                    <?php if (!$consulta) { ?>
                        <div class="alert alert-danger" role="alert">
                            Ha ocurrido un error.
                        </div>
                    <?php } ?>
                    <hr />
                    <h5 class="card-text">Opciones</h5>
                    <a href="tarea.php">
                        <button type="button" class="btn btn-primary">
                            <span class="oi oi-account-logout"></span> Salir
                        </button>
                    </a>
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
