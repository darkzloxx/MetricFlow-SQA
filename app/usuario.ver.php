<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/Usuario.Class.php';

$Usuario = new Usuario($_GET["id"]);
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
            <a id="btnVolver" href="usuarios.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
      
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Propiedades del Usuario</h3>
            </div>
            <div class="card-body">
                <h4 class="card-text">Nombre</h4>
                <p> <?= $Usuario->getNombre(); ?></p>
                <hr />
                <h4 class="card-text">Email</h4>
                <p> <?= $Usuario->getEmail(); ?></p>
                <hr />
                <h4 class="card-text">Proyectos y Roles</h4>
                <table class='table table-bordered table-striped' id="tablaUsuarios">
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Rol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "
    SELECT 
        b.nombre AS nombre_proyecto,
        c.nombre AS nombre_rol
    FROM usuario_proyecto a
    LEFT JOIN proyecto b ON a.id_proyecto = b.id_proyecto
    LEFT JOIN rol c ON c.id = a.id_rol
    WHERE a.id_usuario = " . (int)$_GET["id"];

                        $proyectos = BDConexion::getInstancia()->query($query);
                        $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC);

                        foreach ($proyecto as $Proyec) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($Proyec["nombre_proyecto"]) . '</td>';
                            echo '<td>' . htmlspecialchars($Proyec["nombre_rol"]) . '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
                <!-- El botón "Volver" se muestra en el encabezado para una mejor UX -->
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>