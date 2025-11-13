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
$idProyecto = isset($_POST['proyecto']) ? (int)$_POST['proyecto'] : 0;
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

    // 🔒 Verificar si la métrica está en uso por cualquier proyecto
    $sqlUso = "
    SELECT 1 FROM (
        -- En algún modelo personalizado
        SELECT id_metrica FROM metrica_proyecto_modelo WHERE id_metrica = {$idMetrica}

        UNION
        -- En alguna iteración
        SELECT id_metrica FROM metrica_iteracion WHERE id_metrica = {$idMetrica}

        UNION
        -- En un modelo global en uso
        SELECT mmc.id_metrica
        FROM metrica_modelo_calidad mmc
        INNER JOIN proyecto p ON p.id_modelo_global = mmc.id_modelo
        WHERE mmc.id_metrica = {$idMetrica}

        UNION
        -- En un modelo base usado por modelos personalizados
        SELECT mmc.id_metrica
        FROM metrica_modelo_calidad mmc
        INNER JOIN proyecto_modelo_calidad pmc ON pmc.id_modelo_base = mmc.id_modelo
        WHERE mmc.id_metrica = {$idMetrica}
    ) AS usos
    LIMIT 1";

    $resUso = $cn->query($sqlUso);
    if ($resUso && $resUso->num_rows > 0) {
        header('Location: metricas.php?msg=' . urlencode('⚠️ No se puede eliminar: la métrica base está en uso.') . '&type=danger');
        exit;
    }

    // Eliminar la métrica base
    $cn->query("DELETE FROM metrica_modelo_calidad WHERE id_metrica = {$idMetrica}");
    $cn->query("DELETE FROM metrica WHERE id_metrica = {$idMetrica}");

    header('Location: metricas.php?msg=' . urlencode('✅ Métrica base eliminada correctamente.') . '&type=success');
    exit;
}



// ===========================================================
// 👷‍♂️ CASO 2 → GERENTE / LÍDER (métricas personalizadas)
// ===========================================================
if ($tienePermGestion) {

    // ============================================
    // 1️⃣ Verificar que el modelo pertenece al usuario
    // ============================================
    $sqlCheckModelo = "
        SELECT 1
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
        WHERE pmc.id_proyecto_modelo = {$idModelo}
          AND pmc.id_proyecto = {$idProyecto}
          AND up.id_usuario = {$usr->id}
        LIMIT 1
    ";
    $resCheck = $cn->query($sqlCheckModelo);
    if (!$resCheck || $resCheck->num_rows === 0) {
        header('Location: metricas.php?msg=' . urlencode('❌ No tiene permiso para modificar la métrica en este proyecto.') . '&type=danger');
        exit;
    }

    // ============================================
    // 2️⃣ Caso → MÉTRICA PERSONALIZADA
    // ============================================
    if ($tipoMetrica === 'personalizada') {

        // ❌ Bloquear si está planificada
        $qPlan = "
            SELECT 1
            FROM metrica_iteracion
            WHERE id_metrica = {$idMetrica}
              AND id_iteracion IN (
                    SELECT id_iteracion 
                    FROM iteracion 
                    WHERE id_proyecto = {$idProyecto}
              )
            LIMIT 1";

        if (($res = $cn->query($qPlan)) && $res->num_rows > 0) {
            header('Location: metricas.php?msg=' . urlencode('❌ No se puede eliminar: la métrica ya está planificada o ejecutada.') . '&type=danger');
            exit;
        }

        // ❌ Bloquear si está en uso por OTRO proyecto
        $qUsoOtro = "
            SELECT 1
            FROM metrica_proyecto_modelo mpm
            JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
            WHERE mpm.id_metrica = {$idMetrica}
              AND pmc.id_proyecto <> {$idProyecto}
            LIMIT 1";

        if (($res = $cn->query($qUsoOtro)) && $res->num_rows > 0) {
            header('Location: metricas.php?msg=' . urlencode('❌ No se puede eliminar: la métrica está en uso por otro proyecto.') . '&type=danger');
            exit;
        }

        // ✔ Eliminar definitivamente la métrica personalizada
        $cn->query("DELETE FROM metrica_proyecto_modelo WHERE id_metrica = {$idMetrica}");
        $cn->query("DELETE FROM metrica WHERE id_metrica = {$idMetrica}");

        header('Location: metricas.php?msg=' . urlencode('✅ Métrica personalizada eliminada correctamente.') . '&type=success');
        exit;
    }


    // ============================================
    // 3️⃣ Caso → MÉTRICA BASE → desvincular solo del modelo personalizado
    // ============================================
    if ($tipoMetrica === 'base') {
        $cn->query("
            DELETE FROM metrica_proyecto_modelo 
            WHERE id_metrica = {$idMetrica} 
              AND id_proyecto_modelo = {$idModelo}
        ");

        if ($cn->affected_rows > 0) {
            header('Location: metricas.php?msg=' . urlencode('✔ Métrica base desvinculada del modelo.') . '&type=success');
        } else {
            header('Location: metricas.php?msg=' . urlencode('⚠ Nada para desvincular.') . '&type=danger');
        }
        exit;
    }
}


// ==================================================
// 🚫 CASO 3 → USUARIO SIN PERMISOS
// ==================================================
header('Location: ../app/menu.php?msg=' . urlencode('Acceso denegado a la gestión de métricas.') . '&type=danger');
exit;

