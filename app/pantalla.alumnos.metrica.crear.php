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
        <?php include_once '../gui/navbarAlumnos.php'; ?>
        <div class="container">
            <form action="pantalla.alumnos.metrica.crear.procesar.php" method="post">
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
                            <label for="inputNombre">Iteracion - fase</label>
                             <select id="iteracion" name="iteracion" class="form-control">
                            <?php 
                            $proyectos = "SELECT i.*, f.nombre, f.id_fase FROM iteracion i JOIN fase f on i.id_fase = f.id_fase"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <option value="<?= $Proyec['id_iteracion']; ?>" ><?= $Proyec['numero_iteracion']; ?> - <?= $Proyec['objetivo']; ?> - <?= $Proyec['nombre']; ?></option>
                            <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Metrica</label>
                            <br>
                            <select id="metrica" name="metrica" class="form-control">
                            <?php 
                            $proyectos = "SELECT * FROM metrica "; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <option value="<?= $Proyec['id_metrica']; ?>" title="<?= $Proyec['descripcion']; ?>" ><?= $Proyec['nombre']; ?></option>
                            <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inputNombre">Valor Planificado</label>
                            <input type="number" name="planificacion" class="form-control"  id="inputNombre" placeholder="Ingrese el valor planificado" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputNombre">Valor Ejecutado</label>
                            <input type="number" name="ejecutado" class="form-control" id="inputNombre" placeholder="Ingrese el valor ejecutado">
                        </div>
                        <div class="form-group">
                            <label for="inputNombre">Umbral de Desviacion</label>
                            <input type="number" name="umbral" class="form-control"  id="inputNombre" placeholder="Ingrese el umbral de desviacion" required="">
                        </div>
                        <hr />
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
