<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;

$resultado = "";
$mensaje = "Ha ocurrido un error.";

// Validación de campos requeridos
if (empty($DatosFormulario["id"])) {
    $resultado = false;
    $mensaje = "ID de tarea no proporcionado.";
} elseif (empty($DatosFormulario["nombre"])) {
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
    // Validar que la tarea exista
    $idTarea = intval($DatosFormulario["id"]);
    $query = "SELECT * FROM tarea WHERE id_tarea = $idTarea";
    $consultaTarea = BDConexion::getInstancia()->query($query);
    if ($consultaTarea->num_rows == 0) {
        $resultado = false;
        $mensaje = "La tarea no existe.";
    } else {
        // Validar que la iteración exista
        $idIteracion = intval($DatosFormulario["iteracion"]);
        $query = "SELECT * FROM iteracion WHERE id_iteracion = $idIteracion";
        $consultaIter = BDConexion::getInstancia()->query($query);
        if ($consultaIter->num_rows == 0) {
            $resultado = false;
            $mensaje = "La iteración seleccionada no existe.";
        } else {
            // Actualizar tarea
            $query = "UPDATE tarea SET nombre = '" . BDConexion::getInstancia()->real_escape_string($DatosFormulario["nombre"]) . "', descripcion = '" . BDConexion::getInstancia()->real_escape_string($DatosFormulario["descripcion"]) . "' WHERE id_tarea = $idTarea";
            $consulta = BDConexion::getInstancia()->query($query);
            // Actualizar métricas asociadas
            $query = "DELETE FROM metrica_tarea WHERE id_tarea = $idTarea";
            $consulta = BDConexion::getInstancia()->query($query);
            foreach ($DatosFormulario["permiso"] as $idPermiso) {
                $idPermiso = intval($idPermiso);
                $query = "INSERT INTO metrica_tarea (id_metrica, id_tarea) VALUES ($idPermiso, $idTarea)";
                $consulta = BDConexion::getInstancia()->query($query);
                if (!$consulta) {
                    BDConexion::getInstancia()->rollback();
                    die(BDConexion::getInstancia()->errno);
                }
            }
            // Actualizar iteración asociada
            $query = "DELETE FROM iteracion_tarea WHERE id_tarea = $idTarea";
            $consulta = BDConexion::getInstancia()->query($query);
            $query = "INSERT INTO iteracion_tarea (id_iteracion, id_tarea) VALUES ($idIteracion, $idTarea)";
            $consulta = BDConexion::getInstancia()->query($query);
            if (!$consulta) {
                BDConexion::getInstancia()->rollback();
                die(BDConexion::getInstancia()->errno);
            }
            $resultado = true;
            $mensaje = "Operación realizada con éxito.";
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
