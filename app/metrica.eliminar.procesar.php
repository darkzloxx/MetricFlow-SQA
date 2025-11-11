<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdmin = ControlAcceso::esAdminGlobal();
$tienePermGestion = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);

$idMetrica = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$idModelo  = isset($_POST['modelo']) ? (int)$_POST['modelo'] : 0;

if ($idMetrica <= 0) {
    header('Location: metricas.php?msg=' . urlencode('Métrica inválida.') . '&type=danger');
    exit;
}

// ==========================
// 🔍 Obtener tipo de métrica
// ==========================
$rsTipo = $cn->query("SELECT tipo FROM metrica WHERE id_metrica = {$idMetrica} LIMIT 1");
if (!$rsTipo || $rsTipo->num_rows === 0) {
    header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
    exit;
}
$tipoMetrica = strtolower($rsTipo->fetch_assoc()['tipo']);

// ==================================================
// 🧩 CASO 1 → ADMIN / SUPERADMIN (solo métricas base)
// ==================================================
if ($esAdmin || $esSuperAdmin) {

    // Solo pueden eliminar métricas base
    if ($tipoMetrica !== 'base') {
        header('Location: metricas.php?msg=' . urlencode('No puede eliminar métricas personalizadas desde la gestión global.') . '&type=danger');
        exit;
    }

    // 🔒 Verificar si la métrica está en uso directo o indirecto (por modelos asignados a proyectos)
    $sqlUso = "
    SELECT 1 FROM (
        -- En algún modelo personalizado
        SELECT id_metrica FROM metrica_proyecto_modelo WHERE id_metrica = {$idMetrica}

        UNION

        -- En alguna iteración (planificada o ejecutada)
        SELECT id_metrica FROM metrica_iteracion WHERE id_metrica = {$idMetrica}

        UNION

        -- En un modelo base actualmente asignado a algún proyecto
        SELECT mmc.id_metrica
        FROM metrica_modelo_calidad mmc
        INNER JOIN proyecto p ON p.id_modelo_global = mmc.id_modelo
        WHERE mmc.id_metrica = {$idMetrica}

        UNION

        -- En un modelo base usado como base de un modelo personalizado
        SELECT mmc.id_metrica
        FROM metrica_modelo_calidad mmc
        INNER JOIN proyecto_modelo_calidad pmc ON pmc.id_modelo_base = mmc.id_modelo
        WHERE mmc.id_metrica = {$idMetrica}
    ) AS usos
    LIMIT 1
";

    $resUso = $cn->query($sqlUso);
    if ($resUso && $resUso->num_rows > 0) {
        header('Location: metricas.php?msg=' . urlencode('⚠️ No se puede eliminar: la métrica base está asociada a uno o más modelos que están en uso en proyecto/s.') . '&type=danger');
        exit;
    }

    // ✅ Eliminar definitivamente (no en uso)
    $cn->query("DELETE FROM metrica_modelo_calidad WHERE id_metrica = {$idMetrica}");
    $cn->query("DELETE FROM metrica WHERE id_metrica = {$idMetrica}");

    header('Location: metricas.php?msg=' . urlencode('✅ Métrica base eliminada correctamente del sistema global.') . '&type=success');
    exit;
}

// ===========================================================
// 👷‍♂️ CASO 2 → LÍDER / GERENTE (permiso de gestión de métricas)
// ===========================================================
if ($tienePermGestion) {

    // Verificar que el modelo pertenece al usuario actual
    $sqlCheckModelo = "
        SELECT 1
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
        WHERE pmc.id_proyecto_modelo = {$idModelo}
          AND up.id_usuario = {$usr->id}
        LIMIT 1
    ";
    $resCheck = $cn->query($sqlCheckModelo);
    if (!$resCheck || $resCheck->num_rows === 0) {
        header('Location: metricas.php?msg=' . urlencode('No tiene permiso sobre el modelo indicado.') . '&type=danger');
        exit;
    }

    // ✅ Caso A: Métrica personalizada → eliminar completamente
    if ($tipoMetrica === 'personalizada') {
        $cn->query("DELETE FROM metrica_proyecto_modelo WHERE id_metrica = {$idMetrica}");
        $cn->query("DELETE FROM metrica WHERE id_metrica = {$idMetrica}");
        header('Location: metricas.php?msg=' . urlencode('✅ Métrica personalizada eliminada correctamente.') . '&type=success');
        exit;
    }

    // ✅ Caso B: Métrica base → solo desvincular
    if ($tipoMetrica === 'base') {
        $cn->query("DELETE FROM metrica_proyecto_modelo WHERE id_metrica = {$idMetrica} AND id_proyecto_modelo = {$idModelo}");
        if ($cn->affected_rows > 0) {
            header('Location: metricas.php?msg=' . urlencode('✅ Métrica base desvinculada correctamente del modelo personalizado.') . '&type=success');
        } else {
            header('Location: metricas.php?msg=' . urlencode('⚠️ No se pudo desvincular la métrica base (posiblemente ya no estaba asociada).') . '&type=danger');
        }
        exit;
    }
}

// ==================================================
// 🚫 CASO 3 → USUARIO COMÚN (sin acceso permitido)
// ==================================================
header('Location: ../app/menu.php?msg=' . urlencode('Acceso denegado a la gestión de métricas.') . '&type=danger');
exit;
