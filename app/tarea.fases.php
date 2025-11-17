<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

$cn = BDConexion::getInstancia();
$idProyecto = (int)$_GET['proyecto'];

$sql = "
SELECT DISTINCT f.id_fase, f.nombre
FROM fase f
JOIN iteracion i ON i.id_fase = f.id_fase
WHERE i.id_proyecto = $idProyecto
ORDER BY f.id_fase";
$res = $cn->query($sql)->fetch_all(MYSQLI_ASSOC);

echo '<option value="">Seleccione...</option>';
foreach($res as $r){
    echo "<option value='{$r['id_fase']}'>{$r['nombre']}</option>";
}
