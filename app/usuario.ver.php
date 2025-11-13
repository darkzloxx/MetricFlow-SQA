<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
if (!ControlAcceso::esAdminGlobal()) {
    header('Location: usuarios.php?msg=' . urlencode('Acceso restringido a administradores.') . '&type=danger');
    exit;
}
include_once '../modelo/Usuario.Class.php';

$Usuario = new Usuario($_GET["id"]);

$idUsuario = (int)$_GET["id"];
$query = "
    SELECT 
        b.nombre AS nombre_proyecto,
        c.nombre AS nombre_rol
    FROM usuario_proyecto a
    LEFT JOIN proyecto b ON a.id_proyecto = b.id_proyecto
    LEFT JOIN rol c ON c.id = a.id_rol
    WHERE a.id_usuario = {$idUsuario}";
$proyectos = BDConexion::getInstancia()->query($query);
$listaProyectos = $proyectos ? $proyectos->fetch_all(MYSQLI_ASSOC) : [];
?>

<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Propiedades del Usuario</title>
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
        <div class="mb-3">
            <a href="usuarios.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Propiedades del Usuario</h3>
            </div>
            <div class="card-body">
                <h4 class="card-text">Nombre</h4>
                <p><?= htmlspecialchars($Usuario->getNombre()); ?></p>
                <hr />
                <h4 class="card-text">Email</h4>
                <p><?= htmlspecialchars($Usuario->getEmail()); ?></p>
                <?php
                // Detectar roles principales del usuario
                $rolesUsuario = $Usuario->getRoles() ?? [];
                $esAdmin = false;
                $esSuperAdmin = false;

                foreach ($rolesUsuario as $rol) {
                    $nombreRol = mb_strtolower(trim($rol->getNombre() ?? ''), 'UTF-8');
                    if ($nombreRol === 'administrador') $esAdmin = true;
                    if ($nombreRol === 'superadmin') $esSuperAdmin = true;
                }
                ?>

                <?php if ($esAdmin || $esSuperAdmin): ?>
                    <div class="mt-4 p-3 border rounded d-flex align-items-center justify-content-between"
                        style="background-color: #f8f9fa; border-color: #dee2e6;">
                        <div>
                            <?php if ($esAdmin): ?>
                                <h5 class="mb-1 text-primary">
                                    <span class="oi oi-person mr-1"></span> Rol: Administrador
                                </h5>
                            <?php elseif ($esSuperAdmin): ?>
                                <h5 class="mb-1 text-dark">
                                    <span class="oi oi-star mr-1"></span> Rol: SuperAdmin
                                </h5>
                                <small class="text-muted">Acceso total al sistema, incluyendo configuración avanzada.</small>
                            <?php endif; ?>
                        </div>
                        <span class="oi oi-lock-locked text-secondary" title="Rol fijo"></span>
                    </div>
                <?php endif; ?>


                <?php if (!empty($listaProyectos)) : ?>
                    <hr />
                    <h4 class="card-text">Proyectos y Roles</h4>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Proyecto</th>
                            <th>Rol</th>
                        </tr>
                        <tbody>
                            <?php foreach ($listaProyectos as $Proyec): ?>
                                <tr>
                                    <td><?= htmlspecialchars($Proyec["nombre_proyecto"]) ?></td>
                                    <td><?= htmlspecialchars($Proyec["nombre_rol"]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>