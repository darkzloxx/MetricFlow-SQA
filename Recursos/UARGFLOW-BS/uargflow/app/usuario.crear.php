<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/ColeccionRoles.php';
$Roles = new ColeccionRoles();

$proyectos = "SELECT * FROM proyecto"; 
$proyectos=BDConexion::getInstancia()->query($proyectos);
$proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
$lista = "";
foreach ($proyecto as $Proyec) {
    $lista = $lista . ".append($('<option>').append('" . $Proyec['nombre'] . "'))";
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Usuario</title>
        <script>      
        $(document).ready(function(){
            $('#btn_add_proyecto').click(function(){
                agregarProyecto();
            });
            $("body").on('click', "#btn_del_proyecto", eliminarProyecto);
        });
       
        function agregarProyecto(){
            $("#tablaProyectos")
	.append
	(
		$('<tr>')
        .append
        (
        	$('<td>')
            .append
            (
            	$('<select>').addClass('form-control').attr('name', 'listaProyectos[]').attr('id', 'listaProyectos[]')
                <?= $lista; ?>
            )
        )
        .append
        (
        	$('<td>')
            .append
            (
            	$('<select>').addClass('form-control').attr('name', 'rol[]').attr('id', 'rol[]')
                .append($('<option>').append('Líder del Proyecto'))
                .append($('<option>').append('Gerente de Calidad'))
                .append($('<option>').append('Espectador'))
            )
        )
        .append
        (
        	$('<td>').addClass('text-center')
            .append
            (
            	$('<button>').attr('type', 'button').addClass('btn btn-danger').attr('id', 'btn_del_proyecto').attr('name', 'btn_del_proyecto').text('Eliminar')
            )            
        )        
    ); 
        }
        
        function eliminarProyecto(){
            $(this).parent().parent().fadeOut( "slow", function() { $(this).remove(); } );
            
        }

        </script>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="usuario.crear.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Crear Usuario</h3>
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
                            <input type="text" name="nombre" class="form-control" id="inputNombre" placeholder="Ingrese el nombre del Usuario" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Email</label>
                            <input type="email" name="mail" class="form-control" id="inputMail" placeholder="Ingrese el email del Usuario" required="">
                        </div>
                        <hr />
                         <!-- Proyectos Roles -->
  
                    <div class="form-group">

                  <label>
                    Proyectos:
                    &nbsp;&nbsp;
                    <button type="button" class='btn btn-primary' id="btn_add_proyecto">Nuevo</button>
                    
                  </label>
                  <table class='table table-bordered table-striped' id="tablaProyectos">
                    <tr>
                      <th>Proyecto:</th>
                      <th>Rol:</th>
                      <th>Eliminar:</th>
                    </tr>
                    <tr>
 
                    </tr>
                    
                  </table>                 

                </div>
                <br>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="usuarios.php">
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
