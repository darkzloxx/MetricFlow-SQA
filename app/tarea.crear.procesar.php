<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;
BDConexion::getInstancia()->autocommit(false);
BDConexion::getInstancia()->begin_transaction();

$nombre = $DatosFormulario["nombre"];


$resultado = "";
$mensaje = "Ha ocurrido un error.";

// Validación de campos requeridos
if (empty($DatosFormulario["nombre"])) {
    $resultado = false;
    $mensaje = "El nombre de la tarea es obligatorio.";
} elseif (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/', $DatosFormulario["nombre"])) {
    $resultado = false;
    $mensaje = "El nombre solo puede contener letras, números, espacios, puntos, guiones, barras, paréntesis y dos puntos.";
} elseif (empty($DatosFormulario["descripcion"])) {
    $resultado = false;
    $mensaje = "La descripción de la tarea es obligatoria.";
} elseif (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 .,()_\-\/\\:]+$/', $DatosFormulario["descripcion"])) {
    $resultado = false;
    $mensaje = "La descripción solo puede contener letras, números, espacios, puntos, guiones, barras, paréntesis y dos puntos.";
} elseif (!isset($DatosFormulario["permiso"]) || !is_array($DatosFormulario["permiso"]) || count($DatosFormulario["permiso"]) == 0) {
    $resultado = false;
    $mensaje = "Debe seleccionar al menos una métrica asociada.";
} elseif (empty($DatosFormulario["iteracion"])) {
    $resultado = false;
    $mensaje = "Debe seleccionar una iteración.";
} else {
    // Validar que el nombre no exista
    $query = "SELECT * FROM tarea WHERE nombre = '" . BDConexion::getInstancia()->real_escape_string($nombre) . "'";
    $consulta = BDConexion::getInstancia()->query($query);
    if ($consulta->num_rows > 0) {
        $resultado = false;
        $mensaje = "Ya existe una tarea con el nombre ingresado.";
    } else {
        // Validar que la iteración exista
        $idIteracion = intval($DatosFormulario["iteracion"]);
        $query = "SELECT * FROM iteracion WHERE id_iteracion = $idIteracion";
        $consultaIter = BDConexion::getInstancia()->query($query);
        if ($consultaIter->num_rows == 0) {
            $resultado = false;
            $mensaje = "La iteración seleccionada no existe.";
        } else {
            // Crear la tarea
            $query = "INSERT INTO tarea (nombre, descripcion) VALUES ('" . BDConexion::getInstancia()->real_escape_string($DatosFormulario["nombre"]) . "', '" . BDConexion::getInstancia()->real_escape_string($DatosFormulario["descripcion"]) . "')";
            $consulta = BDConexion::getInstancia()->query($query);
            if (!$consulta) {
                BDConexion::getInstancia()->rollback();
                die(BDConexion::getInstancia()->errno);
            }
            $idTarea = BDConexion::getInstancia()->insert_id;
            // Insertar métricas asociadas
            foreach ($DatosFormulario["permiso"] as $idPermiso) {
                $idPermiso = intval($idPermiso);
                $query = "INSERT INTO metrica_tarea (id_metrica, id_tarea) VALUES ($idPermiso, $idTarea)";
                $consulta = BDConexion::getInstancia()->query($query);
                if (!$consulta) {
                    BDConexion::getInstancia()->rollback();
                    die(BDConexion::getInstancia()->errno);
                }
            }
            // Insertar iteración asociada
            $query = "INSERT INTO iteracion_tarea (id_iteracion, id_tarea) VALUES ($idIteracion, $idTarea)";
            $consulta = BDConexion::getInstancia()->query($query);
            if (!$consulta) {
                BDConexion::getInstancia()->rollback();
                die(BDConexion::getInstancia()->errno);
            }
            BDConexion::getInstancia()->commit();
            BDConexion::getInstancia()->autocommit(true);
            $resultado = true;
            $mensaje = "Operación Realizada con Éxito";
        }
    }
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Tarea</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Crear Tarea</h3>
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