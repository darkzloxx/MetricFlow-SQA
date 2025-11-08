<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
if ($esAdmin || !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
    http_response_code(403); echo 'Acceso denegado'; exit;
}

$cn = BDConexion::getInstancia();
$idMetrica = isset($_POST['id_metrica']) ? (int)$_POST['id_metrica'] : 0;
// La iteración llega oculta desde la pantalla (iteración ACTUAL);
$idIter = isset($_POST['id_iteracion']) ? (int)$_POST['id_iteracion'] : 0;
$valPlan = isset($_POST['valor_planificado']) ? trim((string)$_POST['valor_planificado']) : '';
$umbral = isset($_POST['umbral']) ? trim((string)$_POST['umbral']) : '';

$errores = [];
if ($idMetrica <= 0) { $errores[] = 'Métrica inválida.'; }
if ($idIter <= 0) { $errores[] = 'No hay iteración activa para registrar planificación.'; }
if ($valPlan === '' || !is_numeric($valPlan)) { $errores[] = 'El valor planificado es obligatorio y debe ser numérico.'; }
if ($umbral === '' || !is_numeric($umbral)) { $errores[] = 'El umbral de desviación es obligatorio y debe ser numérico.'; }

// Validaciones de pertenencia: la iteración debe pertenecer a un proyecto del usuario
if ($idIter > 0) {
    $sqlChk = 'SELECT 1 FROM iteracion i JOIN usuario_proyecto up ON up.id_proyecto = i.id_proyecto WHERE i.id_iteracion = ? AND up.id_usuario = ? LIMIT 1';
    if ($st = $cn->prepare($sqlChk)) {
        $st->bind_param('ii', $idIter, $usr->id);
        $st->execute(); $st->store_result();
        if ($st->num_rows === 0) { $errores[] = 'La iteración seleccionada no pertenece a tus proyectos.'; }
        $st->close();
    }
}

// Chequeo de existencia previa (solo lectura, sin modificar BD)
$yaExiste = false;
if ($idMetrica > 0 && $idIter > 0) {
    $rs = $cn->query('SELECT 1 FROM metrica_iteracion WHERE id_metrica = ' . $idMetrica . ' AND id_iteracion = ' . $idIter . ' AND valor_planificado IS NOT NULL LIMIT 1');
    $yaExiste = (bool)($rs && $rs->num_rows);
}
?>
<html>
  <head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Planificar Métrica</title>
  </head>
  <body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
      <div class="card mt-3">
        <div class="card-header"><h3>Resultado de Planificación</h3></div>
        <div class="card-body">
          <?php if (!empty($errores)): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e){ echo '<li>'.htmlspecialchars($e).'</li>'; } ?></ul></div>
          <?php elseif ($yaExiste): ?>
            <div class="alert alert-warning">Esta métrica ya posee un valor planificado para la iteración seleccionada.</div>
          <?php else: ?>
            <div class="alert alert-success">Validación OK. (Simulación) La planificación sería registrada con los datos provistos.</div>
          <?php endif; ?>
          <hr />
          <h5>Datos ingresados</h5>
          <ul>
            <li>ID Métrica: <?= (int)$idMetrica; ?></li>
            <li>ID Iteración: <?= (int)$idIter; ?></li>
            <li>Valor planificado: <?= htmlspecialchars($valPlan); ?></li>
            <li>Umbral de desviación: <?= htmlspecialchars($umbral); ?></li>
          </ul>
        </div>
        <div class="card-footer">
          <a class="btn btn-outline-primary" href="metricas.php"><span class="oi oi-account-logout"></span> Salir</a>
        </div>
      </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
  </body>
</html>
