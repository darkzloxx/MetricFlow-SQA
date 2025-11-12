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
       <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades de la Tarea</title>

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Propiedades de la Tarea</h3>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Tarea</th>
                            <th>Descripción</th>
                            <th>Número de Iteración</th>
                            <th>Métrica</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                        </tr>
                        <tr>
                            <?php 
                            $proyectos = "SELECT distinct t.*,i.*,m.nombre as nombreMetrica ,f.nombre as nombreFase  
                            FROM tarea t join metrica_tarea mt on t.id_tarea = mt.id_tarea
                            join metrica m on m.id_metrica = mt.id_metrica
                            join metrica_iteracion mi on m.id_metrica = mi.id_metrica
                            join iteracion i on i.id_iteracion = mi.id_iteracion
                            join fase f on f.id_fase = i.id_fase
                            where t.id_tarea  = ". $id . " ORDER BY t.id_tarea asc"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $tiene = 0;
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { 
                                $tiene = 1;?>
                                <td><?= $Proyec['nombre']; ?></td>
                                <td><?= $Proyec['descripcion']; ?></td>
                                <td><?= $Proyec['numero_iteracion']; ?></td>
                                <td><?= $Proyec['nombreMetrica']; ?></td>
                                <td><?= $Proyec['fecha_inicio']; ?></td>
                                <td><?= $Proyec['fecha_fin']; ?></td>
                            </tr>
                        <?php } 
                        if($tiene == 0){
                                echo "<td colspan='5'>La tarea no tiene métricas asociadas</td>";
                        }
                        ?>
                    </table>
                </div>
                <div class="card-footer">
                        <a href="tarea.php">
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
