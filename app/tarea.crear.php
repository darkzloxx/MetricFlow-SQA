<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$idUsuario = (int)$usr->id;

// Proyectos válidos del usuario (con modelo + iteraciones)
$sqlProy = "
SELECT DISTINCT p.id_proyecto, p.nombre
FROM proyecto p
JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
WHERE up.id_usuario = $idUsuario
  AND (p.id_modelo_global IS NOT NULL OR p.id_modelo_personalizado IS NOT NULL)
  AND EXISTS (SELECT 1 FROM iteracion WHERE id_proyecto = p.id_proyecto)
ORDER BY p.nombre";
$proyectos = $cn->query($sqlProy)->fetch_all(MYSQLI_ASSOC);
?>
<html>

<head>
    <meta charset="UTF-8">
    <title>Crear Tarea</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css">
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css">
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-3">

        <?php if (isset($_SESSION['flash'])):
            [$tipo, $mensaje] = $_SESSION['flash'];
            unset($_SESSION['flash']); ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php
        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        ?>

        <form action="tarea.crear.procesar.php" method="post" id="formTarea">

            <div class="card shadow-sm">
                <div class="card-body">

                    <!-- PROYECTO -->
                    <div class="form-group">
                        <label>Proyecto *</label>
                        <select id="proyecto" name="proyecto" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($proyectos as $p): ?>
                                <option value="<?= $p['id_proyecto'] ?>"
                                    <?= ($old['proyecto'] ?? '') == $p['id_proyecto'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Nombre -->
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" id="nombre"
                            value="<?= htmlspecialchars($old['nombre'] ?? '') ?>"
                            class="form-control" required minlength="3">
                        <small id="errorNombre" class="text-danger"></small>
                    </div>


                    <!-- Iteración -->
                    <div class="form-group">
                        <label>Iteración *</label>
                        <select id="iteracion" name="iteracion"
                            class="form-control" required <?= empty($old['proyecto']) ? "disabled" : "" ?>>
                            <option value="">Seleccione proyecto...</option>
                        </select>
                    </div>

                    <!-- Métricas -->
                    <div class="form-group">
                        <label>Métricas disponibles *</label>
                        <div id="contenedorMetricas" class="border rounded p-2 text-muted"></div>
                        <small class="text-danger d-none" id="errMetricas"></small>
                    </div>

                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-outline-success">
                        <span class="oi oi-check"></span> Confirmar
                    </button>
                     <a href="tarea.php" class="btn btn-outline-danger" id="btn_cancelar"
                            onclick="return confirm('¿Está seguro que desea cancelar? Se perderán los datos no guardados.');">
                            <span class="oi oi-x"></span> Cancelar
                        </a>
                </div>
                
            </div>
        </form>

    </div>

    <script>
        $(function() {

            const regex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/():]+$/u;

            function validar($inp, $err, msgVacio) {
                let v = $inp.val().trim();
                if (v === '') {
                    $err.text(msgVacio);
                    $inp.addClass("is-invalid");
                    return false;
                }
                if (!regex.test(v)) {
                    $err.text("Solo se permiten letras, números y los símbolos . - _ / ( ) :");
                    $inp.addClass("is-invalid");
                    return false;
                }
                $err.text("");
                $inp.removeClass("is-invalid");
                return true;
            }

            // Validación en tiempo real
            $("#nombre").on("input", () => validar($("#nombre"), $("#errorNombre"), "El nombre es obligatorio."));

            // Validación submit
            $("#formTarea").on("submit", function(e) {
                let okN = validar($("#nombre"), $("#errorNombre"), "El nombre es obligatorio.");
                let okM = $("input[name='metricas[]']:checked").length > 0;

                if (!okM) {
                    $("#errMetricas").removeClass("d-none").text("Debe seleccionar al menos una métrica.");
                }

                if (!okN || !okD || !okM) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 150);
                }
            });

            // Carga de iteraciones
            function cargarIteraciones(p, idSel = "") {
                $.get("tarea.iteraciones.php", {
                    proyecto: p
                }, function(res) {
                    $("#iteracion").html(res).prop("disabled", false).val(idSel);
                });
            }

            // Carga de métricas
            function cargarMetricas(p, i, seleccionadas = []) {
                $("#contenedorMetricas").html("<em>Cargando...</em>");
                $.get("tarea.metricas.php", {
                    proyecto: p,
                    iteracion: i
                }, function(res) {
                    $("#contenedorMetricas").html(res);
                    seleccionadas.forEach(id => $("#m_" + id).prop("checked", true));
                });
            }

            // Evento cambio proyecto
            $("#proyecto").change(function() {
                let p = $(this).val();
                cargarIteraciones(p);
                $("#contenedorMetricas").html("Seleccione iteración...");
            });

            // Evento cambio iteración
            $("#iteracion").change(function() {
                let p = $("#proyecto").val();
                let i = $(this).val();
                cargarMetricas(p, i);
            });

            // Recargar si hubo error
            <?php if (!empty($old['proyecto'])): ?>
                cargarIteraciones("<?= $old['proyecto'] ?>", "<?= $old['iteracion'] ?? '' ?>");
                <?php if (!empty($old['metricas'])): ?>
                    cargarMetricas("<?= $old['proyecto'] ?>", "<?= $old['iteracion'] ?>", <?= json_encode($old['metricas']) ?>);
                <?php endif ?>
            <?php endif ?>

        });
    </script>


    <?php include_once '../gui/footer.php'; ?>
</body>

</html>