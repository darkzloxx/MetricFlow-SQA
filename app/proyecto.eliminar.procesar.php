<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_PROYECTOS);
if (!ControlAcceso::esAdminGlobal()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acceso restringido a administradores.']);
    exit;
}
include_once '../modelo/BDConexion.Class.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_POST['id'] ?? 0);
$cn = BDConexion::getInstancia();

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$cn->autocommit(false);
$cn->begin_transaction();

$query = "DELETE FROM proyecto WHERE id_proyecto = {$id}";
$ok = $cn->query($query);

if (!$ok) {
    $cn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $cn->error]);
    exit;
}

$cn->commit();
$cn->autocommit(true);

echo json_encode(['success' => true, 'message' => 'Proyecto eliminado correctamente.']);
exit;
?>
