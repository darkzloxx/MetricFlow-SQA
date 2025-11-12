<?php
require_once '../../lib/ControlAcceso.Class.php';
require_once '../../modelo/BDConexion.Class.php';


header('Content-Type: application/json; charset=utf-8');
ControlAcceso::verificaLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método inválido']);
    exit;
}

$nombres = $_POST['nombres'] ?? [];
if (!is_array($nombres) || empty($nombres)) {
    echo json_encode(['ok' => true, 'existentes' => []]);
    exit;
}

$cn = BDConexion::getInstancia();
$placeholders = implode(',', array_fill(0, count($nombres), '?'));
$stmt = $cn->prepare("SELECT nombre FROM metrica WHERE nombre IN ($placeholders)");
$stmt->bind_param(str_repeat('s', count($nombres)), ...$nombres);
$stmt->execute();
$res = $stmt->get_result();

$existentes = [];
while ($row = $res->fetch_assoc()) {
    $existentes[] = $row['nombre'];
}

echo json_encode(['ok' => true, 'existentes' => $existentes]);
exit;
