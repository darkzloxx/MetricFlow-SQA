<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// ============================
// 🔒 Control de acceso
// ============================
if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido para crear modelos.') . '&type=danger');
    exit;
}

// ============================
// 📥 Datos recibidos del formulario
// ============================
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$metricas = isset($_POST['metricas']) && is_array($_POST['metricas']) ? array_map('intval', $_POST['metricas']) : [];

$metricasNuevasNombres = isset($_POST['metricas_nuevas']['nombre']) && is_array($_POST['metricas_nuevas']['nombre']) ? $_POST['metricas_nuevas']['nombre'] : [];
$metricasNuevasDescs = isset($_POST['metricas_nuevas']['descripcion']) && is_array($_POST['metricas_nuevas']['descripcion']) ? $_POST['metricas_nuevas']['descripcion'] : [];

$modeloBaseId = isset($_POST['modelo_base_id']) ? (int)$_POST['modelo_base_id'] : 0;
$editarBase = isset($_POST['editar_base']) ? (int)$_POST['editar_base'] === 1 : false;

$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
$esGlobal = $esAdmin && isset($_POST['global']) && (int)$_POST['global'] === 1;
$proyectoId = isset($_POST['proyecto']) ? (int)$_POST['proyecto'] : 0;

$regexCampos = '/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/u/';
$cn = BDConexion::getInstancia();

// ============================
// 🧠 Validaciones de campos
// ============================
if ($modeloBaseId === 0) {
    if ($nombre === '' || !preg_match($regexCampos, $nombre)) {
        header('Location: modelo.nuevo.php?msg=' . urlencode('El nombre del modelo contiene caracteres no permitidos o está vacío.') . '&type=danger');
        exit;
    }

    if ($descripcion === '' || !preg_match($regexCampos, $descripcion)) {
        header('Location: modelo.nuevo.php?msg=' . urlencode('La descripción contiene caracteres no permitidos o está vacía.') . '&type=danger');
        exit;
    }
}

// Validar nuevas métricas
if (!empty($metricasNuevasNombres)) {
    foreach ($metricasNuevasNombres as $i => $nom) {
        $nom = trim((string)$nom);
        $des = isset($metricasNuevasDescs[$i]) ? trim((string)$metricasNuevasDescs[$i]) : '';
        if ($nom === '' || $des === '' || !preg_match($regexCampos, $nom) || !preg_match($regexCampos, $des)) {
            header('Location: modelo.nuevo.php?msg=' . urlencode('Cada nueva métrica debe tener nombre y descripción válidos.') . '&type=danger');
            exit;
        }
    }
}

// ============================
// 👥 Validaciones de permisos y proyecto
// ============================
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

// ============================
// 🚫 Validar proyecto bloqueado (ya planificado)
// ============================
if (!$esGlobal && $proyectoId > 0) {
    $sqlLock = "SELECT 1 
                FROM metrica_iteracion mi 
                JOIN iteracion i ON mi.id_iteracion = i.id_iteracion 
                JOIN proyecto p ON p.id_proyecto = i.id_proyecto
                WHERE i.id_proyecto = {$proyectoId} 
                  AND p.id_modelo IS NOT NULL
                LIMIT 1";
    $rsLock = $cn->query($sqlLock);
    if ($rsLock && $rsLock->num_rows > 0) {
        header('Location: modelos.php?msg=' . urlencode('No se puede crear o asignar un modelo: el proyecto ya tiene métricas planificadas.') . '&type=danger');
        exit;
    }
}

// ============================
// 🧩 CASO 1: usar modelo base sin editar
// ============================
if ($modeloBaseId > 0 && !$editarBase && $nombre === '' && $descripcion === '' && empty($metricasNuevasNombres)) {
    $cn->autocommit(false);
    $qP = "UPDATE proyecto SET id_modelo = {$modeloBaseId} WHERE id_proyecto = {$proyectoId}";
    if (!$cn->query($qP)) {
        $cn->rollback();
        $cn->autocommit(true);
        header('Location: modelos.php?msg=' . urlencode('Error al vincular el modelo base al proyecto.') . '&type=danger');
        exit;
    }
    $cn->commit();
    $cn->autocommit(true);
    header('Location: modelos.php?msg=' . urlencode('Modelo base vinculado correctamente al proyecto.') . '&type=success');
    exit;
}

// ============================
// ✏️ CASO 2 y 4: Crear modelo nuevo (derivado o desde cero)
// ============================
$cn->autocommit(false);
$cn->begin_transaction();

$ok = $cn->query("
    INSERT INTO modelo_calidad (nombre, descripcion)
    VALUES ('" . $cn->real_escape_string($nombre) . "', '" . $cn->real_escape_string($descripcion) . "')
");
if (!$ok) {
    $cn->rollback();
    $cn->autocommit(true);
    die($cn->error);
}
$idModelo = (int)$cn->insert_id;

// ============================
// 🔗 Asociar modelo al proyecto si corresponde
// ============================
if ($proyectoId > 0) {
    $qP = "UPDATE proyecto SET id_modelo = {$idModelo} WHERE id_proyecto = {$proyectoId}";
    if (!$cn->query($qP)) {
        $cn->rollback();
        $cn->autocommit(true);
        die($cn->error);
    }
}

// ============================
// 🧮 Insertar métricas existentes seleccionadas
// ============================
if (!empty($metricas)) {
    foreach ($metricas as $idMet) {
        $idMet = (int)$idMet;
        if ($idMet <= 0) continue;
        $q = "INSERT INTO metrica_modelo_calidad (id_modelo, id_metrica) VALUES ({$idModelo}, {$idMet})";
        if (!$cn->query($q)) {
            $cn->rollback();
            $cn->autocommit(true);
            die($cn->error);
        }
    }
}

// ============================
// ➕ Insertar nuevas métricas (personalizadas o base)
// ============================
$idProyectoModelo = null;
if (!$esAdmin && $proyectoId > 0) {
    $sqlInsPM = "INSERT INTO proyecto_modelo_calidad (id_proyecto, id_modelo_base, nombre, descripcion, es_personalizado)
                 VALUES ({$proyectoId}, {$idModelo}, '" . $cn->real_escape_string($nombre) . "', '" . $cn->real_escape_string($descripcion) . "', 1)";
    if (!$cn->query($sqlInsPM)) {
        $cn->rollback();
        $cn->autocommit(true);
        die($cn->error);
    }
    $idProyectoModelo = (int)$cn->insert_id;
}

if (!empty($metricasNuevasNombres)) {
    foreach ($metricasNuevasNombres as $i => $nom) {
        $nom = trim((string)$nom);
        $des = isset($metricasNuevasDescs[$i]) ? trim((string)$metricasNuevasDescs[$i]) : '';
        if ($nom === '' || $des === '') continue;

        $tipo = $esAdmin ? 'base' : 'personalizada';
        $qM = "INSERT INTO metrica (nombre, descripcion, tipo)
               VALUES ('" . $cn->real_escape_string($nom) . "', '" . $cn->real_escape_string($des) . "', '" . $cn->real_escape_string($tipo) . "')";
        if (!$cn->query($qM)) {
            $cn->rollback();
            $cn->autocommit(true);
            die($cn->error);
        }
        $newId = (int)$cn->insert_id;

        if ($esAdmin) {
            // Métrica base → global
            $qL = "INSERT INTO metrica_modelo_calidad (id_modelo, id_metrica) VALUES ({$idModelo}, {$newId})";
        } else {
            // Métrica personalizada → solo visible para el proyecto
            if (!$idProyectoModelo) {
                $cn->rollback();
                $cn->autocommit(true);
                die('No se pudo determinar el modelo del proyecto para asociar la métrica personalizada.');
            }
            $qL = "INSERT INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo) VALUES ({$newId}, {$idProyectoModelo})";
        }
        if (!$cn->query($qL)) {
            $cn->rollback();
            $cn->autocommit(true);
            die($cn->error);
        }
    }
}

// ============================
// ✅ Confirmar transacción
// ============================
$cn->commit();
$cn->autocommit(true);

header('Location: modelos.php?msg=' . urlencode('Modelo creado o derivado correctamente.') . '&type=success');
exit;
?>
