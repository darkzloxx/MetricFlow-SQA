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

$ok = false;
$mensaje = "";

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

// Validaciones
if ($fechaInicio < $hoy) {
    $mensaje = "La fecha de inicio no puede ser anterior a hoy.";
    goto fin;
}

if ($fechaFin <= $fechaInicio) {
    $mensaje = "La fecha de fin debe ser posterior a la fecha de inicio.";
    goto fin;
}

// Verificar solapamientos
$sqlSolap = "
    SELECT COUNT(*) AS c
    FROM iteracion
    WHERE id_proyecto = ?
      AND id_iteracion != ?
      AND (
            (DATE(?) BETWEEN fecha_inicio AND fecha_fin)
         OR (DATE(?) BETWEEN fecha_inicio AND fecha_fin)
         OR (fecha_inicio BETWEEN DATE(?) AND DATE(?))
         OR (fecha_fin BETWEEN DATE(?) AND DATE(?))
      )
";
$stmt = $cn->prepare($sqlSolap);
$stmt->bind_param(
    "iissssss",
    $idProyecto,
    $id,
    $fechaInicio,
    $fechaFin,
    $fechaInicio,
    $fechaFin,
    $fechaInicio,
    $fechaFin
);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($r['c'] > 0) {
    $mensaje = "Las fechas modificadas invaden otra iteración.";
    goto fin;
}

// Actualizar
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
    $mensaje = "Error al actualizar la iteración.";
}

$stmt->close();
$cn->autocommit(true);

fin:

$type = $ok ? 'success' : 'danger';
$cn->close();

header('Location: iteraciones.php?msg=' . urlencode($mensaje) . '&type=' . $type);
exit;
