<?php
include_once '../lib/ControlAcceso.Class.php';
// Cualquier usuario autenticado puede ver sus proyectos; los permisos ABM controlan alta/edición/baja
ControlAcceso::verificaLogin();

// Helper de permisos y usuario
$tieneAbmProyectos = ControlAcceso::verificaPermiso(PermisosSistema::ABM_PROYECTOS);
$usr = ControlAcceso::usuarioActual();

// Consulta: si tiene ABM, ve todos los proyectos. Si no, sólo los que le corresponden.
$cn = BDConexion::getInstancia();
if ($tieneAbmProyectos) {
    $sql = "SELECT p.* FROM proyecto p ORDER BY p.id_proyecto";
    $proyectos = $cn->query($sql)->fetch_all(MYSQLI_ASSOC);
} else {
    $sql = "SELECT p.*
            FROM proyecto p
            JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
            WHERE up.id_usuario = ?
            ORDER BY p.id_proyecto";
    $stmt = $cn->prepare($sql);
    $stmt->bind_param('i', $usr->id);
    $stmt->execute();
    $res = $stmt->get_result();
    $proyectos = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>        
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Proyectos</title>

    </head>
    <body>

        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <div class="card">
                <div class="card-header">

                    <h3>Proyectos</h3>
                </div>
                <div class="card-body">
                    <?php if ($tieneAbmProyectos) { ?>
                        <p>
                            <a href="proyecto.crear.php">
                                <button type="button" class="btn btn-success">
                                    <span class="oi oi-plus"></span> Nuevo Proyecto
                                </button>
                            </a>
                        </p>
                    <?php } ?>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Nombre</th>
                            <th>Año</th>
                            <th>Estado</th>
                            <th>Opciones</th>
                        </tr>
                        <?php if (empty($proyectos)) { ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No tenés proyectos asignados.</td>
                            </tr>
                        <?php } else { foreach ($proyectos as $Proyec) { ?>
                            <tr>
                                <td><?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>2025</td>
                                <td><?= htmlspecialchars($Proyec['estado'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a title="Ver" href="proyecto.ver.php?id=<?= (int)$Proyec['id_proyecto']; ?>">
                                        <button type="button" class="btn btn-outline-primary">
                                            <span class="oi oi-eye"></span>
                                        </button>
                                    </a>
                                    <a title="Dashboard" href="dashboard.php?proyecto=<?= (int)$Proyec['id_proyecto']; ?>">
                                        <button type="button" class="btn btn-outline-info">
                                            <span class="oi oi-bar-chart"></span>
                                        </button>
                                    </a>
                                    <?php if ($tieneAbmProyectos) { ?>
                                        <a title="Modificar" href="proyecto.modificar.php?id=<?= (int)$Proyec['id_proyecto']; ?>">
                                            <button type="button" class="btn btn-outline-warning">
                                                <span class="oi oi-pencil"></span>
                                            </button>
                                        </a>
                                        <a title="Eliminar" href="proyecto.eliminar.php?id=<?= (int)$Proyec['id_proyecto']; ?>">
                                            <button type="button" class="btn btn-outline-danger">
                                                <span class="oi oi-trash"></span>
                                            </button>
                                        </a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } } ?>
                    </table>
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>

