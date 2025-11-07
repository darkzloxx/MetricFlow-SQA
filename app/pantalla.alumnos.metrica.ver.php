<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_PERMISOS);
include_once '../modelo/Permiso.php';

$id = $_GET["id"];
$porciones = explode(",", $id);
$idMetrica = $porciones[0]; 
$idIteracion = $porciones[1];
?>


<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
       <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades de la metrica</title>

    </head>
    <body>
        <?php include_once '../gui/navbarAlumnos.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Propiedades de la metrica</h3>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Valor Planificado</th>
                            <th>Valor Ejecutado</th>
                            <th>Umbral</th>
                            <th>Fase</th>
                            <th>Iteracion</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                        </tr>
                        <tr>
                            <?php 
                            $proyectos = "SELECT 
                                        p.nombre AS proyecto,
                                        m.nombre AS metrica,
                                        m.descripcion AS descripcion,
                                        mi.valor_planificado,
                                        mi.valor_ejecutado,
                                        mi.umbral_desviacion,
                                        m.id_metrica,
                                        f.nombre AS nombreFase,
                                        i.numero_iteracion,
                                        i.fecha_inicio,
                                        i.fecha_fin
                                        FROM metrica_iteracion mi
                                        JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                                        JOIN fase f ON i.id_fase = f.id_fase
                                        JOIN proyecto p ON p.id_proyecto = i.id_proyecto
                                        JOIN usuario_proyecto u ON u.id_proyecto = p.id_proyecto
                                        JOIN metrica m ON m.id_metrica = mi.id_metrica
                                        WHERE mi.id_metrica = ".$idMetrica." AND mi.id_iteracion = ".$idIteracion." AND u.id_usuario = ".$_SESSION['usuario']->id; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $tiene = 0;
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { 
                                $tiene = 1;?>
                                <td><?= $Proyec['valor_planificado']; ?></td>
                                <td><?= $Proyec['valor_ejecutado']; ?></td>
                                <td><?= $Proyec['umbral_desviacion']; ?></td>
                                <td><?= $Proyec['nombreFase']; ?></td>
                                <td><?= $Proyec['numero_iteracion']; ?></td>
                                <td><?= $Proyec['fecha_inicio']; ?></td>
                                <td><?= $Proyec['fecha_fin']; ?></td>
                            </tr>
                        <?php } 
                        if($tiene == 0){
                                echo "<td colspan='3'>El modelo no tiene metricas asociadas</td>";
                        }
                        ?>
                    </table>
                </div>
                <div class="card-footer">
                        <a href="pantalla.alumnos.metricas.php">
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
