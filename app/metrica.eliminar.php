<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
if (!$esAdmin) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: metricas.php?msg=' . urlencode('ID inválido.') . '&type=danger');
    exit;
}
$cn = BDConexion::getInstancia();

// Obtener métrica base
$hasTipo = false; $tipo = 'base';
try { if ($rsC = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) { $hasTipo = (bool)$rsC->num_rows; } } catch (Throwable $e) { $hasTipo = false; }
$sqlMet = $hasTipo
    ? 'SELECT id_metrica, nombre, descripcion, tipo FROM metrica WHERE id_metrica = ' . $id . ' LIMIT 1'
    : 'SELECT id_metrica, nombre, descripcion FROM metrica WHERE id_metrica = ' . $id . ' LIMIT 1';
$rsMet = $cn->query($sqlMet);
if (!$rsMet || !$rsMet->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
    exit;
}
$metrica = $rsMet->fetch_assoc();
if ($hasTipo) { $tipo = strtolower(trim($metrica['tipo'] ?? 'base')); }
if ($tipo !== 'base') {
    // Página enfocada a eliminación de métricas base globales
    header('Location: metricas.php?msg=' . urlencode('Solo se confirman eliminaciones de métricas base aquí.') . '&type=warning');
    exit;
}

// Listar modelos donde está asociada
$modelos = [];
$sqlMod = 'SELECT m.id_modelo, m.nombre FROM metrica_modelo_calidad mmc JOIN modelo_calidad m ON m.id_modelo = mmc.id_modelo WHERE mmc.id_metrica = ' . $id . ' ORDER BY m.nombre';
if ($rsMod = $cn->query($sqlMod)) {
    $modelos = $rsMod->fetch_all(MYSQLI_ASSOC);
}
// Para cada modelo obtener cantidad de proyectos que lo usan
$usoModelos = [];
if (!empty($modelos)) {
    foreach ($modelos as $mo) {
        $idM = (int)$mo['id_modelo'];
        $rsCnt = $cn->query('SELECT COUNT(*) c FROM proyecto WHERE id_modelo = ' . $idM);
        $rowCnt = $rsCnt ? $rsCnt->fetch_assoc() : ['c' => 0];
        $usoModelos[$idM] = (int)$rowCnt['c'];
    }
}

// Verificar uso en iteraciones (planificado / ejecutado)
$rsUsoIter = $cn->query('SELECT COUNT(*) c FROM metrica_iteracion WHERE id_metrica = ' . $id);
$cantIter = $rsUsoIter ? (int)$rsUsoIter->fetch_assoc()['c'] : 0;
$rsUsoVals = $cn->query('SELECT COUNT(*) c FROM metrica_iteracion WHERE id_metrica = ' . $id . ' AND (valor_planificado IS NOT NULL OR valor_ejecutado IS NOT NULL)');
$cantVals = $rsUsoVals ? (int)$rsUsoVals->fetch_assoc()['c'] : 0;

// Si tiene cualquier uso, avisar que no puede eliminarse (procesar.php hará validación final también)
$bloqueada = ($cantVals > 0) || (!empty($modelos));
?>
<html>
  <head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Confirmar Eliminación Métrica</title>
  </head>
  <body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
      <div class="mb-3">
        <a href="metricas.php" class="btn btn-outline-secondary">
          <span class="oi oi-arrow-left"></span> Volver
        </a>
      </div>
      <div class="card">
        <div class="card-header">
          <h3>Eliminar Métrica Base</h3>
          <small class="text-muted">Revisá el impacto antes de confirmar.</small>
        </div>
        <div class="card-body">
          <h5 class="mb-3">Métrica</h5>
          <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($metrica['nombre']); ?></p>
          <p class="mb-3"><strong>Descripción:</strong> <?= htmlspecialchars($metrica['descripcion'] ?? ''); ?></p>
          <hr />
          <h5 class="mb-2">Modelos afectados</h5>
          <?php if (empty($modelos)): ?>
            <div class="alert alert-success mb-3">La métrica no está asociada a ningún modelo. (Se puede eliminar si no posee valores planificados ni ejecutados.)</div>
          <?php else: ?>
            <div class="alert alert-warning mb-3">
              La métrica está asociada a los siguientes modelos. Al eliminarla dejará de figurar en ellos.
            </div>
            <table class="table table-sm table-hover">
              <thead class="table-info"><tr><th>Modelo</th><th>Proyectos que lo usan</th></tr></thead>
              <tbody>
              <?php foreach ($modelos as $mo): $idM=(int)$mo['id_modelo']; ?>
                <tr>
                  <td><?= htmlspecialchars($mo['nombre']); ?></td>
                  <td><?= $usoModelos[$idM] ?? 0; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
          <hr />
          <h5 class="mb-2">Uso en iteraciones</h5>
          <p class="mb-1">Iteraciones vinculadas: <?= $cantIter; ?></p>
          <p class="mb-3">Iteraciones con valores planificados / ejecutados: <?= $cantVals; ?></p>
          <?php if ($bloqueada): ?>
            <div class="alert alert-danger">No se podrá eliminar porque la métrica está en uso (modelos y/o iteraciones con datos).</div>
          <?php else: ?>
            <div class="alert alert-info">La métrica cumple las condiciones para eliminarse.</div>
          <?php endif; ?>
        </div>
        <div class="card-footer">
          <?php if (!$bloqueada): ?>
            <form method="post" action="metrica.eliminar.procesar.php" class="d-inline" onsubmit="return confirm('¿Confirma eliminar la métrica? Esta acción es irreversible.');">
              <input type="hidden" name="id" value="<?= (int)$metrica['id_metrica']; ?>" />
              <button type="submit" class="btn btn-outline-danger"><span class="oi oi-trash"></span> Confirmar eliminación</button>
            </form>
          <?php else: ?>
            <button class="btn btn-outline-secondary" disabled><span class="oi oi-lock-locked"></span> Eliminación bloqueada</button>
          <?php endif; ?>
          <a href="metricas.php" class="btn btn-outline-primary ml-2"><span class="oi oi-x"></span> Cancelar</a>
        </div>
      </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
  </body>
</html>
