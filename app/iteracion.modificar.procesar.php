<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
include_once '../modelo/BDConexion.Class.php';

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$id = (int)$_POST['id'];
$objetivo = trim($_POST['objetivo'] ?? '');
$fechaInicio = $_POST['fecha_inicio'] ?? '';
$fechaFin = $_POST['fecha_fin'] ?? '';
$hoy = date('Y-m-d');

// Obtener datos existentes
$sql = "SELECT * FROM iteracion WHERE id_iteracion = ?";
$stmt = $cn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$iter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$iter) {
    $mensaje = "Iteración no encontrada.";
    goto fin;
}

$idProyecto = (int)$iter['id_proyecto'];

// Validaciones básicas
if ($fechaInicio < $hoy) {
    $mensaje = "La fecha de inicio no puede ser anterior a hoy.";
    goto fin;
}

if ($fechaFin <= $fechaInicio) {
    $mensaje = "La fecha de fin debe ser posterior a la fecha de inicio.";
    goto fin;
}

// Validación de solapamientos
$sqlSolap = "
    SELECT COUNT(*) AS c
    FROM iteracion
    WHERE id_proyecto = ?
      AND id_iteracion != ?
      AND (
            DATE(?) = fecha_inicio OR
            DATE(?) = fecha_fin OR
            DATE(?) = fecha_inicio OR
            DATE(?) = fecha_fin OR
            (DATE(?) > fecha_inicio AND DATE(?) < fecha_fin) OR
            (DATE(?) < fecha_fin AND DATE(?) > fecha_inicio)
      )
";

$stmt = $cn->prepare($sqlSolap);
$stmt->bind_param(
    "iissssssss",
    $idProyecto,
    $id,
    $fechaInicio,
    $fechaInicio,
    $fechaFin,
    $fechaFin,
    $fechaInicio,
    $fechaInicio,
    $fechaFin,
    $fechaFin
);

$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($r['c'] > 0) {
    $mensaje = "Las fechas modificadas coinciden o invaden otra iteración.";
    goto fin;
}

// Actualizar iteración (SIN modificar número)
$sqlUpdate = "
    UPDATE iteracion
    SET objetivo = ?, fecha_inicio = ?, fecha_fin = ?
    WHERE id_iteracion = ?
";

$stmt = $cn->prepare($sqlUpdate);
$stmt->bind_param("sssi", $objetivo, $fechaInicio, $fechaFin, $id);

if ($stmt->execute()) {
    $cn->commit();
    $ok = true;
    $mensaje = "Iteración actualizada correctamente.";
} else {
    $cn->rollback();
    $ok = false;
    $mensaje = "Error al actualizar la iteración.";
}

$stmt->close();
$cn->autocommit(true);

fin:
?>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <title>Modificar Iteración</title>
</head>
<body>
<?php include_once '../gui/navbar.php'; ?>
<div class="container mt-4">
<div class="alert alert-<?= $ok ? 'success' : 'danger'; ?>">
    <?= $mensaje ?>
</div>

<a href="iteraciones.php" class="btn btn-primary">
    <span class="oi oi-arrow-left"></span> Volver
</a>

</div>
<?php include_once '../gui/footer.php'; ?>
</body>
</html>
