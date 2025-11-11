<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cn = BDConexion::getInstancia();

// Obtener datos de la iteración actual
$sql = "
    SELECT i.*, f.nombre AS nombre_fase
    FROM iteracion i
    JOIN fase f ON i.id_fase = f.id_fase
    WHERE i.id_iteracion = $id
    LIMIT 1
";
$res = $cn->query($sql);
$iteracion = $res ? $res->fetch_assoc() : null;

if (!$iteracion) {
    die('<div class="alert alert-danger m-4">Iteración no encontrada.</div>');
}

// Cargar todas las fases para el selector
$fasesRes = $cn->query("SELECT id_fase, nombre FROM fase ORDER BY id_fase ASC");
$fases = $fasesRes ? $fasesRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Actualizar Iteración</title>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-4">
        <form action="iteracion.modificar.procesar.php" method="post">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3>Actualizar Iteración</h3>
                    <p>
                        Complete los campos a continuación y presione <b>Confirmar</b>.<br>
                        Si desea cancelar, presione <b>Cancelar</b>.
                    </p>
                </div>
                <div class="card-body">

                    <!-- 🔹 Número de Iteración (solo lectura) -->
                    <div class="form-group">
                        <label for="inputNumero">Número de Iteración</label>
                        <input type="text"
                            name="nombre"
                            class="form-control"
                            id="inputNumero"
                            value="<?= htmlspecialchars($iteracion['numero_iteracion']); ?>"
                            readonly>
                    </div>

                    <!-- 🔹 Objetivo -->
                    <div class="form-group">
                        <label for="inputObjetivo">Objetivo de la Iteración</label>
                        <input type="text"
                            name="objetivo"
                            class="form-control"
                            id="inputObjetivo"
                            value="<?= htmlspecialchars($iteracion['objetivo']); ?>"
                            placeholder="Ingrese un breve objetivo de la iteración"
                            required>
                    </div>

                    <!-- 🔹 Fechas -->
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de inicio</label>
                        <input type="date"
                            id="fecha_inicio"
                            name="fecha_inicio"
                            class="form-control"
                            value="<?= htmlspecialchars($iteracion['fecha_inicio']); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha de fin</label>
                        <input type="date"
                            id="fecha_fin"
                            name="fecha_fin"
                            class="form-control"
                            value="<?= htmlspecialchars($iteracion['fecha_fin']); ?>"
                            required>
                    </div>

                    <!-- 🔹 Fase -->
                    <div class="form-group">
                        <label for="fase">Fase</label>
                        <select id="fase" name="fase" class="form-control">
                            <?php foreach ($fases as $fase): ?>
                                <option value="<?= $fase['id_fase']; ?>"
                                    <?= $fase['id_fase'] == $iteracion['id_fase'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($fase['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="id" value="<?= $id; ?>">

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

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>