<?php
include_once '../lib/ControlAcceso.class.php';
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

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Propiedades del Modelo Asignado al Proyecto</h3>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Modelo</th>
                            <th>Metrica</th>
                            <th>Descripcion</th>
                        </tr>
                        <tr>
                            <?php 
                            $proyectos = "SELECT 
                                        mo.nombre AS modelo_calidad,
                                        m.nombre AS metrica,
                                        m.descripcion AS descripcion
                                        FROM metrica_modelo_calidad mmc
                                        JOIN modelo_calidad mo ON mmc.id_modelo = mo.id_modelo
                                        JOIN metrica m ON mmc.id_metrica = m.id_metrica
                                        JOIN proyecto p ON mo.id_modelo = p.id_modelo
                                        WHERE p.id_proyecto = ".$id."
                                        ORDER BY mo.nombre"; 
                            $proyectos=BDConexion::getInstancia()->query($proyectos);
                            $tiene = 0;
                            $proyecto = $proyectos->fetch_all(MYSQLI_ASSOC); 
                            foreach ($proyecto as $Proyec) { 
                                $tiene = 1;?>
                                <td><?= $Proyec['modelo_calidad']; ?></td>
                                <td><?= $Proyec['metrica']; ?></td>
                                <td><?= $Proyec['descripcion']; ?></td>
                            </tr>
                        <?php } 
                        if($tiene == 0){
                                echo "<td colspan='3'>El modelo no tiene metricas asociadas para el proyecto</td>";
                        }
                        ?>
                    </table>
                </div>
                <div class="card-footer">
                        <a href="pantalla.alumnos.metrica.php?id=<?= (int)$id ?>">
                            <button type="button" class="btn btn-outline-success">
                                <span class="oi oi-check"></span> Gestionar Metricas
                            </button>
                        </a>
                        <a href="modelos.php?id_proyecto=<?= (int)$id ?>">
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
