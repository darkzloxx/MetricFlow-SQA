<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cn = BDConexion::getInstancia();

// Obtener la iteración actual
$sql = "
    SELECT i.*, p.id_proyecto, f.nombre AS nombre_fase
    FROM iteracion i
    JOIN fase f ON i.id_fase = f.id_fase
    JOIN proyecto p ON p.id_proyecto = i.id_proyecto
    WHERE i.id_iteracion = $id
    LIMIT 1
";

$res = $cn->query($sql);
$iteracion = $res ? $res->fetch_assoc() : null;

if (!$iteracion) {
    die('<div class="alert alert-danger m-4">Iteración no encontrada.</div>');
}

$idProyecto = (int)$iteracion['id_proyecto'];

// Iteraciones anteriores (EXCLUYE la actual)
$sqlIter = "
    SELECT fecha_inicio, fecha_fin
    FROM iteracion
    WHERE id_proyecto = $idProyecto
      AND id_iteracion != $id
    ORDER BY fecha_inicio ASC
";

$resI = $cn->query($sqlIter);
$iteraciones = $resI ? $resI->fetch_all(MYSQLI_ASSOC) : [];
?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Actualizar Iteración</title>

    <style>
        #errorFechas {
            display: none;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-4">
        <form action="iteracion.modificar.procesar.php" method="post" id="formIteracion">

            <div class="card shadow-sm">
                <div class="card-header">
                    <h3>Actualizar Iteración</h3>
                </div>

                <div class="card-body">

                    <!-- Número -->
                    <div class="form-group">
                        <label>Número de Iteración</label>
                        <input type="text" class="form-control" value="<?= $iteracion['numero_iteracion']; ?>" readonly>
                    </div>

                    <!-- Objetivo -->
                    <div class="form-group">
                        <label>Objetivo</label>
                        <input type="text" name="objetivo" required class="form-control"
                            value="<?= htmlspecialchars($iteracion['objetivo']); ?>">
                    </div>

                    <!-- Fechas -->
                    <div class="form-group">
                        <label>Fecha de inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control"
                            value="<?= $iteracion['fecha_inicio']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Fecha de fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control"
                            value="<?= $iteracion['fecha_fin']; ?>" required>
                    </div>

                    <div id="errorFechas" class="alert alert-danger"></div>

                    <input type="hidden" name="id" value="<?= $id; ?>">

                </div>

                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-success"><span class="oi oi-check"></span> Confirmar</button>
                    <a href="iteraciones.php" onclick="return confirm('¿Cancelar la edición de la iteración? Se perderán los cambios no guardados.');"><button type="button" class="btn btn-outline-danger">
                            <span class="oi oi-x"></span> Cancelar
                        </button></a>
                </div>
            </div>
        </form>
    </div>

    <?php include_once '../gui/footer.php'; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const iteraciones = <?= json_encode($iteraciones); ?>;
            const hoy = "<?= date('Y-m-d') ?>";

            const inputInicio = document.getElementById("fecha_inicio");
            const inputFin = document.getElementById("fecha_fin");
            const errorBox = document.getElementById("errorFechas");

            // Calcular mínimo permitido (igual que CREAR)
            let minPermitido = hoy;

            if (iteraciones.length > 0) {
                const ultima = iteraciones[iteraciones.length - 1];
                let finUltima = new Date(ultima.fecha_fin);
                finUltima.setDate(finUltima.getDate() + 1); // +1 día
                const yyyy = finUltima.getFullYear();
                const mm = String(finUltima.getMonth() + 1).padStart(2, "0");
                const dd = String(finUltima.getDate()).padStart(2, "0");

                minPermitido = `${yyyy}-${mm}-${dd}`;
            }

            inputInicio.min = minPermitido;
            inputFin.min = minPermitido;

            // ---- VALIDACIÓN ----
            function fechaInvalida(fecha) {
                const f = new Date(fecha);

                return iteraciones.some(it => {
                    const ini = new Date(it.fecha_inicio);
                    const fi = new Date(it.fecha_fin);

                    // Prohibido caer en bordes iguales
                    if (f.getTime() === ini.getTime()) return true;
                    if (f.getTime() === fi.getTime()) return true;

                    // Prohibido caer dentro del rango
                    return f > ini && f < fi;
                });
            }

            function validar() {
                const inicio = inputInicio.value;
                const fin = inputFin.value;
                errorBox.style.display = "none";

                if (!inicio || !fin) return true;

                if (inicio < minPermitido) {
                    errorBox.innerHTML = `La fecha de inicio no puede ser anterior a ${minPermitido}`;
                    errorBox.style.display = "block";
                    return false;
                }

                if (fechaInvalida(inicio)) {
                    errorBox.innerHTML = "La fecha de inicio está dentro o coincide con otra iteración.";
                    errorBox.style.display = "block";
                    return false;
                }

                if (fechaInvalida(fin)) {
                    errorBox.innerHTML = "La fecha de fin está dentro o coincide con otra iteración.";
                    errorBox.style.display = "block";
                    return false;
                }

                if (fin <= inicio) {
                    errorBox.innerHTML = "La fecha de fin debe ser mayor que la fecha de inicio.";
                    errorBox.style.display = "block";
                    return false;
                }

                return true;
            }

            inputInicio.addEventListener("change", validar);
            inputFin.addEventListener("change", validar);

            document.getElementById("formIteracion").addEventListener("submit", function(e) {
                if (!validar()) e.preventDefault();
            });

        });
    </script>

</body>

</html>