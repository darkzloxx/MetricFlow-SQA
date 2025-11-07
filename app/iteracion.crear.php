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
        
        <script type="text/javascript">
            const fechaInicioInput = document.getElementById('fecha_inicio');
            const fechaFinInput = document.getElementById('fecha_fin');

            fechaInicioInput.addEventListener('change', function() {
            // Aquí puedes validar que la fecha de inicio no sea mayor que la de fin.
            // O hacer que la fecha de fin no sea menor que la de inicio.
            fechaFinInput.min = fechaInicioInput.value;
            });

            fechaFinInput.addEventListener('change', function() {
            fechaInicioInput.max = fechaFinInput.value;
            });

            function validarFechas() {
            const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
            const fechaFin = new Date(document.getElementById('fecha_fin').value);

            if (fechaFin.getTime() < fechaInicio.getTime()) {
                $('#myModalFecha').modal('show');
                event.preventDefault();
                return false; // O alguna otra acción, como limpiar el campo
            }
            return true;
            }
        </script>  
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Iteración</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="iteracion.crear.procesar.php" method="post" onSubmit="validarFechas()">
                <div class="card">
                    <div class="card-header">
                        <h3>Crear Iteración</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <h4>Propiedades</h4>
                        <div class="form-group">
                            <label for="inputNombre">Numero de Iteración</label>
                            <input type="number" name="nombre" class="form-control" id="inputNombre" placeholder="Ingrese el numero de la iteración" required="">
                        </div>
                        <div class="form-group">
                        <label for="fecha_inicio">Fecha de inicio:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required="">
                        </div>
                        <div class="form-group">
                        <label for="fecha_fin">Fecha de fin:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputNombre">Objetivos de la iteración</label>
                            <input type="text" name="objetivo" class="form-control" id="inputNombre" placeholder="Ingrese un breve objetivo de la iteración">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Fase</label>
                            <br>
                            <select id="fase" name="fase" class="form-control">
                            <?php 
                            $proyectos = "SELECT * FROM fase"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { ?>
                            <option value="<?= $Proyec['id_fase']; ?>" ><?= $Proyec['nombre']; ?></option>
                            <?php } ?>
                            </select>
                        </div>
                        <hr />
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="iteracion.php">
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
