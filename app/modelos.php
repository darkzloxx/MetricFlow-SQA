<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();
// Permiso requerido para gestionar/visualizar modelos (para no-admin)
$tienePermGestionModelo = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD);

// =====================================================
// OBTENER PROYECTOS ASIGNADOS (o todos si es admin/superadmin)
// =====================================================
if ($esAdminGlobal || $esSuperAdmin) {
    $sql = "SELECT id_proyecto, nombre, id_modelo FROM proyecto ORDER BY nombre";
    $stmt = $cn->query($sql);
} else {
    $sql = "SELECT p.id_proyecto, p.nombre, p.id_modelo 
            FROM usuario_proyecto up
            JOIN proyecto p ON p.id_proyecto = up.id_proyecto
            WHERE up.id_usuario = ?
            ORDER BY p.nombre";
    $stmt = $cn->prepare($sql);
    $stmt->bind_param('i', $usr->id);
    $stmt->execute();
    $stmt = $stmt->get_result();
}
$proyectos = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];

// =====================================================
// PROYECTOS BLOQUEADOS: tienen métricas planificadas en alguna iteración
// No se permite cambiar modelo ni crear/asignar uno nuevo a esos proyectos
// =====================================================
$bloqueados = [];
if (!empty($proyectos)) {
    $ids = array_map(function ($r) {
        return (int)$r['id_proyecto'];
    }, $proyectos);
    $ids = array_filter($ids, function ($v) {
        return $v > 0;
    });
    if (!empty($ids)) {//aca se obtienen los proyectos bloqueados porque tienen metricas planificadas
        $in = implode(',', $ids);
        $sqlB = "SELECT DISTINCT i.id_proyecto AS id
                 FROM metrica_iteracion mi
                 JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                 WHERE i.id_proyecto IN ($in)";
        if ($rsB = $cn->query($sqlB)) {
            while ($row = $rsB->fetch_assoc()) {
                $bloqueados[(int)$row['id']] = true;
            }
        }
    }
}

// =====================================================
// OBTENER MODELOS DISPONIBLES
// =====================================================
$modelos = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
// Mapear usos de modelos por proyectos
$usosModelos = [];
$rsUsos = $cn->query("SELECT p.id_modelo, p.id_proyecto, p.nombre AS proyecto FROM proyecto p WHERE p.id_modelo IS NOT NULL");
if ($rsUsos) {
    while ($row = $rsUsos->fetch_assoc()) {
        $mid = (int)$row['id_modelo'];
        if (!isset($usosModelos[$mid])) {
            $usosModelos[$mid] = [];
        }
        $usosModelos[$mid][] = ['id' => (int)$row['id_proyecto'], 'proyecto' => $row['proyecto']];
    }
}

// =====================================================
// MODELOS CON MÉTRICAS PLANIFICADAS (valor_planificado NO NULL) EN ALGUNO DE SUS PROYECTOS
// Bloquea edición/eliminación SOLO si hay métricas del modelo con planificaciones.
// =====================================================
$modelosPlanificados = [];              // id_modelo => cantidad total de métricas planificadas (suma)
$modelosPlanificadosProyectos = [];     // id_modelo => array de nombres de proyectos donde hay al menos una métrica del modelo planificada
if (!empty($usosModelos)) {
    // Construir lista de ids de modelo usados
    $idsModelosUsados = array_keys($usosModelos);
    $idsModelosUsados = array_filter($idsModelosUsados, function($v){ return (int)$v > 0; });
    if (!empty($idsModelosUsados)) {
        $inModelos = implode(',', $idsModelosUsados);
        // Consulta: métricas planificadas (valor_planificado NO NULL) que pertenecen al modelo (metrica_modelo_calidad) y están planificadas en iteraciones de proyectos que usan ese modelo
        $sqlPlanMod = "SELECT p.id_modelo, p.nombre AS proyecto_nombre, COUNT(mi.id_metrica) AS c
                       FROM metrica_iteracion mi
                       JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                       JOIN proyecto p ON p.id_proyecto = i.id_proyecto
                       JOIN metrica_modelo_calidad mmc ON mmc.id_metrica = mi.id_metrica AND mmc.id_modelo = p.id_modelo
                       WHERE mi.valor_planificado IS NOT NULL AND p.id_modelo IN ($inModelos)
                       GROUP BY p.id_modelo, p.id_proyecto, proyecto_nombre";
        if ($rsPM = $cn->query($sqlPlanMod)) {
            while ($rw = $rsPM->fetch_assoc()) {
                $idm = (int)$rw['id_modelo'];
                $cnt = (int)$rw['c'];
                $nomProy = (string)$rw['proyecto_nombre'];
                if (!isset($modelosPlanificados[$idm])) { $modelosPlanificados[$idm] = 0; }
                $modelosPlanificados[$idm] += $cnt; // suma total de métricas planificadas
                if (!isset($modelosPlanificadosProyectos[$idm])) { $modelosPlanificadosProyectos[$idm] = []; }
                $modelosPlanificadosProyectos[$idm][] = $nomProy; // almacenar nombre (se controla duplicado por GROUP BY p.id_proyecto)
            }
        }
    }
}

// =====================================================
// MAPA: proyectos con métricas planificadas (valor_planificado NO NULL)
// y flag para mostrar/ocultar botón 'Nuevo Modelo Personalizado'
// =====================================================
$mapPlanificados = [];
$hayElegiblePersonalizado = false;
if (!empty($proyectos)) {
    $idsAll = array_map(function ($r) {
        return (int)$r['id_proyecto'];
    }, $proyectos);
    $idsAll = array_filter($idsAll, function ($v) {
        return $v > 0;
    });
    if (!empty($idsAll)) {
        $inAll = implode(',', $idsAll);
        $sqlPlan = "SELECT i.id_proyecto AS id, COUNT(*) AS c
                     FROM metrica_iteracion mi
                     JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                     WHERE mi.valor_planificado IS NOT NULL AND i.id_proyecto IN ($inAll)
                     GROUP BY i.id_proyecto";
        if ($rsPlan = $cn->query($sqlPlan)) {
            while ($row = $rsPlan->fetch_assoc()) {
                $mapPlanificados[(int)$row['id']] = (int)$row['c'];
            }
        }
    }
    // Elegible si el proyecto NO tiene métricas planificadas (independiente de si tiene modelo o no)
    foreach ($proyectos as $pp) {
        $pid = (int)$pp['id_proyecto'];
        $hasPlanned = !empty($mapPlanificados[$pid]);
        if (!$hasPlanned) {
            $hayElegiblePersonalizado = true;
            break;
        }
    }
}
?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modelos</title>
    <style>
          .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background-color: #fff;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #212529;
        }

        /* celdas largas con ellipsis + expand hover */
        .cell-ellipsis {
            max-width: 240px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cell-ellipsis.large {
            max-width: 520px;
        }


        .badge-list .badge {
            max-width: 140px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge-list .badge:hover {
            position: relative;
            white-space: normal;
            word-break: break-word;
            overflow: visible;
            z-index: 4;
        }

        /* Asegurar mismo ancho para botones con solo ícono */
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            padding-left: 0;
            padding-right: 0;
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

        <!-- 🔔 ALERTAS -->
        <div id="alertContainer">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-<?= ($_GET['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['msg']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <script>
                    $('html, body').animate({
                        scrollTop: 0
                    }, 'fast');
                    setTimeout(() => $('.alert').alert('close'), 3000);
                </script>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header">
                <h3>Modelos de calidad</h3>
            </div>
            <div class="card-body">
                <!-- Botón Nuevo Modelo -->
                <p>
                    <?php if ($esAdminGlobal || $esSuperAdmin): ?>
                        <a href="modelo.nuevo.predeterminado.php">
                            <button type="button" class="btn btn-success">
                                <span class="oi oi-plus"></span> Nuevo Modelo Predeterminado
                            </button>
                        </a>
                    <?php else: ?>
                        <?php if ($tienePermGestionModelo && $hayElegiblePersonalizado): ?>
                            <a href="modelo.nuevo.php" class="btn btn-success" title="Crear modelo personalizado">
                                <span class="oi oi-plus"></span> Nuevo Modelo Personalizado
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>

                <?php if (!($esAdminGlobal || $esSuperAdmin)): ?>
                    <!-- Vista para no admin: requiere permiso de gestión de modelo y tener proyectos asignados -->
                    <?php if (!$tienePermGestionModelo): ?>
                        <div class="card my-4 text-center" style="border:1px dashed rgba(220,53,69,0.15); background:rgba(220,53,69,0.03);">
                            <div class="card-body p-4">
                                <i class="oi oi-lock-locked mb-2" style="font-size:2rem; color:#dc3545;"></i>
                                <h5 class="text-danger font-weight-bold mb-2">No tenés permisos para gestionar modelos</h5>
                                <p class="text-muted mb-0">Solicitá el permiso "<?= htmlspecialchars(PermisosSistema::GESTION_MODELO_CALIDAD); ?>" a un administrador si necesitás acceso.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if (empty($proyectos)): ?>
                            <div class="card my-4 text-center" style="border:1px dashed rgba(23,162,184,0.15); background:rgba(23,162,184,0.03);">
                                <div class="card-body p-4">
                                    <i class="oi oi-info mb-2" style="font-size:2rem; color:#17a2b8;"></i>
                                    <h5 class="text-info font-weight-bold mb-2">No tenés proyectos asignados</h5>
                                    <p class="text-muted mb-3">Aún no fuiste asignado a ningún proyecto. Si creés que esto es un error, contactá a un administrador.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <table class="table table-hover table-sm">
                                <tr class="table-info">
                                    <th>Proyecto</th>
                                    <th>Modelo Actual</th>
                                    <th>Tipo</th>
                                    <th>Opciones</th>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($proyectos as $p):
                                            $modeloSel = (int)($p['id_modelo'] ?? 0);
                                            $modeloNombre = '—';
                                            $tipo = '—';
                                            if ($modeloSel) {
                                                $r = $cn->query("SELECT nombre, descripcion FROM modelo_calidad WHERE id_modelo=" . $modeloSel . " LIMIT 1")->fetch_assoc();
                                                $modeloNombre = $r ? $r['nombre'] : '—';
                                                // Si en el futuro hay una columna/flag para predeterminados, puede usarse aquí.
                                                $tipo = 'Predeterminado';
                                            }
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($p['nombre']); ?></td>
                                                <td><?= htmlspecialchars($modeloNombre); ?></td>
                                                <td><span class="badge badge-<?= $tipo === 'Predeterminado' ? 'secondary' : 'info'; ?>"><?= $tipo; ?></span></td>
                                                <td>
                                                    <!-- Ver métricas -->
                                                    <a title="Ver detalles" href="modelo.ver.php?id=<?= (int)$p['id_proyecto']; ?>" class="btn btn-outline-primary btn-icon">
                                                        <span class="oi oi-eye" aria-hidden="true"></span>
                                                    </a>

                                                    <?php if ($modeloSel): ?>
                                                        <?php
                                                            // Para usuarios no admin, mostrar candados explicando que sólo Admin/SuperAdmin pueden editar/eliminar modelos predeterminados
                                                            $tooltipPerm = htmlspecialchars('Solo Administrador/SuperAdmin puede editar o eliminar modelos predeterminados.', ENT_QUOTES, 'UTF-8');
                                                        ?>
                                                        <button class="btn btn-outline-warning btn-icon btn-locked" disabled data-toggle="tooltip" data-html="true" title="<?= $tooltipPerm; ?>" aria-label="Editar bloqueado">
                                                            <span class="oi oi-lock-locked" aria-hidden="true"></span>
                                                        </button>
                                                        <button class="btn btn-outline-danger btn-icon btn-locked" disabled data-toggle="tooltip" data-html="true" title="<?= $tooltipPerm; ?>" aria-label="Eliminar bloqueado">
                                                            <span class="oi oi-lock-locked" aria-hidden="true"></span>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($esAdminGlobal || $esSuperAdmin): ?>
                    <hr />
                    <?php if (empty($modelos)): ?>
                        <div class="text-muted">No hay modelos registrados.</div>
                    <?php else: ?>
                        <table class="table table-hover table-sm">
                            <tr class="table-info">
                                <th>Modelo</th>
                                <th>Usado por</th>
                                <th>Acciones</th>
                            </tr>
                            <tbody>
                                <?php foreach ($modelos as $m):
                                    $mid = (int)$m['id_modelo'];
                                    $usos = $usosModelos[$mid] ?? [];
                                    $cant = count($usos);
                                    $collapseId = 'usos-' . $mid;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold cell-ellipsis" title="<?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($m['nombre']); ?></div>
                                            <div class="text-muted small cell-ellipsis" title="<?= htmlspecialchars($m['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($m['descripcion'] ?? ''); ?></div>
                                        </td>
                                        <td class="cell-ellipsis large" title="<?= $cant > 0 ? htmlspecialchars($cant . ' proyecto(s)', ENT_QUOTES, 'UTF-8') : 'Sin uso'; ?>">
                                            <?php if ($cant === 0): ?>
                                                <span class="badge badge-secondary">Nadie</span>
                                            <?php elseif ($cant === 1): ?>
                                                <?= htmlspecialchars($usos[0]['proyecto']); ?>
                                            <?php else: ?>
                                                <span class="badge badge-info mr-2"><?= $cant; ?> proyectos</span>
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#<?= $collapseId; ?>" aria-expanded="false" aria-controls="<?= $collapseId; ?>">Ver lista</button>
                                                <div class="collapse mt-2 badge-list" id="<?= $collapseId; ?>">
                                                    <?php foreach ($usos as $u): ?>
                                                        <span class="badge badge-light mr-1 mb-1" title="ID <?= (int)$u['id']; ?> | <?= htmlspecialchars($u['proyecto'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($u['proyecto']); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Ver (siempre disponible) -->
                                            <a title="Ver modelo" href="modelo.catalogo.ver.php?id_modelo=<?= $mid; ?>" class="btn btn-outline-primary btn-icon">
                                                <span class="oi oi-eye" aria-hidden="true"></span>
                                            </a>
                                            <!-- Acciones: bloquear SOLO si existen métricas planificadas del modelo en algún proyecto -->
                                            <?php $estaBloqueado = !empty($modelosPlanificados[$mid]); ?>
                                            <?php if ($estaBloqueado): ?>
                                                <?php
                                                    $proysBloq = $modelosPlanificadosProyectos[$mid] ?? [];
                                                    $cantProysBloq = count($proysBloq);
                                                    $tituloBloq = 'No se puede editar ni eliminar porque uno o más proyectos tienen métricas de este modelo con valores planificados.';
                                                    $tooltipBloq = htmlspecialchars($tituloBloq, ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <button class="btn btn-outline-warning btn-icon btn-locked" disabled data-toggle="tooltip" data-html="true" title="<?= $tooltipBloq; ?>" aria-label="Editar bloqueado">
                                                    <span class="oi oi-lock-locked" aria-hidden="true"></span>
                                                </button>
                                                <button class="btn btn-outline-danger btn-icon btn-locked" disabled data-toggle="tooltip" data-html="true" title="<?= $tooltipBloq; ?>" aria-label="Eliminar bloqueado">
                                                    <span class="oi oi-lock-locked" aria-hidden="true"></span>
                                                </button>
                                            <?php else: ?>
                                                <a class="btn btn-outline-warning btn-icon" title="Editar modelo" href="modelo.modificar.php?id_modelo=<?= $mid; ?>">
                                                    <span class="oi oi-pencil"></span>
                                                </a>
                                                <a class="btn btn-outline-danger btn-icon" title="Eliminar modelo" href="modelo.eliminar.procesar.php?id_modelo=<?= $mid; ?>" onclick="return confirm('¿Confirma que desea eliminar este modelo? Se eliminarán solo las métricas asociadas exclusivamente a este modelo (las que no estén vinculadas a ningún otro). Esta acción no se puede deshacer.');">
                                                    <span class="oi oi-trash"></span>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>
<script>
// Inicializar tooltips para botones bloqueados
$(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>