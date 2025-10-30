<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;
BDConexion::getInstancia()->autocommit(false);
BDConexion::getInstancia()->begin_transaction();

$proyecto = $DatosFormulario["proyecto"];
$modelo = $DatosFormulario["modelo"];

$resultado = "";
$mensaje = "Ha ocurrido un error.";

$query = "select * from proyecto where id_modelo is not null and id_proyecto = '{$proyecto}'";
$consulta = BDConexion::getInstancia()->query($query);

if ($consulta->num_rows > 0){
	$resultado = false;
	$mensaje = "Ya existe un modelo cargado para el proyecto";
} else {
		$query = "UPDATE proyecto SET id_modelo = {$modelo} where id_proyecto =  {$proyecto}";
		$consulta = BDConexion::getInstancia()->query($query);
		if (!$consulta) {
			BDConexion::getInstancia()->rollback();
			//arrojar una excepcion
			die(BDConexion::getInstancia()->errno);
		}
		
		$idUsuario = BDConexion::getInstancia()->insert_id;
		
		
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
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Cargar Modelo</title>
    </head>
    <body>
        <?php include_once '../gui/navbarAlumnos.php'; ?>

        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Cargar Modelo</h3>
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
                    <a href="pantalla.alumnos.metricas.php">
                            <button type="button" class="btn btn-outline-success">
                                <span class="oi oi-check"></span> Gestionar Metricas
                            </button>
                        </a>
                    <a href="pantalla.alumnos.modelo.php">
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