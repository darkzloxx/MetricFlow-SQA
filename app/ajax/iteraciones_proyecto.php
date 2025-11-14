<?php
include_once '../../lib/ControlAcceso.Class.php';
include_once '../../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();
$cn = BDConexion::getInstancia();

$idProyecto = (int)($_GET['idProyecto'] ?? 0);

$sql = "SELECT fecha_inicio, fecha_fin 
        FROM iteracion 
        WHERE id_proyecto = {$idProyecto}
        ORDER BY fecha_inicio ASC";

$rs = $cn->query($sql);

echo json_encode($rs ? $rs->fetch_all(MYSQLI_ASSOC) : []);
