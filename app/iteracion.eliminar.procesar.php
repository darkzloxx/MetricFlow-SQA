<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
include_once '../modelo/BDConexion.Class.php';

$DatosFormulario = $_POST;
$idIteracion = (int)$DatosFormulario["id"];

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$resultado = false;
$mensaje = "Ha ocurrido un error.";

// 🔹 1. Verificar si la iteración tiene tareas asociadas
$sqlTareas = "SELECT 1 FROM iteracion_tarea WHERE id_iteracion = ? LIMIT 1";
$stmtTareas = $cn->prepare($sqlTareas);
$stmtTareas->bind_param('i', $idIteracion);
$stmtTareas->execute();
$resTareas = $stmtTareas->get_result();

if ($resTareas && $resTareas->num_rows > 0) {
    $mensaje = "❌ La iteración no puede eliminarse porque tiene tareas asociadas.";
} else {
    // 🔹 2. Verificar si la iteración tiene métricas planificadas o ejecutadas
    $sqlMetricas = "SELECT 1 FROM metrica_iteracion WHERE id_iteracion = ? LIMIT 1";
    $stmtMetricas = $cn->prepare($sqlMetricas);
    $stmtMetricas->bind_param('i', $idIteracion);
    $stmtMetricas->execute();
    $resMetricas = $stmtMetricas->get_result();

    if ($resMetricas && $resMetricas->num_rows > 0) {
        $mensaje = "⚠️ La iteración no puede eliminarse porque tiene métricas planificadas o ejecutadas.";
    } else {
        // 🔹 3. Eliminar la iteración si no tiene dependencias
        $sqlDelete = "DELETE FROM iteracion WHERE id_iteracion = ?";
        $stmtDel = $cn->prepare($sqlDelete);
        $stmtDel->bind_param('i', $idIteracion);

        if ($stmtDel->execute()) {
            $cn->commit();
            $resultado = true;
            $mensaje = "✅ Iteración eliminada correctamente.";
        } else {
            $cn->rollback();
            $mensaje = "Error al eliminar la iteración: " . $stmtDel->error;
        }
        $stmtDel->close();
    }

    $stmtMetricas->close();
}
$stmtTareas->close();

$cn->autocommit(true);
?>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Eliminar Iteración</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
</head>
<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white">
                <h3>Resultado de la eliminación</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-<?= $resultado ? 'success' : 'danger'; ?>">
                    <?= htmlspecialchars($mensaje); ?>
                </div>
                <a href="iteraciones.php" class="btn btn-outline-primary">
                    <span class="oi oi-arrow-left"></span> Volver
                </a>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>
</html>
