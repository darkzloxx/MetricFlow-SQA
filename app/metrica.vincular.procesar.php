<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

$metricas = $_POST['metricas'] ?? [];
$idModelo = (int)($_POST['modelo'] ?? 0);

if (empty($metricas) || $idModelo <= 0) {
    header('Location: metrica.vincular.php?msg=' . urlencode('Debe seleccionar al menos una métrica y un modelo.') . '&type=danger');
    exit;
}

// 🔒 Validación extra: gerente/líder solo puede vincular al modelo asignado a su proyecto
if (!$esAdmin) {
    $check = $cn->query("
        SELECT 1
        FROM proyecto_modelo_calidad pmc
        JOIN usuario_proyecto up ON up.id_proyecto = pmc.id_proyecto
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        WHERE pmc.id_proyecto_modelo = $idModelo
          AND up.id_usuario = {$usr->id}
          AND p.id_modelo_personalizado = pmc.id_proyecto_modelo
        LIMIT 1
    ");
    if ($check->num_rows === 0) {
        header('Location: metrica.vincular.php?msg=' . urlencode('No tiene permiso para vincular métricas a ese modelo. Solo puede vincular al modelo personalizado asignado a su proyecto.') . '&type=danger');
        exit;
    }
}

// 🔗 Asociar métricas
$insertadas = 0;
foreach ($metricas as $idM) {
    $idM = (int)$idM;
    if ($esAdmin) {
        $cn->query("INSERT IGNORE INTO metrica_modelo_calidad (id_metrica, id_modelo) VALUES ($idM, $idModelo)");
    } else {
        $cn->query("INSERT IGNORE INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo) VALUES ($idM, $idModelo)");
    }
    if ($cn->affected_rows > 0) $insertadas++;
}

// ✅ Mensaje final
if ($insertadas > 0) {
    header('Location: metricas.php?msg=' . urlencode("✅ {$insertadas} métrica(s) vinculadas correctamente al modelo seleccionado.") . '&type=success');
} else {
    header('Location: metrica.vincular.php?msg=' . urlencode('⚠️ Las métricas seleccionadas ya estaban asociadas a ese modelo o no tiene permisos.') . '&type=danger');
}
exit;
?>
