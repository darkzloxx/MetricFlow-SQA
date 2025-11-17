<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::verificaLogin();
include_once '../modelo/BDConexion.Class.php';

$cn = BDConexion::getInstancia();

$proyecto = isset($_GET['proyecto']) ? (int) $_GET['proyecto'] : 0;

if ($proyecto <= 0) {
    echo '<option value="">Seleccione proyecto...</option>';
    exit;
}

// Obtener iteraciones con su fase asociada ordenadas por fase y número
$sql = "
SELECT i.id_iteracion, i.numero_iteracion, f.nombre AS fase
FROM iteracion i
JOIN fase f ON f.id_fase = i.id_fase
WHERE i.id_proyecto = $proyecto
ORDER BY f.id_fase, i.numero_iteracion ASC";

$res = $cn->query($sql);

if (!$res || $res->num_rows == 0) {
    echo '<option value="">No hay iteraciones...</option>';
    exit;
}

// Cargar opciones
echo '<option value="">Seleccione...</option>';
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id_iteracion'];
    $iter = (int)$row['numero_iteracion'];
    $fase = htmlspecialchars($row['fase']);

    echo "<option value='$id'>$fase $iter</option>";
}
?>
