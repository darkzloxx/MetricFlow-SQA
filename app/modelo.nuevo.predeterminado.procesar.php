<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Solo Admin o SuperAdmin pueden crear modelos predeterminados (globales)
if (!ControlAcceso::esAdminGlobal()) {
    header('Location: modelos.php?msg=' . urlencode('Solo administradores pueden crear modelos predeterminados.') . '&type=danger');
    exit;
}

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$nombre = 'Modelo Predeterminado ' . date('Ymd-His');
$descripcion = 'Predeterminado global creado por ' . (ControlAcceso::usuarioActual()->nombre ?? 'admin') . ' en ' . date('Y-m-d H:i:s');

$q = "INSERT INTO modelo_calidad (nombre, descripcion) VALUES ('" . $cn->real_escape_string($nombre) . "', '" . $cn->real_escape_string($descripcion) . "')";
if (!$cn->query($q)) {
    $cn->rollback();
    $cn->autocommit(true);
    die($cn->errno);
}

$cn->commit();
$cn->autocommit(true);

header('Location: modelos.php?msg=' . urlencode('Modelo predeterminado creado y disponible para todos los proyectos.') . '&type=success');
exit;
