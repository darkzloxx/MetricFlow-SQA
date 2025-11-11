<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();

$__isAjax = (!empty($_POST['ajax'])) ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

try {
    $idModelo = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
    if ($idModelo <= 0) {
        throw new Exception('Modelo inválido.');
    }

    // =====================================================
    // 🔐 Validación de permisos (solo Gerente o Líder del proyecto)
    // =====================================================
    $idProyectoRow = $cn->query("
        SELECT id_proyecto 
        FROM proyecto_modelo_calidad 
        WHERE id_proyecto_modelo = {$idModelo}
        LIMIT 1
    ")->fetch_assoc();

    $idProyecto = (int)($idProyectoRow['id_proyecto'] ?? 0);
    if ($idProyecto <= 0) {
        throw new Exception('No se encontró el proyecto asociado al modelo.');
    }

    // 🚫 Los Admin y SuperAdmin NO pueden eliminar modelos personalizados
    if (ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal()) {
        throw new Exception('Los administradores globales no pueden eliminar modelos personalizados de proyectos.');
    }

    // ⚙️ Rol del usuario en este proyecto
    $rolProyecto = strtolower(trim(ControlAcceso::rolUsuarioEnProyecto($idProyecto) ?? ''));

    // ⚙️ Solo Gerente o Líder pueden eliminar
    $esGerenteOLider = in_array($rolProyecto, [
        'gerente',
        'gerente de calidad',
        'líder',
        'líder de proyecto',
        'lider de proyecto' // sin tilde, compatibilidad
    ], true);

    if (!$esGerenteOLider) {
        throw new Exception('Solo un Gerente o Líder del proyecto puede eliminar este modelo personalizado.');
    }

    // =====================================================
    // 🔍 Verificar si tiene métricas planificadas (en metrica_iteracion)
    // =====================================================
    // =====================================================
    // 🔍 Verificar si el modelo (personalizado) tiene métricas planificadas o ejecutadas
    // =====================================================
    $sqlUso = "
    SELECT 1
    FROM metrica_iteracion mi
    WHERE mi.id_metrica IN (
        -- Métricas personalizadas del modelo personalizado
        SELECT mpm.id_metrica
        FROM metrica_proyecto_modelo mpm
        WHERE mpm.id_proyecto_modelo = {$idModelo}

        UNION

        -- Métricas base heredadas del modelo global del proyecto
        SELECT mmc.id_metrica
        FROM metrica_modelo_calidad mmc
        JOIN proyecto p ON p.id_modelo_global = mmc.id_modelo
        JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto = p.id_proyecto
        WHERE pmc.id_proyecto_modelo = {$idModelo}
    )
    LIMIT 1
";

    $resUso = $cn->query($sqlUso);
    if ($resUso && $resUso->num_rows > 0) {
        throw new Exception('No se puede eliminar el modelo: contiene métricas base o personalizadas planificadas o en ejecución.');
    }

 

    // =====================================================
    // 🔹 Iniciar transacción
    // =====================================================
    $cn->begin_transaction();

    // Obtener métricas relacionadas antes de borrar vínculos
    $metricasRelacionadas = [];
    $rsM = $cn->query("SELECT id_metrica FROM metrica_proyecto_modelo WHERE id_proyecto_modelo = {$idModelo}");
    if ($rsM) {
        while ($r = $rsM->fetch_assoc()) {
            $metricasRelacionadas[] = (int)$r['id_metrica'];
        }
    }

    // Borrar vínculos del modelo personalizado
    if (!$cn->query("DELETE FROM metrica_proyecto_modelo WHERE id_proyecto_modelo = {$idModelo}")) {
        throw new Exception('Error eliminando relaciones de métricas: ' . $cn->error);
    }

    // Borrar el modelo personalizado
    if (!$cn->query("DELETE FROM proyecto_modelo_calidad WHERE id_proyecto_modelo = {$idModelo} LIMIT 1")) {
        throw new Exception('Error eliminando el modelo personalizado: ' . $cn->error);
    }

    // =====================================================
    // 🧹 Eliminar solo métricas PERSONALIZADAS huérfanas
    // =====================================================
    if (!empty($metricasRelacionadas)) {
        $stmtCountRel = $cn->prepare('SELECT COUNT(*) FROM metrica_proyecto_modelo WHERE id_metrica = ?');
        $stmtCountIter = $cn->prepare('SELECT COUNT(*) FROM metrica_iteracion WHERE id_metrica = ?');
        $stmtDelMet = $cn->prepare('DELETE FROM metrica WHERE id_metrica = ?');

        if (!$stmtCountRel || !$stmtCountIter || !$stmtDelMet) {
            throw new Exception('Error preparando statements auxiliares: ' . $cn->error);
        }

        foreach ($metricasRelacionadas as $mid) {
            $mid = (int)$mid;
            if ($mid <= 0) continue;

            // Verificar tipo
            $tipoRes = $cn->query("SELECT tipo FROM metrica WHERE id_metrica = {$mid} LIMIT 1");
            $tipoRow = $tipoRes ? $tipoRes->fetch_assoc() : null;
            $esPersonalizada = isset($tipoRow['tipo']) && $tipoRow['tipo'] === 'personalizada';

            // Solo eliminar métricas personalizadas
            if (!$esPersonalizada) {
                continue;
            }

            // Verificar que no esté usada en otros modelos o iteraciones
            $stmtCountRel->bind_param('i', $mid);
            $stmtCountRel->execute();
            $stmtCountRel->bind_result($cntRel);
            $stmtCountRel->fetch();
            $stmtCountRel->free_result();
            if ((int)$cntRel > 0) continue;

            $stmtCountIter->bind_param('i', $mid);
            $stmtCountIter->execute();
            $stmtCountIter->bind_result($cntIter);
            $stmtCountIter->fetch();
            $stmtCountIter->free_result();
            if ((int)$cntIter > 0) continue;

            // Eliminar definitivamente
            $stmtDelMet->bind_param('i', $mid);
            $stmtDelMet->execute();
        }

        $stmtCountRel->close();
        $stmtCountIter->close();
        $stmtDelMet->close();
    }

    $cn->commit();

    // =====================================================
    // ✅ Respuesta final
    // =====================================================
    if ($__isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Modelo personalizado eliminado correctamente.']);
        exit;
    } else {
        header('Location: modelos.php?msg=' . urlencode('Modelo personalizado eliminado correctamente.') . '&type=success');
        exit;
    }
} catch (Exception $ex) {
    if ($cn && method_exists($cn, 'rollback')) {
        @$cn->rollback();
    }


    if ($__isAjax) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
        exit;
    } else {
        header('Location: modelos.php?msg=' . urlencode($ex->getMessage()) . '&type=danger');
        exit;
    }
}
