<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/Usuario.Class.php';

$id = (int)$_GET["id"];
$Usuario = new Usuario($id);
$bd = BDConexion::getInstancia();

// ==========================
// CARGA DE PROYECTOS Y ROLES
// ==========================

// Proyectos del sistema (para lista general)
$proyectosRes = $bd->query("SELECT id_proyecto, nombre FROM proyecto ORDER BY nombre ASC");
$proyectos = $proyectosRes->fetch_all(MYSQLI_ASSOC);

// Proyectos ya asignados al usuario
$asignadosRes = $bd->query("
    SELECT a.id_proyecto, b.nombre AS nombre_proyecto, c.id AS id_rol, c.nombre AS nombre_rol
    FROM usuario_proyecto a
    LEFT JOIN proyecto b ON a.id_proyecto = b.id_proyecto
    LEFT JOIN rol c ON a.id_rol = c.id
    WHERE a.id_usuario = {$id}
");
$asignados = $asignadosRes->fetch_all(MYSQLI_ASSOC);

// Roles (excluyendo los prohibidos)
$rolesRes = $bd->query("SELECT id, nombre FROM rol ORDER BY id ASC");
$rolesArr = $rolesRes->fetch_all(MYSQLI_ASSOC);
$forbiddenNames = ['administrador', 'superadmin', 'sin rol'];
$rolesOptionsHtml = '';
$forbiddenRoleIds = [];

foreach ($rolesArr as $r) {
    $rid = (int)$r['id'];
    $rname = htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8');
    $lower = mb_strtolower($r['nombre']);
    if (in_array($lower, $forbiddenNames, true)) {
        $forbiddenRoleIds[] = $rid;
        continue;
    }
    $rolesOptionsHtml .= '<option value="' . $rid . '">' . $rname . '</option>';
}

// Plantilla de proyectos (value = id)
$projectOptionsHtml = '';
foreach ($proyectos as $p) {
    $pid = (int)$p['id_proyecto'];
    $pname = htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8');
    $projectOptionsHtml .= '<option value="' . $pid . '">' . $pname . '</option>';
}

$rolesUsuario = $Usuario->getRoles() ?? [];
$esAdmin = false;
$esSuperAdmin = false;

foreach ($rolesUsuario as $rol) {
    // ✅ Accede con el getter en lugar de la propiedad protegida
    $nombreRol = mb_strtolower(trim($rol->getNombre() ?? ''), 'UTF-8');

    if ($nombreRol === 'administrador') $esAdmin = true;
    if ($nombreRol === 'superadmin') $esSuperAdmin = true;
}

$ocultarProyectos = ($esAdmin || $esSuperAdmin);


?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modificar Usuario</title>

    <script>
        const forbiddenRoleIds = <?= json_encode($forbiddenRoleIds) ?>;

        $(document).ready(function() {
            const ocultarProyectos = <?= $ocultarProyectos ? 'true' : 'false'; ?>;
            if (ocultarProyectos) {
                // Si es admin/superadmin, deshabilitamos toda la lógica de proyectos
                $('#btn_add_proyecto').remove();
                $('#tablaProyectos').remove();
            }

            // Handler delegado único para elementos que declaren data-confirm
            $(document).on('click', 'a[data-confirm], button[data-confirm]', function(e) {

                var msg = $(this).attr('data-confirm') || '¿Está seguro?';
                if (!confirm(msg)) {
                    +e.preventDefault();
                }
            });

            // === VALIDACIÓN NOMBRE ===
            const nameInput = $("#inputNombre");
            const errorName = $("<div class='invalid-feedback d-block text-danger mt-1'></div>");
            nameInput.after(errorName);

            nameInput.on("input", function() {
                const val = nameInput.val().trim();
                const nameRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/;
                if (val === "") {
                    errorName.text("El nombre es obligatorio.");
                    nameInput.addClass("is-invalid");
                } else if (!nameRegex.test(val)) {
                    errorName.text("El nombre solo puede contener letras y espacios.");
                    nameInput.addClass("is-invalid");
                } else {
                    errorName.text("");
                    nameInput.removeClass("is-invalid");
                }
            });

            // === VALIDACIÓN EMAIL ===
            const emailInput = $("#inputEmail");
            const errorEmail = $("<div class='invalid-feedback d-block text-danger mt-1'></div>");
            emailInput.after(errorEmail);

            emailInput.on("input", function() {
                const val = emailInput.val().trim();
                const gmailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
                if (val === "") {
                    errorEmail.text("El email es obligatorio.");
                    emailInput.addClass("is-invalid");
                } else if (!gmailRegex.test(val)) {
                    errorEmail.text("El correo debe ser un Gmail válido (ejemplo: usuario@gmail.com).");
                    emailInput.addClass("is-invalid");
                } else {
                    errorEmail.text("");
                    emailInput.removeClass("is-invalid");
                }
            });

            // === VALIDACIÓN GENERAL AL ENVIAR ===
            // === Confirmación inteligente con diferencias ===
            $("#editUserForm").on("submit", function(e) {
                e.preventDefault(); // primero detenemos el envío
                // === VALIDACIÓN GLOBAL ===
                const nombreVal = $("#inputNombre").val().trim();
                const emailVal = $("#inputEmail").val().trim();
                const nameRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/;
                const gmailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
                let valid = true;

                if (nombreVal === "" || !nameRegex.test(nombreVal)) {
                    $("#inputNombre").addClass("is-invalid");
                    $("#inputNombre").next(".invalid-feedback").text(
                        nombreVal === "" ? "El nombre es obligatorio." : "El nombre solo puede contener letras y espacios."
                    );
                    valid = false;
                }

                if (emailVal === "" || !gmailRegex.test(emailVal)) {
                    $("#inputEmail").addClass("is-invalid");
                    $("#inputEmail").next(".invalid-feedback").text(
                        emailVal === "" ? "El email es obligatorio." : "El correo debe ser un Gmail válido (ejemplo: usuario@gmail.com)."
                    );
                    valid = false;
                }

                if (!valid) {
                    mostrarAlerta("Debe completar todos los campos obligatorios.", "danger");
                    e.preventDefault();
                    return false;
                }


                const nombreActual = "<?= addslashes($Usuario->getNombre()); ?>";
                const emailActual = "<?= addslashes($Usuario->getEmail()); ?>";

                const nuevoNombre = $("#inputNombre").val().trim();
                const nuevoEmail = $("#inputEmail").val().trim();

                // Detectar cambios básicos
                const cambios = [];
                if (nuevoNombre !== nombreActual) {
                    cambios.push(`- Nombre: "${nombreActual}" → "${nuevoNombre}"`);
                }
                if (nuevoEmail !== emailActual) {
                    cambios.push(`- Email: "${emailActual}" → "${nuevoEmail}"`);
                }

                // Detectar cambios en proyectos/roles
                const proyectosOriginales = <?= json_encode($asignados) ?>; // desde PHP
                const proyectosNuevos = [];

                $("#tablaProyectos tbody tr").each(function() {
                    const idProyecto = $(this).find('select[name="listaProyectos[]"]').val();
                    const idRol = $(this).find('select[name="rol[]"]').val();
                    if (idProyecto && idRol) {
                        const nombreProyecto = $(this).find('select[name="listaProyectos[]"] option:selected').text();
                        const nombreRol = $(this).find('select[name="rol[]"] option:selected').text();
                        proyectosNuevos.push({
                            idProyecto,
                            idRol,
                            nombreProyecto,
                            nombreRol
                        });
                    }
                });

                // Comparar con los originales
                const idsOriginales = proyectosOriginales.map(p => p.id_proyecto.toString());
                const idsNuevos = proyectosNuevos.map(p => p.idProyecto.toString());

                // Proyectos eliminados
                proyectosOriginales.forEach(p => {
                    if (!idsNuevos.includes(p.id_proyecto.toString())) {
                        cambios.push(`- Proyecto "${p.nombre_proyecto}" eliminado`);
                    }
                });

                // Proyectos agregados o roles cambiados
                proyectosNuevos.forEach(p => {
                    const existente = proyectosOriginales.find(o => o.id_proyecto.toString() === p.idProyecto.toString());
                    if (!existente) {
                        cambios.push(`- Proyecto "${p.nombreProyecto}" agregado con rol "${p.nombreRol}"`);
                    } else if (existente.id_rol.toString() !== p.idRol.toString()) {
                        cambios.push(`- Proyecto "${p.nombreProyecto}": Rol "${existente.nombre_rol}" → "${p.nombreRol}"`);
                    }
                });

                // Si no hay cambios, advertir
                if (cambios.length === 0) {
                    alert("No se detectaron cambios para guardar.");
                    return false;
                }

                // Mostrar resumen
                const mensaje = `Está a punto de modificar el usuario "${nombreActual}".\n\nCambios detectados:\n${cambios.join("\n")}\n\n¿Desea confirmar los cambios?`;
                if (!confirm(mensaje)) return false;

                // --- Envío AJAX ---
                const formData = $(this).serialize() + "&ajax=1";

                $.post("usuario.modificar.procesar.php", formData)
                    .done(function(resp) {
                        let json;
                        try {
                            json = typeof resp === "object" ? resp : JSON.parse(resp);
                        } catch {
                            mostrarAlerta("Respuesta inesperada del servidor.", "danger");
                            return;
                        }

                        if (json.success) {
                            const msg = encodeURIComponent(json.message || "Usuario actualizado correctamente.");
                            window.location.href = "usuarios.php?msg=" + msg + "&type=success";
                        } else {
                            mostrarAlerta(json.message || "Error al actualizar el usuario.", "danger");
                        }
                    })
                    .fail(function(xhr) {
                        const msg = xhr.responseJSON?.error || "Error en la comunicación con el servidor.";
                        mostrarAlerta(msg, "danger");
                    });
            }); // <- cierre del on("submit")

            // --- Función alerta reutilizable ---
            function mostrarAlerta(mensaje, tipo) {
                const $alert = $(`
        <div class="alert alert-${tipo} alert-dismissible fade show mt-3" role="alert">
            ${mensaje}
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);
                $("#alertContainer").html($alert);
                $("html, body").animate({
                    scrollTop: 0
                }, "fast");
                setTimeout(() => $alert.alert("close"), 3500);
            }

            // === LÓGICA PROYECTOS/ROLES ===
            refreshProjectOptions();

            $('#btn_add_proyecto').click(function() {
                const master = $('#projectTemplate option').map(function() {
                    return {
                        v: $(this).val(),
                        t: $(this).text()
                    };
                }).get();
                const selected = $('select[name="listaProyectos[]"]').map(function() {
                    return $(this).val();
                }).get().filter(Boolean);
                const available = master.filter(p => selected.indexOf(p.v) === -1).length;
                if (available <= 0) {
                    alert('No hay proyectos disponibles para asignar.');
                    return;
                }
                agregarProyecto();
            });

            $("body").on('click', "#btn_del_proyecto", eliminarProyecto);

            $(document).on('submit', '#editUserForm', function(e) {
                $('select[name="listaProyectos[]"] option, select[name="rol[]"] option').prop('disabled', false);

                const rows = $('#tablaProyectos tbody tr');
                let invalid = false,
                    forbidden = false;
                rows.each(function() {
                    const proj = $(this).find('select[name="listaProyectos[]"]').val();
                    const rol = $(this).find('select[name="rol[]"]').val();
                    if ((proj && !rol) || (rol && !proj)) invalid = true;
                    if (rol && forbiddenRoleIds.indexOf(parseInt(rol)) !== -1) forbidden = true;
                });
                if (invalid) {
                    e.preventDefault();
                    alert('Complete Proyecto y Rol en todas las filas.');
                } else if (forbidden) {
                    e.preventDefault();
                    alert('No puede asignar roles de Administrador, Superadmin o Sin rol.');
                }
            });
        });

        function refreshProjectOptions() {
            const master = $('#projectTemplate option').map(function() {
                return {
                    v: $(this).val(),
                    t: $(this).text()
                };
            }).get();
            const selected = $('select[name="listaProyectos[]"]').map(function() {
                return $(this).val();
            }).get().filter(Boolean);

            $('select[name="listaProyectos[]"]').each(function() {
                const $this = $(this);
                const myVal = $this.val();
                $this.html($('#projectTemplate').html());
                if (myVal) $this.val(myVal);
                selected.forEach(val => {
                    if (val !== myVal) $this.find('option[value="' + val + '"]').prop('disabled', true).hide();
                });
                if ($this.find('option:selected').prop('disabled')) $this.val('');
            });

            const uniqueSelected = [...new Set(selected)];
            const availableCount = master.filter(p => uniqueSelected.indexOf(p.v) === -1).length;
            $('#btn_add_proyecto').toggle(availableCount > 0);

            const rowCount = $('#tablaProyectos tbody tr').length;
            $('#tablaProyectos').toggle(rowCount > 0);
        }

        function agregarProyecto() {
            const master = $('#projectTemplate option').map(function() {
                return {
                    v: $(this).val(),
                    t: $(this).text()
                };
            }).get();
            const selected = $('select[name="listaProyectos[]"]').map(function() {
                return $(this).val();
            }).get().filter(Boolean);
            const available = master.filter(p => selected.indexOf(p.v) === -1);
            if (!available.length) return;

            const optsHtml = available.map(p => '<option value="' + p.v + '">' + p.t + '</option>').join('');
            const $row = $('<tr>')
                .append($('<td>').append($('<select>').addClass('form-control').attr('name', 'listaProyectos[]').html(optsHtml)))
                .append($('<td>').append($('<select>').addClass('form-control').attr('name', 'rol[]').html($('#roleTemplate').html())))
                .append($('<td>').addClass('text-center').append($('<button>').attr('type', 'button').addClass('btn btn-danger btn-sm').attr('id', 'btn_del_proyecto').text('Eliminar')));
            $("#tablaProyectos tbody").append($row);
            $("#tablaProyectos").show();
            setTimeout(refreshProjectOptions, 20);
        }

        function eliminarProyecto() {
            // Confirmación antes de eliminar la fila (muestra el nombre del proyecto si está disponible)

            var $tr = $(this).closest('tr');
            var nombreProyecto = $tr.find('select[name="listaProyectos[]"] option:selected').text() || '';
            var msg = '¿Confirma que desea eliminar esta asignación';
            if (nombreProyecto) msg += ' del proyecto \"' + nombreProyecto + '\"';
            msg += '?';
            if (!confirm(msg)) {

                return;
            }
            $tr.fadeOut("slow", function() {
                +$(this).remove();
                refreshProjectOptions();
            });
        }
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


        <form id="editUserForm" action="usuario.modificar.procesar.php" method="post">
            <div class="card">
                <div class="card-header">
                    <h3>Modificar Usuario</h3>
                    <p>Actualice los datos y presione <b>Confirmar</b>. Si desea cancelar, presione <b>Cancelar</b>.</p>


                </div>

                <div class="card-body">
                    <div id="alertContainer"></div>

                    <div class="form-group">
                        <label for="inputNombre">Nombre</label>
                        <input type="text" name="nombre" class="form-control" id="inputNombre"
                            value="<?= htmlspecialchars($Usuario->getNombre()); ?>">
                    </div>
                    <div class="form-group">
                        <label for="inputEmail">Email</label>
                        <input type="email" name="email" class="form-control" id="inputEmail"
                            value="<?= htmlspecialchars($Usuario->getEmail()); ?>">
                    </div>

                    <input type="hidden" name="id" value="<?= $Usuario->getId(); ?>">
                    <hr />

                    <?php if (!$ocultarProyectos): ?>
                        <label>
                            Proyecto/s:
                            <button type="button" class="btn btn-primary btn-sm" id="btn_add_proyecto">
                                <span class="oi oi-plus mr-1"></span> Nuevo
                            </button>
                        </label>

                        <!-- Plantillas ocultas -->
                        <div style="display:none;">
                            <select id="projectTemplate"><?= $projectOptionsHtml ?></select>
                            <select id="roleTemplate"><?= $rolesOptionsHtml ?></select>
                        </div>

                        <table class="table table-bordered" id="tablaProyectos" <?= empty($asignados) ? 'style="display:none;"' : '' ?>>
                            <thead>
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Rol</th>
                                    <th>Eliminar</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php foreach ($asignados as $row): ?>
                                    <tr>
                                        <td>
                                            <select class="form-control" name="listaProyectos[]">
                                                <?php foreach ($proyectos as $p): ?>
                                                    <option value="<?= $p['id_proyecto']; ?>" <?= $p['id_proyecto'] == $row['id_proyecto'] ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($p['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control" name="rol[]">
                                                <?php foreach ($rolesArr as $r):
                                                    $rid = (int)$r['id'];
                                                    if (in_array(mb_strtolower($r['nombre']), $forbiddenNames, true)) continue;
                                                ?>
                                                    <option value="<?= $rid; ?>" <?= $rid == $row['id_rol'] ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($r['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm" id="btn_del_proyecto">Eliminar</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <strong>Nota:</strong> Los usuarios con rol <b>Administrador</b> o <b>SuperAdmin</b> no pueden tener proyectos asignados ni cambiar su rol.
                        </div>
                    <?php endif; ?>

                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-outline-success">
                        <span class="oi oi-check"></span> Confirmar
                    </button>
                    <a href="usuarios.php" class="btn btn-outline-danger" id="btn_cancelar"
                        onclick="return confirm('¿Está seguro que desea cancelar? Se perderán los cambios no guardados.');">
                        <span class="oi oi-x"></span> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>