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

// ===============================
// 🔍 Validaciones de seguridad
// ===============================

// 1️⃣ ¿Tiene iteraciones creadas?
$sqlIt = "SELECT COUNT(*) AS c FROM iteracion WHERE id_proyecto = $id";
$cIt = (int)$cn->query($sqlIt)->fetch_assoc()['c'];
if ($cIt > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Este proyecto no puede eliminarse porque ya tiene iteraciones creadas.'
    ]);
    exit;
}

// 2️⃣ ¿Tiene métricas planificadas?
$sqlPlan = "
    SELECT COUNT(*) AS c 
    FROM metrica_iteracion mi
    JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
    WHERE i.id_proyecto = $id
      AND mi.valor_planificado IS NOT NULL";
$cPlan = (int)$cn->query($sqlPlan)->fetch_assoc()['c'];
if ($cPlan > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Este proyecto no puede eliminarse porque tiene métricas planificadas.'
    ]);
    exit;
}

// 3️⃣ ¿Tiene métricas ejecutadas?
$sqlExec = "
    SELECT COUNT(*) AS c 
    FROM metrica_iteracion mi
    JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
    WHERE i.id_proyecto = $id
      AND mi.valor_ejecutado IS NOT NULL";
$cExec = (int)$cn->query($sqlExec)->fetch_assoc()['c'];
if ($cExec > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Este proyecto no puede eliminarse porque tiene métricas ejecutadas.'
    ]);
    exit;
}

// ===============================
// 🔥 Eliminación segura
// ===============================
$cn->autocommit(false);

$ok = $cn->query("DELETE FROM proyecto WHERE id_proyecto = $id");

if (!$ok) {
    $cn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar el proyecto: ' . $cn->error
    ]);
    exit;
}

$cn->commit();
$cn->autocommit(true);

echo json_encode([
    'success' => true,
    'message' => 'Proyecto eliminado correctamente.'
]);
exit;
