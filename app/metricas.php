<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);

if (!($esSuperAdmin || $esAdminGlobal || $tienePermGestionMetricas)) {
    header('Location: ../app/menu.php?msg=' . urlencode('Acceso denegado.') . '&type=danger');
    exit;
}

$cn = BDConexion::getInstancia();
?>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Métricas</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

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

        .cell-ellipsis.large {
            max-width: 520px;
        }

        .cell-ellipsis:hover {
            white-space: normal;
            word-break: break-word;
            background: #f8f9fa;
            border-radius: .25rem;
            padding: .2rem .4rem;
            z-index: 4;
            position: relative;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-3">
        <div class="mb-3">
            <a href="proyectos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div id="flash-alert" class="alert alert-<?= ($_GET['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <script>
                setTimeout(() => $('#flash-alert').alert('close'), 3000);
            </script>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Gestión de Métricas</h3>
            </div>
            <div class="card-body">
                <?php if ($tienePermGestionMetricas): ?>
                    <a href="metrica.nueva.php" class="btn btn-success">
                        <span class="oi oi-plus"></span> Nueva Métrica
                    </a>
                <?php endif; ?>
                <br><br>

                <?php
                /* ==========================================================
               🧑‍💼 ADMIN / SUPERADMIN → Métricas base globales
            ========================================================== */
                if ($esAdminGlobal || $esSuperAdmin):
                    $sql = "SELECT id_metrica, nombre, descripcion FROM metrica WHERE tipo='base' ORDER BY nombre ASC";
                    $rs = $cn->query($sql);
                    $metricas = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
                ?>
                    <h5 class="mb-3 text-secondary">Métricas base globales</h5>
                    <?php if (empty($metricas)): ?>
                        <div class="text-muted">No hay métricas base registradas en el sistema.</div>
                    <?php else: ?>
                        <table class="table table-hover table-sm">
                            <tr class="table-info">
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Opciones</th>
                            </tr>
                            <tbody>
                                <?php foreach ($metricas as $m): ?>
                                    <tr>
                                        <td class="font-weight-bold cell-ellipsis"><?= htmlspecialchars($m['nombre']); ?></td>
                                        <td class="cell-ellipsis large"><?= htmlspecialchars($m['descripcion']); ?></td>
                                        <td>
                                            <a href="metrica.ver.php?id=<?= $m['id_metrica']; ?>" class="btn btn-outline-primary" title="Ver">
                                                <span class="oi oi-eye"></span>
                                            </a>
                                            <form action="metrica.eliminar.procesar.php" method="post" style="display:inline-block;"
                                                onsubmit="return confirm('¿Confirma eliminar esta métrica base global?');">
                                                <input type="hidden" name="id" value="<?= $m['id_metrica']; ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                    <span class="oi oi-trash"></span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php
                /* ==========================================================
               👷‍♂️ LÍDER / GERENTE → Proyectos asignados y sus modelos
            ========================================================== */
                else:
                    $idUsuario = (int)$usr->id;
                    $sqlProyectos = "
                    SELECT p.id_proyecto, p.nombre AS proyecto,
                           COALESCE(mg.id_modelo, pmc.id_proyecto_modelo) AS id_modelo,
                           COALESCE(mg.nombre, pmc.nombre) AS modelo,
                           CASE WHEN mg.id_modelo IS NOT NULL THEN 'global' ELSE 'personalizado' END AS tipo_modelo
                    FROM proyecto p
                    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
                    LEFT JOIN modelo_calidad mg ON mg.id_modelo = p.id_modelo_global
                    LEFT JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = p.id_modelo_personalizado
                    WHERE up.id_usuario = {$idUsuario}
                    ORDER BY p.nombre ASC";
                    $resProy = $cn->query($sqlProyectos);

                    if (!$resProy || $resProy->num_rows === 0): ?>
                        <div class="alert alert-warning">
                            <span class="oi oi-warning"></span> No tenés proyectos con modelos de calidad asignados.
                        </div>
                        <?php else:
                        while ($proy = $resProy->fetch_assoc()):
                            $idModelo = (int)$proy['id_modelo'];
                            $tipoModelo = $proy['tipo_modelo'];
                            $nombreModelo = htmlspecialchars($proy['modelo'] ?? 'Sin modelo');
                            $nombreProyecto = htmlspecialchars($proy['proyecto']);

                            $rolProyecto = strtolower(trim(ControlAcceso::rolUsuarioEnProyecto($proy['id_proyecto']) ?? ''));
                            $esGerenteOLider = in_array($rolProyecto, [
                                'gerente',
                                'gerente de calidad',
                                'líder',
                                'líder de proyecto',
                                'lider de proyecto'
                            ], true);

                            // 🔹 Iteración actual
                            $iter = null;
                            $sqlIter = "
                            SELECT i.id_iteracion, i.numero_iteracion, i.fecha_inicio, i.fecha_fin, f.nombre AS fase
                            FROM iteracion i
                            JOIN fase f ON f.id_fase = i.id_fase
                            WHERE i.id_proyecto = {$proy['id_proyecto']}
                              AND CURRENT_DATE() BETWEEN i.fecha_inicio AND i.fecha_fin
                            LIMIT 1";
                            $rsIter = $cn->query($sqlIter);
                            if ($rsIter && $rsIter->num_rows > 0) {
                                $iter = $rsIter->fetch_assoc();
                            }

                            // 🔹 Métricas del modelo
                            $sqlM = ($tipoModelo === 'global') ? "
                            SELECT m.id_metrica, m.nombre, m.descripcion, m.tipo
                            FROM metrica_modelo_calidad mmc
                            JOIN metrica m ON m.id_metrica = mmc.id_metrica
                            WHERE mmc.id_modelo = {$idModelo}
                            ORDER BY m.nombre ASC" : "
                            SELECT m.id_metrica, m.nombre, m.descripcion, m.tipo
                            FROM metrica_proyecto_modelo mpm
                            JOIN metrica m ON m.id_metrica = mpm.id_metrica
                            WHERE mpm.id_proyecto_modelo = {$idModelo}
                            ORDER BY m.nombre ASC";
                            $resM = $cn->query($sqlM);
                            $metricas = $resM ? $resM->fetch_all(MYSQLI_ASSOC) : [];
                        ?>
                            <div class="alert alert-info mb-2">
                                <h5 class="mb-0 font-weight-bold"><?= $nombreProyecto; ?></h5>
                                <small>
                                    Modelo: <strong><?= $nombreModelo; ?></strong> (<?= ucfirst($tipoModelo); ?>)
                                    <br>
                                    <?php if ($iter): ?>
                                        Iteración actual:
                                        <strong><?= htmlspecialchars($iter['fase']); ?> <?= (int)$iter['numero_iteracion']; ?></strong>
                                        (<?= htmlspecialchars($iter['fecha_inicio']); ?> a <?= htmlspecialchars($iter['fecha_fin']); ?>)
                                    <?php else: ?>
                                        <span class="text-danger">Sin iteración activa actualmente</span>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <?php if (empty($metricas)): ?>
                                <div class="text-muted mb-4">No hay métricas definidas para este modelo.</div>
                            <?php else: ?>
                                <table class="table table-hover table-sm">
                                    <tr class="table-info">
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Tipo</th>
                                        <?php if ($esGerenteOLider): ?>
                                            <th class="text-center">Planificado</th>
                                            <th class="text-center">Ejecutado</th>
                                        <?php endif; ?>
                                        <th>Opciones</th>
                                    </tr>
                                    <tbody>
                                        <?php foreach ($metricas as $m):
                                            $idM = (int)$m['id_metrica'];
                                            $esBase = ($m['tipo'] === 'base');

                                            $valorPlan = null;
                                            $valorEjec = null;
                                            $yaPlanificada = false;

                                            // 🔹 Si hay iteración activa, buscamos valores
                                            if ($iter) {
                                                $sqlPlan = "SELECT valor_planificado, valor_ejecutado 
                            FROM metrica_iteracion 
                            WHERE id_metrica={$idM} 
                              AND id_iteracion={$iter['id_iteracion']} 
                            LIMIT 1";
                                                $rsPlan = $cn->query($sqlPlan);
                                                if ($rsPlan && $rsPlan->num_rows > 0) {
                                                    $rowPlan = $rsPlan->fetch_assoc();
                                                    $valorPlan = $rowPlan['valor_planificado'];
                                                    $valorEjec = $rowPlan['valor_ejecutado'];
                                                    $yaPlanificada = true;
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td class="font-weight-bold cell-ellipsis"><?= htmlspecialchars($m['nombre']); ?></td>
                                                <td class="cell-ellipsis large"><?= htmlspecialchars($m['descripcion']); ?></td>
                                                <td><span class="badge badge-<?= $esBase ? 'secondary' : 'info'; ?>"><?= ucfirst($m['tipo']); ?></span></td>

                                                <?php if ($esGerenteOLider): ?>
                                                    <td class="text-center">
                                                        <?= $valorPlan !== null
                                                            ? htmlspecialchars($valorPlan)
                                                            : '<span class="text-muted small">-</span>'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= $valorEjec !== null
                                                            ? htmlspecialchars($valorEjec)
                                                            : '<span class="text-muted small">-</span>'; ?>
                                                    </td>
                                                <?php endif; ?>

                                                <td>
                                                    <a href="metrica.ver.php?id=<?= $idM; ?>" class="btn btn-outline-primary btn-icon" title="Ver">
                                                        <span class="oi oi-eye"></span>
                                                    </a>

                                                    <?php if ($esGerenteOLider): ?>
                                                        <?php if (!$iter): ?>
                                                            <button class="btn btn-outline-secondary btn-icon" disabled title="No hay una iteración activa para planificar">
                                                                <span class="oi oi-lock-locked"></span>
                                                            </button>
                                                        <?php elseif (!$yaPlanificada): ?>
                                                            <a href="metrica.planificar.php?id=<?= $idM; ?>&proyecto=<?= $proy['id_proyecto']; ?>"
                                                                class="btn btn-outline-success btn-icon"
                                                                title="Planificar métrica (registrar valor planificado)">
                                                                <span class="oi oi-spreadsheet"></span>
                                                            </a>

                                                        <?php else: ?>
                                                            <button class="btn btn-outline-success btn-icon" disabled title="Métrica ya planificada en la iteración actual">
                                                                <span class="oi oi-lock-locked"></span>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($iter && $yaPlanificada): ?>
                                                            <a href="metrica.ejecutar.php?id=<?= $idM; ?>&proyecto=<?= $proy['id_proyecto']; ?>"
                                                                class="btn btn-outline-info btn-icon"
                                                                title="Ejecutar métrica (registrar valor real)">
                                                                <span class="oi oi-play-circle"></span>
                                                            </a>

                                                        <?php else: ?>
                                                            <button class="btn btn-outline-info btn-icon" disabled title="<?= !$iter ? 'Debe existir una iteración activa' : 'Debe planificarse antes de ejecutar'; ?>">
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
                <?php endwhile;
                    endif;
                endif; ?>
            </div>
        </div>
    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>