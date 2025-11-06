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

    // Borrar relaciones y el modelo
    if (!$cn->query('DELETE FROM metrica_modelo_calidad WHERE id_modelo = ' . $idModelo)) {
        throw new Exception('Error eliminando relaciones de métricas: ' . $cn->error);
    }
    if (!$cn->query('DELETE FROM modelo_calidad WHERE id_modelo = ' . $idModelo . ' LIMIT 1')) {
        throw new Exception('Error eliminando el modelo: ' . $cn->error);
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
