<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

// ===========================
// 🔹 Cargar métricas y modelos según el rol
// ===========================
if ($esAdmin) {
    // Admin: solo métricas base y modelos globales
    $sqlMetricas = "SELECT id_metrica, nombre, tipo 
                    FROM metrica 
                    WHERE tipo = 'base' 
                    ORDER BY nombre";
    $sqlModelos = "SELECT id_modelo AS id, nombre 
                   FROM modelo_calidad 
                   ORDER BY nombre";
} else {
    // Gerente / Líder: métricas base y personalizadas propias + su modelo
    $sqlMetricas = "
        SELECT DISTINCT m.id_metrica, m.nombre, m.tipo
        FROM metrica m
        LEFT JOIN metrica_modelo_calidad mmc ON mmc.id_metrica = m.id_metrica
        LEFT JOIN metrica_proyecto_modelo mpm ON mpm.id_metrica = m.id_metrica
        JOIN usuario_proyecto up ON up.id_proyecto = mpm.id_proyecto_modelo
        WHERE up.id_usuario = {$usr->id}
           OR m.tipo = 'base'
        ORDER BY m.nombre
    ";

    // Solo el modelo personalizado asignado al proyecto del usuario
    $sqlModelos = "
        SELECT pmc.id_proyecto_modelo AS id, pmc.nombre, p.nombre AS proyecto
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
        WHERE up.id_usuario = {$usr->id}
          AND p.id_modelo_personalizado = pmc.id_proyecto_modelo
        ORDER BY p.nombre
    ";
}

$metricas = $cn->query($sqlMetricas)->fetch_all(MYSQLI_ASSOC);
$modelos = $cn->query($sqlModelos)->fetch_all(MYSQLI_ASSOC);
?>

<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Vincular Métrica Existente</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-4">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-<?= ($_GET['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?= $_GET['msg']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <script>
                $('html, body').animate({
                    scrollTop: 0
                }, 'fast');
                setTimeout(() => $('.alert').alert('close'), 4000);
            </script>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="mb-0">Vincular Métrica Existente</h3>
                <p class="mb-0 mt-1 text-muted">
                    <?= $esAdmin
                        ? 'Solo se muestran métricas base y modelos globales.'
                        : 'Solo puede vincular métricas al modelo personalizado asignado a su proyecto.'; ?>
                </p>
            </div>

            <form action="metrica.vincular.procesar.php" method="post">
                <div class="card-body">

                    <!-- 🔹 Métricas -->
                    <div class="form-group">
                        <label><strong>Métricas disponibles</strong></label>
                        <?php if (empty($metricas)): ?>
                            <div class="text-muted mt-2">No hay métricas disponibles para vincular.</div>
                        <?php else: ?>
                            <div class="border rounded p-2" style="max-height: 280px; overflow-y: auto;">
                                <?php foreach ($metricas as $m): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="metricas[]" value="<?= $m['id_metrica']; ?>">
                                        <label class="form-check-label">
                                            <?= htmlspecialchars($m['nombre']); ?>
                                            <small class="text-muted">(<?= ucfirst($m['tipo']); ?>)</small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 🔹 Modelos -->
                    <div class="form-group">
                        <label><strong>Asociar a modelo</strong></label>
                        <?php if (empty($modelos)): ?>
                            <div class="text-muted mt-2">No hay modelos disponibles para asociar.</div>
                        <?php else: ?>
                            <select name="modelo" class="form-control" required>
                                <option value="">Seleccione un modelo...</option>
                                <?php foreach ($modelos as $mod): ?>
                                    <option value="<?= $mod['id']; ?>">
                                        <?= $esAdmin
                                            ? htmlspecialchars($mod['nombre'])
                                            : htmlspecialchars($mod['proyecto'] . ' — ' . $mod['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-success">
                        <span class="oi oi-check"></span> Vincular
                    </button>
                    <a href="metricas.php" class="btn btn-outline-secondary">
                        <span class="oi oi-x"></span> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>