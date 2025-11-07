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
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Actualizar Métrica</title>

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="pantalla.alumnos.metrica.modificar.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Actualizar Métrica</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="inputNombre">Valor Planificado</label>
                            <?php $proyectos = "SELECT * FROM metrica_iteracion where id_metrica = ".$idMetrica." and id_iteracion = ".$idIteracion; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC);
                            foreach ($proyecto as $Proyec) { ?>
                            <input type="number" name="planificado" class="form-control" id="inputNombre" value="<?= $Proyec['valor_planificado']; ?>" placeholder="Ingrese el nombre de la Metrica" required="">
                            <br>
                            <label for="inputNombre">Valor Ejecutado</label>
                            <input type="number" name="ejecutado" class="form-control" id="inputNombre" value="<?= $Proyec['valor_ejecutado']; ?>" placeholder="Ingrese el nombre de la Metrica" required="">
                            <br>
                            <label for="inputNombre">Umbral de Desviación</label>
                            <input type="number" name="umbral" class="form-control" id="inputNombre" value="<?= $Proyec['umbral_desviacion']; ?>" placeholder="Ingrese el nombre de la Metrica" required="">
                            <br>
                           
                        </div>                     
                        
                        <input type="hidden" name="id" class="form-control" id="id" value="<?= $idMetrica; ?>,<?= $idIteracion; ?>" >
                        <?php } ?>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="pantalla.alumnos.metricas.php">
                            <button type="button" class="btn btn-outline-danger">
                                <span class="oi oi-x"></span> Cancelar
                            </button>
                        </a>
                    </div>
                </div>
            </form>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>