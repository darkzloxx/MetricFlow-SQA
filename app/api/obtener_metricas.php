<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
header('Content-Type: application/json; charset=utf-8');

$idModelo = (int)($_GET['id_modelo'] ?? 0);
$cn = BDConexion::getInstancia();

$data = [];
if ($idModelo > 0) {
    $res = $cn->query("SELECT m.id_metrica, m.nombre 
                       FROM metrica_modelo_calidad mmc 
                       JOIN metrica m ON m.id_metrica = mmc.id_metrica 
                       WHERE mmc.id_modelo = {$idModelo}");
    while ($r = $res->fetch_assoc()) {
        $data[] = $r;
    }
}

echo json_encode(['metricas' => $data]);
