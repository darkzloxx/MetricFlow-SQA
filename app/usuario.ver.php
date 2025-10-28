<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/Usuario.Class.php';

$Usuario = new Usuario($_GET["id"]);
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Propiedades del Usuario</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Propiedades del Usuario</h3>
                </div>
                <div class="card-body">
                    <h4 class="card-text">Nombre</h4>
                    <p> <?= $Usuario->getNombre(); ?></p>
                    <hr />
                    <h4 class="card-text">Email</h4>
                    <p> <?= $Usuario->getEmail(); ?></p>
                    <hr />
                    <h4 class="card-text">Proyectos:</h4>
                     <table class='table table-bordered table-striped' id="tablaProyectos">
                    <tr>
                      <th>Proyecto:</th>
                      <th>Rol:</th>
                    </tr>
                    <tr>
                    <?php $proyectos = "SELECT b.nombre as nombre_proyecto,c.nombre as nombre_rol FROM usuario_proyecto a 
                            left join proyecto b on a.id_proyecto = b.id_proyecto 
                            left join rol c on c.id = a.rol
                            WHERE a.id_usuario = '".$_GET["id"]."'";; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            //$proyecto = mysqli_fetch_array($proyectos); 
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC);
                            foreach ($proyecto as $Proyec) { ?>
                         <?= $coso = "
                <td>". $Proyec["nombre_proyecto"]."</td>
                      <td>". $Proyec["nombre_rol"]."</td>
            "; ?> 
                    <?php } ?>
                            </tr>
                    
                  </table>
                    <hr />
                    <h5 class="card-text">Opciones</h5>
                    <a href="usuarios.php">
                        <button type="button" class="btn btn-primary">
                            <span class="oi oi-account-logout"></span> Salir
                        </button>
                    </a>
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
