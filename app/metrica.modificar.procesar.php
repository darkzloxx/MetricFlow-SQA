<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
$usr = ControlAcceso::usuarioActual();

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

/* ===========================================================
   🛡 VALIDACIONES DEL LADO SERVIDOR
   =========================================================== */
if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s.-]+$/u', $nombre)) {
    header('Location: metrica.modificar.php?id=' . $id .
        '&msg=' . urlencode('El nombre solo puede contener letras, espacios, puntos y guiones.') .
        '&type=danger');
    exit;
}

if ($descripcion !== '' && !preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s.-]+$/u', $descripcion)) {
    header('Location: metrica.modificar.php?id=' . $id .
        '&msg=' . urlencode('La descripción solo puede contener letras, espacios, puntos y guiones.') .
        '&type=danger');
    exit;
}

$cn->autocommit(false);
$cn->begin_transaction();

/* ===========================================================
   🔍 Detectar tipo de métrica
   =========================================================== */
$hasTipo = false;
$tipo = null;

try {
    if ($rs = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) {
        $hasTipo = $rs->num_rows > 0;
    }
} catch (Throwable $e) {
    $hasTipo = false;
}

if ($hasTipo) {
    $rsT = $cn->query("SELECT tipo FROM metrica WHERE id_metrica = {$id} LIMIT 1");
    if (!$rsT || !$rsT->num_rows) {
        $cn->rollback(); $cn->autocommit(true);
        header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
        exit;
    }
    $tipo = strtolower(trim($rsT->fetch_assoc()['tipo']));
}

/* 🚫 Si es base → solo Admin puede editar */
if ($tipo === 'base' && !$esAdmin) {
    $cn->rollback(); $cn->autocommit(true);
    header('Location: metricas.php?msg=' . urlencode('No está autorizado a editar métricas base.') . '&type=danger');
    exit;
}

/* ===========================================================
   🔍 Obtener el proyecto actual de la métrica personalizada
   =========================================================== */
$idProyecto = 0;

if ($tipo === 'personalizada') {
    $sqlProy = "
        SELECT p.id_proyecto
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN metrica_proyecto_modelo mpm ON mpm.id_proyecto_modelo = pmc.id_proyecto_modelo
        WHERE mpm.id_metrica = {$id}
        LIMIT 1
    ";
    $rsProy = $cn->query($sqlProy);
    if ($rsProy && $rsProy->num_rows > 0) {
        $idProyecto = (int)$rsProy->fetch_assoc()['id_proyecto'];
    }
}

/* ===========================================================
   🔒 BLOQUEO SI LA MÉTRICA PERSONALIZADA ESTÁ EN OTRO PROYECTO
   =========================================================== */
if ($tipo === 'personalizada') {

    // ✔ usada en OTRO proyecto (no el actual)
    $qUsoOtro = "
        SELECT 1
        FROM metrica_proyecto_modelo mpm
        JOIN proyecto_modelo_calidad pmc 
            ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
        WHERE mpm.id_metrica = {$id}
          AND pmc.id_proyecto <> {$idProyecto}
        LIMIT 1
    ";
    $rs = $cn->query($qUsoOtro);
    if ($rs && $rs->num_rows > 0) {
        $cn->rollback(); $cn->autocommit(true);
        header('Location: metricas.php?msg=' . urlencode('No se puede editar: esta métrica personalizada está siendo usada por OTRO proyecto.') . '&type=danger');
        exit;
    }

    // ✔ bloqueada si ya está planificada en este proyecto
    $qPlan = "
        SELECT 1
        FROM metrica_iteracion 
        WHERE id_metrica = {$id}
          AND id_iteracion IN (
                SELECT id_iteracion
                FROM iteracion
                WHERE id_proyecto = {$idProyecto}
          )
        LIMIT 1
    ";
    $rs = $cn->query($qPlan);
    if ($rs && $rs->num_rows > 0) {
        $cn->rollback(); $cn->autocommit(true);
        header('Location: metricas.php?msg=' . urlencode('No se puede editar: esta métrica ya está planificada o ejecutada en este proyecto.') . '&type=danger');
        exit;
    }
}

/* ===========================================================
   Nombre único
   =========================================================== */
$nomEsc = $cn->real_escape_string($nombre);
$rsDup = $cn->query("SELECT 1 FROM metrica WHERE nombre = '{$nomEsc}' AND id_metrica <> {$id} LIMIT 1");
if ($rsDup && $rsDup->num_rows > 0) {
    $cn->rollback(); $cn->autocommit(true);
    header('Location: metrica.modificar.php?id=' . $id . '&msg=' . urlencode('Ya existe otra métrica con ese nombre.') . '&type=danger');
    exit;
}

/* ===========================================================
   VALIDAR MODELOS SELECCIONADOS
   =========================================================== */
if ($esAdmin) {
    $sel = isset($_POST['modelos_globales']) ? $_POST['modelos_globales'] : [];
    if (empty($sel)) {
        $cn->rollback(); $cn->autocommit(true);
        header('Location: metrica.modificar.php?id=' . $id .
            '&msg=' . urlencode('Debe seleccionar al menos un modelo global.') .
            '&type=danger');
        exit;
    }
} else {
    $sel = isset($_POST['modelos_proyecto']) ? array_map('intval', $_POST['modelos_proyecto']) : [];
    if (empty($sel)) {
        $cn->rollback(); $cn->autocommit(true);
        header('Location: metrica.modificar.php?id=' . $id .
            '&msg=' . urlencode('Debe seleccionar al menos un modelo del proyecto.') .
            '&type=danger');
        exit;
    }
}

/* ===========================================================
   UPDATE MÉTRICA
   =========================================================== */
$desEsc = $cn->real_escape_string($descripcion);
$qUpd = "UPDATE metrica SET nombre = '{$nomEsc}', descripcion = '{$desEsc}' WHERE id_metrica = {$id}";
if (!$cn->query($qUpd)) {
    $cn->rollback(); $cn->autocommit(true);
    die($cn->errno);
}

/* ===========================================================
   ACTUALIZAR ASOCIACIONES
   =========================================================== */
if ($esAdmin) {
    // Globales
    $cn->query("DELETE FROM metrica_modelo_calidad WHERE id_metrica = {$id}");
    foreach ($sel as $mid) {
        $mid = (int)$mid;
        if ($mid > 0) {
            $cn->query("INSERT INTO metrica_modelo_calidad (id_metrica, id_modelo) VALUES ($id, $mid)");
        }
    }

} else {
    // Personalizadas
    $valid = [];

    if (!empty($sel)) {
        $in = implode(',', $sel);
        $sqlCheck = "
            SELECT pmc.id_proyecto_modelo
            FROM proyecto_modelo_calidad pmc
            JOIN usuario_proyecto up ON up.id_proyecto = pmc.id_proyecto
            WHERE up.id_usuario = {$usr->id}
              AND pmc.id_proyecto_modelo IN ($in)
        ";
        if ($rs = $cn->query($sqlCheck)) {
            while ($r = $rs->fetch_assoc()) {
                $valid[] = (int)$r['id_proyecto_modelo'];
            }
        }
    }

    $cn->query("DELETE FROM metrica_proyecto_modelo WHERE id_metrica = {$id}");
    foreach ($valid as $pmid) {
        $cn->query("INSERT INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo) VALUES ($id, $pmid)");
    }
}

/* ===========================================================
   FIN
   =========================================================== */
$cn->commit();
$cn->autocommit(true);

header('Location: metricas.php?msg=' . urlencode('Métrica actualizada correctamente.') . '&type=success');
exit;
