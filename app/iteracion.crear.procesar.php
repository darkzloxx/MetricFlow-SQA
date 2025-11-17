<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
include_once '../modelo/BDConexion.Class.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$Datos = $_POST;

// ==============================
// 📥 Datos del formulario
// ==============================
$idProyecto   = (int)($Datos["id_proyecto"] ?? 0);
$numero       = (int)($Datos["numero"] ?? 0);
$fechaInicio  = $Datos["fecha_inicio"] ?? "";
$fechaFin     = $Datos["fecha_fin"] ?? "";
$objetivo     = trim($Datos["objetivo"] ?? "");
$idFase       = (int)($Datos["fase"] ?? 0);

$_SESSION['form_data'] = $Datos;

$hoy = date('Y-m-d');
$ok = false;
$mensaje = "Ha ocurrido un error.";

// ==============================
// 🚨 Validaciones
// ==============================
if (!$idProyecto || !$idFase || $fechaInicio === "" || $fechaFin === "") {
    $mensaje = "Debe completar todos los campos obligatorios.";
    goto fin;
}

if ($fechaInicio < $hoy) {
    $mensaje = "La fecha de inicio no puede ser anterior a hoy.";
    goto fin;
}

if ($fechaFin <= $fechaInicio) {
    $mensaje = "La fecha de fin debe ser posterior a la fecha de inicio.";
    goto fin;
}

// Solapamiento
$sqlSolap = "
    SELECT COUNT(*) AS c
    FROM iteracion
    WHERE id_proyecto = ?
      AND (DATE(?) <= fecha_fin AND DATE(?) >= fecha_inicio)
";
$stmtSolap = $cn->prepare($sqlSolap);
$stmtSolap->bind_param("iss", $idProyecto, $fechaInicio, $fechaFin);
$stmtSolap->execute();
$rSolap = $stmtSolap->get_result()->fetch_assoc();
$stmtSolap->close();

if ($rSolap["c"] > 0) {
    $mensaje = "Las fechas ingresadas se superponen con otra iteración existente.";
    goto fin;
}

// Número duplicado
$sqlCheck = "
    SELECT COUNT(*) AS c
    FROM iteracion
    WHERE id_proyecto = ? AND id_fase = ? AND numero_iteracion = ?
";
$stmtChk = $cn->prepare($sqlCheck);
$stmtChk->bind_param("iii", $idProyecto, $idFase, $numero);
$stmtChk->execute();
$rChk = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if ($rChk["c"] > 0) {
    $mensaje = "Ya existe una iteración con ese número en la fase seleccionada.";
    goto fin;
}

// Número automático si viene 0
if ($numero === 0) {
    $sqlNext = "
        SELECT COALESCE(MAX(numero_iteracion), 0) + 1 AS siguiente
        FROM iteracion
        WHERE id_proyecto = ?
    ";
    $stmtNext = $cn->prepare($sqlNext);
    $stmtNext->bind_param("i", $idProyecto);
    $stmtNext->execute();
    $numero = (int)$stmtNext->get_result()->fetch_assoc()['siguiente'];
    $stmtNext->close();
}

// ==============================
// 🔹 Insertar
// ==============================
$sqlInsert = "
    INSERT INTO iteracion (id_proyecto, numero_iteracion, fecha_inicio, fecha_fin, objetivo, id_fase)
    VALUES (?, ?, ?, ?, ?, ?)
";
$stmtIns = $cn->prepare($sqlInsert);
$stmtIns->bind_param("iisssi", $idProyecto, $numero, $fechaInicio, $fechaFin, $objetivo, $idFase);

if ($stmtIns->execute()) {
    $cn->commit();
    $ok = true;
    $mensaje = "Iteración creada correctamente.";
    unset($_SESSION['form_data']);
} else {
    $cn->rollback();
    $mensaje = "Error al crear la iteración.";
}

$stmtIns->close();
$cn->autocommit(true);

fin:
$type = $ok ? 'success' : 'danger';
$cn->close();

header('Location: iteraciones.php?msg=' . urlencode($mensaje) . '&type=' . $type);
exit;
