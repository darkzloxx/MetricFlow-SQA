<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/ColeccionRoles.php';
$Roles = new ColeccionRoles();
date_default_timezone_set('UTC');
$fecha = date("Y/m/d");
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script> 
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Tarea</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="tarea.crear.procesar.php" method="post" >
                <div class="card">
                    <div class="card-header">
                        <h3>Crear Tarea</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <h4>Propiedades</h4>
                        <div class="form-group">
                            <label for="inputNombre">Nombre de Tarea</label>
                            <input type="text" name="nombre" class="form-control" id="inputNombre" placeholder="Ingrese el nombre de la tarea" required="">
                        </div>
                        <div class="form-group">
                        <label for="fecha_inicio">Descripción:</label>
                        <input type="text" id="descripcion" name="descripcion" class="form-control" placeholder="Ingrese una breve descripción">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Métricas asociadas:</label>
                            <br>
                            <?php 
                            $id_proyecto = 1;
                            $proyectos = "SELECT m.* FROM proyecto p join metrica_modelo_calidad mm on mm.id_modelo = p.id_modelo
                            join metrica m on m.id_metrica = mm.id_metrica
                            WHERE p.id_proyecto = ". $id_proyecto . " ORDER BY m.id_metrica asc"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="<?= $Proyec['id_metrica']; ?>" id="rol[<?= $$Proyec['id_metrica']; ?>]" name="permiso[<?= $Proyec['id_metrica']; ?>]" />
                                <label class="form-check-label" for="permiso" title = "<?= $Proyec['descripcion']; ?>">
                                    <?= $Proyec['nombre']; ?>
                                </label>
                            </div>
                        <?php } ?>
                        </div>
                        <div class="form-group">
                            <label for="inputNombre">Iteración - fase</label>
                            <select id="iteracion" name="iteracion" class="form-control">
                            <?php 
                            
                            $proyectos = "SELECT i.*, f.nombre, f.id_fase FROM iteracion i JOIN fase f on i.id_fase = f.id_fase
                            WHERE ('".$fecha."' BETWEEN fecha_inicio and fecha_fin OR '".$fecha."' < fecha_fin ) and i.id_proyecto = ". $id_proyecto; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <option value="<?= $Proyec['id_iteracion']; ?>" ><?= $Proyec['numero_iteracion']; ?> - <?= $Proyec['objetivo']; ?> - <?= $Proyec['nombre']; ?></option>
                            <?php } ?>
                            </select>

                            <div class="form-group mt-3">
                                <label for="metricas_existentes">Métricas existentes:</label>
                                <div>
                                <?php
                                    $metricas = BDConexion::getInstancia()->query("SELECT * FROM metrica ORDER BY id_metrica ASC");
                                    $listaMetricas = $metricas->fetch_all(MYSQLI_ASSOC);
                                    foreach ($listaMetricas as $metrica) {
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="<?= $metrica['id_metrica']; ?>" id="metrica_<?= $metrica['id_metrica']; ?>" name="permiso[]" />
                                        <label class="form-check-label" for="metrica_<?= $metrica['id_metrica']; ?>" title="<?= $metrica['descripcion']; ?>">
                                            <?= $metrica['nombre']; ?>
                                        </label>
                                    </div>
                                <?php } ?>
                                </div>
                            </div>
                        </div>
                        <hr />
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="tarea.php">
                            <button type="button" class="btn btn-outline-danger">
                                <span class="oi oi-x"></span> Cancelar
                            </button>
                        </a>
                    </div>
                    <div class="modal" tabindex="-1" role="dialog" id = "myModalFecha">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Fechas Incorrectas</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Fecha de inicio anterior a fecha de fin.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                        </div>
                    </div>
                    </div>
                </div>
            </form>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
