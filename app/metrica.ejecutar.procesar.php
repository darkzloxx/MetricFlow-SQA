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
$idIter = isset($_POST['id_iteracion']) ? (int)$_POST['id_iteracion'] : 0;
$valEjec = isset($_POST['valor_ejecutado']) ? trim((string)$_POST['valor_ejecutado']) : '';

$errores = [];
if ($idMetrica <= 0) { $errores[] = 'Métrica inválida.'; }
if ($idIter <= 0) { $errores[] = 'Iteración inválida.'; }
if ($valEjec === '' || !is_numeric($valEjec)) { $errores[] = 'El valor ejecutado es obligatorio y debe ser numérico.'; }

// Validar que iteración pertenece al usuario
if ($idIter > 0) {
    $sqlChk = 'SELECT 1 FROM iteracion i JOIN usuario_proyecto up ON up.id_proyecto = i.id_proyecto WHERE i.id_iteracion = ? AND up.id_usuario = ? LIMIT 1';
    if ($st = $cn->prepare($sqlChk)) {
        $st->bind_param('ii', $idIter, $usr->id);
        $st->execute(); $st->store_result();
        if ($st->num_rows === 0) { $errores[] = 'La iteración seleccionada no pertenece a tus proyectos.'; }
        $st->close();
    }
}

// Verificar que esté planificada y aún no ejecutada
$planOk = false; $yaEjecutada = false; $valPlan = null; $umbral = null;
if ($idMetrica > 0 && $idIter > 0) {
    $sqlPlan = 'SELECT valor_planificado, valor_ejecutado, umbral_desviacion FROM metrica_iteracion WHERE id_metrica = '.$idMetrica.' AND id_iteracion = '.$idIter.' LIMIT 1';
    if ($rsP = $cn->query($sqlPlan)) {
        if ($fila = $rsP->fetch_assoc()) {
            $planOk = $fila['valor_planificado'] !== null;
            $yaEjecutada = $fila['valor_ejecutado'] !== null;
            $valPlan = $fila['valor_planificado'];
            $umbral = $fila['umbral_desviacion'];
        }
    }
    if (!$planOk) { $errores[] = 'La métrica no está planificada para la iteración seleccionada.'; }
    if ($yaEjecutada) { $errores[] = 'La métrica ya tiene un valor ejecutado en esta iteración.'; }
}
?>
<html>
  <head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Ejecutar Métrica</title>
  </head>
  <body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
      <div class="card mt-3">
        <div class="card-header"><h3>Resultado de Ejecución</h3></div>
        <div class="card-body">
          <?php if (!empty($errores)): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e){ echo '<li>'.htmlspecialchars($e).'</li>'; } ?></ul></div>
          <?php else: ?>
            <div class="alert alert-success">Validación OK. (Simulación) La ejecución sería registrada con el valor <?= htmlspecialchars($valEjec); ?>.</div>
            <p class="mt-2 mb-0"><small class="text-muted">Valor planificado: <?= htmlspecialchars((string)$valPlan); ?> | Umbral: <?= htmlspecialchars((string)$umbral); ?></small></p>
          <?php endif; ?>
          <hr />
          <h5>Datos ingresados</h5>
          <ul>
            <li>ID Métrica: <?= (int)$idMetrica; ?></li>
            <li>ID Iteración: <?= (int)$idIter; ?></li>
            <li>Valor ejecutado: <?= htmlspecialchars($valEjec); ?></li>
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
