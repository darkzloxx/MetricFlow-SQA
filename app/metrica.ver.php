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
                        <tr>
                            <?php 
                            $sql = "SELECT 
                                        mo.nombre AS modelo_calidad,
                                        m.nombre AS metrica,
                                        m.descripcion AS descripcion
                                    FROM metrica_modelo_calidad mmc
                                    JOIN modelo_calidad mo ON mmc.id_modelo = mo.id_modelo
                                    JOIN metrica m ON mmc.id_metrica = m.id_metrica
                                    WHERE m.id_metrica = {$id}
                                    ORDER BY mo.nombre"; 
                            $rs = BDConexion::getInstancia()->query($sql);
                            $tiene = 0;
                            $filas = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
                            foreach ($filas as $row) { 
                                $tiene = 1;?>
                                <td><?= htmlspecialchars($row['metrica']); ?></td>
                                <td><?= htmlspecialchars($row['descripcion']); ?></td>
                                <td><?= htmlspecialchars($row['modelo_calidad']); ?></td>
                            </tr>
                        <?php } 
                        if($tiene == 0){
                                echo "<td colspan='3'>La métrica no tiene modelos asociados</td>";
                        }
                        ?>
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
