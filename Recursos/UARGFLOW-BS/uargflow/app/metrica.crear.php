<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/ColeccionRoles.php';
$Roles = new ColeccionRoles();


?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Metrica</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="metrica.crear.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Crear Metrica</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <h4>Propiedades</h4>
                        <div class="form-group">
                            <label for="inputNombre">Nombre</label>
                            <input type="text" name="nombre" class="form-control" pattern="[a-zA-Z\s]+" id="inputNombre" placeholder="Ingrese el nombre de la Metrica" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Descripcion</label>
                            <br>
                            <input type="text" name="descripcion" class="form-control" pattern="[a-zA-Z\s]+" id="inputDescripcion" placeholder="Ingrese una breve Descripcion" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Modelo asociado</label>
                            <br>
                            <?php 
                            $proyectos = "SELECT * FROM modelo_calidad"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="<?= $Proyec['id_modelo']; ?>" id="rol[<?= $$Proyec['id_modelo']; ?>]" name="permiso[<?= $Proyec['id_modelo']; ?>]" />
                                <label class="form-check-label" for="permiso">
                                    <?= $Proyec['nombre']; ?>
                                </label>
                            </div>
                        <?php } ?>
                        </div>
                        <hr />
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="metricas.php">
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
