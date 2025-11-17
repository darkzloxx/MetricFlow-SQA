<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';

$id = intval($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: tarea.php?msg=" . urlencode("Tarea inválida.") . "&type=danger");
    exit;
}

$cn = BDConexion::getInstancia();

$sql = "
SELECT 
    t.nombre AS nombreTarea,
    i.numero_iteracion,
    m.nombre AS nombreMetrica,
    i.fecha_inicio,
    i.fecha_fin,
    f.nombre AS nombreFase
FROM tarea t
JOIN metrica_tarea mt ON t.id_tarea = mt.id_tarea
JOIN metrica m ON m.id_metrica = mt.id_metrica
JOIN iteracion_tarea it ON it.id_tarea = t.id_tarea
JOIN iteracion i ON i.id_iteracion = it.id_iteracion
JOIN fase f ON f.id_fase = i.id_fase
WHERE t.id_tarea = $id
ORDER BY f.id_fase ASC, i.numero_iteracion ASC, m.nombre ASC
";

$res = $cn->query($sql);
$datos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>

<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades de la Tarea</title>
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
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-3">

        <div class="mb-3">
            <a href="tarea.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="mb-0 text-primary">Propiedades de la Tarea</h3>
            </div>

            <div class="card-body">

                <?php if (empty($datos)): ?>

                    <div class="alert alert-warning">⚠️ Esta tarea no tiene métricas asociadas.</div>

                <?php else: ?>

                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Tarea</th>
                            <th>Fase</th>
                            <th>Iteración</th>
                            <th>Métrica</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                        </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nombreTarea']); ?></td>
                                    <td><?= htmlspecialchars($row['nombreFase']); ?></td>
                                    <td class="text-center"><?= intval($row['numero_iteracion']); ?></td>
                                    <td><?= htmlspecialchars($row['nombreMetrica']); ?></td>
                                    <td><?= htmlspecialchars($row['fecha_inicio']); ?></td>
                                    <td><?= htmlspecialchars($row['fecha_fin']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php endif; ?>

            </div>
        </div>

    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>