<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$idUsuario = (int)$usr->id;

$idMetrica = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idMetrica <= 0) {
    header('Location: metricas.php?msg=' . urlencode('Métrica inválida.') . '&type=danger');
    exit;
}

// ==============================
// 🔹 1. Validar existencia y tipo
// ==============================
$sql = "SELECT tipo, nombre FROM metrica WHERE id_metrica = {$idMetrica} LIMIT 1";
$rs = $cn->query($sql);
if (!$rs || !$rs->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
    exit;
}
$row = $rs->fetch_assoc();
$tipo = strtolower(trim($row['tipo']));
$nombreMetrica = htmlspecialchars($row['nombre']);

// ==============================
// 🔹 2. Reglas según tipo
// ==============================
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();

if ($tipo === 'base') {
    // Solo Admin/SuperAdmin pueden eliminar métricas base
    if (!($esAdminGlobal || $esSuperAdmin)) {
        header('Location: metricas.php?msg=' . urlencode('Solo los administradores pueden eliminar métricas base.') . '&type=danger');
        exit;
    }

    // Bloquear eliminación si la métrica base está usada en algún modelo
    $sqlUso = "
        SELECT COUNT(*) AS usados 
        FROM metrica_modelo_calidad 
        WHERE id_metrica = {$idMetrica}
    ";
    $rsUso = $cn->query($sqlUso);
    $enUso = ($rsUso && $rsUso->fetch_assoc()['usados'] > 0);
    if ($enUso) {
        header('Location: metricas.php?msg=' . urlencode("No se puede eliminar la métrica base '{$nombreMetrica}' porque está en uso en uno o más modelos de calidad.") . '&type=danger');
        exit;
    }

    // Eliminar métrica base
    $cn->query("DELETE FROM metrica WHERE id_metrica = {$idMetrica}");
    header('Location: metricas.php?msg=' . urlencode("Métrica base '{$nombreMetrica}' eliminada correctamente.") . '&type=success');
    exit;
}

// ==============================
// 🔹 3. Validar pertenencia del usuario para personalizadas
// ==============================
$sqlCheck = "
    SELECT COUNT(*) AS pertenece
    FROM metrica_proyecto_modelo mpm
    JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
    JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    WHERE mpm.id_metrica = {$idMetrica} AND up.id_usuario = {$idUsuario}
";
$rsCheck = $cn->query($sqlCheck);
$pertenece = ($rsCheck && $rsCheck->fetch_assoc()['pertenece'] > 0);
if (!$pertenece) {
    header('Location: metricas.php?msg=' . urlencode('No tiene permiso para eliminar esta métrica personalizada.') . '&type=danger');
    exit;
}

// ==============================
// 🔹 4. Bloquear si está planificada o ejecutada
// ==============================
$sqlUsoPlanif = "
    SELECT COUNT(*) AS total
    FROM metrica_iteracion
    WHERE id_metrica = {$idMetrica}
";
$rsPlanif = $cn->query($sqlUsoPlanif);
$estaPlanificada = ($rsPlanif && $rsPlanif->fetch_assoc()['total'] > 0);
if ($estaPlanificada) {
    header('Location: metricas.php?msg=' . urlencode("No se puede eliminar la métrica '{$nombreMetrica}' porque está planificada o tiene valores ejecutados.") . '&type=danger');
    exit;
}

// ==============================
// 🔹 5. Eliminar vínculos y la métrica
// ==============================
$cn->autocommit(false);
try {
    $cn->query("DELETE FROM metrica_proyecto_modelo WHERE id_metrica = {$idMetrica}");
    $cn->query("DELETE FROM metrica WHERE id_metrica = {$idMetrica}");
    $cn->commit();

    header('Location: metricas.php?msg=' . urlencode("Métrica personalizada '{$nombreMetrica}' eliminada correctamente.") . '&type=success');
    exit;
} catch (Throwable $e) {
    $cn->rollback();
    header('Location: metricas.php?msg=' . urlencode('Error al eliminar la métrica: ' . $e->getMessage()) . '&type=danger');
    exit;
}
?>
