<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_PROYECTOS);
include_once '../modelo/BDConexion.Class.php';

$bd = BDConexion::getInstancia();
$isAjax = !empty($_POST['ajax']);

try {
    $id = (int)($_POST['id_proyecto'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $objetivo = trim($_POST['objetivo'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    if ($id <= 0) {
        throw new Exception("ID de proyecto inválido.");
    }
    if ($nombre === '') {
        throw new Exception("El nombre no puede estar vacío.");
    }

    // Campos opcionales: si vienen vacíos, se guardan como NULL
    $descripcion = ($descripcion === '') ? null : $descripcion;
    $objetivo = ($objetivo === '') ? null : $objetivo;

    // ===========================
    // Actualizar el proyecto
    // ===========================
    $stmt = $bd->prepare("
        UPDATE proyecto 
        SET nombre = ?, descripcion = ?, objetivo = ?, estado = ? 
        WHERE id_proyecto = ?
    ");
    $stmt->bind_param('ssssi', $nombre, $descripcion, $objetivo, $estado, $id);

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar: " . $stmt->error);
    }
    $stmt->close();

    $msg = "Proyecto actualizado correctamente.";

    // ===========================
    // Respuesta según modo (AJAX o redirección)
    // ===========================
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    } else {
        header("Location: proyectos.php?msg=" . urlencode($msg) . "&type=success");
        exit;
    }

} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header("Location: proyectos.php?msg=" . urlencode("Error: " . $e->getMessage()) . "&type=danger");
    }
    exit;
}
?>
