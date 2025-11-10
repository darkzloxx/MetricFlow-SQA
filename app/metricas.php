<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);
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

        /* Texto truncado y expansión visual */
        .cell-ellipsis {
            max-width: 240px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all .2s ease-in-out;
        }

        .cell-ellipsis.large {
            max-width: 520px;
        }

        .cell-ellipsis:hover {
            position: relative;
            white-space: normal;
            word-break: break-word;
            overflow: visible;
            z-index: 4;
            background: #f8f9fa;
            border-radius: .25rem;
            padding: .2rem .4rem;
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

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Gestión de Métricas</h3>
                <?php if ($tienePermGestionMetricas): ?>
                    <a href="metrica.crear.php" class="btn btn-success btn-sm">
                        <span class="oi oi-plus"></span> Agregar Métrica
                    </a>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php
                if ($esAdminGlobal || $esSuperAdmin) {
                    // Admin/SuperAdmin → listado global (solo Ver; no editar/eliminar personalizadas aquí)
                    $sql = "SELECT id_metrica, nombre, descripcion, tipo FROM metrica ORDER BY nombre ASC";
                    $rs = $cn->query($sql);
                    $metricas = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];

                    if (empty($metricas)): ?>
                        <div class="text-muted">No hay métricas registradas.</div>
                    <?php else: ?>
                        <table class="table table-hover table-sm">
                            <tr class="table-info">
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Tipo</th>
                                <th>Opciones</th>
                            </tr>
                            <tbody>
                                <?php foreach ($metricas as $m):
                                    $id  = (int)$m['id_metrica'];
                                    $tipo = htmlspecialchars($m['tipo']);
                                    $esBase = ($tipo === 'base');
                                ?>
                                    <tr>
                                        <td class="font-weight-bold cell-ellipsis"><?= htmlspecialchars($m['nombre']); ?></td>
                                        <td class="cell-ellipsis large"><?= htmlspecialchars($m['descripcion']); ?></td>
                                        <td>
                                            <span class="badge badge-<?= $esBase ? 'secondary' : 'info'; ?>">
                                                <?= ucfirst($tipo); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Admin/SuperAdmin: solo Ver en esta vista -->
                                            <a href="metrica.ver.php?id=<?= $id; ?>" class="btn btn-outline-primary btn-sm" title="Ver">
                                                <span class="oi oi-eye"></span>
                                            </a>
                                            <?php if ($esBase): ?>
                                                <button class="btn btn-outline-warning btn-sm" disabled title="Métrica base (bloqueado)">
                                                    <span class="oi oi-lock-locked"></span>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" disabled title="Métrica base (bloqueado)">
                                                    <span class="oi oi-lock-locked"></span>
                                                </button>
                                            <?php else: ?>
                                                <!-- Personalizadas: no editar/eliminar aquí; lo hacen líderes/gerentes en sus proyectos -->
                                                <button class="btn btn-outline-secondary btn-sm" disabled title="Edición solo desde proyecto">
                                                    <span class="oi oi-ban"></span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif;
                } else {
                    // Usuarios comunes → métricas agrupadas por proyecto y modelo
                    $idUsuario = (int)$usr->id;

                    $sqlProyectos = "
                SELECT DISTINCT
                    p.id_proyecto,
                    p.nombre AS proyecto,
                    COALESCE(mg.nombre, pmc.nombre) AS modelo,
                    CASE WHEN mg.id_modelo IS NOT NULL THEN 'global' ELSE 'personalizado' END AS tipo_modelo,
                    COALESCE(mg.id_modelo, pmc.id_proyecto_modelo) AS id_modelo
                FROM proyecto p
                JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
                LEFT JOIN modelo_calidad mg ON mg.id_modelo = p.id_modelo_global
                LEFT JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = p.id_modelo_personalizado
                WHERE up.id_usuario = {$idUsuario}
                ORDER BY p.nombre ASC
            ";

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
                        ?>
                            <!-- Banner del proyecto -->
                            <div class="alert alert-info mb-2">
                                <h5 class="mb-0 font-weight-bold"><?= $nombreProyecto; ?></h5>
                                <small>Modelo: <strong><?= $nombreModelo; ?></strong> (<?= ucfirst($tipoModelo); ?>)</small>
                            </div>

                            <?php
                            // Cargar métricas del modelo actual
                            if ($tipoModelo === 'global') {
                                $sqlM = "
                        SELECT m.id_metrica, m.nombre, m.descripcion, m.tipo
                        FROM metrica_modelo_calidad mmc
                        JOIN metrica m ON m.id_metrica = mmc.id_metrica
                        WHERE mmc.id_modelo = {$idModelo}
                        ORDER BY m.nombre ASC
                    ";
                            } else { // personalizado
                                $sqlM = "
                        SELECT m.id_metrica, m.nombre, m.descripcion, m.tipo
                        FROM metrica_proyecto_modelo mpm
                        JOIN metrica m ON m.id_metrica = mpm.id_metrica
                        WHERE mpm.id_proyecto_modelo = {$idModelo}
                        ORDER BY m.nombre ASC
                    ";
                            }

                            $resM = $cn->query($sqlM);
                            $metricas = $resM ? $resM->fetch_all(MYSQLI_ASSOC) : [];

                            if (empty($metricas)): ?>
                                <div class="text-muted mb-4">No hay métricas definidas para este modelo.</div>
                            <?php else: ?>
                                <table class="table table-hover table-sm">
                                    <tr class="table-info">
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Tipo</th>
                                        <th>Opciones</th>
                                    </tr>
                                    <tbody>
                                        <?php foreach ($metricas as $m):
                                            $idM = (int)$m['id_metrica'];
                                            $tipoM = htmlspecialchars($m['tipo']);
                                            $esBase = ($tipoM === 'base');
                                        ?>
                                            <tr>
                                                <td class="font-weight-bold cell-ellipsis"><?= htmlspecialchars($m['nombre']); ?></td>
                                                <td class="cell-ellipsis large"><?= htmlspecialchars($m['descripcion']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $esBase ? 'secondary' : 'info'; ?>">
                                                        <?= ucfirst($tipoM); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <!-- Ver (siempre) -->
                                                    <a href="metrica.ver.php?id=<?= $idM; ?>" class="btn btn-outline-primary btn-icon" title="Ver">
                                                        <span class="oi oi-eye"></span>
                                                    </a>

                                                    <?php if ($tipoModelo === 'personalizado' && !$esBase && $tienePermGestionMetricas): ?>
                                                        <!-- Editar / Eliminar SOLO para métricas personalizadas del modelo de este proyecto -->
                                                        <a href="metrica.modificar.personalizado.php?id=<?= $idM; ?>" class="btn btn-outline-warning btn-icon" title="Editar métrica personalizada">
                                                            <span class="oi oi-pencil"></span>
                                                        </a>
                                                        <a href="metrica.eliminar.personalizado.php?id=<?= $idM; ?>"
                                                            class="btn btn-outline-danger btn-icon"
                                                            onclick="return confirm('¿Confirma eliminar esta métrica personalizada? Esta acción no se puede deshacer.');"
                                                            title="Eliminar métrica personalizada">
                                                            <span class="oi oi-trash"></span>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                <?php
                        endwhile;
                    endif;
                } // fin else usuarios
                ?>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>