<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/Usuario.Class.php';
include_once '../modelo/ColeccionRoles.php';

$id = $_GET["id"];
$Usuario = new Usuario($id);

// =============================
// FUNCIONES AUXILIARES
// =============================
function id() {
    return $_GET["id"];
}

function proycetos_roles() {
    $id = $_GET["id"];

    // Obtener proyectos y roles actuales del usuario
   $usuario = "SELECT b.nombre AS nombre_proyecto, c.nombre AS nombre_rol
            FROM usuario_proyecto a
            LEFT JOIN proyecto b ON a.id_proyecto = b.id_proyecto
            LEFT JOIN rol c ON a.id_rol = c.id
            WHERE a.id_usuario = '".$id."'";

    $usuarios = BDConexion::getInstancia()->query($usuario);

    // Obtener todos los roles disponibles del sistema
    $rolesSistema = [];
    $resRoles = BDConexion::getInstancia()->query("SELECT nombre FROM rol ORDER BY id ASC");
    while ($r = mysqli_fetch_assoc($resRoles)) {
        $rolesSistema[] = $r['nombre'];
    }

    // Generar HTML
    $html = '';
    while ($row = mysqli_fetch_assoc($usuarios)) {
        $html .= '<tr>
            <td>
                <select class="form-control" name="listaProyectos[]">
                    <option>'. htmlspecialchars($row["nombre_proyecto"]) .'</option>
                </select>
            </td>
            <td>
                <select class="form-control" name="rol[]">';
                foreach ($rolesSistema as $rol) {
                    $selected = ($rol === $row["nombre_rol"]) ? "selected" : "";
                    $html .= '<option '.$selected.'>'. htmlspecialchars($rol) .'</option>';
                }
        $html .= '</select>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger" id="btn_del_proyecto" name="btn_del_proyecto">Eliminar</button>
            </td>
        </tr>';
    }

    return $html;
}

// =============================
// CARGA DE PROYECTOS Y ROLES PARA EL JS
// =============================
$proyectos = BDConexion::getInstancia()->query("SELECT nombre FROM proyecto");
$listaProyectos = "";
while ($p = mysqli_fetch_assoc($proyectos)) {
    $listaProyectos .= ".append($('<option>').append('".$p['nombre']."'))";
}

$roles = BDConexion::getInstancia()->query("SELECT nombre FROM rol ORDER BY id ASC");
$listaRoles = "";
while ($r = mysqli_fetch_assoc($roles)) {
    $listaRoles .= ".append($('<option>').append('".$r['nombre']."'))";
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
    $(document).ready(function() {
        $('#btn_add_proyecto').click(function() {
            agregarProyecto();
        });
        $("body").on('click', "#btn_del_proyecto", eliminarProyecto);
    });

    function agregarProyecto() {
        $("#tablaProyectos").append(
            $('<tr>')
            .append(
                $('<td>')
                .append(
                    $('<select>')
                    .addClass('form-control')
                    .attr('name', 'listaProyectos[]')
                    .attr('id', 'listaProyectos[]')
                    <?= $listaProyectos; ?>
                )
            )
            .append(
                $('<td>')
                .append(
                    $('<select>')
                    .addClass('form-control')
                    .attr('name', 'rol[]')
                    .attr('id', 'rol[]')
                    <?= $listaRoles; ?>
                )
            )
            .append(
                $('<td>').addClass('text-center')
                .append(
                    $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-danger')
                    .attr('id', 'btn_del_proyecto')
                    .attr('name', 'btn_del_proyecto')
                    .text('Eliminar')
                )
            )
        );
    }

    function eliminarProyecto() {
        $(this).closest('tr').fadeOut("slow", function() { $(this).remove(); });
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
                    Complete los campos a continuación. Luego, presione <b>Confirmar</b>.<br />
                    Si desea cancelar, presione <b>Cancelar</b>.
                </p>
            </div>

            <div class="card-body">
                <div class="form-group">
                    <label for="inputNombre">Nombre</label>
                    <input type="text" name="nombre" class="form-control" id="inputNombre"
                           value="<?= htmlspecialchars($Usuario->getNombre()); ?>" required>
                </div>

                <div class="form-group">
                    <label for="inputEmail">Email</label>
                    <input type="email" name="email" class="form-control" id="inputEmail"
                           value="<?= htmlspecialchars($Usuario->getEmail()); ?>" required>
                </div>

                <input type="hidden" name="id" value="<?= $Usuario->getId(); ?>">
                <hr />

                <div class="form-group">
                    <label>
                        Proyectos:
                        <button type="button" class='btn btn-primary' id="btn_add_proyecto">Nuevo</button>
                    </label>

                    <table class='table table-bordered table-striped' id="tablaProyectos">
                        <tr>
                            <th>Proyecto:</th>
                            <th>Rol:</th>
                            <th>Eliminar:</th>
                        </tr>
                        <?= proycetos_roles(); ?>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-outline-success">
                    <span class="oi oi-check"></span> Confirmar
                </button>
                <a href="usuarios.php" class="btn btn-outline-danger">
                    <span class="oi oi-x"></span> Cancelar
                </a>
            </div>
        </div>
    </form>
</div>

<?php include_once '../gui/footer.php'; ?>
</body>
</html>
