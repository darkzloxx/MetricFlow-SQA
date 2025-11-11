<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();
$tienePermGestionModelo = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD);

// =====================================================
// 🔹 OBTENER PROYECTOS (solo si NO es admin/superadmin)
// =====================================================
if (!$esAdminGlobal && !$esSuperAdmin) {
    $sql = "SELECT p.id_proyecto, p.nombre, p.id_modelo_global, p.id_modelo_personalizado
            FROM usuario_proyecto up
            JOIN proyecto p ON p.id_proyecto = up.id_proyecto
            WHERE up.id_usuario = ?
            ORDER BY p.nombre";
    $stmt = $cn->prepare($sql);
    $stmt->bind_param('i', $usr->id);
    $stmt->execute();
    $stmt = $stmt->get_result();
    $proyectos = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $proyectos = [];
}

// =====================================================
// 🔹 OBTENER MODELOS GLOBALES (solo admin/superadmin)
// =====================================================
if ($esAdminGlobal || $esSuperAdmin) {
    $modelos = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
} else {
    $modelos = [];
}

// =====================================================
// 🔹 MAPA DE USO Y BLOQUEO (solo admin/superadmin)
// =====================================================
$usosModelos = [];
$modelosPlanificados = [];
$modelosPlanificadosProyectos = [];

if ($esAdminGlobal || $esSuperAdmin) {
    $rsUsos = $cn->query("SELECT p.id_modelo_global AS id_modelo, p.id_proyecto, p.nombre AS proyecto 
                           FROM proyecto p WHERE p.id_modelo_global IS NOT NULL");
    if ($rsUsos) {
        while ($row = $rsUsos->fetch_assoc()) {
            $mid = (int)$row['id_modelo'];
            if (!isset($usosModelos[$mid])) {
                $usosModelos[$mid] = [];
            }
            $usosModelos[$mid][] = ['id' => (int)$row['id_proyecto'], 'proyecto' => $row['proyecto']];
        }
    }

    if (!empty($usosModelos)) {
        $idsModelosUsados = array_keys($usosModelos);
        $inModelos = implode(',', $idsModelosUsados);
        $sqlPlan = "SELECT p.id_modelo_global AS id_modelo, p.nombre AS proyecto_nombre, COUNT(mi.id_metrica) AS c
                    FROM metrica_iteracion mi
                    JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                    JOIN proyecto p ON p.id_proyecto = i.id_proyecto
                    JOIN metrica_modelo_calidad mmc ON mmc.id_metrica = mi.id_metrica AND mmc.id_modelo = p.id_modelo_global
                    WHERE mi.valor_planificado IS NOT NULL AND p.id_modelo_global IN ($inModelos)
                    GROUP BY p.id_modelo_global, p.id_proyecto, proyecto_nombre";
        if ($rsPM = $cn->query($sqlPlan)) {
            while ($rw = $rsPM->fetch_assoc()) {
                $idm = (int)$rw['id_modelo'];
                $cnt = (int)$rw['c'];
                $nomProy = (string)$rw['proyecto_nombre'];
                if (!isset($modelosPlanificados[$idm])) {
                    $modelosPlanificados[$idm] = 0;
                }
                $modelosPlanificados[$idm] += $cnt;
                if (!isset($modelosPlanificadosProyectos[$idm])) {
                    $modelosPlanificadosProyectos[$idm] = [];
                }
                $modelosPlanificadosProyectos[$idm][] = $nomProy;
            }
        }
    }
}

// =====================================================
// 🔹 MAPA DE PLANIFICADOS (solo para vista de gerente/líder)
// =====================================================
$mapPlanificados = [];
$hayElegiblePersonalizado = false;
if (!empty($proyectos)) {
    $idsAll = array_column($proyectos, 'id_proyecto');
    $idsAll = array_filter($idsAll, fn($v) => $v > 0);
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
    foreach ($proyectos as $pp) {
        $pid = (int)$pp['id_proyecto'];
        if (empty($mapPlanificados[$pid])) {
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
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modelos</title>
    <style>
        .btn-icon {
            width: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .cell-ellipsis {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }


        /* Hover visual coherente en disabled */
        .btn.disabled,
        .btn:disabled {
            pointer-events: auto !important;
            opacity: 0.8;
            transition: all .2s;
        }

        .btn-outline-warning.disabled:hover,
        .btn-outline-warning:disabled:hover {
            background: #ffc107;
            color: #212529;
            border-color: #ffc107;
        }

        .btn-outline-danger.disabled:hover,
        .btn-outline-danger:disabled:hover {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }

        .btn-outline-secondary.disabled:hover,
        .btn-outline-secondary:disabled:hover {
            background: #6c757d;
            color: #fff;
            border-color: #6c757d;
        }

        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background: #fff;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            color: #212529;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a href="proyectos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Modelos de calidad</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <?php $flash = $_SESSION['flash_message'];
                    unset($_SESSION['flash_message']); ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($flash['text']); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($esAdminGlobal || $esSuperAdmin): ?>
                    <!-- ===========================================
                 🧩 VISTA ADMIN/SUPERADMIN: SOLO MODELOS GLOBALES
                 =========================================== -->
                    <p>
                        <a href="modelo.nuevo.predeterminado.php" class="btn btn-success">
                            <span class="oi oi-plus"></span> Nuevo Modelo Global
                        </a>
                    </p>

                    <?php if (empty($modelos)): ?>
                        <div class="text-muted">No hay modelos globales registrados.</div>
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
                                    $estaBloqueado = !empty($modelosPlanificados[$mid]);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold"><?= htmlspecialchars($m['nombre']); ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($m['descripcion']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($cant === 0): ?>
                                                <span class="badge badge-secondary">Ningún proyecto</span>
                                            <?php else: ?>
                                                <span class="badge badge-info"><?= $cant; ?> proyecto(s)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="modelo.catalogo.ver.php?id_modelo=<?= $mid; ?>" class="btn btn-outline-primary btn-icon" title="Ver modelo"><span class="oi oi-eye"></span></a>
                                            <?php if ($estaBloqueado): ?>
                                                <?php $tooltip = htmlspecialchars('No se puede editar/eliminar: modelo en uso con métricas planificadas.', ENT_QUOTES, 'UTF-8'); ?>
                                                <button class="btn btn-outline-warning btn-icon" disabled data-toggle="tooltip" title="<?= $tooltip; ?>"><span class="oi oi-lock-locked"></span></button>
                                                <button class="btn btn-outline-danger btn-icon" disabled data-toggle="tooltip" title="<?= $tooltip; ?>"><span class="oi oi-lock-locked"></span></button>
                                            <?php else: ?>
                                                <a href="modelo.modificar.php?id_modelo=<?= $mid; ?>" class="btn btn-outline-warning btn-icon" title="Editar modelo"><span class="oi oi-pencil"></span></a>
                                                <a href="modelo.eliminar.procesar.php?id_modelo=<?= $mid; ?>" class="btn btn-outline-danger btn-icon" title="Eliminar modelo" onclick="return confirm('¿Confirma que desea eliminar este modelo? Esta acción no se puede deshacer.');"><span class="oi oi-trash"></span></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- ===========================================
                 👤 VISTA GERENTE/LÍDER: MODELOS POR PROYECTO
                 =========================================== -->
                    <?php if (!$tienePermGestionModelo): ?>
                        <div class="alert alert-warning">No tenés permisos para gestionar modelos de calidad.</div>
                    <?php elseif (empty($proyectos)): ?>
                        <div class="alert alert-info">No tenés proyectos asignados.</div>
                    <?php else: ?>
                        <p>
                            <?php if ($hayElegiblePersonalizado): ?>
                                <a href="modelo.nuevo.php" class="btn btn-success"><span class="oi oi-plus"></span> Crear Personalizado/Asignar/Editar Modelo Base</a>
                            <?php endif; ?>
                        </p>

                        <table class="table table-hover table-sm">
                            <tr class="table-info">
                                <th>Proyecto</th>
                                <th>Modelo Actual</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                            <tbody>
                                <?php foreach ($proyectos as $p):
                                    $modeloGlobalId = (int)($p['id_modelo_global'] ?? 0);
                                    $modeloPersId   = (int)($p['id_modelo_personalizado'] ?? 0);
                                    $modeloNombre   = '—';
                                    $tipo           = '—';

                                    if ($modeloGlobalId > 0) {
                                        $r = $cn->query("SELECT nombre FROM modelo_calidad WHERE id_modelo = {$modeloGlobalId} LIMIT 1")->fetch_assoc();
                                        if ($r) {
                                            $modeloNombre = $r['nombre'];
                                            $tipo = 'Predeterminado';
                                        }
                                    } elseif ($modeloPersId > 0) {
                                        $r2 = $cn->query("SELECT nombre FROM proyecto_modelo_calidad WHERE id_proyecto_modelo = {$modeloPersId} LIMIT 1")->fetch_assoc();
                                        if ($r2) {
                                            $modeloNombre = $r2['nombre'];
                                            $tipo = 'Personalizado';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['nombre']); ?></td>
                                        <td><?= htmlspecialchars($modeloNombre); ?></td>
                                        <td><span class="badge badge-<?= $tipo === 'Predeterminado' ? 'secondary' : ($tipo === 'Personalizado' ? 'info' : 'light'); ?>"><?= $tipo; ?></span></td>
                                        <td>
                                            <a href="modelo.ver.php?id=<?= (int)$p['id_proyecto']; ?>" class="btn btn-outline-primary btn-icon" title="Ver detalles"><span class="oi oi-eye"></span></a>
                                            <?php if ($tipo === 'Predeterminado'): ?>
                                                <button class="btn btn-outline-warning btn-icon" disabled title="Los modelos globales no pueden modificarse.">
                                                    <span class="oi oi-lock-locked"></span>
                                                </button>
                                                <a href="#"
                                                    onclick="confirmarDesvinculacionModeloGlobal(<?= (int)$p['id_proyecto']; ?>, event)"
                                                    class="btn btn-outline-danger btn-icon"
                                                    title="Desvincular modelo predeterminado del proyecto">
                                                    <span class="oi oi-x"></span>
                                                </a>

                                            <?php elseif ($tipo === 'Personalizado'): ?>
                                                <a href="modelo.editar.personalizado.php?id=<?= $modeloPersId; ?>" class="btn btn-outline-warning btn-icon" title="Editar modelo personalizado"><span class="oi oi-pencil"></span></a>
                                                <a href="#" onclick="confirmarEliminacionPersonalizado(<?= (int)$modeloPersId; ?>, event)"
                                                    class="btn btn-outline-danger btn-icon"
                                                    title="Eliminar modelo personalizado">
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
    <script>
        $(function() {
            $('[data-toggle="tooltip"]').tooltip();

            // 🔹 Cierra automáticamente el flash después de 2 segundos
            setTimeout(() => {
                $('.alert').alert('close');
            }, 2000);
        });

        function confirmarEliminacionPersonalizado(idModelo, e) {
            if (e) e.preventDefault(); // evita que el link recargue la página

            if (!confirm('⚠️ ¿Confirma que desea eliminar este modelo personalizado y sus métricas asociadas? Esta acción no se puede deshacer.')) {
                return;
            }

            $.ajax({
                url: 'modelo.eliminar.personalizado.procesar.php',
                type: 'POST',
                data: {
                    id: idModelo,
                    ajax: true
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) {
                        alert('✅ ' + resp.message);
                        window.location.reload();
                    } else {
                        alert('❌ ' + (resp.error || 'Error al eliminar el modelo.'));
                    }
                },
                error: function(xhr) {
                    console.error("Respuesta servidor:", xhr.responseText);
                    alert('⚠️ Error de comunicación con el servidor.');
                }
            });
        }

        function confirmarDesvinculacionModeloGlobal(idProyecto, e) {
            if (e) e.preventDefault();

            if (!confirm('⚠️ ¿Confirma que desea desvincular el modelo predeterminado de este proyecto?\n\nNo se eliminará el modelo, solo se quitará la asignación.')) {
                return;
            }

            $.ajax({
                url: 'modelo.desvincular.global.procesar.php',
                type: 'POST',
                data: {
                    id_proyecto: idProyecto,
                    ajax: true
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) {
                        alert('✅ ' + resp.message);
                        window.location.reload();
                    } else {
                        alert('❌ ' + (resp.error || 'Error al desvincular el modelo.'));
                    }
                },
                error: function(xhr) {
                    console.error("Respuesta servidor:", xhr.responseText);
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        alert('❌ ' + (resp.error || 'Error en el servidor.'));
                    } catch (e) {
                        alert('⚠️ Error de comunicación con el servidor.');
                    }
                }
            });
        }
    </script>
</body>

</html>