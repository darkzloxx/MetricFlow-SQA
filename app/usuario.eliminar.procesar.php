<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;

$bd = BDConexion::getInstancia();
$bd->autocommit(false);
$bd->begin_transaction();

$idUsuario = (int)$DatosFormulario["id"];

// Eliminar relaciones del usuario en otras tablas
$tablasDependientes = ['usuario_rol', 'usuario_proyecto'];
foreach ($tablasDependientes as $tabla) {
    $query = "DELETE FROM $tabla WHERE id_usuario = {$idUsuario}";
    $ok = $bd->query($query);
    if (!$ok) {
        $bd->rollback();
        die("Error eliminando de $tabla: " . $bd->error);
    }
}

// Eliminar el usuario principal
$query = "DELETE FROM usuario WHERE id_usuario = {$idUsuario}";
$consulta = $bd->query($query);
if (!$consulta) {
    $bd->rollback();
    die("Error eliminando usuario: " . $bd->error);
}

$bd->commit();
$bd->autocommit(true);
?>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Eliminar Usuario</title>
<style>
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background-color: #fff;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #212529;
        }
    </style>
    </head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a id="btnVolver" href="usuarios.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
    <p></p>
    <div class="card">
        <div class="card-header">
            <h3>Eliminar Usuario</h3>
        </div>
        <div class="card-body">
            <?php if ($consulta) { ?>
                <div class="alert alert-success" role="alert">
                    Operaci&oacute;n realizada con &eacute;xito.
                </div>
            <?php } else { ?>
                <div class="alert alert-danger" role="alert">
                    Ha ocurrido un error.
                </div>
            <?php } ?>
            
        </div>
    </div>
</div>
<?php include_once '../gui/footer.php'; ?>
</body>
</html>
