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
                // Solo cierra el mensaje flash, no las demás alertas
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
                <br> <br>
                <?php
                /* ==========================================================
           🧑‍💼 ADMIN / SUPERADMIN → Métricas base globales
           ========================================================== */
                if ($esAdminGlobal || $esSuperAdmin):
                    $sql = "SELECT id_metrica, nombre, descripcion 
                    FROM metrica 
                    WHERE tipo='base' 
                    ORDER BY nombre ASC";
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

                            // Cargar métricas del modelo (aunque esté vacío)
                            if ($tipoModelo === 'global') {
                                $sqlM = "
                            SELECT m.id_metrica, m.nombre, m.descripcion, m.tipo
                            FROM metrica_modelo_calidad mmc
                            JOIN metrica m ON m.id_metrica = mmc.id_metrica
                            WHERE mmc.id_modelo = {$idModelo}
                            ORDER BY m.nombre ASC";
                            } else {
                                $sqlM = "
                            SELECT m.id_metrica, m.nombre, m.descripcion, m.tipo
                            FROM metrica_proyecto_modelo mpm
                            JOIN metrica m ON m.id_metrica = mpm.id_metrica
                            WHERE mpm.id_proyecto_modelo = {$idModelo}
                            ORDER BY m.nombre ASC";
                            }

                            $resM = $cn->query($sqlM);
                            $metricas = $resM ? $resM->fetch_all(MYSQLI_ASSOC) : [];
                        ?>
                            <!-- Banner del proyecto y modelo -->
                            <div class="alert alert-info mb-2">
                                <h5 class="mb-0 font-weight-bold"><?= $nombreProyecto; ?></h5>
                                <small>Modelo: <strong><?= $nombreModelo; ?></strong> (<?= ucfirst($tipoModelo); ?>)</small>
                            </div>

                            <?php if (empty($metricas)): ?>
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
                                            $esBase = ($m['tipo'] === 'base');
                                        ?>
                                            <tr>
                                                <td class="font-weight-bold cell-ellipsis"><?= htmlspecialchars($m['nombre']); ?></td>
                                                <td class="cell-ellipsis large"><?= htmlspecialchars($m['descripcion']); ?></td>
                                                <td><span class="badge badge-<?= $esBase ? 'secondary' : 'info'; ?>"><?= ucfirst($m['tipo']); ?></span></td>
                                                <td>
                                                    <a href="metrica.ver.php?id=<?= $idM; ?>" class="btn btn-outline-primary btn-icon" title="Ver">
                                                        <span class="oi oi-eye"></span>
                                                    </a>

                                                    <?php if ($tienePermGestionMetricas): ?>
                                                        <?php if ($esBase && $tipoModelo === 'personalizado'): ?>
                                                            <!-- Desvincular métrica base -->
                                                            <form action="metrica.eliminar.procesar.php" method="post" style="display:inline-block;"
                                                                onsubmit="return confirm('¿Desvincular esta métrica base del modelo personalizado?');">
                                                                <input type="hidden" name="id" value="<?= $idM; ?>">
                                                                <input type="hidden" name="modelo" value="<?= $idModelo; ?>">
                                                                <button type="submit" class="btn btn-outline-warning ">
                                                                    <span class="oi oi-x"></span>
                                                                </button>
                                                            </form>
                                                        <?php elseif (!$esBase && $tipoModelo === 'personalizado'): ?>
                                                            <!-- Editar métrica personalizada -->
                                                            <a href="metrica.modificar.personalizado.php?id=<?= $idM; ?>"
                                                                class="btn btn-outline-warning"
                                                                title="Editar métrica personalizada">
                                                                <span class="oi oi-pencil"></span>
                                                            </a>

                                                            <!-- Eliminar métrica personalizada -->
                                                            <form action="metrica.eliminar.procesar.php" method="post" style="display:inline-block;"
                                                                onsubmit="return confirm('¿Eliminar esta métrica personalizada? Esta acción no se puede deshacer.');">
                                                                <input type="hidden" name="id" value="<?= $idM; ?>">
                                                                <input type="hidden" name="modelo" value="<?= $idModelo; ?>">
                                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar métrica personalizada">
                                                                    <span class="oi oi-trash"></span>
                                                                </button>
                                                            </form>
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