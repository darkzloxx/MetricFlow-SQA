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
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Metricas</title>

    </head>
    <body>

        <?php include_once '../gui/navbarAlumnos.php'; ?>

        <div class="container">
            <div class="card">
                <div class="card-header">

                    <h3>Metricas</h3>
                </div>
                <div class="card-body">
                    <p>
                        <a href="pantalla.alumnos.metrica.crear.php">
                            <button type="button" class="btn btn-success">
                                <span class="oi oi-plus"></span> Nueva Metrica
                            </button>
                        </a>
                    </p>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Estado</th>
                            <th>Proyecto</th>
                            <th>Nombre</th>
                            <th>Valor Planificado</th>
                            <th>Valor Ejecutado</th>
                            <th>Umbral</th>
                            <th>Opciones</th>
                        </tr>
                        <!-- <tr style="background-color: #bf2c08;"> -->
                        <tr>
                            <?php 
                            $proyectos = "SELECT 
                                        p.nombre AS proyecto,
                                        m.nombre AS metrica,
                                        m.descripcion AS descripcion,
                                        mi.valor_planificado,
                                        mi.valor_ejecutado,
                                        mi.umbral_desviacion,
                                        mi.id_metrica,
                                        mi.id_iteracion
                                        FROM metrica_modelo_calidad mmc
                                        JOIN modelo_calidad mo ON mmc.id_modelo = mo.id_modelo
                                        JOIN metrica m ON mmc.id_metrica = m.id_metrica
                                        JOIN metrica_iteracion mi ON mi.id_metrica = mmc.id_metrica
                                        JOIN proyecto p ON mo.id_modelo = p.id_modelo
                                        JOIN usuario_proyecto u ON u.id_proyecto = p.id_proyecto
                                        where u.id_usuario = ".$_SESSION['usuario']->id."
                                        ORDER BY mo.nombre"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) {
                                $estado = 0;
                                $ejecutado = $Proyec['valor_ejecutado'];
                                $planificado = $Proyec['valor_planificado'];
                                $umbral = $Proyec['umbral_desviacion'];
                                if($planificado <= $ejecutado){
                                    $estado = "
                                        <button type='button' class='btn btn-outline-success'>
                                            <span class='oi oi-circle-check'></span>
                                        </button>";
                                } else {
                                    if($umbral <= $ejecutado){
                                        $estado = "
                                        <button type='button' class='btn btn-outline-warning'>
                                            <span class='oi oi-circle-check'></span>
                                        </button>";
                                    } else {
                                        $estado = "
                                        <button type='button' class='btn btn-outline-danger'>
                                            <span class='oi oi-circle-x'></span>
                                        </button>";
                                    }
                                }

                                ?>
                                <td ><?= $estado; ?></td>
                                <td ><?= $Proyec['proyecto']; ?></td>
                                <td title="<?= $Proyec['descripcion']; ?>"><?= $Proyec['metrica']; ?></td>
                                <td><?= $Proyec['valor_planificado']; ?></td>
                                <td><?= $Proyec['valor_ejecutado']; ?></td>
                                <td><?= $Proyec['umbral_desviacion']; ?></td>
                                <td>
                                    <a title="Ver detalle" href="pantalla.alumnos.metrica.ver.php?id=<?= $Proyec['id_metrica']; ?>,<?= $Proyec['id_iteracion']; ?>">
                                        <button type="button" class="btn btn-outline-info">
                                            <span class="oi oi-zoom-in"></span>
                                        </button>
                                    </a>
                                    <a title="Modificar" href="pantalla.alumnos.metrica.modificar.php?id=<?= $Proyec['id_metrica']; ?>,<?= $Proyec['id_iteracion']; ?>">
                                        <button type="button" class="btn btn-outline-warning">
                                            <span class="oi oi-pencil"></span>
                                        </button>
                                    </a>
                                    <a title="Eliminar" href="pantalla.alumnos.metrica.eliminar.php?id=<?= $Proyec['id_metrica']; ?>,<?= $Proyec['id_iteracion']; ?>">
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