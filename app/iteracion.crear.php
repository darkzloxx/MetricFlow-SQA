<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Solo líderes de proyecto
if (!ControlAcceso::verificaPermiso(PermisosSistema::ABM_ITERACIONES)) {
    header('Location: ../app/menu.php?msg=' . urlencode('Acceso restringido: solo líderes de proyecto pueden gestionar iteraciones.') . '&type=danger');
    exit;
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
?>
<html>

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Iteración</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-4">
        <form action="iteracion.crear.procesar.php" method="post" onsubmit="return validarFechas();">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3>Crear Iteración</h3>
                    <p>Complete los campos a continuación y presione <b>Confirmar</b>. Para cancelar, use <b>Cancelar</b>.</p>
                </div>
                <div class="card-body">

                    <!-- 🔹 PROYECTO -->
                    <?php if ($unicoProyecto): ?>
                        <input type="hidden" name="id_proyecto" value="<?= $proyectos[0]['id_proyecto']; ?>">
                        <div class="alert alert-info">
                            <strong>Proyecto seleccionado automáticamente:</strong> <?= htmlspecialchars($proyectos[0]['nombre']); ?>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="proyecto">Proyecto</label>
                            <select id="proyecto" name="id_proyecto" class="form-control" required>
                                <option value="">Seleccione un proyecto...</option>
                                <?php foreach ($proyectos as $p): ?>
                                    <option value="<?= $p['id_proyecto']; ?>"><?= htmlspecialchars($p['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- 🔹 FASE -->
                    <div class="form-group">
                        <label for="fase">Fase</label>
                        <select id="fase" name="fase" class="form-control">
                            <?php
                            $resFase = $cn->query("SELECT id_fase, nombre FROM fase ORDER BY id_fase ASC");
                            $fases = $resFase ? $resFase->fetch_all(MYSQLI_ASSOC) : [];
                            foreach ($fases as $f): ?>
                                <option value="<?= $f['id_fase']; ?>"><?= htmlspecialchars($f['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 🔹 NÚMERO DE ITERACIÓN -->
                    <div class="form-group">
                        <label for="inputNumero">Número de Iteración</label>
                        <input type="number" id="inputNumero" name="numero" class="form-control" readonly placeholder="Seleccioná una fase">
                    </div>


                    <!-- 🔹 FECHAS -->
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de inicio:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha de fin:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required>
                    </div>

                    <!-- 🔹 OBJETIVO -->
                    <div class="form-group">
                        <label for="objetivo">Objetivo de la Iteración</label>
                        <input type="text" name="objetivo" class="form-control" id="objetivo"
                            placeholder="Ingrese un breve objetivo de la iteración">
                    </div>



                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-outline-success">
                        <span class="oi oi-check"></span> Confirmar
                    </button>
                    <a href="iteraciones.php" class="btn btn-outline-danger">
                        <span class="oi oi-x"></span> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
        function validarFechas() {
            const inicio = new Date(document.getElementById('fecha_inicio').value);
            const fin = new Date(document.getElementById('fecha_fin').value);
            if (fin < inicio) {
                alert('La fecha de fin no puede ser anterior a la fecha de inicio.');
                return false;
            }
            return true;
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const faseSelect = document.getElementById("fase");
            const numeroInput = document.getElementById("inputNumero");
            const proyectoSelect = document.getElementById("proyecto");
            const idProyecto = <?= $unicoProyecto ? $proyectos[0]['id_proyecto'] : 'null'; ?>;

            function cargarNumeroIteracion() {
                const idFase = faseSelect.value;
                const proyecto = idProyecto ?? (proyectoSelect ? proyectoSelect.value : null);
                if (!idFase || !proyecto) {
                    numeroInput.value = '';
                    numeroInput.placeholder = 'Seleccioná una fase';
                    return;
                }

                fetch(`./ajax/next_iteracion.php?idProyecto=${proyecto}&idFase=${idFase}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error del servidor:', data.error);
                            numeroInput.value = '';
                            return;
                        }
                        numeroInput.value = data.siguiente ?? 1;
                    })
                    .catch(err => {
                        console.error('Error al obtener el número de iteración:', err);
                        numeroInput.value = '';
                    });

            }

            // 🔹 Escuchar cambios en fase o proyecto
            faseSelect.addEventListener("change", cargarNumeroIteracion);
            if (proyectoSelect) proyectoSelect.addEventListener("change", cargarNumeroIteracion);

            // 🔹 Cargar automáticamente al iniciar si hay fase seleccionada y proyecto fijo
            if (idProyecto && faseSelect.value) {
                cargarNumeroIteracion();
            }
        });
    </script>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>