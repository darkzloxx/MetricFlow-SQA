<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_PERMISOS);
include_once '../modelo/Permiso.php';

$id = $_GET["id"];
?>


<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
       <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades de la Iteración</title>

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Propiedades de la Iteración</h3>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Iteración</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Objetivo</th>
                            <th>Fase</th>
                        </tr>
                        <tr>
                            <?php 
                            $proyectos = "SELECT i.*,f.nombre FROM iteracion i join fase f on i.id_fase = f.id_fase
                            where id_iteracion = ". $id . " ORDER BY i.id_fase asc"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $tiene = 0;
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { 
                                $tiene = 1;?>
                                <td><?= $Proyec['numero_iteracion']; ?></td>
                                <td><?= $Proyec['fecha_inicio']; ?></td>
                                <td><?= $Proyec['fecha_fin']; ?></td>
                                <td><?= $Proyec['objetivo']; ?></td>
                                <td><?= $Proyec['nombre']; ?></td>
                            </tr>
                        <?php } 
                        if($tiene == 0){
                                echo "<td colspan='5'>El modelo no tiene métricas asociadas</td>";
                        }
                        ?>
                    </table>
                </div>
                <div class="card-footer">
                        <a href="iteracion.php">
                            <button type="button" class="btn btn-outline-danger">
                                <span class="oi oi-x"></span> Volver
                            </button>
                        </a>
                    </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
