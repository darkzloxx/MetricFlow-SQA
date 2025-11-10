<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$usr = ControlAcceso::usuarioActual();
$cn = BDConexion::getInstancia();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';

if ($id <= 0 || $nombre === '') {
    header('Location: metricas.php?msg=' . urlencode('Datos inválidos.') . '&type=danger');
    exit;
}

// Verificar que sea personalizada y accesible
$sql = "SELECT tipo FROM metrica WHERE id_metrica = {$id} LIMIT 1";
$rs = $cn->query($sql);
if (!$rs || !$rs->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
    exit;
}
$row = $rs->fetch_assoc();
if (strtolower($row['tipo'] ?? '') !== 'personalizada') {
    header('Location: metricas.php?msg=' . urlencode('Solo se pueden modificar métricas personalizadas.') . '&type=danger');
    exit;
}

// Validar acceso del usuario
$sqlAcceso = "
    SELECT 1
    FROM metrica_proyecto_modelo mpm
    JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
    JOIN usuario_proyecto up ON up.id_proyecto = pmc.id_proyecto
    WHERE up.id_usuario = {$usr->id} AND mpm.id_metrica = {$id}
    LIMIT 1";
$rsAcceso = $cn->query($sqlAcceso);
if (!$rsAcceso || !$rsAcceso->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('No tiene permisos para modificar esta métrica.') . '&type=danger');
    exit;
}

// Actualizar datos
$nomEsc = $cn->real_escape_string($nombre);
$descEsc = $cn->real_escape_string($descripcion);

$qUpd = "UPDATE metrica SET nombre='{$nomEsc}', descripcion='{$descEsc}' WHERE id_metrica={$id}";
if (!$cn->query($qUpd)) {
    header('Location: metricas.php?msg=' . urlencode('Error al actualizar la métrica personalizada.') . '&type=danger');
    exit;
}

header('Location: metricas.php?msg=' . urlencode('Métrica personalizada actualizada correctamente.') . '&type=success');
exit;
