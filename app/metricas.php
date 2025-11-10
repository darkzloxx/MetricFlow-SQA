<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);
$proyectoContextoId = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;

$metricsBase = [];
$proyectosAccesibles = [];
$iteracionesUsuario = 0;
$hasTipo = false;
$bannerInfo = null;

// Detectar columna tipo
try {
    if ($rsCols = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) {
        $hasTipo = (bool)$rsCols->num_rows;
    }
} catch (Throwable $e) {
}

// ==================== ADMIN / SUPERADMIN ====================
if ($esAdminGlobal || $esSuperAdmin) {
    $sqlBase = $hasTipo
        ? "SELECT id_metrica, nombre, descripcion, tipo FROM metrica WHERE tipo='base' ORDER BY nombre"
        : "SELECT id_metrica, nombre, descripcion FROM metrica ORDER BY nombre";
    if ($rsBase = $cn->query($sqlBase)) {
        $metricsBase = $rsBase->fetch_all(MYSQLI_ASSOC);
    }
} else {
    // ==================== USUARIO NORMAL ====================
    if ($usr) {
        $sqlProy = "SELECT p.id_proyecto, p.nombre AS proyecto, m.id_modelo, m.nombre AS modelo
                    FROM proyecto p
                    JOIN modelo_calidad m ON m.id_modelo = p.id_modelo
                    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
                    WHERE up.id_usuario = ? AND p.id_modelo IS NOT NULL
                    ORDER BY p.nombre";
        $stP = $cn->prepare($sqlProy);
        $stP->bind_param('i', $usr->id);
        $stP->execute();
        $resP = $stP->get_result();
        $proyectosAccesibles = $resP ? $resP->fetch_all(MYSQLI_ASSOC) : [];
        $stP->close();

        // Iteraciones del usuario
        $sqlIterCnt = "SELECT COUNT(*) c
                       FROM iteracion i
                       JOIN usuario_proyecto up ON up.id_proyecto = i.id_proyecto
                       WHERE up.id_usuario = ?";
        $stmtI = $cn->prepare($sqlIterCnt);
        $stmtI->bind_param('i', $usr->id);
        $stmtI->execute();
        $resI = $stmtI->get_result();
        if ($rowI = $resI->fetch_assoc()) {
            $iteracionesUsuario = (int)$rowI['c'];
        }
        $stmtI->close();
    }

    // Filtro por proyecto si viene por GET
    if ($proyectoContextoId > 0) {
        $proyectosAccesibles = array_values(array_filter($proyectosAccesibles, fn($p) => (int)$p['id_proyecto'] === $proyectoContextoId));
        if (!empty($proyectosAccesibles)) {
            $bannerInfo = $proyectosAccesibles[0]; // para mostrar banner
        }
    }

    // Cargar métricas
    foreach ($proyectosAccesibles as &$px) {
        $px['metricas'] = [];
        $idModelo = (int)$px['id_modelo'];
        $idProyecto = (int)$px['id_proyecto'];

        // Base
        $sqlMetB = $hasTipo
            ? "SELECT met.id_metrica, met.nombre, met.descripcion, met.tipo
               FROM metrica_modelo_calidad mmc
               JOIN metrica met ON met.id_metrica = mmc.id_metrica
               WHERE mmc.id_modelo = {$idModelo}"
            : "SELECT met.id_metrica, met.nombre, met.descripcion
               FROM metrica_modelo_calidad mmc
               JOIN metrica met ON met.id_metrica = mmc.id_metrica
               WHERE mmc.id_modelo = {$idModelo}";
        $rsB = $cn->query($sqlMetB);
        while ($r = $rsB->fetch_assoc()) {
            $px['metricas'][(int)$r['id_metrica']] = $r;
        }

        // Personalizadas
        $rsPM = $cn->query("SELECT id_proyecto_modelo FROM proyecto_modelo_calidad WHERE id_proyecto = {$idProyecto}");
        $idsPM = [];
        while ($rr = $rsPM->fetch_assoc()) {
            $idsPM[] = (int)$rr['id_proyecto_modelo'];
        }
        if (!empty($idsPM)) {
            $inPM = implode(',', $idsPM);
            $sqlMetP = $hasTipo
                ? "SELECT met.id_metrica, met.nombre, met.descripcion, met.tipo
                   FROM metrica_proyecto_modelo mpm
                   JOIN metrica met ON met.id_metrica = mpm.id_metrica
                   WHERE mpm.id_proyecto_modelo IN ({$inPM})"
                : "SELECT met.id_metrica, met.nombre, met.descripcion
                   FROM metrica_proyecto_modelo mpm
                   JOIN metrica met ON met.id_metrica = mpm.id_metrica
                   WHERE mpm.id_proyecto_modelo IN ({$inPM})";
            $rsP = $cn->query($sqlMetP);
            while ($r = $rsP->fetch_assoc()) {
                $px['metricas'][(int)$r['id_metrica']] = $r;
            }
        }

        // Estados
        if (!empty($px['metricas'])) {
            $idsM = implode(',', array_keys($px['metricas']));
            $sqlE = "SELECT mi.id_metrica,
                            MAX(CASE WHEN mi.valor_planificado IS NOT NULL THEN 1 ELSE 0 END) tiene_plan,
                            MAX(CASE WHEN mi.valor_ejecutado IS NOT NULL THEN 1 ELSE 0 END) tiene_ejec
                     FROM metrica_iteracion mi
                     JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                     WHERE i.id_proyecto = {$idProyecto} AND mi.id_metrica IN ({$idsM})
                     GROUP BY mi.id_metrica";
            $rsE = $cn->query($sqlE);
            while ($r = $rsE->fetch_assoc()) {
                $estado[(int)$r['id_metrica']] = [
                    'plan' => ((int)$r['tiene_plan'] === 1),
                    'ejec' => ((int)$r['tiene_ejec'] === 1)
                ];
            }
            foreach ($px['metricas'] as $idm => &$mm) {
                $mm['_estado_plan'] = $estado[$idm]['plan'] ?? false;
                $mm['_estado_ejec'] = $estado[$idm]['ejec'] ?? false;
            }
        }
    }
}
?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Métricas</title>
    <style>
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background: #fff;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            color: #212529;
        }

        .cell-ellipsis {
            max-width: 240px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cell-ellipsis:hover {
            white-space: normal;
            word-break: break-word;
            background: #f8f9fa;
            border-radius: .25rem;
            padding: .1rem .2rem;
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            padding: 0;
        }

        .btn.disabled,
        .btn:disabled {
            pointer-events: auto !important;
            opacity: 0.8;
            transition: all .2s;
        }

        .btn-outline-warning:disabled:hover {
            background: #ffc107;
            color: #212529;
        }

        .btn-outline-danger:disabled:hover {
            background: #dc3545;
            color: #fff;
        }

        .btn-outline-secondary:disabled:hover {
            background: #6c757d;
            color: #fff;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a href="proyectos.php" class="btn btn-outline-secondary"><span class="oi oi-arrow-left mr-1"></span> Volver</a>
            <?php if ($esAdminGlobal || $esSuperAdmin || $tienePermGestionMetricas): ?>
                <a href="metrica.crear.php" class="btn btn-success ml-2">
                    <span class="oi oi-plus"></span> Nueva Métrica
                </a>
            <?php endif; ?>
        </div>

        <?php if ($bannerInfo): ?>
            <div class="card mb-3" style="border:1px dashed rgba(23,162,184,0.3); background:rgba(23,162,184,0.04);">
                <div class="card-body py-3">
                    <strong>Proyecto:</strong> <?= htmlspecialchars($bannerInfo['proyecto']); ?>
                    — <strong>Modelo:</strong> <span class="badge badge-info"><?= htmlspecialchars($bannerInfo['modelo']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-<?= ($_GET['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <script>
                setTimeout(() => $('.alert').alert('close'), 3000);
            </script>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Métricas</h3>
            </div>
            <div class="card-body">
                <?php if ($esAdminGlobal || $esSuperAdmin): ?>
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
                                        <td class="cell-ellipsis"><?= htmlspecialchars($m['nombre']); ?></td>
                                        <td class="cell-ellipsis"><?= htmlspecialchars($m['descripcion'] ?? ''); ?></td>
                                        <td><span class="badge badge-secondary">Base</span></td>
                                        <td>
                                            <a href="metrica.ver.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-primary btn-icon" title="Ver"><span class="oi oi-eye"></span></a>
                                            <a href="metrica.modificar.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-warning btn-icon" title="Editar"><span class="oi oi-pencil"></span></a>
                                            <a href="metrica.eliminar.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-danger btn-icon" title="Eliminar"><span class="oi oi-trash"></span></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (empty($proyectosAccesibles)): ?>
                        <div class="alert alert-warning"><span class="oi oi-warning mr-2"></span> No hay proyectos con modelo de calidad asignado.</div>
                    <?php else: ?>
                        <?php foreach ($proyectosAccesibles as $px): ?>
                            <h5 class="mt-3 mb-2"><strong><?= htmlspecialchars($px['proyecto']); ?></strong> — <span class="badge badge-info"><?= htmlspecialchars($px['modelo']); ?></span></h5>
                            <?php if ($iteracionesUsuario === 0): ?>
                                <div class="alert alert-info small"><span class="oi oi-lock-locked mr-1"></span> Necesitás crear una <strong>iteración</strong> para planificar o registrar valores.</div>
                            <?php endif; ?>
                            <?php if (empty($px['metricas'])): ?>
                                <div class="text-muted">Este proyecto no tiene métricas asociadas.</div>
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
                                        <?php foreach ($px['metricas'] as $m):
                                            $isBase = $hasTipo ? (strtolower(trim($m['tipo'] ?? '')) === 'base') : true;
                                            $estadoPlan = (bool)($m['_estado_plan'] ?? false);
                                            $estadoEjec = (bool)($m['_estado_ejec'] ?? false);
                                            $tooltip = $isBase ? 'Las métricas base no pueden modificarse ni eliminarse.'
                                                : ($estadoPlan ? 'La métrica ya está planificada.'
                                                    : ($iteracionesUsuario === 0 ? 'No hay iteraciones disponibles.' : ''));
                                        ?>
                                            <tr>
                                                <td class="cell-ellipsis"><?= htmlspecialchars($m['nombre']); ?></td>
                                                <td class="cell-ellipsis"><?= htmlspecialchars($m['descripcion'] ?? ''); ?></td>
                                                <td><span class="badge badge-<?= $isBase ? 'secondary' : 'info'; ?>"><?= $isBase ? 'Base' : 'Personalizada'; ?></span></td>
                                                <td>
                                                    <a href="metrica.ver.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-primary btn-icon" title="Ver"><span class="oi oi-eye"></span></a>
                                                    <button class="btn btn-outline-secondary btn-icon" disabled data-toggle="tooltip" title="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8'); ?>"><span class="oi oi-lock-locked"></span></button>
                                                    <button class="btn btn-outline-secondary btn-icon" disabled data-toggle="tooltip" title="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8'); ?>"><span class="oi oi-lock-locked"></span></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
    <script>
        $(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>

</html>