<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$esAdmin = ControlAcceso::esAdminGlobal();
$esSuper = ControlAcceso::esSuperAdminGlobal();
$__isAjax = !empty($_POST['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
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
    $idModelo = (int)($_POST['id_modelo'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $metricas = $_POST['metricas'] ?? [];
    $newNames = $_POST['new_metric_name'] ?? [];
    $newDescs = $_POST['new_metric_desc'] ?? [];

    if ($idModelo <= 0) throw new Exception('Modelo inválido.');
    if ($nombre === '') throw new Exception('El nombre del modelo es obligatorio.');
    if ($descripcion === '') throw new Exception('La descripción del modelo es obligatoria.');

    // Validaciones de formato: letras (con acentos y Ñ), espacios, guiones y puntos
    $nameRegex = '/^[A-Za-zÁÉÍÓÚáéíóúÑñ .-]+$/u';
    if (!preg_match($nameRegex, $nombre)) {
        throw new Exception('El nombre del modelo solo puede contener letras, espacios, guiones y puntos.');
    }
    if (!preg_match($nameRegex, $descripcion)) {
        throw new Exception('La descripción del modelo solo puede contener letras, espacios, guiones y puntos.');
    }

    // No permitir modificar si el modelo está en uso por proyectos
    $enUso = false;
    if ($rsU = $cn->query("SELECT COUNT(*) c FROM proyecto WHERE id_modelo = $idModelo")) {
        $rowU = $rsU->fetch_assoc();
        $enUso = ((int)$rowU['c'] > 0);
    }
    if ($enUso) throw new Exception('El modelo está siendo utilizado por proyectos y no puede modificarse.');

    // Obtener relaciones previas para detectar desvinculadas
    $prevIds = [];
    if ($rsPrev = $cn->query('SELECT id_metrica FROM metrica_modelo_calidad WHERE id_modelo = ' . (int)$idModelo)) {
        while ($r = $rsPrev->fetch_assoc()) { $prevIds[] = (int)$r['id_metrica']; }
    }

    $cn->begin_transaction();

    // Actualizar datos del modelo
    $stmt = $cn->prepare('UPDATE modelo_calidad SET nombre = ?, descripcion = ? WHERE id_modelo = ?');
    if (!$stmt) throw new Exception('Error preparando UPDATE: ' . $cn->error);
    $stmt->bind_param('ssi', $nombre, $descripcion, $idModelo);
    if (!$stmt->execute()) throw new Exception('Error al actualizar modelo: ' . $stmt->error);
    $stmt->close();

    // Crear nuevas métricas y agregarlas a la lista de seleccionadas
    $metricIds = array_map('intval', $metricas);
    // Validar nuevas métricas (nombres válidos)
    $validNew = [];
    if (is_array($newNames) && count($newNames)) {
        $stmtIns = $cn->prepare('INSERT INTO metrica (nombre, descripcion) VALUES (?, ?)');
        if (!$stmtIns) throw new Exception('Error preparando INSERT metrica: ' . $cn->error);
        foreach ($newNames as $idx => $nm) {
            $nm = trim((string)$nm);
            $ds = trim((string)($newDescs[$idx] ?? ''));
            if ($nm === '') continue;
            if (!preg_match($nameRegex, $nm)) {
                throw new Exception('Nombre de nueva métrica inválido: "' . $nm . '". Use solo letras, espacios, guiones y puntos.');
            }
            $stmtIns->bind_param('ss', $nm, $ds);
            if (!$stmtIns->execute()) throw new Exception('Error insertando métrica: ' . $stmtIns->error);
            $metricIds[] = (int)$cn->insert_id;
            $validNew[] = $nm;
        }
        $stmtIns->close();
    }

    // Debe quedar al menos una métrica asociada (existente o nueva)
    if (count($metricIds) === 0) {
        throw new Exception('Debe seleccionar al menos una métrica para el modelo.');
    }

    // Calcular métricas que se desvinculan en esta edición
    $toRemove = array_values(array_diff($prevIds, $metricIds));

    // Reemplazar relaciones de métricas del modelo
    if (!$cn->query('DELETE FROM metrica_modelo_calidad WHERE id_modelo = ' . (int)$idModelo)) {
        throw new Exception('Error limpiando métricas del modelo: ' . $cn->error);
    }
    if (!empty($metricIds)) {
        $stmtRel = $cn->prepare('INSERT INTO metrica_modelo_calidad (id_modelo, id_metrica) VALUES (?, ?)');
        if (!$stmtRel) throw new Exception('Error preparando INSERT relación: ' . $cn->error);
        foreach ($metricIds as $mid) {
            $mid = (int)$mid; if ($mid <= 0) continue;
            $stmtRel->bind_param('ii', $idModelo, $mid);
            if (!$stmtRel->execute()) throw new Exception('Error insertando relación: ' . $stmtRel->error);
        }
        $stmtRel->close();
    }

    // Eliminar de la tabla metrica aquellas que quedaron sin vínculo a ningún modelo
    if (!empty($toRemove)) {
        $stmtCountRel = $cn->prepare('SELECT COUNT(*) FROM metrica_modelo_calidad WHERE id_metrica = ?');
        $stmtCountIter = $cn->prepare('SELECT COUNT(*) FROM metrica_iteracion WHERE id_metrica = ?');
        $stmtDelMet = $cn->prepare('DELETE FROM metrica WHERE id_metrica = ?');
        if (!$stmtCountRel || !$stmtCountIter || !$stmtDelMet) throw new Exception('Error preparando statements auxiliares: ' . $cn->error);
        foreach ($toRemove as $mid) {
            $mid = (int)$mid; if ($mid <= 0) continue;
            // ¿sigue vinculada a algún modelo?
            $stmtCountRel->bind_param('i', $mid);
            if (!$stmtCountRel->execute()) throw new Exception('Error verificando vínculos de métrica: ' . $stmtCountRel->error);
            $stmtCountRel->bind_result($cntRel); $stmtCountRel->fetch(); $stmtCountRel->free_result();
            if ((int)$cntRel > 0) continue; // aún está en uso por otro modelo

            // ¿está usada en iteraciones planeadas? (por seguridad)
            $stmtCountIter->bind_param('i', $mid);
            if (!$stmtCountIter->execute()) throw new Exception('Error verificando uso en iteraciones: ' . $stmtCountIter->error);
            $stmtCountIter->bind_result($cntIter); $stmtCountIter->fetch(); $stmtCountIter->free_result();
            if ((int)$cntIter > 0) continue; // no borrar si tiene uso histórico/planeado

            // borrar definitivamente la métrica
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
        echo json_encode(['success' => true, 'message' => 'Modelo actualizado correctamente.']);
        exit;
    } else {
        header('Location: modelos.php?msg=' . urlencode('Modelo actualizado correctamente.') . '&type=success');
        exit;
    }
} catch (Exception $ex) {
    if ($cn && $cn->errno === 0) {
        // ensure rollback if a transaction was started
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
