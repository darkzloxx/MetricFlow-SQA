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
// Nuevas métricas desde el modal
$metricasNuevasNombres = isset($_POST['metricas_nuevas']['nombre']) && is_array($_POST['metricas_nuevas']['nombre']) ? $_POST['metricas_nuevas']['nombre'] : [];
$metricasNuevasDescs = isset($_POST['metricas_nuevas']['descripcion']) && is_array($_POST['metricas_nuevas']['descripcion']) ? $_POST['metricas_nuevas']['descripcion'] : [];

// Global/Admin vs Proyecto específico
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
$esGlobal = $esAdmin && isset($_POST['global']) && (int)$_POST['global'] === 1;
$proyectoId = isset($_POST['proyecto']) ? (int)$_POST['proyecto'] : 0;

if ($nombre === '') {
    header('Location: modelo.nuevo.php?msg=' . urlencode('El nombre del modelo es obligatorio.') . '&type=danger');
    exit;
}

// Si no es admin, debe asignar a un proyecto del cual forme parte
if (!$esAdmin) {
    if ($proyectoId <= 0) {
        header('Location: modelo.nuevo.php?msg=' . urlencode('Debe seleccionar un proyecto válido para asignar el modelo.') . '&type=danger');
        exit;
    }
    if (!ControlAcceso::usuarioPerteneceAProyecto($proyectoId)) {
        header('Location: modelos.php?msg=' . urlencode('No pertenece al proyecto seleccionado.') . '&type=danger');
        exit;
    }
}

// Si se pretende asignar a un proyecto (no global), y el proyecto ya tiene métricas planificadas, bloquear
if (!$esGlobal && $proyectoId > 0) {
    // Bloquear solo si el proyecto YA tiene un modelo asignado y hay métricas planificadas
    $sqlLock = "SELECT 1 
                FROM metrica_iteracion mi 
                JOIN iteracion i ON mi.id_iteracion = i.id_iteracion 
                JOIN proyecto p ON p.id_proyecto = i.id_proyecto
                WHERE i.id_proyecto = {$proyectoId} 
                  AND p.id_modelo IS NOT NULL
                LIMIT 1";
    $rsLock = BDConexion::getInstancia()->query($sqlLock);
    if ($rsLock && $rsLock->num_rows > 0) {
        header('Location: modelos.php?msg=' . urlencode('No se puede crear/asignar un modelo personalizado: el proyecto ya tiene un modelo con métricas planificadas.') . '&type=danger');
        exit;
    }
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

// Insertar nuevas métricas y vincular
if (!empty($metricasNuevasNombres)) {
    foreach ($metricasNuevasNombres as $i => $nom) {
        $nom = trim((string)$nom);
        $des = isset($metricasNuevasDescs[$i]) ? trim((string)$metricasNuevasDescs[$i]) : '';
        if ($nom === '') continue;
        $qM = "INSERT INTO metrica (nombre, descripcion) VALUES ('".$cn->real_escape_string($nom)."', '".$cn->real_escape_string($des)."')";
        if (!$cn->query($qM)) {
            $cn->rollback();
            $cn->autocommit(true);
            die($cn->errno);
        }
        $newId = (int)$cn->insert_id;
        $qL = "INSERT INTO metrica_modelo_calidad (id_modelo, id_metrica) VALUES ({$idModelo}, {$newId})";
        if (!$cn->query($qL)) {
            $cn->rollback();
            $cn->autocommit(true);
            die($cn->errno);
        }
    }
}

// Asignar al proyecto si corresponde
if (!$esGlobal) {
    // Si es admin y vino un proyecto, o si es usuario no-admin (validado arriba)
    if ($proyectoId > 0) {
        $qP = "UPDATE proyecto SET id_modelo = {$idModelo} WHERE id_proyecto = {$proyectoId}";
        if (!$cn->query($qP)) {
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
