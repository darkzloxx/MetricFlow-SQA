<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/ColeccionUsuarios.php';
$ColeccionUsuarios = new ColeccionUsuarios();

?>

<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>        
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modelo</title>
    </head>
    <body>

        <?php include_once '../gui/navbarAlumnos.php'; ?>

        <div class="container">

            <div class="card">
                <div class="card-header">
                    <h3>Modelo</h3>
                </div>
                <div class="card-body">
                    <p>
                        <a href="pantalla.alumnos.modelo.crear.php">
                        <button type="button" class="btn btn-success">
                            <span class="oi oi-plus"></span> Asignar Modelo
                        </button>
                    </a>
                    </p>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Proyecto</th>
                            <th>Modelo</th>
                            <th>Opciones</th>
                        </tr>
                        <tr>
                            <?php 
                            $proyectos = "SELECT 
                                        p.nombre AS proyecto,
                                        m.nombre AS nombreModelo,
                                        up.id_proyecto
                                        FROM usuario_proyecto up
                                        JOIN usuario u ON up.id_usuario = u.id
                                        JOIN proyecto p ON up.id_proyecto = p.id_proyecto
                                        JOIN modelo_calidad m ON p.id_modelo = m.id_modelo
                                        where u.id = ".$_SESSION['usuario']->id."
                                        ORDER BY p.id_proyecto"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                                <td><?= $Proyec['proyecto']; ?></td>
                                <td><?= $Proyec['nombreModelo']; ?></td>
                                <td>
                                    <a title="Ver detalle" href="pantalla.alumnos.modelo.ver.php?id=<?= $Proyec['id_proyecto']; ?>">
                                        <button type="button" class="btn btn-outline-info">
                                            <span class="oi oi-zoom-in"></span>
                                        </button>
                                    </a>
                                    <!--
                                    <a title="Modificar" href="pantalla.alumnos.modelo.modificar.php?id=<?= $Proyec['id_proyecto']; ?>">
                                        <button type="button" class="btn btn-outline-warning">
                                            <span class="oi oi-pencil"></span>
                                        </button>
                                    </a>
                                    <a title="Eliminar" href="pantalla.alumnos.modelo.eliminar.php?id=<?= $Proyec['id_proyecto']; ?>">
                                        <button type="button" class="btn btn-outline-danger">
                                            <span class="oi oi-trash"></span>
                                        </button>
                                    </a>  
                                    -->
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>

