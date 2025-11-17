<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$idTarea = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idTarea <= 0) {
    $_SESSION['flash'] = ["danger", "Tarea inválida"];
    header("Location: tarea.php");
    exit;
}

$cn = BDConexion::getInstancia();

// Datos de la tarea actual
$sql = "SELECT t.*, i.id_proyecto, i.id_iteracion
        FROM tarea t
        JOIN iteracion_tarea it ON it.id_tarea = t.id_tarea
        JOIN iteracion i ON i.id_iteracion = it.id_iteracion
        WHERE t.id_tarea = $idTarea LIMIT 1";
$datos = $cn->query($sql)->fetch_assoc();

if (!$datos) {
    $_SESSION['flash'] = ["danger", "Tarea no encontrada"];
    header("Location: tarea.php");
    exit;
}

$idProyecto = (int)$datos['id_proyecto'];

// Métricas asociadas originales
$sqlM = "SELECT id_metrica FROM metrica_tarea WHERE id_tarea = $idTarea";
$resM = $cn->query($sqlM);
$metricasActuales = [];
while ($m = $resM->fetch_assoc()) $metricasActuales[] = (int)$m['id_metrica'];

$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
?>
<html>

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modificar Tarea</title>

    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css">
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css">
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

    <style>
        .text-small {
            font-size: .85rem
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-3">
        <?php if (isset($_SESSION['flash'])): [$tipo, $msg] = $_SESSION['flash'];
            unset($_SESSION['flash']); ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $msg ?>
                <button class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <form action="tarea.modificar.procesar.php" method="post" id="formTarea">
            <div class="card shadow-sm">

                <div class="card-header">
                    <h3>Modificar Tarea</h3>
                </div>
                <div class="card-body">

                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control"
                            value="<?= htmlspecialchars($old['nombre'] ?? $datos['nombre']) ?>"
                            required minlength="3">
                        <small id="errorNombre" class="text-danger"></small>
                    </div>

                    <div class="form-group">
                        <label>Iteración *</label>
                        <select id="iteracion" name="iteracion" class="form-control" required></select>
                    </div>

                    <div class="form-group">
                        <label>Métricas *</label>
                        <div id="contenedorMetricas"><em>Cargando...</em></div>
                    </div>

                    <input type="hidden" name="id" value="<?= $idTarea ?>">
                    <input type="hidden" id="proyecto" value="<?= $idProyecto ?>">

                </div>

                <div class="card-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-success">
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
        // 🔹 Datos originales
        const idTarea = <?= $idTarea ?>;
        const nombreActual = <?= json_encode($datos['nombre']); ?>;
        const iteracionActual = <?= intval($datos['id_iteracion']); ?>;
        const metricasActuales = <?= json_encode($metricasActuales); ?>;

        // 🔹 Validación de nombre
        const regex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/():]+$/u;
        $("#nombre").on("input", function() {
            $("#errorNombre").text(regex.test($(this).val()) ? "" : "Solo se permiten letras, números y los símbolos . - _ / ( ) :");
        });

        // ------------------- AJUSTES AJAX -------------------

        // Cargar iteraciones
        function cargarIteraciones(sel = "") {
            $.get("tarea.iteraciones.php", {
                proyecto: $("#proyecto").val()
            }, res => {
                $("#iteracion").html(res);
                if (sel) $("#iteracion").val(sel);
            });
        }

        // Cargar métricas + marcar seleccionadas
        function cargarMetricas(itSel, metricasSel = []) {
            $.get("tarea.metricas.php", {
                proyecto: $("#proyecto").val(),
                iteracion: itSel,
                tarea: idTarea
            }, res => {
                $("#contenedorMetricas").html(res);
                metricasSel.forEach(id => $("#met_" + id).prop("checked", true));
            });
        }

        // ------------------- INIT -------------------
        $(function() {

            cargarIteraciones("<?= $old['iteracion'] ?? $datos['id_iteracion'] ?>");
            cargarMetricas(
                "<?= $old['iteracion'] ?? $datos['id_iteracion'] ?>",
                <?= json_encode($old['metricas'] ?? $metricasActuales) ?>
            );

            $("#iteracion").change(function() {
                cargarMetricas($(this).val(), metricasActuales);
            });

            $("#formTarea").on("submit", function(e) {
                e.preventDefault();

                const nuevoNombre = $("#nombre").val().trim();
                const nuevaIter = parseInt($("#iteracion").val());
                const nuevasMet = $("input[name='metricas[]']:checked").map(function() {
                    return parseInt($(this).val());
                }).get();

                let cambios = [];

                // 📝 Nombre exacto cambiado
                if (nuevoNombre !== nombreActual)
                    cambios.push(`- Nombre: "${nombreActual}" → "${nuevoNombre}"`);

                // 🔁 Iteración cambiada
                if (nuevaIter !== iteracionActual)
                    cambios.push(`- Iteración: ${iteracionActual} → ${nuevaIter}`);

                // 📊 Métricas agregadas y eliminadas
                const agregadas = nuevasMet.filter(x => !metricasActuales.includes(x));
                const removidas = metricasActuales.filter(x => !nuevasMet.includes(x));

                agregadas.forEach(id => {
                    cambios.push(`- Métrica agregada: ${$("#met_"+id).data("nombre")}`);
                });

                removidas.forEach(id => {
                    cambios.push(`- Métrica eliminada: ${$("#met_"+id).data("nombre")}`);
                });

                if (cambios.length === 0) {
                    alert("No se detectaron cambios.");
                    return;
                }

                if (confirm("Cambios detectados:\n\n" + cambios.join("\n") + "\n\n¿Confirmar?"))
                    this.submit();
            });
        });
    </script>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>