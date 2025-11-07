<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);

// =====================================================
// VISTA UNIFICADA DE MÉTRICAS
//  - Admin/SuperAdmin: ven TODAS las métricas base del sistema.
//  - No admin: ven métricas asociadas al modelo seleccionado (vía proyectos asignados).
//    Las métricas de tipo 'base' solo se pueden ver (sin editar/eliminar).
// =====================================================

$metricsBase = [];
$modelosAccesibles = [];
$metricsDelModelo = [];
$selectedModelId = 0;

// Detectar si la columna 'tipo' existe en la tabla metrica (para compatibilidad)
$hasTipo = false;
try {
    if ($rsCols = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) {
        $hasTipo = (bool)$rsCols->num_rows;
    }
} catch (Throwable $e) {
    $hasTipo = false;
}

if ($esAdminGlobal || $esSuperAdmin) {
    // Admin global / superadmin: mostrar todas las métricas base.
    $sqlBase = $hasTipo
        ? "SELECT id_metrica, nombre, descripcion, tipo FROM metrica WHERE tipo='base' ORDER BY nombre"
        : "SELECT id_metrica, nombre, descripcion FROM metrica ORDER BY nombre"; // sin 'tipo', considerar como base
    if ($rsBase = $cn->query($sqlBase)) {
        $metricsBase = $rsBase->fetch_all(MYSQLI_ASSOC);
    }
} else {
    // Usuario no admin: obtener modelos a través de proyectos asignados.
    $modelosAccesibles = [];
    if ($usr) {
        $sqlModelos = "SELECT DISTINCT m.id_modelo, m.nombre, m.descripcion
                       FROM proyecto p
                       JOIN modelo_calidad m ON m.id_modelo = p.id_modelo
                       JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
                       WHERE up.id_usuario = ? AND p.id_modelo IS NOT NULL
                       ORDER BY m.nombre";
        if ($stmtM = $cn->prepare($sqlModelos)) {
            $stmtM->bind_param('i', $usr->id);
            $stmtM->execute();
            $resM = $stmtM->get_result();
            $modelosAccesibles = $resM ? $resM->fetch_all(MYSQLI_ASSOC) : [];
            $stmtM->close();
        }
    }
    if (!empty($modelosAccesibles)) {
        $selectedModelId = isset($_GET['id_modelo']) ? (int)$_GET['id_modelo'] : (int)$modelosAccesibles[0]['id_modelo'];
        $validIds = array_map(function($m){ return (int)$m['id_modelo']; }, $modelosAccesibles);
        if (!in_array($selectedModelId, $validIds, true)) {
            $selectedModelId = (int)$modelosAccesibles[0]['id_modelo'];
        }
        // Obtener métricas asociadas al modelo seleccionado.
        $sqlMet = $hasTipo
            ? "SELECT met.id_metrica, met.nombre, met.descripcion, met.tipo
               FROM metrica_modelo_calidad mmc
               JOIN metrica met ON met.id_metrica = mmc.id_metrica
               WHERE mmc.id_modelo = ?
               ORDER BY met.nombre"
            : "SELECT met.id_metrica, met.nombre, met.descripcion
               FROM metrica_modelo_calidad mmc
               JOIN metrica met ON met.id_metrica = mmc.id_metrica
               WHERE mmc.id_modelo = ?
               ORDER BY met.nombre";
        if ($stmtMet = $cn->prepare($sqlMet)) {
            $stmtMet->bind_param('i', $selectedModelId);
            $stmtMet->execute();
            $resMet = $stmtMet->get_result();
            $metricsDelModelo = $resMet ? $resMet->fetch_all(MYSQLI_ASSOC) : [];
            $stmtMet->close();
        }
    }
}
?>
<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Métricas</title>
    <style>
        .btn-icon{display:inline-flex;align-items:center;justify-content:center;width:40px;padding-left:0;padding-right:0;}
        .table-sm td, .table-sm th { vertical-align: middle; }
    </style>
</head>
<body>
<?php include_once '../gui/navbar.php'; ?>
<div class="container">
    <div class="mb-3">
        <a id="btnVolver" href="proyectos.php" class="btn btn-outline-secondary">
            <span class="oi oi-arrow-left mr-1"></span> Volver
        </a>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>Métricas</h3>
            <small class="text-muted">Vista unificada. Las métricas base son globales y solo lectura para usuarios no administradores.</small>
        </div>
        <div class="card-body">
            <?php if ($esAdminGlobal || $esSuperAdmin): ?>
                <p>
                    <a href="metrica.crear.php" class="btn btn-success">
                        <span class="oi oi-plus"></span> Nueva Métrica
                    </a>
                </p>
                <?php if (empty($metricsBase)): ?>
                    <div class="text-muted">No hay métricas base registradas.</div>
                <?php else: ?>
                    <table class="table table-hover table-sm">
                        <thead class="table-info">
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($metricsBase as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['nombre']); ?></td>
                                <td><?= htmlspecialchars(mb_strimwidth($m['descripcion'] ?? '',0,120,'…','UTF-8')); ?></td>
                                <td><span class="badge badge-secondary">Base</span></td>
                                <td>
                                    <a title="Ver" href="metrica.ver.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-primary btn-icon">
                                        <span class="oi oi-eye"></span>
                                    </a>
                                    <!-- Para futuras acciones (editar/eliminar) validar reglas; base podría bloquear eliminación si está en uso -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($modelosAccesibles)): ?>
                    <div class="card my-4 text-center" style="border:1px dashed rgba(23,162,184,0.15); background:rgba(23,162,184,0.03);">
                        <div class="card-body p-4">
                            <i class="oi oi-info mb-2" style="font-size:2rem; color:#17a2b8;"></i>
                            <h5 class="text-info font-weight-bold mb-2">No tenés modelos accesibles</h5>
                            <p class="text-muted mb-0">Aún no estás asignado a proyectos con modelo configurado.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="get" class="form-inline mb-3">
                        <label for="selModelo" class="mr-2">Modelo:</label>
                        <select id="selModelo" name="id_modelo" class="form-control mr-2" onchange="this.form.submit()">
                            <?php foreach ($modelosAccesibles as $mod): ?>
                                <option value="<?= (int)$mod['id_modelo']; ?>" <?= $selectedModelId === (int)$mod['id_modelo'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($mod['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($tienePermGestionMetricas): ?>
                            <a href="metrica.crear.php" class="btn btn-success" title="Crear métrica (se asociará seleccionando modelos en el formulario)">
                                <span class="oi oi-plus"></span> Nueva Métrica
                            </a>
                        <?php endif; ?>
                    </form>
                    <?php if (empty($metricsDelModelo)): ?>
                        <div class="text-muted">El modelo seleccionado no tiene métricas asociadas.</div>
                    <?php else: ?>
                        <table class="table table-hover table-sm">
                            <thead class="table-info">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Tipo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($metricsDelModelo as $m):
                                $isBase = $hasTipo ? (strtolower(trim($m['tipo'] ?? '')) === 'base') : true;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['nombre']); ?></td>
                                    <td><?= htmlspecialchars(mb_strimwidth($m['descripcion'] ?? '',0,120,'…','UTF-8')); ?></td>
                                    <td>
                                        <span class="badge badge-<?= $isBase ? 'secondary' : 'info'; ?>"><?= $isBase ? 'Base' : 'Personalizada'; ?></span>
                                    </td>
                                    <td>
                                        <a title="Ver" href="metrica.ver.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-primary btn-icon">
                                            <span class="oi oi-eye"></span>
                                        </a>
                                        <?php if (!$isBase && $tienePermGestionMetricas): ?>
                                            <!-- Botones futuros para editar/eliminar métricas personalizadas -->
                                            <!-- <a class="btn btn-outline-warning btn-icon" title="Editar" href="metrica.modificar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                <span class="oi oi-pencil"></span>
                                            </a> -->
                                            <!-- <a class="btn btn-outline-danger btn-icon" title="Eliminar" href="metrica.eliminar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                <span class="oi oi-trash"></span>
                                            </a> -->
                                        <?php else: ?>
                                            <?php if ($isBase): ?>
                                                <button class="btn btn-outline-secondary btn-icon" disabled title="Métrica base - solo lectura">
                                                    <span class="oi oi-lock-locked"></span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include_once '../gui/footer.php'; ?>
</body>
</html>