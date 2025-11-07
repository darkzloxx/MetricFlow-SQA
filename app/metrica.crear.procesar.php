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

$DatosFormulario = $_POST;
$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$nombre = trim((string)($DatosFormulario["nombre"] ?? ''));
$descripcion = trim((string)($DatosFormulario["descripcion"] ?? ''));

$resultado = "";
$mensaje = "Ha ocurrido un error.";

if ($nombre === '') {
    http_response_code(400);
    echo 'El nombre es obligatorio';
    exit;
}

$nombreEsc = $cn->real_escape_string($nombre);
$qDup = "SELECT 1 FROM metrica WHERE nombre = '{$nombreEsc}' LIMIT 1";
$rsDup = $cn->query($qDup);
if ($rsDup && $rsDup->num_rows > 0){
    $resultado = false;
    $mensaje = "Ya existe una métrica con el nombre ingresado";
} else {

    // Detectar si existe columna 'tipo'
    $hasTipo = false;
    try {
        if ($rsCols = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) {
            $hasTipo = (bool)$rsCols->num_rows;
        }
    } catch (Throwable $e) { $hasTipo = false; }

    $tipo = $esAdmin ? 'base' : 'personalizada';
    $descEsc = $cn->real_escape_string($descripcion);
    if ($hasTipo) {
        $tipoEsc = $cn->real_escape_string($tipo);
        $query = "INSERT INTO metrica (nombre, descripcion, tipo) VALUES ('{$nombreEsc}', '{$descEsc}', '{$tipoEsc}')";
    } else {
        $query = "INSERT INTO metrica (nombre, descripcion) VALUES ('{$nombreEsc}', '{$descEsc}')";
    }
    $okIns = $cn->query($query);
    if (!$okIns) {
        $cn->rollback();
        $cn->autocommit(true);
        die($cn->errno);
    }

    $idMetrica = (int)$cn->insert_id;

    if ($esAdmin) {
        // Asociar a modelos globales seleccionados
        $sel = isset($DatosFormulario['modelos_globales']) && is_array($DatosFormulario['modelos_globales']) ? $DatosFormulario['modelos_globales'] : [];
        foreach ($sel as $idMod) {
            $id = (int)$idMod; if ($id <= 0) continue;
            $qL = "INSERT INTO metrica_modelo_calidad (id_metrica, id_modelo) VALUES ({$idMetrica}, {$id})";
            if (!$cn->query($qL)) {
                $cn->rollback();
                $cn->autocommit(true);
                die($cn->errno);
            }
        }
    } else {
        // Asociar a modelos personalizados de proyecto seleccionados
        $sel = isset($DatosFormulario['modelos_proyecto']) && is_array($DatosFormulario['modelos_proyecto']) ? $DatosFormulario['modelos_proyecto'] : [];
        if (!empty($sel)) {
            // Validar que pertenecen al usuario
            $usr = ControlAcceso::usuarioActual();
            $ids = array_map('intval', $sel);
            $ids = array_filter($ids, function($v){ return $v>0;});
            if (!empty($ids)) {
                $in = implode(',', $ids);
                $sqlCheck = "SELECT pmc.id_proyecto_modelo
                             FROM proyecto_modelo_calidad pmc
                             JOIN usuario_proyecto up ON up.id_proyecto = pmc.id_proyecto
                             WHERE up.id_usuario = ".(int)$usr->id." AND pmc.id_proyecto_modelo IN ($in)";
                $valid = [];
                if ($rsV = $cn->query($sqlCheck)) {
                    while ($r = $rsV->fetch_assoc()) { $valid[] = (int)$r['id_proyecto_modelo']; }
                }
                foreach ($ids as $idpm) {
                    if (!in_array($idpm, $valid, true)) continue;
                    $qLp = "INSERT INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo) VALUES ({$idMetrica}, {$idpm})";
                    if (!$cn->query($qLp)) {
                        $cn->rollback();
                        $cn->autocommit(true);
                        die($cn->errno);
                    }
                }
            }
        }
    }

    $cn->commit();
    $cn->autocommit(true);
    $resultado = true;
    $mensaje = "Operación realizada con éxito";
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Metrica</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>

        <div class="container">
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Crear Metrica</h3>
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