<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
include_once '../modelo/BDConexion.Class.php';

$DatosFormulario = $_POST;
$cn = BDConexion::getInstancia();

// Sanitización
$id = (int)$DatosFormulario["id"];
$numero = (int)$DatosFormulario["nombre"]; // o usar otro campo si corresponde
$fase = (int)$DatosFormulario["fase"];
$objetivo = $cn->real_escape_string(trim($DatosFormulario["objetivo"]));
$fecha_inicio = $cn->real_escape_string(trim($DatosFormulario["fecha_inicio"]));
$fecha_fin = $cn->real_escape_string(trim($DatosFormulario["fecha_fin"]));

// Preparar consulta
$sql = "UPDATE iteracion
        SET numero_iteracion = ?, 
            objetivo = ?, 
            fecha_inicio = ?, 
            fecha_fin = ?, 
            id_fase = ?
        WHERE id_iteracion = ?";
$stmt = $cn->prepare($sql);
$stmt->bind_param('isssii', $numero, $objetivo, $fecha_inicio, $fecha_fin, $fase, $id);

$ok = $stmt->execute();
$stmt->close();
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Actualizar Iteración</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <div class="card mt-3">
                <div class="card-header">
                    <h3>Actualizar Iteración</h3>
                </div>
                <div class="card-body">
                    <?php if ($ok): ?>
                        <div class="alert alert-success" role="alert">
                            <span class="oi oi-check mr-1"></span> Operación realizada con éxito.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger" role="alert">
                            <span class="oi oi-warning mr-1"></span> Ha ocurrido un error al actualizar la iteración.
                        </div>
                    <?php endif; ?>
                    <hr />
                    <h5 class="card-text">Opciones</h5>
                    <a href="iteracion.php" class="btn btn-primary">
                        <span class="oi oi-account-logout"></span> Salir
                    </a>
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
