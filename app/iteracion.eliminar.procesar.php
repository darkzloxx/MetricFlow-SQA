<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
include_once '../modelo/BDConexion.Class.php';

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$usr = ControlAcceso::usuarioActual();
$idUsuario = (int)$usr->id;
$idIter = (int)($_POST['id'] ?? 0);

$ok = false;
$mensaje = "Error inesperado.";

// 1️⃣ Verificar existencia de iteración + rol de líder en ese proyecto
$sqlCheck = "
    SELECT i.id_proyecto
    FROM iteracion i
    JOIN proyecto p ON p.id_proyecto = i.id_proyecto
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    JOIN rol r ON r.id = up.id_rol
    WHERE i.id_iteracion = ?
      AND up.id_usuario = ?
      AND LOWER(r.nombre) LIKE '%líder%'
    LIMIT 1
";
$stmt = $cn->prepare($sqlCheck);
$stmt->bind_param("ii", $idIter, $idUsuario);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    $mensaje = "No tenés permisos para eliminar esta iteración.";
    goto fin;
}

$idProyecto = (int)$res['id_proyecto'];

// 2️⃣ Verificar si tiene métricas ejecutadas
$sqlCheckMetrics = "
    SELECT COUNT(*) AS c
    FROM metrica_iteracion
    WHERE id_iteracion = ?
      AND valor_ejecutado IS NOT NULL
      AND valor_ejecutado <> ''
";
$stmt = $cn->prepare($sqlCheckMetrics);
$stmt->bind_param("i", $idIter);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($r['c'] > 0) {
    $mensaje = "La iteración tiene métricas ejecutadas y no puede eliminarse.";
    goto fin;
}

// 3️⃣ Eliminar registros relacionados: metrica_iteracion
$sqlDelMI = "DELETE FROM metrica_iteracion WHERE id_iteracion = ?";
$stmt = $cn->prepare($sqlDelMI);
$stmt->bind_param("i", $idIter);
$stmt->execute();
$stmt->close();

// 4️⃣ Eliminar la iteración
$sqlDelIter = "DELETE FROM iteracion WHERE id_iteracion = ?";
$stmt = $cn->prepare($sqlDelIter);
$stmt->bind_param("i", $idIter);
if ($stmt->execute()) {
    $cn->commit();
    $ok = true;
    $mensaje = "Iteración eliminada correctamente.";
} else {
    $cn->rollback();
    $mensaje = "Error al intentar eliminar la iteración.";
}
$stmt->close();
$cn->autocommit(true);

fin:
$type = $ok ? 'success' : 'danger';
$cn->close();

header('Location: iteraciones.php?msg=' . urlencode($mensaje) . '&type=' . $type);
exit;
