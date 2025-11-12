<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$esAdmin = ControlAcceso::esAdminGlobal();
$esSuper = ControlAcceso::esSuperAdminGlobal();
$__isAjax = !empty($_POST['ajax']) || 
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!($esAdmin || $esSuper)) {
    if ($__isAjax) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores.']);
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Acceso restringido a administradores.'];
        header('Location: modelos.php');
    }
    exit;
}

$cn = BDConexion::getInstancia();

try {
    $regexGeneral = "/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _\.\-\/\\():]+$/u";

    $idModelo = (int)($_POST['id_modelo'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $metricas = $_POST['metricas'] ?? [];
    $metricasNuevasNombres = $_POST['new_metric_name'] ?? [];
    $metricasNuevasDescs = $_POST['new_metric_desc'] ?? [];

    if ($idModelo <= 0) {
        throw new Exception("ID de modelo inválido.");
    }

    if ($nombre === '' || $descripcion === '') {
        throw new Exception('Debe completar todos los campos obligatorios.');
    }

    if (!preg_match($regexGeneral, $nombre)) {
        throw new Exception('El nombre contiene caracteres no permitidos.');
    }
    if (!preg_match($regexGeneral, $descripcion)) {
        throw new Exception('La descripción contiene caracteres no permitidos.');
    }

    // === Validar unicidad del nombre (excluyendo el mismo modelo) ===
    $stmt = $cn->prepare("SELECT COUNT(*) AS c FROM modelo_calidad WHERE nombre = ? AND id_modelo <> ?");
    $stmt->bind_param("si", $nombre, $idModelo);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int)$row['c'] > 0) {
        throw new Exception("Ya existe un modelo con el nombre \"$nombre\".");
    }

    // === Validar unicidad de métricas nuevas ===
    foreach ($metricasNuevasNombres as $idx => $nomM) {
        $nomM = trim($nomM);
        if ($nomM === '') continue;
        if (!preg_match($regexGeneral, $nomM)) {
            throw new Exception("La métrica \"$nomM\" contiene caracteres no permitidos.");
        }

        $stmt = $cn->prepare("SELECT COUNT(*) AS c FROM metrica WHERE nombre = ?");
        $stmt->bind_param("s", $nomM);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ((int)$row['c'] > 0) {
            throw new Exception("Ya existe una métrica con el nombre \"$nomM\".");
        }
    }

    // === Iniciar transacción ===
    $cn->begin_transaction();

    // === Actualizar modelo ===
    $stmt = $cn->prepare("UPDATE modelo_calidad SET nombre = ?, descripcion = ? WHERE id_modelo = ?");
    $stmt->bind_param("ssi", $nombre, $descripcion, $idModelo);
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar el modelo: " . $stmt->error);
    }
    $stmt->close();

    // === Insertar nuevas métricas (si las hay) ===
    $metricIds = array_map('intval', $metricas);
    if (is_array($metricasNuevasNombres)) {
        $stmtM = $cn->prepare("INSERT INTO metrica (nombre, descripcion, tipo) VALUES (?, ?, 'base')");
        foreach ($metricasNuevasNombres as $idx => $nomM) {
            $nomM = trim($nomM);
            $descM = trim($metricasNuevasDescs[$idx] ?? '');
            if ($nomM === '') continue;
            $stmtM->bind_param("ss", $nomM, $descM);
            if (!$stmtM->execute()) {
                throw new Exception("Error insertando métrica '$nomM': " . $stmtM->error);
            }
            $metricIds[] = $cn->insert_id;
        }
        $stmtM->close();
    }

    if (empty($metricIds)) {
        throw new Exception("Debe seleccionar o crear al menos una métrica para el modelo.");
    }

    // === Actualizar relaciones ===
    $cn->query("DELETE FROM metrica_modelo_calidad WHERE id_modelo = $idModelo");
    $stmtRel = $cn->prepare("INSERT INTO metrica_modelo_calidad (id_modelo, id_metrica) VALUES (?, ?)");
    foreach ($metricIds as $mid) {
        $stmtRel->bind_param("ii", $idModelo, $mid);
        if (!$stmtRel->execute()) {
            throw new Exception("Error vinculando métrica ID $mid: " . $stmtRel->error);
        }
    }
    $stmtRel->close();

    $cn->commit();

    if ($__isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => "Modelo \"$nombre\" actualizado correctamente."]);
        exit;
    }

    $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Modelo \"$nombre\" actualizado correctamente."];
    header("Location: modelos.php");
    exit;

} catch (Exception $ex) {
    if ($cn) $cn->rollback();

    $_SESSION['form_data'] = $_POST;
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $ex->getMessage()];

    if ($__isAjax) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
        exit;
    }

    header("Location: modelo.modificar.php?id_modelo=" . ($idModelo ?? 0));
    exit;
}
