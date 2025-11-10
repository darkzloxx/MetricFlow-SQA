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
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Actualizar Tarea</title>

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="tarea.modificar.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Actualizar Tarea</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="inputNombre">Nombre</label>
                            <?php $proyectos = "SELECT * FROM tarea 
                            where id_tarea = ". $_GET["id"] ; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC);
                            foreach ($proyecto as $Proyec) { ?>
                            <input type="text" name="nombre" class="form-control" id="inputNombre" value="<?= $Proyec['nombre']; ?>" placeholder="Ingrese el nombre de la Iteración" required="">
                        </div>
                        <div class="form-group">
                        <label for="inputMail">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" id="inputNombre" placeholder="Ingrese una breve descripción" value = "<?= $Proyec['descripcion']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Métricas asociadas:</label>
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
                        <?php } }?>
                        </div>
                        <input type="hidden" name="id" class="form-control" id="id" value="<?= $_GET["id"]; ?>" >
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
                </div>
            </form>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>