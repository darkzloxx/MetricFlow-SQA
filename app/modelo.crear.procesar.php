<?php
include_once '../lib/ControlAcceso.Class.php';
// Acceso: Admin/SuperAdmin SIEMPRE. Caso contrario: requiere permiso Gestión de Modelo y pertenecer al proyecto destino
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;
BDConexion::getInstancia()->autocommit(false);
BDConexion::getInstancia()->begin_transaction();

$proyecto = isset($DatosFormulario["proyecto"]) ? (int)$DatosFormulario["proyecto"] : 0;
$modelo = $DatosFormulario["modelo"];

$resultado = null;
$mensaje = "Ha ocurrido un error.";

if ($proyecto <= 0) {
    $resultado = false;
    $mensaje = 'Proyecto inválido.';
} else if (!ControlAcceso::esAdminGlobal()) {
    if (!ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD) || !ControlAcceso::usuarioPerteneceAProyecto($proyecto)) {
        $resultado = false;
        $mensaje = 'Acceso restringido: requiere permiso y pertenecer al proyecto.';
    }
}

if ($resultado !== false) {
    // Obtener modelo actual del proyecto (si existe)
    $sqlCurr = "SELECT id_modelo FROM proyecto WHERE id_proyecto = {$proyecto} LIMIT 1";
    $rsCurr = BDConexion::getInstancia()->query($sqlCurr);
    $rowCurr = ($rsCurr && $rsCurr->num_rows) ? $rsCurr->fetch_assoc() : null;
    $modeloActual = $rowCurr ? (int)$rowCurr['id_modelo'] : 0;

    if ($modeloActual > 0) {
        if ($modeloActual === (int)$modelo) {
            // No hay cambios
            BDConexion::getInstancia()->commit();
            BDConexion::getInstancia()->autocommit(true);
            $resultado = true;
            $mensaje = "El modelo seleccionado ya está asignado al proyecto.";
        } else {
            // Verificar si existen métricas planificadas en iteraciones del proyecto
            $sqlLock = "SELECT COUNT(*) AS total
                        FROM metrica_iteracion mi
                        JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
                        WHERE i.id_proyecto = {$proyecto}";
            $rsLock = BDConexion::getInstancia()->query($sqlLock);
            $totalPlan = ($rsLock && $rsLock->num_rows) ? (int)$rsLock->fetch_assoc()['total'] : 0;

            if ($totalPlan > 0) {
                // No permitir cambiar modelo si hay métricas registradas
                BDConexion::getInstancia()->rollback();
                BDConexion::getInstancia()->autocommit(true);
                $resultado = false;
                $mensaje = "No se puede cambiar el modelo porque el proyecto ya tiene métricas planificadas en alguna iteración.";
            } else {
                // Permitir cambio de modelo
                $query = "UPDATE proyecto SET id_modelo = " . ((int)$modelo) . " WHERE id_proyecto = " . ((int)$proyecto);
                $ok = BDConexion::getInstancia()->query($query);
                if (!$ok) {
                    BDConexion::getInstancia()->rollback();
                    die(BDConexion::getInstancia()->errno);
                }
                BDConexion::getInstancia()->commit();
                BDConexion::getInstancia()->autocommit(true);
                $resultado = true;
                $mensaje = "Modelo de calidad cambiado correctamente.";
            }
        }
    } else {
        // Asignación inicial del modelo
        $query = "UPDATE proyecto SET id_modelo = " . ((int)$modelo) . " WHERE id_proyecto = " . ((int)$proyecto);
        $ok = BDConexion::getInstancia()->query($query);
        if (!$ok) {
            BDConexion::getInstancia()->rollback();
            die(BDConexion::getInstancia()->errno);
        }
        BDConexion::getInstancia()->commit();
        BDConexion::getInstancia()->autocommit(true);
        $resultado = true;
        $mensaje = "Modelo de calidad asignado correctamente al proyecto.";
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
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Cargar Modelo</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Cargar Modelo</h3>
                </div>
                <div class="card-body">
                    <?php if ($resultado) { ?>
                        <div class="alert alert-success" role="alert">
                            <?= $mensaje; ?>
                        </div>
                    <?php } ?>   
                    <?php if (!$resultado) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $mensaje; ?>
                        </div>
                    <?php } ?>
                    <hr />
                    <h5 class="card-text">Opciones</h5>
                    <a href="pantalla.alumnos.metrica.php?id=<?= (int)$proyecto ?>">
                            <button type="button" class="btn btn-outline-success">
                                <span class="oi oi-check"></span> Gestionar Metricas
                            </button>
                        </a>
                    <a href="modelos.php?id_proyecto=<?= (int)$proyecto ?>">
                        <button type="button" class="btn btn-primary">
                            <span class="oi oi-account-logout"></span> Salir
                        </button>
                    </a>
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>