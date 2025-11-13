<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
if (!ControlAcceso::esAdminGlobal()) {
    header('Location: usuarios.php?msg=' . urlencode('Acceso restringido a administradores.') . '&type=danger');
    exit;
}
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
$forbiddenRoleIds = []; // ids de roles que no deben poder asignarse a proyectos

// roles prohibidos por nombre (comparación case-insensitive)
$forbiddenNames = ['administrador', 'superadmin', 'sin rol'];

foreach ($rolesArr as $r) {
    $rid = htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8');
    $rname = htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8');
    $nameLower = mb_strtolower(trim($r['nombre']), 'UTF-8');

    if (in_array($nameLower, $forbiddenNames, true)) {
        // registrar id prohibido y NO agregar a la plantilla de opciones
        $forbiddenRoleIds[] = $rid;
        continue;
    }

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
            // === Validación de nombre ===
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

            // === Validación de email ===
            const emailInput = $("#inputMail");
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

            // === Validación completa al enviar el formulario ===
            $("#createUserForm").on("submit", function(e) {
                const nombre = nameInput.val().trim();
                const email = emailInput.val().trim();
                const nameRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/;
                const gmailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;

                let valid = true;

                if (nombre === "" || !nameRegex.test(nombre)) {
                    errorName.text(nombre === "" ? "El nombre es obligatorio." : "El nombre solo puede contener letras y espacios.");
                    nameInput.addClass("is-invalid");
                    valid = false;
                }

                if (email === "" || !gmailRegex.test(email)) {
                    errorEmail.text(email === "" ? "El email es obligatorio." : "El correo debe ser un Gmail válido (ejemplo: usuario@gmail.com).");
                    emailInput.addClass("is-invalid");
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                    return false;
                }
            });
        });


        $(document).ready(function() {
            // inicializar plantilla maestra
            // plantilla inyectada en HTML (#projectTemplate)
            $('#btn_add_proyecto').click(function() {
                // calcular disponibilidad antes de agregar (trabajando con IDs)
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
                    alert('No hay proyectos disponibles para asignar. Modifique la fila existente.');
                    return;
                }
                agregarProyecto();
            });
            $("body").on('click', "#btn_del_proyecto", eliminarProyecto);

            // Inicializar opciones
            refreshProjectOptions();
        });
        // IDs de roles prohibidos (generado desde PHP)
        const forbiddenRoleIds = <?= json_encode($forbiddenRoleIds ?? []); ?>;

        function refreshProjectOptions() {
            try {
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

                // Mostrar/ocultar la tabla según si hay filas
                const rowCount = $('#tablaProyectos tbody tr').length;
                $('#tablaProyectos').toggle(rowCount > 0);
            } catch (e) {}
        }

        $(document).on('change', 'select[name="listaProyectos[]"]', function() {
            refreshProjectOptions();
        });

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
            // mostrar inmediatamente la tabla y actualizar opciones
            $("#tablaProyectos").show();
            setTimeout(refreshProjectOptions, 20);
        }

        function eliminarProyecto() {
            $(this).closest('tr').fadeOut("slow", function() {
                $(this).remove();
                refreshProjectOptions();
            });
        }
        $(document).ready(function() {
            $("#createUserForm").on("submit", function(e) {
                e.preventDefault();

                // Validación previa ya está implementada arriba
                const form = $(this);
                const formData = form.serialize();

                $.post("usuario.crear.procesar.php", formData)
                    .done(function(resp) {
                        console.log("📦 Respuesta del servidor:", resp);

                        let json;
                        try {
                            json = typeof resp === "object" ? resp : JSON.parse(resp);
                        } catch (err) {
                            console.error("❌ Error al parsear JSON:", err);
                            mostrarAlerta("Respuesta inesperada del servidor.", "danger");
                            return;
                        }

                        console.log("✅ JSON interpretado:", json);

                        if (json.success === true) {
                            console.log("➡️ Redirigiendo a usuarios.php ...");
                            const msg = encodeURIComponent(json.mensaje);
                            window.location.href = "usuarios.php?msg=" + msg + "&type=success";
                        } else {
                            console.warn("⚠️ json.success no es true:", json);
                            mostrarAlerta(json.mensaje || "Error desconocido.", "danger");
                        }
                    })
                    .fail(function() {
                        mostrarAlerta("⚠️ Error en la comunicación con el servidor.", "danger");
                    });

            });

            // Función para mostrar alertas Bootstrap
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
                    <!-- Contenedor de alertas -->
                    <div id="alertContainer"></div>

                    <h4>Propiedades</h4>
                    <div class="form-group">
                        <label for="inputNombre">Nombre</label>
                        <input type="text" name="nombre"
                            pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ ]+"
                            class="form-control" id="inputNombre"
                            placeholder="Ingrese el nombre del Usuario">
                    </div>
                    <div class="form-group">
                        <label for="inputMail">Email</label>
                        <input type="email" name="mail" class="form-control" id="inputMail" placeholder="Ingrese el email del Usuario">
                    </div>
                    <hr />
                    <!-- Proyectos Roles -->

                    <div class=" form-group">

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

                        <table class='table table-bordered' id="tablaProyectos" style="display:none;">
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
                        <a href="usuarios.php" class="btn btn-outline-danger" id="btn_cancelar"
                            onclick="return confirm('¿Está seguro que desea cancelar? Se perderán los datos no guardados.');">
                            <span class="oi oi-x"></span> Cancelar
                        </a>

                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>