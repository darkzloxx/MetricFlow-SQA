<?php
include_once '../lib/ControlAcceso.class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);
include_once '../modelo/Permiso.php';

$id = (int)($_GET["id"] ?? 0);
?>

<html lang="es">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Propiedades de la Iteración</title>

    <style>
        .cell-ellipsis {
            max-width: 600px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cell-ellipsis:hover {
            white-space: normal;
            word-break: break-word;
            background: #f8f9fa;
            border-radius: .25rem;
            padding: .3rem .4rem;
        }

        .table thead th {
            background-color: #d7eff6;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container mt-4">

        <div class="card shadow-sm">
            <div class="card-header">
                <h3>Propiedades de la Iteración</h3>
            </div>

            <div class="card-body">
                <table class="table table-hover table-sm">
                    <tr class="table-info">
                            <th>Iteración</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Objetivo</th>
                            <th>Fase</th>
                        </tr>
                    <tbody>
                        <?php
                        $sql = "
                            SELECT i.*, f.nombre AS fase
                            FROM iteracion i
                            JOIN fase f ON i.id_fase = f.id_fase
                            WHERE id_iteracion = {$id}
                            LIMIT 1
                        ";
                        $res = BDConexion::getInstancia()->query($sql);
                        $data = $res ? $res->fetch_assoc() : null;

                        if ($data): ?>
                            <tr>
                                <td><?= htmlspecialchars($data['numero_iteracion']); ?></td>
                                <td><?= htmlspecialchars($data['fecha_inicio']); ?></td>
                                <td><?= htmlspecialchars($data['fecha_fin']); ?></td>
                                <td class="cell-ellipsis"><?= nl2br(htmlspecialchars($data['objetivo'])); ?></td>
                                <td><?= htmlspecialchars($data['fase']); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Sin información disponible
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer text-right">
                <a href="iteraciones.php" class="btn btn-outline-danger">
                    <span class="oi oi-x"></span> Volver
                </a>
            </div>
        </div>

    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>
