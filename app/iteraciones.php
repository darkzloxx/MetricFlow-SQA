<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Solo usuarios con permiso ABM_ITERACIONES (líderes de proyecto)
if (!ControlAcceso::verificaPermiso(PermisosSistema::ABM_ITERACIONES)) {
    header('Location: ../app/menu.php?msg=' . urlencode('Acceso restringido: solo líderes de proyecto pueden gestionar iteraciones.') . '&type=danger');
    exit;
}

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$idUsuario = (int)$usr->id;

// =====================================================
// 🔹 Iteraciones de proyectos donde el usuario es líder
// =====================================================
$sqlIter = "
    SELECT i.id_iteracion, i.numero_iteracion, i.fecha_inicio, i.fecha_fin,
           i.objetivo, f.nombre AS fase, p.nombre AS proyecto,
           COUNT(mi.id_metrica) AS tiene_metricas
    FROM iteracion i
    JOIN fase f ON f.id_fase = i.id_fase
    JOIN proyecto p ON p.id_proyecto = i.id_proyecto
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    JOIN rol r ON r.id = up.id_rol
    LEFT JOIN metrica_iteracion mi ON mi.id_iteracion = i.id_iteracion
    WHERE up.id_usuario = {$idUsuario}
      AND LOWER(r.nombre) LIKE '%líder%'
    GROUP BY i.id_iteracion
    ORDER BY p.nombre ASC, i.numero_iteracion ASC";


$rs = $cn->query($sqlIter);
$iteraciones = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
?>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Iteraciones</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <style>
        .cell-ellipsis {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cell-ellipsis:hover {
            white-space: normal;
            word-break: break-word;
            background: #f8f9fa;
            border-radius: .25rem;
            padding: .2rem .4rem;
        }

        .table thead th {
            background-color: #f1f3f5;
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

    <div class="container mt-4">
        <div class="mb-3">
            <a href="proyectos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Iteraciones de mis Proyectos</h3>
            <a href="iteracion.crear.php" class="btn btn-success">
                <span class="oi oi-plus"></span> Nueva Iteración
            </a>
        </div>


        <?php if (isset($_GET['msg'])): ?>
            <div id="flash-alert" class="alert alert-<?= ($_GET['type'] ?? 'info'); ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <script>
                setTimeout(() => $('#flash-alert').alert('close'), 3000);
            </script>
        <?php endif; ?>

        <?php if (empty($iteraciones)): ?>
            <div class="alert alert-warning">
                <span class="oi oi-warning"></span> No hay iteraciones registradas en los proyectos que liderás.
            </div>
        <?php else: ?>
            <table class="table table-hover table-sm">
                <tr class="table-info">
                    <th>Proyecto</th>
                    <th>Fase</th>
                    <th>N° Iteración</th>
                    <th>Objetivo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Acciones</th>
                </tr>
                <tbody>
                    <?php foreach ($iteraciones as $it): ?>
                        <tr>
                            <td class="font-weight-bold"><?= htmlspecialchars($it['proyecto']); ?></td>
                            <td><?= htmlspecialchars($it['fase']); ?></td>
                            <td><?= (int)$it['numero_iteracion']; ?></td>
                            <td class="cell-ellipsis"><?= htmlspecialchars($it['objetivo']); ?></td>
                            <td><?= htmlspecialchars($it['fecha_inicio']); ?></td>
                            <td><?= htmlspecialchars($it['fecha_fin']); ?></td>
                            <td>
                                <!-- 🔹 Botón Ver SIEMPRE disponible -->
                                <a href="iteracion.ver.php?id=<?= $it['id_iteracion']; ?>"
                                    class="btn btn-outline-primary" title="Ver">
                                    <span class="oi oi-eye"></span>
                                </a>

                                <?php if ((int)$it['tiene_metricas'] === 0): ?>

                                    <!-- 🟢 EDITAR habilitado -->
                                    <a href="iteracion.modificar.php?id=<?= $it['id_iteracion']; ?>"
                                        class="btn btn-outline-warning" title="Editar">
                                        <span class="oi oi-pencil"></span>
                                    </a>

                                    <!-- 🟢 ELIMINAR habilitado -->
                                    <form action="iteracion.eliminar.procesar.php" method="post"
                                        style="display:inline-block;"
                                        onsubmit="return confirm('¿Eliminar esta iteración? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="id" value="<?= $it['id_iteracion']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                            <span class="oi oi-trash"></span>
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <!-- 🔒 EDITAR deshabilitado -->
                                    <button class="btn btn-outline-warning disabled"
                                        data-toggle="tooltip"
                                        title="No se puede editar: tiene métricas planificadas">
                                        <span class="oi oi-lock-locked"></span>
                                    </button>

                                    <!-- 🔒 ELIMINAR deshabilitado -->
                                    <button class="btn btn-outline-danger disabled"
                                        data-toggle="tooltip"
                                        title="No se puede eliminar: tiene métricas planificadas">
                                        <span class="oi oi-lock-locked"></span>
                                    </button>

                                <?php endif; ?>

                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>