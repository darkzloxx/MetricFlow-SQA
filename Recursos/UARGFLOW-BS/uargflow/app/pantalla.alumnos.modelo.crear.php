<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/ColeccionUsuarios.php';
$ColeccionUsuarios = new ColeccionUsuarios();


?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Cargar Modelo</title>
    </head>
    <body>
        <?php include_once '../gui/navbarAlumnos.php'; ?>
        <div class="container">
            <form action="pantalla.alumnos.modelo.crear.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Cargar Modelo</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <h4>Propiedades</h4>
                        <div class="form-group">
                            <label for="inputMail">Proyecto</label>
                            <br>
                            <select id="proyecto" name="proyecto" class="form-control">
                            <?php 
                            $proyectos = "SELECT 
                                        p.nombre AS proyecto,
                                        up.id_proyecto
                                        FROM usuario_proyecto up
                                        JOIN usuario u ON up.id_usuario = u.id
                                        JOIN proyecto p ON up.id_proyecto = p.id_proyecto
                                        where u.id =  ".$_SESSION['usuario']->id; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <option value="<?= $Proyec['id_proyecto']; ?>" ><?= $Proyec['proyecto']; ?></option>
                            <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Modelo asociado</label>
                            <br>
                            <select id="modelo" name="modelo" class="form-control">
                            <?php 
                            $proyectos = "SELECT * FROM modelo_calidad"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <option value="<?= $Proyec['id_modelo']; ?>" title="<?= $Proyec['descripcion']; ?>"><?= $Proyec['nombre']; ?></option>
                        <?php } ?>
                            </select>
                        </div>
                        <hr />
                <br>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="pantalla.alumnos.modelo.php">
                            <button type="button" class="btn btn-outline-danger">
                                <span class="oi oi-x"></span> Cancelar
                            </button>
                        </a>
                    </div>
                </div>
                </div>
            </form>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>