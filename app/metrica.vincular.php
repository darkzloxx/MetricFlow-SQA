<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

// ===========================
// 🔹 Cargar modelos según el rol
// ===========================
if ($esAdmin) {
    // Admin → solo modelos globales
    $sqlModelos = "SELECT id_modelo AS id, nombre, 'base' AS tipo FROM modelo_calidad ORDER BY nombre";
} else {
    // Gerente/Líder → solo su modelo personalizado asignado al proyecto
    $sqlModelos = "
        SELECT pmc.id_proyecto_modelo AS id, pmc.nombre, p.nombre AS proyecto, 'personalizado' AS tipo
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
        WHERE up.id_usuario = {$usr->id}
          AND p.id_modelo_personalizado = pmc.id_proyecto_modelo
        ORDER BY p.nombre
    ";
}
$modelos = $cn->query($sqlModelos)->fetch_all(MYSQLI_ASSOC);

// ===========================
// 🔹 Determinar el modelo actual (GET o único)
// ===========================
$idModelo = isset($_GET['modelo']) ? (int)$_GET['modelo'] : 0;
if ($idModelo === 0 && count($modelos) === 1) {
    // Si solo hay un modelo disponible, se usa automáticamente
    $idModelo = (int)$modelos[0]['id'];
}

// ===========================
// 🔹 Cargar métricas no vinculadas
// ===========================
$metricas = [];
if ($idModelo > 0) {
    if ($esAdmin) {
        // Admin → solo métricas BASE no asociadas al modelo global
        $sqlMetricas = "
            SELECT m.id_metrica, m.nombre, m.tipo
            FROM metrica m
            WHERE m.tipo = 'base'
              AND m.id_metrica NOT IN (
                  SELECT id_metrica FROM metrica_modelo_calidad WHERE id_modelo = $idModelo
              )
            ORDER BY m.nombre
        ";
    } else {
        // Gerente/Líder → métricas base o personalizadas no asociadas a su modelo
        $sqlMetricas = "
            SELECT m.id_metrica, m.nombre, m.tipo
            FROM metrica m
            WHERE m.tipo IN ('base','personalizada')
              AND m.id_metrica NOT IN (
                  SELECT id_metrica FROM metrica_proyecto_modelo WHERE id_proyecto_modelo = $idModelo
              )
            ORDER BY m.nombre
        ";
    }
    $rs = $cn->query($sqlMetricas);
    $metricas = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
}
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
            $('html, body').animate({ scrollTop: 0 }, 'fast');
            setTimeout(() => $('.alert').alert('close'), 4000);
        </script>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0">Vincular Métrica Existente</h3>
            <p class="mb-0 mt-1 text-muted">
                <?= $esAdmin
                    ? 'Solo puede vincular métricas base a modelos globales.'
                    : 'Puede vincular métricas base o personalizadas al modelo personalizado de su proyecto.'; ?>
            </p>
        </div>

        <form action="metrica.vincular.procesar.php" method="post">
            <div class="card-body">

                <!-- 🔹 Selección de modelo -->
                <?php if (empty($modelos)): ?>
                    <div class="alert alert-info">No hay modelos personalizados     disponibles.</div>
                <?php elseif (count($modelos) === 1): ?>
                    <?php $m = $modelos[0]; ?>
                    <div class="form-group">
                        <label><strong>Modelo seleccionado</strong></label>
                        <p class="form-control-plaintext font-weight-bold">
                            <?= htmlspecialchars($m['nombre']); ?>
                            <?php if (!$esAdmin): ?>
                                <small class="text-muted">(Proyecto: <?= htmlspecialchars($m['proyecto']); ?>)</small>
                            <?php endif; ?>
                        </p>
                        <input type="hidden" name="modelo" value="<?= (int)$m['id']; ?>">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label><strong>Seleccione un modelo</strong></label>
                        <select name="modelo" class="form-control" required onchange="location.href='?modelo='+this.value;">
                            <option value="">Seleccione un modelo...</option>
                            <?php foreach ($modelos as $mod): ?>
                                <option value="<?= $mod['id']; ?>" <?= $idModelo == $mod['id'] ? 'selected' : ''; ?>>
                                    <?= $esAdmin
                                        ? htmlspecialchars($mod['nombre'])
                                        : htmlspecialchars($mod['proyecto'] . ' — ' . $mod['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- 🔹 Métricas disponibles -->
                <?php if ($idModelo > 0): ?>
                    <div class="form-group mt-3">
                        <label><strong>Métricas disponibles para vincular</strong></label>
                        <?php if (empty($metricas)): ?>
                            <p class="text-muted mt-2">No hay métricas disponibles. Todas ya están asociadas a este modelo.</p>
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
                <?php endif; ?>

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
