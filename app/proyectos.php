<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::verificaLogin();
$tieneAbmProyectos = ControlAcceso::verificaPermiso(PermisosSistema::ABM_PROYECTOS);
$usr = ControlAcceso::usuarioActual();

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
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Proyectos</title>

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
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container">

        <!-- 🔔 Contenedor de alertas dinámicas -->
        <div id="alertContainer" class="mt-3">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-<?= ($_GET['type'] === 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['msg']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <script>
                    $('html, body').animate({
                        scrollTop: 0
                    }, 'fast');
                    setTimeout(() => $('.alert').alert('close'), 3500);
                </script>
                <script>
                    (function() {
                        if (!window.history || !window.history.replaceState) return;
                        const params = new URLSearchParams(window.location.search);
                        if (!params.has('msg')) return;
                        params.delete('msg');
                        params.delete('type');
                        const newSearch = params.toString();
                        const newUrl = window.location.pathname + (newSearch ? ('?' + newSearch) : '');
                        window.history.replaceState({}, document.title, newUrl);
                    })();
                </script>
            <?php endif; ?>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3>Proyectos</h3>
            </div>
            <div class="card-body">
                <?php if ($tieneAbmProyectos): ?>
                    <p>
                        <a href="proyecto.crear.php">
                            <button type="button" class="btn btn-success">
                                <span class="oi oi-plus"></span> Nuevo Proyecto
                            </button>
                        </a>
                    </p>
                <?php endif; ?>

                <?php if (empty($proyectos)): ?>
                    <div class="card my-4 text-center"
                        style="border:1px dashed rgba(23,162,184,0.15); background:rgba(23,162,184,0.03);">
                        <div class="card-body p-4">
                            <i class="oi oi-info mb-2" style="font-size:2rem; color:#17a2b8;"></i>
                            <h5 class="text-info font-weight-bold mb-2">No tenés proyectos asignados</h5>
                            <p class="text-muted mb-3">Aún no fuiste asignado a ningún proyecto. Si creés que esto es un error, contactá a un administrador.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Nombre</th>
                            <th>Año</th>
                            <th>Estado</th>
                            <th>Opciones</th>
                        </tr>
                        <?php foreach ($proyectos as $Proyec): ?>
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
                                    <?php if ($tieneAbmProyectos): ?>
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
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include_once '../gui/footer.php'; ?>

</body>

</html>