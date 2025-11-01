<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_PROYECTOS);
?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Proyecto</title>

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

    <script>
        $(document).ready(function() {
            // === Validación del nombre ===
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

            // === Envío AJAX del formulario ===
            $("#createProjectForm").on("submit", function(e) {
                e.preventDefault();
                const nombre = nameInput.val().trim();
                const descripcion = $("#inputDescripcion").val().trim();
                const objetivo = $("#inputObjetivo").val().trim();
                const nameRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/;

                // Validación básica
                if (nombre === "" || !nameRegex.test(nombre)) {
                    errorName.text(nombre === "" ? "El nombre es obligatorio." : "El nombre solo puede contener letras y espacios.");
                    nameInput.addClass("is-invalid");
                    return;
                }

                $.post("proyecto.crear.procesar.php", {
                        nombre: nombre,
                        descripcion: descripcion,
                        objetivo: objetivo // 👈 nuevo campo opcional
                    })
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

                        if (json.success === true) {
                            const msg = encodeURIComponent(json.mensaje);
                            window.location.href = "proyectos.php?msg=" + msg + "&type=success";
                        } else {
                            mostrarAlerta(json.mensaje || "Error desconocido.", "danger");
                        }
                    })
                    .fail(function() {
                        mostrarAlerta("⚠️ Error en la comunicación con el servidor.", "danger");
                    });
            });

            // === Cancelar con confirmación ===
            $(document).on("click", "#btn_cancelar", function(e) {
                e.preventDefault();
                if (confirm("¿Está seguro que desea cancelar? Se perderán los datos no guardados.")) {
                    window.location.href = "proyectos.php";
                }
            });

            // === Función para mostrar alertas Bootstrap ===
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
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">

        <form id="createProjectForm" action="proyecto.crear.procesar.php" method="post">
            <div class="card">
                <div class="card-header">
                    <h3>Crear Proyecto</h3>
                    <p>
                        Complete los campos a continuación.<br>
                        Luego, presione <b>Confirmar</b>.<br>
                        Si desea cancelar, presione <b>Cancelar</b>.
                    </p>
                </div>

                <div class="card-body">
                    <div id="alertContainer"></div>
                    <div class="form-group">
                        <label for="inputNombre">Nombre</label>
                        <input type="text" name="nombre" id="inputNombre" class="form-control" placeholder="Ingrese el nombre del Proyecto" required>
                    </div>

                    <div class="form-group">
                        <label for="inputDescripcion">Descripción <small class="text-muted">(opcional)</small></label>
                        <textarea name="descripcion" id="inputDescripcion" class="form-control" placeholder="Ingrese una breve descripción" rows="5"></textarea>
                    </div>
                    <!-- 👇 Nuevo campo, misma lógica que Descripción -->
                    <div class="form-group">
                        <label for="inputObjetivo">Objetivo <small class="text-muted">(opcional)</small></label>
                        <textarea name="objetivo" id="inputObjetivo" class="form-control" placeholder="Describa el objetivo principal del proyecto" rows="5"></textarea>
                    </div>
                </div>


            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-outline-success">
                    <span class="oi oi-check"></span> Confirmar
                </button>
                <button type="button" id="btn_cancelar" class="btn btn-outline-danger">
                    <span class="oi oi-x"></span> Cancelar
                </button>
            </div>
    </div>
    </form>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>