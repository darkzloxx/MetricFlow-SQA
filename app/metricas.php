<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

// Contexto opcional de proyecto para filtrar métricas (flujo de planificación desde wizard Paso 5)
$proyectoContextoId = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);

// =====================================================
// VISTA DE MÉTRICAS (Reglas solicitadas)
// 1. Admin / SuperAdmin:
//    - Ven SOLO métricas de tipo 'base'.
//    - Pueden crear / editar / eliminar métricas BASE siempre que no estén en uso.
//    - NO planifican NI ejecutan métricas (no se muestran esos íconos).
// 2. Gerente / Líder (usuario con permiso GESTION_METRICAS, pero no Admin global):
//    - Debe haber seleccionado / existir un modelo de calidad y al menos una iteración en sus proyectos para entrar.
//    - Solo ve métricas asociadas al modelo seleccionado.
//    - Solo puede crear métricas PERSONALIZADAS (tipo 'personalizada').
//    - Solo puede editar / eliminar métricas PERSONALIZADAS que no hayan sido planificadas (sin filas en metrica_iteracion con valor_planificado) y que sean "propias".
//      (No contamos columna de creador en esquema actual; asumimos pertenencia por asociación al modelo del proyecto del usuario.)
//    - Puede planificar una métrica personalizada no planificada (ícono calendario) y luego registrar ejecución si está planificada y aún sin valor ejecutado.
//    - Íconos con candado cuando acción no permitida.
// =====================================================

$metricsBase = [];
$modelosAccesibles = [];
$metricsDelModelo = [];
$selectedModelId = 0;
$proyectosAccesibles = []; // listado de proyectos del usuario con su modelo
$iteracionesUsuario = 0; // cantidad de iteraciones en proyectos del usuario (para restricción de navegación)

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
    // Usuario no admin: obtener modelos a través de proyectos asignados y contar iteraciones.
    if ($usr) {
        // Proyectos accesibles (con su modelo)
        $sqlProy = "SELECT p.id_proyecto, p.nombre AS proyecto, m.id_modelo, m.nombre AS modelo
                    FROM proyecto p
                    JOIN modelo_calidad m ON m.id_modelo = p.id_modelo
                    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
                    WHERE up.id_usuario = ? AND p.id_modelo IS NOT NULL
                    ORDER BY p.nombre";
        if ($stP = $cn->prepare($sqlProy)) {
            $stP->bind_param('i', $usr->id);
            $stP->execute();
            $resP = $stP->get_result();
            $proyectosAccesibles = $resP ? $resP->fetch_all(MYSQLI_ASSOC) : [];
            $stP->close();
        }
        // Contar iteraciones en proyectos del usuario (para restricción de navegación)
        $sqlIterCnt = "SELECT COUNT(*) c
                       FROM iteracion i
                       JOIN usuario_proyecto up ON up.id_proyecto = i.id_proyecto
                       WHERE up.id_usuario = ?";
        if ($stmtI = $cn->prepare($sqlIterCnt)) {
            $stmtI->bind_param('i', $usr->id);
            $stmtI->execute();
            $resI = $stmtI->get_result();
            if ($rowI = $resI->fetch_assoc()) {
                $iteracionesUsuario = (int)$rowI['c'];
            }
            $stmtI->close();
        }
    }
    // Construir métricas por proyecto (incluye base y personalizadas)
    // Si se viene con un contexto de proyecto (?proyecto=ID) y el usuario tiene acceso, limitar la vista a ese proyecto
    if ($proyectoContextoId > 0) {
        $proyectosAccesibles = array_values(array_filter($proyectosAccesibles, function ($p) use ($proyectoContextoId) {
            return (int)$p['id_proyecto'] === $proyectoContextoId;
        }));
    }

    foreach ($proyectosAccesibles as &$px) {
        $px['metricas'] = [];
        $idModelo = (int)$px['id_modelo'];
        $idProyecto = (int)$px['id_proyecto'];
        // Base desde el modelo
        $sqlMetB = $hasTipo
            ? 'SELECT met.id_metrica, met.nombre, met.descripcion, met.tipo FROM metrica_modelo_calidad mmc JOIN metrica met ON met.id_metrica = mmc.id_metrica WHERE mmc.id_modelo = ' . $idModelo
            : 'SELECT met.id_metrica, met.nombre, met.descripcion FROM metrica_modelo_calidad mmc JOIN metrica met ON met.id_metrica = mmc.id_metrica WHERE mmc.id_modelo = ' . $idModelo;
        if ($rsB = $cn->query($sqlMetB)) {
            while ($r = $rsB->fetch_assoc()) {
                $px['metricas'][(int)$r['id_metrica']] = $r;
            }
        }
        // Personalizadas del proyecto
        $idsPM = [];
        if ($rsPM = $cn->query('SELECT id_proyecto_modelo FROM proyecto_modelo_calidad WHERE id_proyecto = ' . $idProyecto)) {
            while ($rr = $rsPM->fetch_assoc()) {
                $idsPM[] = (int)$rr['id_proyecto_modelo'];
            }
        }
        if (!empty($idsPM)) {
            $inPM = implode(',', array_filter($idsPM, function ($v) {
                return $v > 0;
            }));
            $sqlMetP = $hasTipo
                ? 'SELECT met.id_metrica, met.nombre, met.descripcion, met.tipo FROM metrica_proyecto_modelo mpm JOIN metrica met ON met.id_metrica = mpm.id_metrica WHERE mpm.id_proyecto_modelo IN (' . $inPM . ')'
                : 'SELECT met.id_metrica, met.nombre, met.descripcion FROM metrica_proyecto_modelo mpm JOIN metrica met ON met.id_metrica = mpm.id_metrica WHERE mpm.id_proyecto_modelo IN (' . $inPM . ')';
            if ($rsP = $cn->query($sqlMetP)) {
                while ($r = $rsP->fetch_assoc()) {
                    $px['metricas'][(int)$r['id_metrica']] = $r;
                }
            }
        }
        // Estado plan/ejec por proyecto (iteraciones del proyecto)
        if (!empty($px['metricas'])) {
            $idsM = implode(',', array_keys($px['metricas']));
            $sqlE = 'SELECT mi.id_metrica,
                            MAX(CASE WHEN mi.valor_planificado IS NOT NULL THEN 1 ELSE 0 END) tiene_plan,
                            MAX(CASE WHEN mi.valor_ejecutado IS NOT NULL THEN 1 ELSE 0 END) tiene_ejec
                     FROM metrica_iteracion mi
                     JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                     WHERE i.id_proyecto = ' . $idProyecto . ' AND mi.id_metrica IN (' . $idsM . ')
                     GROUP BY mi.id_metrica';
            $estado = [];
            if ($rsE = $cn->query($sqlE)) {
                while ($r = $rsE->fetch_assoc()) {
                    $estado[(int)$r['id_metrica']] = [
                        'plan' => ((int)$r['tiene_plan'] === 1),
                        'ejec' => ((int)$r['tiene_ejec'] === 1)
                    ];
                }
            }
            foreach ($px['metricas'] as $idm => &$mm) {
                $mm['_estado_plan'] = $estado[$idm]['plan'] ?? false;
                $mm['_estado_ejec'] = $estado[$idm]['ejec'] ?? false;
            }
            unset($mm);
        }
    }
    unset($px);
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
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            padding-left: 0;
            padding-right: 0;
        }
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background-color: #fff;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #212529;
        }
        .table-sm td,
        .table-sm th {
            vertical-align: middle;
        }

        /* Utilidad genérica para celdas con ellipsis + expand on hover */
        .cell-ellipsis {
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cell-ellipsis:hover {
            position: relative;
            white-space: normal;
            word-break: break-word;
            overflow: visible;
            z-index: 2;
            background: #f8f9fa;
            border-radius: .25rem;
            padding: .1rem .2rem;
        }
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
                                         <td class="cell-ellipsis" ><?= htmlspecialchars($m['nombre']); ?></td>
                                        <td class="cell-ellipsis"><?= htmlspecialchars($m['descripcion'] ?? ''); ?></td>
                                        <td><span class="badge badge-secondary">Base</span></td>
                                        <td>
                                            <a title="Ver" href="metrica.ver.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-primary btn-icon">
                                                <span class="oi oi-eye"></span>
                                            </a>
                                            <a class="btn btn-outline-warning btn-icon" title="Editar" href="metrica.modificar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                <span class="oi oi-pencil"></span>
                                            </a>
                                            <a class="btn btn-outline-danger btn-icon" title="Eliminar" href="metrica.eliminar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                <span class="oi oi-trash"></span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($proyectoContextoId > 0 && empty($proyectosAccesibles)): ?>
                        <div class="alert alert-warning">
                            No tenés acceso al proyecto seleccionado o no existe.
                            <a href="metricas.php" class="ml-2">Quitar filtro</a>
                        </div>
                    <?php elseif (empty($proyectosAccesibles) || $iteracionesUsuario === 0): ?>
                        <div class="card my-4 text-center" style="border:1px dashed rgba(23,162,184,0.15); background:rgba(23,162,184,0.03);">
                            <div class="card-body p-4">
                                <i class="oi oi-lock-locked mb-2" style="font-size:2rem; color:#dc3545;"></i>
                                <?php if (empty($modelosAccesibles)): ?>
                                    <h5 class="text-danger font-weight-bold mb-2">Necesitás un modelo de calidad</h5>
                                    <p class="text-muted mb-0">Primero seleccioná o creá un modelo de calidad en tu proyecto.</p>
                                <?php elseif ($iteracionesUsuario === 0): ?>
                                    <h5 class="text-danger font-weight-bold mb-2">Necesitás al menos una iteración</h5>
                                    <p class="text-muted mb-0">Creá una iteración antes de gestionar métricas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($proyectoContextoId > 0): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Planificación de métricas <small class="text-muted">(Proyecto filtrado)</small></h5>
                                <a href="metricas.php" class="btn btn-sm btn-outline-secondary" title="Ver todos los proyectos"><span class="oi oi-x"></span> Quitar filtro</a>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($proyectosAccesibles as $px): ?>
                            <div class="mb-3">
                                <h5 class="mb-2">Proyecto: <strong><?= htmlspecialchars($px['proyecto']); ?></strong> — Modelo: <span class="badge badge-info"><?= htmlspecialchars($px['modelo']); ?></span>
                                    <?php if ($proyectoContextoId > 0): ?>
                                        <span class="badge badge-secondary ml-1" title="Filtro activo">Filtrado</span>
                                    <?php endif; ?>
                                </h5>
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
                                                $puedeEditar = !$isBase && $tienePermGestionMetricas && !$estadoPlan;
                                                $puedeEliminar = $puedeEditar;
                                                $puedePlanificar = !$isBase && !$estadoPlan;
                                                $puedeEjecutar = !$isBase && $estadoPlan && !$estadoEjec;
                                            ?>
                                                <tr>
                                                    <td class="cell-ellipsis" title="<?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($m['nombre']); ?></td>
                                                    <td class="cell-ellipsis" title="<?= htmlspecialchars($m['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($m['descripcion'] ?? ''); ?></td>
                                                    <td><span class="badge badge-<?= $isBase ? 'secondary' : 'info'; ?>"><?= $isBase ? 'Base' : 'Personalizada'; ?></span></td>
                                                    <td>
                                                        <a title="Ver" href="metrica.ver.php?id=<?= (int)$m['id_metrica']; ?>" class="btn btn-outline-primary btn-icon"><span class="oi oi-eye"></span></a>
                                                        <?php if ($puedeEditar): ?>
                                                            <a class="btn btn-outline-warning btn-icon" title="Editar" href="metrica.modificar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                                <span class="oi oi-pencil"></span>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary btn-icon" disabled title="Editar no permitido"><span class="oi oi-lock-locked"></span></button>
                                                        <?php endif; ?>
                                                        <?php if ($puedeEliminar): ?>
                                                            <a class="btn btn-outline-danger btn-icon" title="Eliminar" href="metrica.eliminar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                                <span class="oi oi-trash"></span>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary btn-icon" disabled title="Eliminar no permitido"><span class="oi oi-lock-locked"></span></button>
                                                        <?php endif; ?>
                                                        <?php if ($puedePlanificar): ?>
                                                            <a class="btn btn-outline-info btn-icon" title="Planificar" href="metrica.planificar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                                <span class="oi oi-calendar"></span>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary btn-icon" disabled title="Planificar no disponible"><span class="oi oi-lock-locked"></span></button>
                                                        <?php endif; ?>
                                                        <?php if ($puedeEjecutar): ?>
                                                            <a class="btn btn-outline-success btn-icon" title="Registrar ejecución" href="metrica.ejecutar.php?id=<?= (int)$m['id_metrica']; ?>">
                                                                <span class="oi oi-play"></span>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary btn-icon" disabled title="Ejecución no disponible"><span class="oi oi-lock-locked"></span></button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>