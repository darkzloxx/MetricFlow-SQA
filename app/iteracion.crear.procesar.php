<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
include_once '../modelo/BDConexion.Class.php';

$DatosFormulario = $_POST;
$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$idProyecto = (int)$DatosFormulario["id_proyecto"];
$numero = (int)$DatosFormulario["nombre"];
$fecha_inicio = $cn->real_escape_string($DatosFormulario["fecha_inicio"]);
$fecha_fin = $cn->real_escape_string($DatosFormulario["fecha_fin"]);
$objetivo = $cn->real_escape_string(trim($DatosFormulario["objetivo"]));
$fase = (int)$DatosFormulario["fase"];

$resultado = false;
$mensaje = "Ha ocurrido un error.";

// Verificar si ya existe el mismo número en esa fase/proyecto
$sqlCheck = "
    SELECT 1 FROM iteracion 
    WHERE numero_iteracion = {$numero} 
      AND id_proyecto = {$idProyecto} 
      AND id_fase = {$fase}
    LIMIT 1";
$res = $cn->query($sqlCheck);

if ($res && $res->num_rows > 0) {
    $mensaje = "Ya existe una iteración con ese número en la fase seleccionada.";
} else {
    $sqlInsert = "
        INSERT INTO iteracion (id_proyecto, numero_iteracion, fecha_inicio, fecha_fin, objetivo, id_fase)
        VALUES ({$idProyecto}, {$numero}, '{$fecha_inicio}', '{$fecha_fin}', '{$objetivo}', {$fase})";

    if ($cn->query($sqlInsert)) {
        $cn->commit();
        $resultado = true;
        $mensaje = "Iteración creada exitosamente.";
    } else {
        $cn->rollback();
        $mensaje = "Error al crear la iteración: " . $cn->error;
    }
}
$cn->autocommit(true);
?>
<html>

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Iteración</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h3>Crear Iteración</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-<?= $resultado ? 'success' : 'danger'; ?>">
                    <?= htmlspecialchars($mensaje); ?>
                </div>
                <a href="iteraciones.php" class="btn btn-primary">
                    <span class="oi oi-arrow-left"></span> Volver
                </a>
            </div>
        </div>
    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>