<?php
include_once '../lib/ControlAcceso.Class.php';
// Permiso de gestión de métricas en lugar de ABM_USUARIOS
ControlAcceso::requierePermiso(PermisosSistema::GESTION_METRICAS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;
BDConexion::getInstancia()->autocommit(false);
BDConexion::getInstancia()->begin_transaction();

$nombre = $DatosFormulario["nombre"];

$resultado = "";
$mensaje = "Ha ocurrido un error.";

$nombreEsc = BDConexion::getInstancia()->real_escape_string($nombre);
$query = "SELECT * FROM metrica WHERE nombre = '{$nombreEsc}'";
$consulta = BDConexion::getInstancia()->query($query);

if ($consulta->num_rows > 0){
	$resultado = false;
	$mensaje = "Ya existe una metrica con el nombre ingresado";
} else {

$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
// Si es admin se considera 'base', caso contrario 'personalizada'
$tipo = $esAdmin ? 'base' : 'personalizada';
$descEsc = BDConexion::getInstancia()->real_escape_string($DatosFormulario["descripcion"]);
$tipoEsc = BDConexion::getInstancia()->real_escape_string($tipo);
$query = "INSERT INTO metrica (nombre, descripcion, tipo) VALUES ('{$nombreEsc}', '{$descEsc}', '{$tipoEsc}')";
$consulta = BDConexion::getInstancia()->query($query);
if (!$consulta) {
    BDConexion::getInstancia()->rollback();
    //arrojar una excepcion
    die(BDConexion::getInstancia()->errno);
}

$idMetrica = BDConexion::getInstancia()->insert_id;

if (isset($DatosFormulario['permiso']) && is_array($DatosFormulario['permiso'])) {
    foreach ($DatosFormulario["permiso"] as $idPermiso) {
        $idPermiso = (int)$idPermiso;
        if ($idPermiso <= 0) { continue; }
        $query = "INSERT INTO metrica_modelo_calidad VALUES ({$idMetrica}, {$idPermiso})";
        $consulta = BDConexion::getInstancia()->query($query);
        if (!$consulta) {
            BDConexion::getInstancia()->rollback();
            die(BDConexion::getInstancia()->errno);
        }
    }
}

BDConexion::getInstancia()->commit();
BDConexion::getInstancia()->autocommit(true);
$resultado = true;
$mensaje = "Operacion Realizada con Exito";
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Metrica</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Crear Metrica</h3>
                </div>
                <div class="card-body">
                    <?php if ($resultado) { ?>
                        <div class="alert alert-success" role="alert">
                            <?= $mensaje; ?>
                        </div>
                    <?php } ?>   
                    <?php if (!$resultado) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $mensaje; ?>
                        </div>
                    <?php } ?>
                    <hr />
                    <h5 class="card-text">Opciones</h5>
                    <a href="metricas.php">
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