<?php
include_once '../lib/ControlAcceso.class.php';
include_once '../modelo/BDConexion.Class.php';
// Acceso: Admin/SuperAdmin SIEMPRE. Caso contrario: requiere permiso Gestión de Modelo y pertenecer al proyecto
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: modelos.php?msg=' . urlencode('Proyecto inválido.') . '&type=danger');
    exit;
}
if (!ControlAcceso::esAdminGlobal()) {
    if (!ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD) || !ControlAcceso::usuarioPerteneceAProyecto($id)) {
        header('Location: modelos.php?msg=' . urlencode('Acceso restringido: requiere permiso y pertenecer al proyecto.') . '&type=danger');
        exit;
    }
}
?>


<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades del Modelo</title>
    <style>
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background-color: #fff;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #212529;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a id="btnVolver" href="modelos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
        <p></p>
        <?php
        // Obtener nombre del modelo asignado (si existe)
        $nombreModelo = null;
        $qModelo = "SELECT mo.nombre AS nombre_modelo
                            FROM proyecto p
                            LEFT JOIN modelo_calidad mo ON mo.id_modelo = p.id_modelo
                            WHERE p.id_proyecto = " . (int)$id . " LIMIT 1";
        if ($rsNom = BDConexion::getInstancia()->query($qModelo)) {
            $rwNom = $rsNom->fetch_assoc();
            $nombreModelo = $rwNom ? ($rwNom['nombre_modelo'] ?? null) : null;
        }
        ?>
        <div class="card">
            <div class="card-header">
                <h3>
                    <?= $nombreModelo ? ('Modelo asignado: ' . htmlspecialchars($nombreModelo)) : 'Proyecto sin modelo asignado'; ?>
                </h3>
            </div>
            <div class="card-body">
                <?php
                // Bloqueo: si el proyecto ya tiene métricas planificadas en alguna iteración,
                // no debe permitirse gestionar métricas (ocultar botón en el footer)
                $sqlPlan = "SELECT 1 
                                    FROM metrica_iteracion mi 
                                    JOIN iteracion i ON i.id_iteracion = mi.id_iteracion 
                                    WHERE i.id_proyecto = " . (int)$id . " AND mi.valor_planificado IS NOT NULL 
                                    LIMIT 1";
                $rsPlan = BDConexion::getInstancia()->query($sqlPlan);
                $bloqueado = ($rsPlan && $rsPlan->num_rows > 0);
                ?>
                <?php if ($nombreModelo): ?>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Métrica</th>
                            <th>Descripción</th>
                        </tr>
                        <?php
                        $sqlDet = "SELECT 
                                            m.nombre AS metrica,
                                            m.descripcion AS descripcion
                                           FROM metrica_modelo_calidad mmc
                                           JOIN modelo_calidad mo ON mmc.id_modelo = mo.id_modelo
                                           JOIN metrica m ON mmc.id_metrica = m.id_metrica
                                           JOIN proyecto p ON mo.id_modelo = p.id_modelo
                                           WHERE p.id_proyecto = " . (int)$id . " 
                                           ORDER BY m.nombre";
                        $rsDet = BDConexion::getInstancia()->query($sqlDet);
                        $tiene = 0;
                        if ($rsDet) {
                            while ($row = $rsDet->fetch_assoc()) {
                                $tiene = 1; ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['metrica']); ?></td>
                                    <td><?= htmlspecialchars($row['descripcion']); ?></td>
                                </tr>
                        <?php }
                        }
                        if ($tiene == 0) {
                            echo "<tr><td colspan='2'>El modelo no tiene métricas asociadas para el proyecto</td></tr>";
                        }
                        ?>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Este proyecto aún no tiene un modelo de calidad asignado.</div>
                <?php endif; ?>
            </div>
            <?php if (!$bloqueado && $nombreModelo) { ?>
                <div class="card-footer">

                    <a href="pantalla.alumnos.metrica.php?id=<?= (int)$id ?>">
                        <button type="button" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Gestionar Metricas
                        </button>
                    </a>


                </div>
            <?php } ?>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>