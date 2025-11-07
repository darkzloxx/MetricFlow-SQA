<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
if (!$esAdmin && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$cn = BDConexion::getInstancia();
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nombre = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
$descripcion = isset($_POST['descripcion']) ? trim((string)$_POST['descripcion']) : '';

if ($id <= 0 || $nombre === '') {
    header('Location: metricas.php?msg=' . urlencode('Datos inválidos del formulario.') . '&type=danger');
    exit;
}

$cn->autocommit(false);
$cn->begin_transaction();

// Detectar columna 'tipo'
$hasTipo = false; $tipo = null;
try { if ($rsC = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) { $hasTipo = (bool)$rsC->num_rows; } } catch (Throwable $e) { $hasTipo = false; }

// Cargar tipo si existe y validar permisos
if ($hasTipo) {
    $rsT = $cn->query('SELECT tipo FROM metrica WHERE id_metrica = ' . $id . ' LIMIT 1');
    if (!$rsT || !$rsT->num_rows) {
        $cn->rollback(); $cn->autocommit(true);
        header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
        exit;
    }
    $rowT = $rsT->fetch_assoc();
    $tipo = strtolower(trim((string)($rowT['tipo'] ?? '')));
}
if ((!$hasTipo || $tipo === 'base') && !$esAdmin) {
    $cn->rollback(); $cn->autocommit(true);
    header('Location: metricas.php?msg=' . urlencode('No está autorizado a editar métricas base.') . '&type=danger');
    exit;
}

// Validar nombre único (excepto esta misma métrica)
$nomEsc = $cn->real_escape_string($nombre);
$rsDup = $cn->query("SELECT 1 FROM metrica WHERE nombre = '{$nomEsc}' AND id_metrica <> {$id} LIMIT 1");
if ($rsDup && $rsDup->num_rows > 0) {
    $cn->rollback(); $cn->autocommit(true);
    header('Location: metrica.modificar.php?id=' . $id . '&msg=' . urlencode('Ya existe otra métrica con ese nombre.') . '&type=danger');
    exit;
}

// Actualizar datos de la métrica
$desEsc = $cn->real_escape_string($descripcion);
$qUpd = "UPDATE metrica SET nombre = '{$nomEsc}', descripcion = '{$desEsc}' WHERE id_metrica = {$id}";
if (!$cn->query($qUpd)) {
    $cn->rollback(); $cn->autocommit(true); die($cn->errno);
}

// Actualizar asociaciones
if ($esAdmin) {
    // Globales
    $cn->query('DELETE FROM metrica_modelo_calidad WHERE id_metrica = ' . $id);
    $sel = isset($_POST['modelos_globales']) && is_array($_POST['modelos_globales']) ? $_POST['modelos_globales'] : [];
    foreach ($sel as $mid) {
        $m = (int)$mid; if ($m<=0) continue;
        $qIns = 'INSERT INTO metrica_modelo_calidad (id_metrica, id_modelo) VALUES (' . $id . ', ' . $m . ')';
        if (!$cn->query($qIns)) { $cn->rollback(); $cn->autocommit(true); die($cn->errno); }
    }
} else {
    // Proyecto personalizados: validar pertenencia
    $usr = ControlAcceso::usuarioActual();
    $sel = isset($_POST['modelos_proyecto']) && is_array($_POST['modelos_proyecto']) ? array_map('intval', $_POST['modelos_proyecto']) : [];
    $sel = array_values(array_filter($sel, function($v){ return $v>0; }));
    // validar que los seleccionados pertenecen al usuario
    $valid = [];
    if (!empty($sel)) {
        $in = implode(',', $sel);
        $sqlCheck = "SELECT pmc.id_proyecto_modelo
                     FROM proyecto_modelo_calidad pmc
                     JOIN usuario_proyecto up ON up.id_proyecto = pmc.id_proyecto
                     WHERE up.id_usuario = ".(int)$usr->id." AND pmc.id_proyecto_modelo IN ($in)";
        if ($rs = $cn->query($sqlCheck)) {
            while ($r=$rs->fetch_assoc()) { $valid[] = (int)$r['id_proyecto_modelo']; }
        }
    }
    // sync: eliminar todo y reinsertar solo válidos
    $cn->query('DELETE FROM metrica_proyecto_modelo WHERE id_metrica = ' . $id);
    foreach ($valid as $pmid) {
        $qIns = 'INSERT INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo) VALUES (' . $id . ', ' . $pmid . ')';
        if (!$cn->query($qIns)) { $cn->rollback(); $cn->autocommit(true); die($cn->errno); }
    }
}

$cn->commit();
$cn->autocommit(true);
header('Location: metricas.php?msg=' . urlencode('Métrica actualizada correctamente.') . '&type=success');
exit;
