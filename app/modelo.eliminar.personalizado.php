<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();

$idModelo = (int)($_GET['id'] ?? 0);
if ($idModelo <= 0) {
    header('Location: modelos.php?msg=' . urlencode('Modelo inválido.') . '&type=danger');
    exit;
}

// Validar que el usuario tenga acceso al modelo
$sql = "
    SELECT pmc.*, p.nombre AS proyecto
    FROM proyecto_modelo_calidad pmc
    JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    WHERE pmc.id_proyecto_modelo = {$idModelo}
      AND up.id_usuario = {$usr->id}
";
$res = $cn->query($sql);
if (!$res || $res->num_rows === 0) {
    header('Location: modelos.php?msg=' . urlencode('No tiene permisos para eliminar este modelo.') . '&type=danger');
    exit;
}
$modelo = $res->fetch_assoc();
?>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Modelo Personalizado - <?= Constantes::NOMBRE_SISTEMA; ?></title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
</head>
<body>
<?php include_once '../gui/navbar.php'; ?>

<div class="container mt-5">
    <div class="card shadow-sm border-danger">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-0"><span class="oi oi-warning mr-2"></span>Confirmar eliminación</h4>
        </div>
        <div class="card-body">
            <p>¿Confirma que desea eliminar el modelo personalizado <strong><?= htmlspecialchars($modelo['nombre']); ?></strong> asociado al proyecto <strong><?= htmlspecialchars($modelo['proyecto']); ?></strong>?</p>
            <p class="text-danger mb-0"><strong>Esta acción no se puede deshacer.</strong></p>
        </div>
        <div class="card-footer text-right">
            <a href="modelo.eliminar.personalizado.procesar.php?id=<?= (int)$idModelo; ?>" class="btn btn-danger">
                <span class="oi oi-trash"></span> Eliminar definitivamente
            </a>
            <a href="modelos.php" class="btn btn-outline-secondary">
                <span class="oi oi-x"></span> Cancelar
            </a>
        </div>
    </div>
</div>

<?php include_once '../gui/footer.php'; ?>
</body>
</html>
