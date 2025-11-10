<?php
include_once '../lib/ControlAcceso.class.php';
include_once '../modelo/BDConexion.Class.php';
// Solo requiere login para visualizar
ControlAcceso::verificaLogin();

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
?>


<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades de la métrica</title>

</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <p></p>
        <div class="card">
            <div class="card-header">
                <h3>Propiedades de la métrica</h3>
            </div>
            <div class="card-body">
                <table class="table table-hover table-sm">
                    <tr class="table-info">
                        <th>Métrica</th>
                        <th>Descripción</th>
                        <th>Modelo Asociado</th>
                    </tr>
                        <?php
                        // ===============================
                        // 🔍 Buscar modelo global o personalizado asociado
                        // ===============================

                        // Globales
                        $sqlGlobal = "
    SELECT 
        'Global' AS tipo_modelo,
        mo.nombre AS modelo_calidad,
        m.nombre AS metrica,
        m.descripcion AS descripcion
    FROM metrica_modelo_calidad mmc
    JOIN modelo_calidad mo ON mmc.id_modelo = mo.id_modelo
    JOIN metrica m ON mmc.id_metrica = m.id_metrica
    WHERE m.id_metrica = {$id}
";

                        // Personalizados
                        $sqlPers = "
    SELECT 
        'Personalizado' AS tipo_modelo,
        pmc.nombre AS modelo_calidad,
        m.nombre AS metrica,
        m.descripcion AS descripcion
    FROM metrica_proyecto_modelo mpm
    JOIN proyecto_modelo_calidad pmc ON mpm.id_proyecto_modelo = pmc.id_proyecto_modelo
    JOIN metrica m ON mpm.id_metrica = m.id_metrica
    WHERE m.id_metrica = {$id}
";

                        // Ejecutar ambas
                        $cn = BDConexion::getInstancia();
                        $resGlobal = $cn->query($sqlGlobal);
                        $resPers = $cn->query($sqlPers);

                        $filas = [];
                        if ($resGlobal && $resGlobal->num_rows > 0) {
                            $filas = array_merge($filas, $resGlobal->fetch_all(MYSQLI_ASSOC));
                        }
                        if ($resPers && $resPers->num_rows > 0) {
                            $filas = array_merge($filas, $resPers->fetch_all(MYSQLI_ASSOC));
                        }

                        $tiene = !empty($filas);
                        ?>

                        <?php if ($tiene): ?>
                            <?php foreach ($filas as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['metrica']); ?></td>
                        <td><?= htmlspecialchars($row['descripcion']); ?></td>
                        <td>
                            <?= htmlspecialchars($row['modelo_calidad']); ?>
                            <span class="badge badge-<?= $row['tipo_modelo'] === 'Global' ? 'secondary' : 'info'; ?>">
                                <?= $row['tipo_modelo']; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-muted text-center">
                        La métrica no tiene modelos asociados.
                    </td>
                </tr>
            <?php endif; ?>
                </table>
            </div>
            <div class="card-footer">
                <a href="metricas.php">
                    <button type="button" class="btn btn-outline-danger">
                        <span class="oi oi-x"></span> Volver
                    </button>
                </a>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>