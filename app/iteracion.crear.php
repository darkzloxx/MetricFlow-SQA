<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Solo líderes de proyecto
if (!ControlAcceso::verificaPermiso(PermisosSistema::ABM_ITERACIONES)) {
    header('Location: ../app/menu.php?msg=' . urlencode('Acceso restringido: solo líderes de proyecto pueden gestionar iteraciones.') . '&type=danger');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$idUsuario = (int)$usr->id;

// =============================================================
// 🔹 Cargar proyectos donde el usuario es líder
// =============================================================
$sqlProy = "
    SELECT p.id_proyecto, p.nombre
    FROM proyecto p
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    JOIN rol r ON r.id = up.id_rol
    WHERE up.id_usuario = {$idUsuario}
      AND LOWER(r.nombre) IN ('líder', 'líder de proyecto', 'lider de proyecto')
    ORDER BY p.nombre ASC";

$rsProy = $cn->query($sqlProy);
$proyectos = $rsProy ? $rsProy->fetch_all(MYSQLI_ASSOC) : [];
$unicoProyecto = count($proyectos) === 1;
// Cargar iteraciones existentes del proyecto (para validación de fechas)
$iteracionesExistentes = [];
if ($unicoProyecto) {
    $idP = (int)$proyectos[0]['id_proyecto'];
    $sqlIter = "SELECT fecha_inicio, fecha_fin FROM iteracion WHERE id_proyecto = {$idP} ORDER BY fecha_inicio ASC";
    $rsIter = $cn->query($sqlIter);
    $iteracionesExistentes = $rsIter ? $rsIter->fetch_all(MYSQLI_ASSOC) : [];
}

// =============================================================
// 🔹 Cargar fases
// =============================================================
$resFase = $cn->query("SELECT id_fase, nombre FROM fase ORDER BY id_fase ASC");
$fases = $resFase ? $resFase->fetch_all(MYSQLI_ASSOC) : [];

// Form data temporal
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

?>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Iteración</title>

    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />

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

        #errorFechas {
            display: none;
        }
    </style>
</head>

<body>

    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-4">

        <div class="mb-3">
            <a href="iteraciones.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-<?= ($_GET['type'] ?? '') === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['msg']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <script>
                $('html, body').animate({
                    scrollTop: 0
                }, 'fast');
            </script>
        <?php endif; ?>


        <!-- ===================== -->
        <!--     FORMULARIO        -->
        <!-- ===================== -->

        <form action="iteracion.crear.procesar.php" method="post" id="formIteracion">

            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="mb-0">Crear Iteración</h3>
                    <p class="text-muted mt-1 mb-0">Complete los campos requeridos y confirme la operación.</p>
                </div>

                <div class="card-body">

                    <!-- 🔹 PROYECTO -->
                    <?php if ($unicoProyecto): ?>
                        <input type="hidden" name="id_proyecto" value="<?= $proyectos[0]['id_proyecto']; ?>">
                        <div class="alert alert-info">
                            <strong>Proyecto seleccionado automáticamente:</strong>
                            <?= htmlspecialchars($proyectos[0]['nombre']); ?>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="proyecto">Proyecto</label>
                            <select id="proyecto" name="id_proyecto" class="form-control" required>
                                <option value="">Seleccione un proyecto...</option>
                                <?php foreach ($proyectos as $p): ?>
                                    <option value="<?= $p['id_proyecto']; ?>"
                                        <?= isset($form['id_proyecto']) && $form['id_proyecto'] == $p['id_proyecto'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- 🔹 FASE -->
                    <div class="form-group">
                        <label for="fase">Fase del proyecto</label>
                        <select id="fase" name="fase" class="form-control" required>
                            <option value="">Seleccione una fase...</option>
                            <?php foreach ($fases as $f): ?>
                                <option value="<?= $f['id_fase']; ?>"
                                    <?= isset($form['fase']) && $form['fase'] == $f['id_fase'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 🔹 NÚMERO DE ITERACIÓN -->
                    <div class="form-group">
                        <label for="inputNumero">Número de Iteración</label>
                        <input type="number" id="inputNumero" name="numero" class="form-control"
                            value="<?= $form['numero'] ?? '' ?>" readonly placeholder="Seleccione fase">
                    </div>

                    <!-- 🔹 FECHAS -->
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= $form['fecha_inicio'] ?? '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha de fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= $form['fecha_fin'] ?? '' ?>" required>
                    </div>

                    <div id="errorFechas" class="alert alert-danger"></div>

                    <!-- 🔹 OBJETIVO -->
                    <div class="form-group">
                        <label for="objetivo">Objetivo de la Iteración</label>
                        <input type="text" name="objetivo" id="objetivo" class="form-control"
                            placeholder="Ejemplo: Planificación del sprint"
                            value="<?= htmlspecialchars($form['objetivo'] ?? '') ?>">
                    </div>

                </div>

                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-success">
                        <span class="oi oi-check"></span> Confirmar
                    </button>
                    <a href="iteraciones.php" onclick="return confirm('¿Cancelar la creación de iteración? Se perderán los cambios no guardados.');"><button type="button" class="btn btn-outline-danger">
                            <span class="oi oi-x"></span> Cancelar
                        </button></a>
                </div>
            </div>
        </form>

    </div>

    <?php include_once '../gui/footer.php'; ?>


    <!-- ====================================== -->
    <!--     CARGA DINÁMICA DEL NÚMERO          -->
    <!-- ====================================== -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const faseSelect = document.getElementById("fase");
            const numeroInput = document.getElementById("inputNumero");
            const proyectoSelect = document.getElementById("proyecto");
            const idProyecto = <?= $unicoProyecto ? $proyectos[0]['id_proyecto'] : 'null' ?>;

            function cargarNumero() {
                const idFase = faseSelect.value;
                const proyecto = idProyecto ?? (proyectoSelect ? proyectoSelect.value : null);

                if (!idFase || !proyecto) {
                    numeroInput.value = '';
                    numeroInput.placeholder = 'Seleccione fase';
                    return;
                }

                fetch(`./ajax/next_iteracion.php?idProyecto=${proyecto}&idFase=${idFase}`)
                    .then(res => res.json())
                    .then(data => {
                        numeroInput.value = data.siguiente ?? '';
                    })
                    .catch(() => {
                        numeroInput.value = '';
                    });
            }

            faseSelect.addEventListener("change", cargarNumero);
            if (proyectoSelect) proyectoSelect.addEventListener("change", cargarNumero);

            // carga inicial si ya hay datos
            if (idProyecto && faseSelect.value) cargarNumero();
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const inputInicio = document.getElementById('fecha_inicio');
            const inputFin = document.getElementById('fecha_fin');
            const errorBox = document.getElementById('errorFechas');
            const proyectoSelect = document.getElementById('proyecto');
            const hoy = "<?= date('Y-m-d') ?>";

            let iteraciones = <?= json_encode($iteracionesExistentes); ?>;

            // -----------------------------
            // ACTUALIZA las iteraciones cuando cambia el proyecto
            // -----------------------------
            function cargarIteracionesProyecto(idProyecto) {

                if (!idProyecto) {
                    iteraciones = [];
                    aplicarRestricciones();
                    return;
                }

                fetch(`./ajax/iteraciones_proyecto.php?idProyecto=${idProyecto}`)
                    .then(res => res.json())
                    .then(data => {
                        iteraciones = data;
                        aplicarRestricciones();
                    });
            }

            // -----------------------------
            // APLICA restricciones de fecha
            // -----------------------------
            function aplicarRestricciones() {

                let minPermitido = hoy;

                if (iteraciones.length > 0) {

                    const ultima = iteraciones[iteraciones.length - 1];

                    // Convertir a Date()
                    const fin = new Date(ultima.fecha_fin);

                    // sumar 1 día
                    fin.setDate(fin.getDate() + 1);

                    // formato yyyy-mm-dd
                    const siguienteDia = fin.toISOString().split('T')[0];

                    if (siguienteDia > hoy) {
                        minPermitido = siguienteDia;
                    }
                }

                inputInicio.min = minPermitido;
                inputFin.min = minPermitido;

                validarFechas();
            }

            // -----------------------------
            // Detectar si una fecha cae en un rango ocupado
            // -----------------------------
            function fechaEnRango(fecha) {
                const f = new Date(fecha);

                return iteraciones.some(r => {
                    const ini = new Date(r.fecha_inicio);
                    const fin = new Date(r.fecha_fin);

                    // PROHIBIDO:
                    // iniciar el mismo día que finaliza otra
                    // finalizar el mismo día que inicia otra
                    if (f.getTime() === ini.getTime() || f.getTime() === fin.getTime()) {
                        return true;
                    }

                    // PROHIBIDO: caer dentro del rango
                    return f > ini && f < fin;
                });
            }


            // -----------------------------
            // Validación completa frontend
            // -----------------------------
            function validarFechas() {

                errorBox.style.display = 'none';
                const inicio = inputInicio.value;
                const fin = inputFin.value;

                if (!inicio || !fin) return true;

                let minPermitido = inputInicio.min;

                if (inicio < minPermitido) {
                    errorBox.innerHTML = `La fecha de inicio no puede ser anterior a ${minPermitido}.`;
                    errorBox.style.display = 'block';
                    return false;
                }

                if (fechaEnRango(inicio)) {
                    errorBox.innerHTML = `La fecha de inicio está dentro del rango de otra iteración.`;
                    errorBox.style.display = 'block';
                    return false;
                }

                if (fechaEnRango(fin)) {
                    errorBox.innerHTML = `La fecha de fin está dentro del rango de otra iteración.`;
                    errorBox.style.display = 'block';
                    return false;
                }

                if (fin <= inicio) {
                    errorBox.innerHTML = `La fecha de fin debe ser mayor que la fecha de inicio.`;
                    errorBox.style.display = 'block';
                    return false;
                }

                return true;
            }

            // -----------------------------
            // Eventos
            // -----------------------------
            if (proyectoSelect) {
                proyectoSelect.addEventListener("change", function() {
                    cargarIteracionesProyecto(this.value);
                });
            }

            inputInicio.addEventListener("change", validarFechas);
            inputFin.addEventListener("change", validarFechas);



            // Aplicar restricciones iniciales si el proyecto es único
            aplicarRestricciones();
        });
    </script>


</body>

</html>