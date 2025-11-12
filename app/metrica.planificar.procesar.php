<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

// 🚫 Los administradores no pueden planificar
if ($esAdmin) {
  header('Location: metricas.php?msg=' . urlencode('Los administradores no planifican métricas.') . '&type=warning');
  exit;
}

// Validar permisos
if (!ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
  header('Location: metricas.php?msg=' . urlencode('Acceso denegado a la planificación de métricas.') . '&type=danger');
  exit;
}

// Validar POST
$idMetrica = isset($_POST['id_metrica']) ? (int)$_POST['id_metrica'] : 0;
$idIteracion = isset($_POST['id_iteracion']) ? (int)$_POST['id_iteracion'] : 0;
$valorPlanificado = isset($_POST['valor_planificado']) ? trim($_POST['valor_planificado']) : '';
$umbral = isset($_POST['umbral']) ? trim($_POST['umbral']) : '';

if ($idMetrica <= 0 || $idIteracion <= 0 || $valorPlanificado === '' || $umbral === '') {
  header('Location: metricas.php?msg=' . urlencode('Datos incompletos para planificar la métrica.') . '&type=danger');
  exit;
}

$cn = BDConexion::getInstancia();

// ===========================================================
// 🔹 Verificar que la iteración sea la actual (según la fecha)
// ===========================================================
$sqlCheck = "
    SELECT i.id_iteracion, f.nombre AS fase, i.numero_iteracion
    FROM iteracion i
    JOIN fase f ON f.id_fase = i.id_fase
    WHERE i.id_iteracion = {$idIteracion}
      AND CURRENT_DATE() BETWEEN i.fecha_inicio AND i.fecha_fin
    LIMIT 1";
$rsCheck = $cn->query($sqlCheck);

if (!$rsCheck || $rsCheck->num_rows === 0) {
  header('Location: metricas.php?msg=' . urlencode('La iteración seleccionada no está activa actualmente. Solo se puede planificar en la iteración actual.') . '&type=warning');
  exit;
}
$iter = $rsCheck->fetch_assoc();

// ===========================================================
// 🔹 Comprobar si ya hay una planificación existente
// ===========================================================
$sqlExist = "
    SELECT 1
    FROM metrica_iteracion 
    WHERE id_metrica = {$idMetrica} AND id_iteracion = {$idIteracion}
    LIMIT 1";
$rsExist = $cn->query($sqlExist);

if ($rsExist && $rsExist->num_rows > 0) {
  // 🔄 Ya existe → actualizar valores
  $stmt = $cn->prepare("
        UPDATE metrica_iteracion
        SET valor_planificado = ?, umbral_desviacion = ?
        WHERE id_metrica = ? AND id_iteracion = ?");
  $stmt->bind_param('ddii', $valorPlanificado, $umbral, $idMetrica, $idIteracion);
  $ok = $stmt->execute();
  $stmt->close();

  if ($ok) {
    header('Location: metricas.php?msg=' . urlencode("Planificación actualizada correctamente para {$iter['fase']} {$iter['numero_iteracion']}.") . '&type=success');
    exit;
  } else {
    header('Location: metricas.php?msg=' . urlencode('Error al actualizar la planificación.') . '&type=danger');
    exit;
  }
} else {
  // 🆕 No existe → insertar nuevo registro
  $stmt = $cn->prepare("
        INSERT INTO metrica_iteracion (id_metrica, id_iteracion, valor_planificado, umbral_desviacion)
        VALUES (?, ?, ?, ?)");
  $stmt->bind_param('iidd', $idMetrica, $idIteracion, $valorPlanificado, $umbral);
  $ok = $stmt->execute();
  $stmt->close();

  if ($ok) {
    header('Location: metricas.php?msg=' . urlencode("Métrica planificada correctamente en {$iter['fase']} {$iter['numero_iteracion']}.") . '&type=success');
    exit;
  } else {
    header('Location: metricas.php?msg=' . urlencode('Error al registrar la planificación.') . '&type=danger');
    exit;
  }
}
