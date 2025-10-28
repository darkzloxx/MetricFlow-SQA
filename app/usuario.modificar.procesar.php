<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;
$idUsuario = $DatosFormulario["id"];

BDConexion::getInstancia()->autocommit(false);
BDConexion::getInstancia()->begin_transaction();

$query = "UPDATE usuario "
        . "SET nombre = '{$DatosFormulario["nombre"]}', email = '{$DatosFormulario["email"]}' "
        . "WHERE id = {$idUsuario}";
$consulta = BDConexion::getInstancia()->query($query);
if (!$consulta) {
    BDConexion::getInstancia()->rollback();
    //arrojar una excepcion
    die(BDConexion::getInstancia()->errno);
}

$query = "DELETE FROM usuario_proyecto "
        . "WHERE id_usuario = {$idUsuario}";
$consulta = BDConexion::getInstancia()->query($query);
if (!$consulta) {
    BDConexion::getInstancia()->rollback();
    //arrojar una excepcion
    die(BDConexion::getInstancia()->errno);
}

if (isset($_POST['listaProyectos'])) {
    $listaProyectos = $_POST['listaProyectos'];
    $rol = $_POST['rol'];
    $cont = count($listaProyectos);
        for ($i = 0; $i < $cont; ++$i) {
            if ($listaProyectos[$i] != " ") {
                $proyectosId = "SELECT id_proyecto FROM proyecto where nombre = '".$listaProyectos[$i]."'"; 
                $proyectosId=BDConexion::getInstancia()->query($proyectosId);
                $proyectoId = $proyectosId->fetch_all(MYSQLI_ASSOC); 
                foreach ($proyectoId as $ProyecId) {
                    $id_proyecto =  $ProyecId['id_proyecto'];
                    }
                $rolId = "SELECT id FROM rol where nombre = '".$rol[$i]."'"; 
                $rolId=BDConexion::getInstancia()->query($rolId);
                $rolId = $rolId->fetch_all(MYSQLI_ASSOC); 
                foreach ($rolId as $idRol) {
                    $id_rol = $idRol['id'] ;
                    }
                $sql = "INSERT INTO usuario_proyecto VALUES($idUsuario,$id_proyecto,$id_rol)";
                $consultaGestion = BDConexion::getInstancia()->query($sql);
            } else {
                $consultaGestion = true;
            }
        }

} 

BDConexion::getInstancia()->commit();
BDConexion::getInstancia()->autocommit(true);
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Actualizar Usuario</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h3>Actualizar Usuario</h3>
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
                    <a href="usuarios.php">
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
