<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::verificaLogin();
include_once '../modelo/BDConexion.Class.php';

$cn = BDConexion::getInstancia();

$idProyecto = (int)($_GET['proyecto'] ?? 0);
$idIteracion = (int)($_GET['iteracion'] ?? 0);
$idTarea = (int)($_GET['tarea'] ?? 0); // ← IMPORTANTE para modo MODIFICAR

if ($idProyecto <= 0 || $idIteracion <= 0) {
    echo "<span class='text-danger'>Seleccione una iteración válida.</span>";
    exit;
}

// Obtener modelo asignado al proyecto
$sql = "
SELECT id_modelo_global, id_modelo_personalizado
FROM proyecto
WHERE id_proyecto = $idProyecto
LIMIT 1";
$proy = $cn->query($sql)->fetch_assoc();
$idBase = (int)$proy['id_modelo_global'];
$idPersonal = (int)$proy['id_modelo_personalizado'];

// Obtener métricas del modelo (sea global o personalizado)
if ($idPersonal > 0) {
    $sqlMetricas = "
        SELECT DISTINCT m.id_metrica, m.nombre, m.descripcion
        FROM metrica m
        JOIN metrica_proyecto_modelo mpm ON mpm.id_metrica = m.id_metrica
        JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
        WHERE pmc.id_proyecto = $idProyecto
        ORDER BY m.nombre ASC";
} else {
    $sqlMetricas = "
        SELECT DISTINCT m.id_metrica, m.nombre, m.descripcion
        FROM metrica m
        JOIN metrica_modelo_calidad mmc ON mmc.id_metrica = m.id_metrica
        WHERE mmc.id_modelo = $idBase
        ORDER BY m.nombre ASC";
}

$res = $cn->query($sqlMetricas);

if (!$res || $res->num_rows === 0) {
    echo "<span class='text-danger'>No hay métricas para este proyecto.</span>";
    exit;
}

// ==========================
// Si es modificación → cargar métricas asociadas a la tarea
// ==========================
$metricasActuales = [];
if ($idTarea > 0) {
    $sqlSel = "SELECT id_metrica FROM metrica_tarea WHERE id_tarea = $idTarea";
    $resSel = $cn->query($sqlSel);
    while ($row = $resSel->fetch_assoc()) {
        $metricasActuales[] = (int)$row['id_metrica'];
    }
}

// Render checkboxes
while ($m = $res->fetch_assoc()) {
    $id = (int)$m['id_metrica'];
    $nombre = htmlspecialchars($m['nombre']);
    $desc = htmlspecialchars($m['descripcion']);

    $checked = in_array($id, $metricasActuales) ? "checked" : "";

    echo "
     <div class='form-check'>
        <input class='form-check-input' type='checkbox'
               name='metricas[]'
               value='$id'
               id='met_$id'
               data-nombre=\"$nombre\"
               $checked>
        <label class='form-check-label text-small' for='met_$id' title='$desc'>
            $nombre
        </label>
    </div>";
}
?>
