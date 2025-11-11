<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
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
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Actualizar Iteración</title>

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="iteracion.modificar.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Actualizar Iteración</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="inputNombre">Numero</label>
                            <?php $proyectos = "SELECT i.*,f.nombre FROM iteracion i join fase f on i.id_fase = f.id_fase
                            where id_iteracion = ". $_GET["id"] . " ORDER BY i.id_fase asc"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC);
                            foreach ($proyecto as $Proyec) { ?>
                            <input type="text" name="nombre" class="form-control" id="inputNombre" value="<?= $Proyec['numero_iteracion']; ?>" placeholder="Ingrese el numero de la Iteración" required="">
                        </div>
                        <div class="form-group">
                        <label for="inputMail">Objetivos de la Iteración</label>
                            <input type="text" name="objetivo" class="form-control" id="inputNombre" placeholder="Ingrese un breve objetivo de la iteración" value = "<?= $Proyec['objetivo']; ?>">
                        </div>
                        <div class="form-group">
                        <label for="fecha_inicio">Fecha de inicio:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value = "<?= $Proyec['fecha_inicio']; ?>" required="">
                        </div>
                        <div class="form-group">
                        <label for="fecha_fin">Fecha de fin:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value = "<?= $Proyec['fecha_fin']; ?>" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Fase</label>
                            <select id="fase" name="fase" class="form-control">
                            <?php 
                            $proyectos = "SELECT * FROM fase where id_fase != ".$Proyec['id_fase']; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            ?>
                            <option value="<?= $Proyec['id_fase']; ?>" ><?= $Proyec['nombre']; ?></option>
                            <?php
                            foreach ($proyecto as $Proyec) { ?>
                            <option value="<?= $Proyec['id_fase']; ?>" ><?= $Proyec['nombre']; ?></option>
                            <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        </div>
                        <input type="hidden" name="id" class="form-control" id="id" value="<?= $_GET["id"]; ?>" >
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="iteraciones.php">
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