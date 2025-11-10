<?php
require_once '../../lib/ControlAcceso.Class.php';
require_once '../../modelo/BDConexion.Class.php';


header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id_modelo']) ? (int)$_GET['id_modelo'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

$cn = BDConexion::getInstancia();
$sql = "SELECT id_modelo, nombre, descripcion FROM modelo_calidad WHERE id_modelo = ?";
$stmt = $cn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows) {
    $row = $res->fetch_assoc();
    echo json_encode(['ok' => true, 'data' => $row]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Modelo no encontrado']);
}
exit;
