<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$esAdmin = ControlAcceso::esAdminGlobal();
$esSuper = ControlAcceso::esSuperAdminGlobal();
$__isAjax = (!empty($_POST['ajax'])) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if (!($esAdmin || $esSuper)) {
    if ($__isAjax) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores.']);
    } else {
        header('Location: modelos.php?msg=' . urlencode('Acceso restringido a administradores.') . '&type=danger');
    }
    exit;
}

$cn = BDConexion::getInstancia();

try {
    $idModelo = (int)($_POST['id_modelo'] ?? ($_GET['id_modelo'] ?? 0));
    if ($idModelo <= 0) throw new Exception('Modelo inválido.');

    // No permitir borrar si está en uso
    $enUso = false;
    if ($rsU = $cn->query('SELECT COUNT(*) c FROM proyecto WHERE id_modelo = ' . $idModelo)) {
        $row = $rsU->fetch_assoc();
        $enUso = ((int)$row['c'] > 0);
    }
    if ($enUso) throw new Exception('No se puede eliminar: el modelo está siendo utilizado por proyectos.');

    $cn->begin_transaction();

    // Obtener métricas relacionadas ANTES de borrar relaciones
    $metricasRelacionadas = [];
    if ($rsM = $cn->query('SELECT id_metrica FROM metrica_modelo_calidad WHERE id_modelo = ' . (int)$idModelo)) {
        while ($r = $rsM->fetch_assoc()) { $metricasRelacionadas[] = (int)$r['id_metrica']; }
    }

    // Borrar relaciones y el modelo
    if (!$cn->query('DELETE FROM metrica_modelo_calidad WHERE id_modelo = ' . (int)$idModelo)) {
        throw new Exception('Error eliminando relaciones de métricas: ' . $cn->error);
    }
    if (!$cn->query('DELETE FROM modelo_calidad WHERE id_modelo = ' . (int)$idModelo . ' LIMIT 1')) {
        throw new Exception('Error eliminando el modelo: ' . $cn->error);
    }

    // Eliminar métricas huérfanas (sin relación con otros modelos ni uso en iteraciones)
    if (!empty($metricasRelacionadas)) {
        $stmtCountRel = $cn->prepare('SELECT COUNT(*) FROM metrica_modelo_calidad WHERE id_metrica = ?');
        $stmtCountIter = $cn->prepare('SELECT COUNT(*) FROM metrica_iteracion WHERE id_metrica = ?');
        $stmtDelMet = $cn->prepare('DELETE FROM metrica WHERE id_metrica = ?');
        if (!$stmtCountRel || !$stmtCountIter || !$stmtDelMet) throw new Exception('Error preparando statements auxiliares: ' . $cn->error);
        foreach ($metricasRelacionadas as $mid) {
            $mid = (int)$mid; if ($mid <= 0) continue;
            $stmtCountRel->bind_param('i', $mid);
            if (!$stmtCountRel->execute()) throw new Exception('Error verificando vínculos de métrica: ' . $stmtCountRel->error);
            $stmtCountRel->bind_result($cntRel); $stmtCountRel->fetch(); $stmtCountRel->free_result();
            if ((int)$cntRel > 0) continue; // aún vinculada a otro modelo

            $stmtCountIter->bind_param('i', $mid);
            if (!$stmtCountIter->execute()) throw new Exception('Error verificando uso en iteraciones: ' . $stmtCountIter->error);
            $stmtCountIter->bind_result($cntIter); $stmtCountIter->fetch(); $stmtCountIter->free_result();
            if ((int)$cntIter > 0) continue; // usada en iteraciones -> conservar

            $stmtDelMet->bind_param('i', $mid);
            if (!$stmtDelMet->execute()) throw new Exception('Error eliminando métrica huérfana: ' . $stmtDelMet->error);
        }
        $stmtCountRel->close();
        $stmtCountIter->close();
        $stmtDelMet->close();
    }

    $cn->commit();

    if ($__isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Modelo eliminado correctamente.']);
        exit;
    } else {
        header('Location: modelos.php?msg=' . urlencode('Modelo eliminado correctamente.') . '&type=success');
        exit;
    }
} catch (Exception $ex) {
    if ($cn && $cn->errno === 0) {
        $cn->rollback();
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
