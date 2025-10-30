<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/ColeccionRoles.php';
$Roles = new ColeccionRoles();

$proyectos = "SELECT id_proyecto, nombre FROM proyecto";
$proyectos = BDConexion::getInstancia()->query($proyectos);
$proyecto = $proyectos->fetch_all(MYSQLI_ASSOC);
$lista = "";
$optionsHtml = "";
foreach ($proyecto as $Proyec) {
    $id = htmlspecialchars($Proyec['id_proyecto'], ENT_QUOTES, 'UTF-8');
    $nombreSafe = htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8');
    $lista = $lista . ".append($('<option>').append('" . $nombreSafe . "'))";
    $optionsHtml .= '<option value="' . $id . '">' . $nombreSafe . '</option>';
}

// Cargar roles desde la BD y generar plantilla para el SELECT de roles (value = id)
$rolesRes = BDConexion::getInstancia()->query("SELECT id, nombre FROM rol");
$rolesArr = $rolesRes->fetch_all(MYSQLI_ASSOC);
$rolesOptionsHtml = '';
foreach ($rolesArr as $r) {
    $rid = htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8');
    $rname = htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8');
    $rolesOptionsHtml .= '<option value="' . $rid . '">' . $rname . '</option>';
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
        $(document).ready(function() {
            // inicializar plantilla maestra
            // plantilla inyectada en HTML (#projectTemplate)
            $('#btn_add_proyecto').click(function() {
                // calcular disponibilidad antes de agregar (trabajando con IDs)
                const master = $('#projectTemplate option').map(function() {
                    return { v: $(this).val(), t: $(this).text() };
                }).get();
                const selected = $('select[name="listaProyectos[]"]').map(function() {
                    return $(this).val();
                }).get().filter(Boolean);
                const available = master.filter(p => selected.indexOf(p.v) === -1).length;
                if (available <= 0) {
                    alert('No hay proyectos disponibles para asignar. Modifique la fila existente.');
                    return;
                }
                agregarProyecto();
            });
            $("body").on('click', "#btn_del_proyecto", eliminarProyecto);

            // Inicializar opciones
            refreshProjectOptions();
        });

        function refreshProjectOptions() {
            try {
                const master = $('#projectTemplate option').map(function() {
                    return { v: $(this).val(), t: $(this).text() };
                }).get();
                const selected = $('select[name="listaProyectos[]"]').map(function() {
                    return $(this).val();
                }).get().filter(Boolean);

                $('select[name="listaProyectos[]"]').each(function() {
                    const $this = $(this);
                    const myVal = $this.val();
                    $this.html($('#projectTemplate').html());
                    if (myVal) $this.val(myVal);
                    selected.forEach(function(val) {
                        if (val !== myVal) {
                            $this.find('option').filter(function() {
                                return $(this).val() === val;
                            }).prop('disabled', true).hide();
                        }
                    });
                    if ($this.find('option:selected').prop('disabled')) {
                        $this.val('');
                    }
                });

                const uniqueSelected = Array.from(new Set(selected));
                const availableCount = master.filter(p => uniqueSelected.indexOf(p.v) === -1).length;
                // ocultar por completo el botón "Nuevo" cuando no haya proyectos disponibles
                $('#btn_add_proyecto').toggle(availableCount > 0);
            } catch (e) {}
        }

        $(document).on('change', 'select[name="listaProyectos[]"]', function() {
            refreshProjectOptions();
        });

        function agregarProyecto() {
            const master = $('#projectTemplate option').map(function() {
                return { v: $(this).val(), t: $(this).text() };
            }).get();
            const selected = $('select[name="listaProyectos[]"]').map(function() {
                return $(this).val();
            }).get().filter(Boolean);
            const available = master.filter(p => selected.indexOf(p.v) === -1);
            if (!available.length) return;

            const optsHtml = available.map(p => '<option value="' + p.v + '">' + p.t + '</option>').join('');

            const $row = $('<tr>')
                .append(
                    $('<td>').append(
                        $('<select>').addClass('form-control')
                        .attr('name', 'listaProyectos[]') // ✅ name es lo que importa
                        .html(optsHtml)
                    )
                )
                .append(
                    $('<td>').append(
                        // utilizar la plantilla de roles (value = id, texto = nombre)
                        $('<select>').addClass('form-control')
                        .attr('name', 'rol[]')
                        .html($('#roleTemplate').html())
                    )
                )
                .append(
                    $('<td>').addClass('text-center').append(
                        $('<button>').attr('type', 'button')
                        .addClass('btn btn-danger')
                        .addClass('btn-sm')
                        .attr('id', 'btn_del_proyecto')
                        .text('Eliminar')
                    )
                );

            $("#tablaProyectos tbody").append($row);
            setTimeout(refreshProjectOptions, 20);
        }

        function eliminarProyecto() {
            $(this).closest('tr').fadeOut("slow", function() {
                $(this).remove();
                refreshProjectOptions();
            });
        }
        // Validar antes de enviar: asegurarse que si hay filas, cada una tenga proyecto y rol
        $(document).on('submit', '#createUserForm', function(e) {
            // habilitar opciones deshabilitadas para que los valores seleccionados se envíen
            $('select[name="listaProyectos[]"] option, select[name="rol[]"] option').prop('disabled', false);

            const rows = $('#tablaProyectos tbody tr');
            let invalid = false;
            rows.each(function(idx, tr) {
                const proj = $(tr).find('select[name="listaProyectos[]"]').val();
                const rol = $(tr).find('select[name="rol[]"]').val();
                // si hay proyecto pero no rol, marcar inválido
                if ((proj && proj.trim() !== '') && (!rol || rol.trim() === '')) {
                    invalid = true;
                    return false; // break
                }
                // si hay rol pero no proyecto, también inválido
                if ((rol && rol.trim() !== '') && (!proj || proj.trim() === '')) {
                    invalid = true;
                    return false;
                }
            });
            if (invalid) {
                e.preventDefault();
                alert('Por favor, complete Proyecto y Rol en todas las filas antes de enviar.');
                return false;
            }
            // otherwise allow submit
        });
    </script>
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
        <form id="createUserForm" action="usuario.crear.procesar.php" method="post">
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
                        <input type="text" name="nombre" pattern="[A-Za-z]+" class="form-control" id="inputNombre" placeholder="Ingrese el nombre del Usuario" required="">
                    </div>
                    <div class="form-group">
                        <label for="inputMail">Email</label>
                        <input type="email" name="mail" class="form-control" id="inputMail" placeholder="Ingrese el email del Usuario" required="">
                    </div>
                    <hr />
                    <!-- Proyectos Roles -->

                    <div class="form-group">

                        <label>
                            Proyecto/s:
                            &nbsp;&nbsp;
                            <button type="button" class='btn btn-primary' id="btn_add_proyecto"> <span class="oi oi-plus mr-1"></span> Nuevo
                            </button>

                        </label>

                        <!-- Plantilla oculta con la lista completa de proyectos y roles (value = id) -->
                        <div style="display:none;">
                            <select id="projectTemplate">
                                <?= $optionsHtml ?? '' ?>
                            </select>
                            <select id="roleTemplate">
                                <?= $rolesOptionsHtml ?? '' ?>
                            </select>
                        </div>

                        <table class='table table-bordered' id="tablaProyectos">
                            <thead class="thead-metricflow">
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Rol</th>
                                    <th>Eliminar</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
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