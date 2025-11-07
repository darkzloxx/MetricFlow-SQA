<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Acceso: Admin/SuperAdmin bypass; otros requieren permiso Gestión de Métricas
ControlAcceso::verificaLogin();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
if (!$esAdmin && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: metricas.php?msg=' . urlencode('Métrica inválida.') . '&type=danger');
    exit;
}

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

// Detectar si existe la columna 'tipo' y obtener tipo si aplica
$tipo = null; $hasTipo = false;
try { if ($rsC = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) { $hasTipo = (bool)$rsC->num_rows; } } catch (Throwable $e) { $hasTipo = false; }
if ($hasTipo) {
    $rsTipo = $cn->query('SELECT tipo FROM metrica WHERE id_metrica = ' . $id . ' LIMIT 1');
    if ($rsTipo && $rsTipo->num_rows) {
        $rowT = $rsTipo->fetch_assoc();
        $tipo = $rowT ? ($rowT['tipo'] ?? null) : null;
    } else {
        $cn->rollback();
        $cn->autocommit(true);
        header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
        exit;
    }
}

// Si no admin y la métrica es base (o no se conoce el tipo) => bloquear
if (!$esAdmin && (is_null($tipo) || strtolower((string)$tipo) === 'base')) {
    $cn->rollback();
    $cn->autocommit(true);
    header('Location: metricas.php?msg=' . urlencode('No está autorizado a eliminar métricas base.') . '&type=danger');
    exit;
}

// Regla de integridad: bloquear eliminación según uso
try {
    // Siempre bloquear si tiene valores planificados o ejecutados
    $rsUsoVals = $cn->query('SELECT COUNT(*) c FROM metrica_iteracion WHERE id_metrica = ' . $id . ' AND (valor_planificado IS NOT NULL OR valor_ejecutado IS NOT NULL)');
    $rowUV = $rsUsoVals ? $rsUsoVals->fetch_assoc() : ['c' => 0];
    if ((int)$rowUV['c'] > 0) {
        throw new Exception('No se puede eliminar: la métrica tiene valores planificados o ejecutados en iteraciones.');
    }

    if ($tipo === 'base') {
        // Para métricas base, también bloquear si está asociada a algún modelo o usada en iteraciones (aunque sin valores)
        $rsRel = $cn->query('SELECT COUNT(*) c FROM metrica_modelo_calidad WHERE id_metrica = ' . $id);
        $rowRel = $rsRel ? $rsRel->fetch_assoc() : ['c' => 0];
        if ((int)$rowRel['c'] > 0) {
            throw new Exception('No se puede eliminar: la métrica base está asociada a uno o más modelos.');
        }
        $rsIter = $cn->query('SELECT COUNT(*) c FROM metrica_iteracion WHERE id_metrica = ' . $id);
        $rowIter = $rsIter ? $rsIter->fetch_assoc() : ['c' => 0];
        if ((int)$rowIter['c'] > 0) {
            throw new Exception('No se puede eliminar: la métrica base está vinculada a iteraciones.');
        }
    }

    // Eliminar relaciones y la métrica
    // Relaciones con modelos de proyecto (si existieran)
    $cn->query('DELETE FROM metrica_proyecto_modelo WHERE id_metrica = ' . $id);
    if (!$cn->query('DELETE FROM metrica_modelo_calidad WHERE id_metrica = ' . $id)) {
        throw new Exception('Error eliminando relaciones del modelo: ' . $cn->error);
    }
    if (!$cn->query('DELETE FROM metrica WHERE id_metrica = ' . $id . ' LIMIT 1')) {
        throw new Exception('Error eliminando la métrica: ' . $cn->error);
    }

    $cn->commit();
    $cn->autocommit(true);
    $consulta = true;
} catch (Exception $e) {
    $cn->rollback();
    $cn->autocommit(true);
    $consulta = false;
    $mensajeError = $e->getMessage();
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Eliminar Metrica</title>

    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Baja de Metrica</h3>
                </div>
                <div class="card-body">
                    <?php if ($consulta) { ?>
                        <div class="alert alert-success" role="alert">
                            Operaci&oacute;n realizada con &eacute;xito.
                        </div>
                    <?php } ?>   
                    <?php if (!$consulta) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?= isset($mensajeError) ? htmlspecialchars($mensajeError) : 'Ha ocurrido un error.'; ?>
                        </div>
                    <?php } ?>
                    <hr />
                    <h5 class="card-text">Opciones</h5>
                    <a href="metricas.php">
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
