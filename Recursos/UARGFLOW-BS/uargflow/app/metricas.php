<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_PERMISOS);
include_once '../modelo/ColeccionPermisos.php';
$ColeccionPermisos = new ColeccionPermisos();
?>

<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>        
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Métricas</title>

    </head>
    <body>

        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <div class="card">
                <div class="card-header">

                    <h3>Métricas</h3>
                </div>
                <div class="card-body">
                    <p>
                        <a href="metrica.crear.php">
                            <button type="button" class="btn btn-success">
                                <span class="oi oi-plus"></span> Nueva Métrica
                            </button>
                        </a>
                    </p>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Opciones</th>
                        </tr>
                        <tr>
                            <?php 
                            $proyectos = "SELECT * FROM metrica"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                                <td><?= $Proyec['nombre']; ?></td>
                                <td><?= $Proyec['descripcion']; ?></td>
                                <td>
                                    <a title="Ver detalle" href="metrica.ver.php?id=<?= $Proyec['id_metrica']; ?>">
                                        <button type="button" class="btn btn-outline-info">
                                            <span class="oi oi-zoom-in"></span>
                                        </button>
                                    </a>
                                    <a title="Modificar" href="metrica.modificar.php?id=<?= $Proyec['id_metrica']; ?>">
                                        <button type="button" class="btn btn-outline-warning">
                                            <span class="oi oi-pencil"></span>
                                        </button>
                                    </a>
                                    <a title="Eliminar" href="metrica.eliminar.php?id=<?= $Proyec['id_metrica']; ?>">
                                        <button type="button" class="btn btn-outline-danger">
                                            <span class="oi oi-trash"></span>
                                        </button>
                                    </a>  
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