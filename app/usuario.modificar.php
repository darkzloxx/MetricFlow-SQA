<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/Usuario.Class.php';
include_once '../modelo/ColeccionRoles.php';
$id = $_GET["id"];
$Usuario = new Usuario($id);
function id(){
    $id = $_GET["id"];
    return $id;
}
function proycetos_roles(){
    $id = id();
  $usuario = "SELECT b.nombre as nombre_proyecto,c.nombre as nombre_rol FROM usuario_proyecto a 
  left join proyecto b on a.id_proyecto = b.id_proyecto 
  left join rol c on c.id = a.rol
  WHERE a.id_usuario = '".$id."'"; 
  $usuarios=BDConexion::getInstancia()->query($usuario);
  $primero = 0;
  $html='';
  if ($row = mysqli_fetch_array($usuarios)){
    do{
       if($primero == 0){
            $html.=     '<tr>
                <td><select class="form-control" id="proyecto[]" name="proyecto[]">
                <option>'. $row["nombre_proyecto"].'</option>
            </select></td>
                      <td><select class="form-control" id="rol[]" name="rol[]">
                <option>'. $row["nombre_rol"].'</option>
            </select></td>
            <td class="text-center">
                            <button type="button" class="btn btn-danger" id="btn_del_proyecto" name="btn_del_proyecto">Eliminar</button>
                        </td>
            </tr>';
        ++$primero;
        }
        else{
        $html.=     '<tr>
                <td><select class="form-control" id="proyecto[]" name="proyecto[]">
                <option>'. $row["nombre_proyecto"].'</option>
            </select></td>
                      <td><select class="form-control" id="rol[]" name="rol[]">
                <option>'. $row["nombre_rol"].'</option>
            </select></td>
            <td class="text-center">
                            <button type="button" class="btn btn-danger" id="btn_del_proyecto" name="btn_del_proyecto">Eliminar</button>
                        </td>
            </tr>';
        ++$primero;
        }
    }while($row = mysqli_fetch_array($usuarios));
   }
   else{
       if($primero == 0){
       $html.=     '';
       }
       
   }
   return $html;
}

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
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Actualizar Usuario</title>
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
            <form action="usuario.modificar.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Actualizar Usuario</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="inputNombre">Nombre</label>
                            <input type="text" name="nombre" class="form-control" id="inputNombre" value="<?= $Usuario->getNombre(); ?>" placeholder="Ingrese el nombre del usuario" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputEmail">Email</label>
                            <input type="email" name="email" class="form-control" id="inputEmail" value="<?= $Usuario->getEmail() ?>" placeholder="Ingrese el email del usuario" required="">
                        </div>

                        <input type="hidden" name="id" class="form-control" id="id" value="<?= $Usuario->getId(); ?>" >
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
                        <?php
                       echo $output = proycetos_roles()
                        ?>  
                    </tr>
                    
                  </table>                 

                </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span>
                            Confirmar
                        </button>
                        <a href="usuarios.php">
                            <button type="button" class="btn btn-outline-danger">
                                <span class="oi oi-x"></span>
                                Cancelar
                            </button>
                        </a>
                    </div>
                </div>
            </form>
        </div>
          <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
