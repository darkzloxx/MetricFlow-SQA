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
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Iteración</title>

    </head>
    <body>

        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <div class="card">
                <div class="card-header">

                    <h3>Iteraciones</h3>
                </div>
                <div class="card-body">
                    <p>
                        <a href="iteracion.crear.php">
                            <button type="button" class="btn btn-success">
                                <span class="oi oi-plus"></span> Nuevo Iteración
                            </button>
                        </a>
                    </p>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Numero</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Fase</th>
                            <th>Opciones</th>
                        </tr>
                        <tr>
                            <?php 
                            $id = 1;
                            $proyectos = "SELECT i.*,f.nombre FROM iteracion i join fase f on i.id_fase = f.id_fase
                            where id_proyecto = ". $id . " ORDER BY i.id_fase asc"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                                <td><?= $Proyec['numero_iteracion']; ?></td>
                                <td><?= $Proyec['fecha_inicio']; ?></td>
                                <td><?= $Proyec['fecha_fin']; ?></td>
                                <td><?= $Proyec['nombre']; ?></td>
                                <td>
                                    <a title="Ver detalle" href="iteracion.ver.php?id=<?= $Proyec['id_iteracion']; ?>">
                                        <button type="button" class="btn btn-outline-info">
                                            <span class="oi oi-zoom-in"></span>
                                        </button>
                                    </a>
                                    <a title="Modificar" href="iteracion.modificar.php?id=<?= $Proyec['id_iteracion']; ?>">
                                        <button type="button" class="btn btn-outline-warning">
                                            <span class="oi oi-pencil"></span>
                                        </button>
                                    </a>
                                    <a title="Eliminar" href="iteracion.eliminar.php?id=<?= $Proyec['id_iteracion']; ?>">
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