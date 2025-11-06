<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Acceso: Admin/SuperAdmin o permiso de gestión de modelo
if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido para crear modelos.') . '&type=danger');
    exit;
}

$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$metricas = isset($_POST['metricas']) && is_array($_POST['metricas']) ? array_map('intval', $_POST['metricas']) : [];

if ($nombre === '') {
    header('Location: modelo.nuevo.php?msg=' . urlencode('El nombre del modelo es obligatorio.') . '&type=danger');
    exit;
}

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$ok = $cn->query("INSERT INTO modelo_calidad (nombre, descripcion) VALUES ('" . $cn->real_escape_string($nombre) . "', '" . $cn->real_escape_string($descripcion) . "')");
if (!$ok) {
    $cn->rollback();
    $cn->autocommit(true);
    die($cn->errno);
}

$idModelo = (int)$cn->insert_id;

// Insertar vínculos con métricas seleccionadas
if (!empty($metricas)) {
    foreach ($metricas as $idMet) {
        $idMet = (int)$idMet;
        if ($idMet <= 0) continue;
        $q = "INSERT INTO metrica_modelo_calidad (id_modelo, id_metrica) VALUES ({$idModelo}, {$idMet})";
        if (!$cn->query($q)) {
            $cn->rollback();
            $cn->autocommit(true);
            die($cn->errno);
        }
    }
}

$cn->commit();
$cn->autocommit(true);

header('Location: modelos.php?msg=' . urlencode('Modelo creado correctamente.') . '&type=success');
exit;
