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
function id()
{
    return $_GET["id"];
}

function proycetos_roles()
{
    $id = $_GET["id"];

    // Obtener proyectos y roles actuales del usuario
    $usuario = "SELECT b.nombre AS nombre_proyecto, c.nombre AS nombre_rol
            FROM usuario_proyecto a
            LEFT JOIN proyecto b ON a.id_proyecto = b.id_proyecto
            LEFT JOIN rol c ON a.id_rol = c.id
            WHERE a.id_usuario = '" . $id . "'";

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
                    <option>' . htmlspecialchars($row["nombre_proyecto"]) . '</option>
                </select>
            </td>
            <td>
                <select class="form-control" name="rol[]">';
        foreach ($rolesSistema as $rol) {
            $selected = ($rol === $row["nombre_rol"]) ? "selected" : "";
            $html .= '<option ' . $selected . '>' . htmlspecialchars($rol) . '</option>';
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
$optionsHtml = "";
while ($p = mysqli_fetch_assoc($proyectos)) {
    $nombreSafe = htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8');
    $listaProyectos .= ".append($('<option>').append('" . $nombreSafe . "'))";
    $optionsHtml .= '<option>' . $nombreSafe . '</option>';
}

// Contadores para decidir si mostramos el botón "Nuevo"
$totalProjectsRow = BDConexion::getInstancia()->query("SELECT COUNT(*) AS total FROM proyecto");
$totalProjects = 0;
if ($totalProjectsRow) {
    $tmp = mysqli_fetch_assoc($totalProjectsRow);
    $totalProjects = (int)($tmp['total'] ?? 0);
}
$assignedRow = BDConexion::getInstancia()->query("SELECT COUNT(*) AS total FROM usuario_proyecto WHERE id_usuario = '" . $id . "'");
$assignedCount = 0;
if ($assignedRow) {
    $tmp2 = mysqli_fetch_assoc($assignedRow);
    $assignedCount = (int)($tmp2['total'] ?? 0);
}
// Mostrar botón Nuevo solo si hay proyectos no asignados aún
$showAddButton = ($totalProjects > $assignedCount);

$roles = BDConexion::getInstancia()->query("SELECT nombre FROM rol ORDER BY id ASC");
$listaRoles = "";
while ($r = mysqli_fetch_assoc($roles)) {
    $listaRoles .= ".append($('<option>').append('" . $r['nombre'] . "'))";
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
            // Inicializar plantilla maestra de proyectos
            // La plantilla real se inyecta en el HTML desde PHP (elemento #projectTemplate)

            $('#btn_add_proyecto').click(function() {
                // Antes de agregar, verificar si hay proyectos disponibles
                const master = $('#projectTemplate option').map(function() {
                    return $(this).text();
                }).get();
                const selected = $('select[name="listaProyectos[]"]').map(function() {
                    return $(this).val();
                }).get().filter(Boolean);
                const available = master.filter(p => selected.indexOf(p) === -1).length;
                if (available <= 0) {
                    alert('No hay proyectos disponibles para asignar. Modifique la fila existente.');
                    return;
                }
                agregarProyecto();
            });
            $("body").on('click', "#btn_del_proyecto", eliminarProyecto);

            // Inicializar opciones según proyectos ya asignados
            refreshProjectOptions();
        });

        // Evita que se pueda seleccionar el mismo proyecto en más de una fila
        function refreshProjectOptions() {
            try {
                // Master list de proyectos (desde plantilla)
                const master = $('#projectTemplate option').map(function() {
                    return $(this).text();
                }).get();
                // Recolectar valores seleccionados
                const selected = $('select[name="listaProyectos[]"]').map(function() {
                    return $(this).val();
                }).get().filter(Boolean);

                // Para cada select, reconstruir opciones desde el master y ocultar/deshabilitar las ya seleccionadas en otros selects
                $('select[name="listaProyectos[]"]').each(function() {
                    const $this = $(this);
                    const myVal = $this.val();
                    // reconstruir opciones
                    $this.html($('#projectTemplate').html());
                    // restaurar selección previa si existe en template
                    if (myVal) $this.val(myVal);
                    // deshabilitar las seleccionadas en otros selects
                    selected.forEach(function(val) {
                        if (val !== myVal) {
                            $this.find('option').filter(function() {
                                return $(this).text() === val;
                            }).prop('disabled', true).hide();
                        }
                    });
                    // si el valor actual quedó deshabilitado (ej. por inconsistencia), forzar a empty
                    if ($this.find('option:selected').prop('disabled')) {
                        $this.val('');
                    }
                });

                // Calcular proyectos disponibles (únicos)
                const uniqueSelected = Array.from(new Set(selected));
                const availableCount = master.filter(p => uniqueSelected.indexOf(p) === -1).length;
                // ocultar por completo el botón "Nuevo" cuando no haya proyectos disponibles
                $('#btn_add_proyecto').toggle(availableCount > 0);
            } catch (e) {}
        }

        // Actualizar opciones al cambiar selección
        $(document).on('change', 'select[name="listaProyectos[]"]', function() {
            refreshProjectOptions();
        });

        function agregarProyecto() {
            // Construir lista de proyectos disponibles (master - seleccionados)
            const master = $('#projectTemplate option').map(function() {
                return $(this).text();
            }).get();
            const selected = $('select[name="listaProyectos[]"]').map(function() {
                return $(this).val();
            }).get().filter(Boolean);
            const available = master.filter(p => selected.indexOf(p) === -1);
            if (!available.length) {
                // nada disponible (defensivo)
                return;
            }

            // Crear html de opciones sólo con los disponibles
            const optsHtml = available.map(p => '<option>' + p + '</option>').join('');

            const $row = $('<tr>')
                .append(
                    $('<td>').append(
                        $('<select>').addClass('form-control').attr('name', 'listaProyectos[]').attr('id', 'listaProyectos[]').html(optsHtml)
                    )
                )
                .append(
                    $('<td>').append(
                        $('<select>').addClass('form-control').attr('name', 'rol[]').attr('id', 'rol[]') <?= $listaRoles; ?>
                    )
                )
                .append(
                    $('<td>').addClass('text-center').append(
                        $('<button>').attr('type', 'button').addClass('btn btn-danger').attr('id', 'btn_del_proyecto').attr('name', 'btn_del_proyecto').text('Eliminar')
                    )
                );
            $("#tablaProyectos").append($row);
            // Después de agregar, refrescar las opciones para bloquear duplicados (normaliza selects)
            setTimeout(refreshProjectOptions, 20);
        }

        function eliminarProyecto() {
            $(this).closest('tr').fadeOut("slow", function() {
                $(this).remove();
                refreshProjectOptions();
            });
        }
    </script>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

     <style>
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background-color: #fff;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #212529;
        }
    </style>


</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a id="btnVolver" href="usuarios.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
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
                            <?php if ($showAddButton) { ?>
                                   <button type="button" class='btn btn-primary' id="btn_add_proyecto">        <span class="oi oi-plus mr-1"></span> Nuevo </button>
                            <?php } ?>
                        </label>

                        <!-- Plantilla oculta con la lista completa de proyectos -->
                        <div style="display:none;">
                            <select id="projectTemplate">
                                <?= $optionsHtml ?? '' ?>
                            </select>
                        </div>

                        <table class='table table-bordered' id="tablaProyectos">
                            <table class='table table-bordered table-striped' id="tablaProyectos">
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Rol</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?= proycetos_roles(); ?>
                            </tbody>
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