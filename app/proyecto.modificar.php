<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_PROYECTOS);
include_once '../modelo/BDConexion.Class.php';

$id = (int)($_GET['id'] ?? 0);
$bd = BDConexion::getInstancia();

// ==========================
// CARGA DE DATOS DEL PROYECTO
// ==========================
// 👇 agregamos "objetivo" en el SELECT
$stmt = $bd->prepare("SELECT id_proyecto, nombre, descripcion, objetivo, estado FROM proyecto WHERE id_proyecto = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$Proyecto = $res->fetch_assoc();
$stmt->close();

if (!$Proyecto) {
    die("Proyecto no encontrado.");
}
?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modificar Proyecto</title>

    <script>
        $(document).ready(function() {

            const nameInput = $("#inputNombre");
            const errorName = $("<div class='invalid-feedback d-block text-danger mt-1'></div>");
            nameInput.after(errorName);

            nameInput.on("input", function() {
                const val = nameInput.val().trim();
                const nameRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _-]+$/;
                if (val === "") {
                    errorName.text("El nombre es obligatorio.");
                    nameInput.addClass("is-invalid");
                } else if (!nameRegex.test(val)) {
                    errorName.text("El nombre solo puede contener letras, números, espacios, guiones o guiones bajos.");
                    nameInput.addClass("is-invalid");
                } else {
                    errorName.text("");
                    nameInput.removeClass("is-invalid");
                }
            });

            $("#editProjectForm").on("submit", function(e) {
                e.preventDefault();

                const nombreVal = $("#inputNombre").val().trim();
                const descVal = $("#inputDescripcion").val().trim();
                const objVal  = $("#inputObjetivo").val().trim(); // 👈 nuevo
                const estadoVal = $("#inputEstado").val();
                let valid = true;

                const nameRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _-]+$/;
                if (nombreVal === "") {
                    errorName.text("El nombre es obligatorio.");
                    nameInput.addClass("is-invalid");
                    valid = false;
                } else if (!nameRegex.test(nombreVal)) {
                    errorName.text("El nombre solo puede contener letras, números, espacios, guiones o guiones bajos.");
                    nameInput.addClass("is-invalid");
                    valid = false;
                } else {
                    errorName.text("");
                    nameInput.removeClass("is-invalid");
                }

                if (!valid) {
                    alert("Por favor, corrija los campos marcados antes de continuar.");
                    return false;
                }

                // ===== Detección de cambios =====
                const nombreActual = "<?= addslashes($Proyecto['nombre']); ?>";
                const descActual   = "<?= addslashes($Proyecto['descripcion']); ?>";
                const objActual    = "<?= addslashes($Proyecto['objetivo'] ?? ''); ?>";
                const estadoActual = "<?= addslashes($Proyecto['estado']); ?>";

                const cambios = [];
                if (nombreVal !== nombreActual) cambios.push(`- Nombre: "${nombreActual}" → "${nombreVal}"`);
                if (descVal !== descActual)     cambios.push(`- Descripción modificada`);
                if (objVal  !== objActual)      cambios.push(`- Objetivo modificado`);
                if (estadoVal !== estadoActual) cambios.push(`- Estado: "${estadoActual}" → "${estadoVal}"`);

                if (cambios.length === 0) {
                    alert("No se detectaron cambios para guardar.");
                    return false;
                }

                const msg = `Está a punto de modificar el proyecto "${nombreActual}".\n\nCambios detectados:\n${cambios.join("\n")}\n\n¿Desea confirmar los cambios?`;
                if (!confirm(msg)) return false;

                // Envío AJAX con el nuevo campo objetivo
                $.ajax({
                    url: "proyecto.modificar.procesar.php",
                    type: "POST",
                    dataType: "json",
                    data: $(this).serialize() + "&ajax=1",
                    success: function(resp) {
                        if (resp.success) {
                            window.location.href = "proyectos.php?msg=" + encodeURIComponent(resp.message) + "&type=success";
                        } else {
                            alert("Error: " + resp.message);
                        }
                    },
                    error: function(xhr) {
                        alert("Error al procesar: " + xhr.responseText);
                    }
                });
            });
        });
    </script>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <form id="editProjectForm" action="proyecto.modificar.procesar.php" method="post">
            <div class="card">
                <div class="card-header">
                    <h3>Modificar Proyecto</h3>
                    <p>Actualice los datos y presione <b>Confirmar</b>. Si desea cancelar, presione <b>Cancelar</b>.</p>
                </div>

                <div class="card-body">
                    <div class="form-group">
                        <label for="inputNombre">Nombre</label>
                        <input type="text" name="nombre" class="form-control" id="inputNombre"
                               value="<?= htmlspecialchars($Proyecto['nombre']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="inputDescripcion">Descripción <small class="text-muted">(opcional)</small></label>
                        <textarea name="descripcion" class="form-control" id="inputDescripcion" rows="4"><?= htmlspecialchars($Proyecto['descripcion']); ?></textarea>
                    </div>

                    <!-- 👇 Nuevo campo, misma lógica que Descripción -->
                    <div class="form-group">
                        <label for="inputObjetivo">Objetivo <small class="text-muted">(opcional)</small></label>
                        <textarea name="objetivo" class="form-control" id="inputObjetivo" rows="3"><?= htmlspecialchars($Proyecto['objetivo'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="inputEstado">Estado</label>
                        <select name="estado" id="inputEstado" class="form-control">
                            <?php
                            $estados = ['Registrado', 'En Progreso', 'Finalizado', 'Cancelado'];
                            foreach ($estados as $e) {
                                $sel = ($Proyecto['estado'] === $e) ? 'selected' : '';
                                echo "<option value='$e' $sel>$e</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <input type="hidden" name="id_proyecto" value="<?= $Proyecto['id_proyecto']; ?>">
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-outline-success">
                        <span class="oi oi-check"></span> Confirmar
                    </button>
                    <a href="proyectos.php" class="btn btn-outline-danger"
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
