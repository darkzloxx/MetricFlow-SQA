<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();

$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);

if (!$esAdmin && !$tienePermGestionMetricas) {
    http_response_code(403);
    echo "Acceso denegado";
    exit;
}

$cn = BDConexion::getInstancia();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: metricas.php?msg=" . urlencode("Métrica inválida.") . "&type=danger");
    exit;
}

// =========================================================
// 🔍 Cargar métrica
// =========================================================
$hasTipo = false;
$rsC = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'");
$hasTipo = ($rsC && $rsC->num_rows > 0);

$sqlMet = $hasTipo
    ? "SELECT id_metrica, nombre, descripcion, tipo FROM metrica WHERE id_metrica = $id LIMIT 1"
    : "SELECT id_metrica, nombre, descripcion FROM metrica WHERE id_metrica = $id LIMIT 1";

$rsM = $cn->query($sqlMet);
if (!$rsM || !$rsM->num_rows) {
    header("Location: metricas.php?msg=" . urlencode("La métrica no existe.") . "&type=danger");
    exit;
}

$metrica = $rsM->fetch_assoc();
$tipo = $hasTipo ? strtolower(trim($metrica["tipo"])) : "base";

// =========================================================
// ⛔ ADMIN — bloquear edición si la métrica base está en uso
// =========================================================
if ($esAdmin && $tipo === "base") {

    $idM = $id;

    // Está en uso en modelo global asignado a proyecto
    $q1 = "
        SELECT 1
        FROM metrica_modelo_calidad mmc
        JOIN proyecto p ON p.id_modelo_global = mmc.id_modelo
        WHERE mmc.id_metrica = $idM
        LIMIT 1
    ";
    $usoGlobal = ($cn->query($q1)->num_rows > 0);

    // Está en uso en modelo personalizado asignado a proyecto
    $q2 = "
        SELECT 1
        FROM metrica_proyecto_modelo mpm
        JOIN proyecto p ON p.id_modelo_personalizado = mpm.id_proyecto_modelo
        WHERE mpm.id_metrica = $idM
        LIMIT 1
    ";
    $usoPersonal = ($cn->query($q2)->num_rows > 0);

    // Está planificada en alguna iteración
    $q3 = "
        SELECT 1
        FROM metrica_iteracion
        WHERE id_metrica = $idM
        LIMIT 1
    ";
    $usoIteracion = ($cn->query($q3)->num_rows > 0);

    if ($usoGlobal || $usoPersonal || $usoIteracion) {
        header("Location: metricas.php?msg=" . urlencode("⚠️ No se puede editar una métrica base que está en uso por uno o más proyectos.") . "&type=danger");
        exit;
    }
}


// =========================================================
// ⛔ Reglas de acceso según tipo
// =========================================================

// BASE → solo Admin puede editar
if ($tipo === "base" && !$esAdmin) {
    http_response_code(403);
    echo "Acceso denegado (solo administrador puede editar métricas base)";
    exit;
}

// PERSONALIZADA → admin NO debe verlas
if ($tipo === "personalizada" && $esAdmin) {
    header("Location: metricas.php?msg=" . urlencode("Los administradores no editan métricas personalizadas.") . "&type=danger");
    exit;
}

// =========================================================
// 🔍 Cargar asociaciones existentes
// =========================================================

$seleccionadosProyecto = [];
$modelosProyecto = [];

if ($tipo === "personalizada") {

    // Desde qué modelo personalizado fue creada
    $rsSel = $cn->query("SELECT id_proyecto_modelo FROM metrica_proyecto_modelo WHERE id_metrica = $id");
    while ($r = $rsSel->fetch_assoc()) {
        $seleccionadosProyecto[] = (int)$r["id_proyecto_modelo"];
    }

    // Modelos personalizados donde pertenece el usuario
    $idUser = (int) $usr->id;

    $sqlPM = "
        SELECT pmc.id_proyecto_modelo, pmc.nombre, p.nombre AS proyecto
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
        WHERE up.id_usuario = $idUser
        ORDER BY p.nombre, pmc.nombre
    ";

    $rsPM = $cn->query($sqlPM);
    $modelosProyecto = $rsPM ? $rsPM->fetch_all(MYSQLI_ASSOC) : [];
}

?>
<html>

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA ?> - Editar Métrica</title>

    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css">
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css">
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

    <style>
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background: #fff;
        }
        .btn-outline-secondary:hover {
            background: #f8f9fa;
            color: #212529;
        }
    </style>
</head>

<body>

<?php include '../gui/navbar.php'; ?>

<div class="container">

    <div class="mb-3">
        <a href="metricas.php" class="btn btn-outline-secondary">
            <span class="oi oi-arrow-left mr-1"></span> Volver
        </a>
    </div>

    <form action="metrica.modificar.procesar.php" method="post">

        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="card">
            <div class="card-header">
                <h3>
                    Editar Métrica
                    <span class="badge badge-<?= $tipo === "base" ? "secondary" : "info" ?>">
                        <?= ucfirst($tipo) ?>
                    </span>
                </h3>
            </div>

            <div class="card-body">

                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text"
                           class="form-control"
                           id="nombre"
                           name="nombre"
                           maxlength="100"
                           required
                           value="<?= htmlspecialchars($metrica["nombre"]) ?>">
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text"
                           class="form-control"
                           id="descripcion"
                           name="descripcion"
                           maxlength="255"
                           value="<?= htmlspecialchars($metrica["descripcion"]) ?>">
                </div>

                <!-- ============================================ -->
                <!-- Asociaciones SOLO si es personalizada        -->
                <!-- ============================================ -->
                <?php if ($tipo === "personalizada"): ?>
                <div class="form-group">
                    <label>Modelos personalizados</label><br>

                    <?php if (empty($modelosProyecto)): ?>
                        <div class="text-muted">No tenés modelos personalizados asignados.</div>

                    <?php else:
                        foreach ($modelosProyecto as $mp):
                            $pid = (int)$mp["id_proyecto_modelo"];
                    ?>
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="mp<?= $pid ?>"
                                   name="modelos_proyecto[]"
                                   value="<?= $pid ?>"
                                <?= in_array($pid, $seleccionadosProyecto, true) ? "checked" : "" ?>>
                            <label class="form-check-label" for="mp<?= $pid ?>">
                                <?= htmlspecialchars($mp["proyecto"] . " — " . $mp["nombre"]) ?>
                            </label>
                        </div>
                    <?php endforeach; endif; ?>

                    <small class="form-text text-muted">
                        La métrica solo puede pertenecer a tus modelos personalizados.
                    </small>
                </div>
                <?php endif; ?>

            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-outline-success">
                    <span class="oi oi-check"></span> Guardar
                </button>

              <a href="metricas.php" class="btn btn-outline-danger" onclick="return confirm('¿Cancelar los cambios?');">
                        <span class="oi oi-x"></span> Cancelar
                    </a>
            </div>

        </div>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const form = document.querySelector("form");
    const nombreInput = document.querySelector("#nombre");
    const descInput = document.querySelector("#descripcion");

    const nombreOriginal = nombreInput.value.trim();
    const descOriginal = descInput.value.trim();

    const checkboxesOriginales = Array.from(
        document.querySelectorAll("input[type='checkbox']")
    ).map(c => c.checked);

    const regexNombre = /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s.-]+$/;

    form.addEventListener("submit", e => {
        e.preventDefault();

        const nombreActual = nombreInput.value.trim();
        const descActual = descInput.value.trim();
        const checkboxesActuales = Array.from(
            document.querySelectorAll("input[type='checkbox']")
        ).map(c => c.checked);

        let cambios = [];

        if (!regexNombre.test(nombreActual)) {
            alert("El nombre solo puede contener letras, espacios, guiones y puntos.");
            return;
        }

        if (descActual !== "" && !regexNombre.test(descActual)) {
            alert("La descripción solo puede contener letras, espacios, guiones y puntos.");
            return;
        }

        const algunoMarcado = document.querySelectorAll("input[type='checkbox']:checked").length > 0;

        // Solo obligatorio para modelos personalizados
        if (<?= json_encode($tipo === "personalizada") ?> && !algunoMarcado) {
            alert("Debe seleccionar al menos un modelo personalizado.");
            return;
        }

        if (nombreOriginal !== nombreActual) cambios.push(`- Nombre modificado`);
        if (descOriginal !== descActual) cambios.push(`- Descripción modificada`);

        let cambiosAsociaciones = false;
        for (let i = 0; i < checkboxesOriginales.length; i++) {
            if (checkboxesOriginales[i] !== checkboxesActuales[i]) {
                cambiosAsociaciones = true;
                break;
            }
        }

        if (cambiosAsociaciones) cambios.push("- Asociaciones modificadas");

        if (cambios.length === 0) {
            alert("No se detectaron cambios.");
            return;
        }

        if (confirm("Cambios detectados:\n\n" + cambios.join("\n") + "\n\n¿Confirmar?")) {
            form.submit();
        }
    });
});
</script>

<?php include '../gui/footer.php'; ?>
</body>

</html>
