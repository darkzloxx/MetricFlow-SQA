<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
// Admin no ejecuta métricas
if ($esAdmin) {
  header('Location: metricas.php?msg=' . urlencode('Los administradores no registran ejecución de métricas.') . '&type=warning');
  exit;
}
if (!ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
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

// Cargar métrica
$hasTipo = false;
try {
  if ($rsC = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) {
    $hasTipo = (bool)$rsC->num_rows;
  }
} catch (Throwable $e) {
  $hasTipo = false;
}
$sqlMet = $hasTipo ? 'SELECT id_metrica,nombre,descripcion,tipo FROM metrica WHERE id_metrica=' . $id . ' LIMIT 1' : 'SELECT id_metrica,nombre,descripcion FROM metrica WHERE id_metrica=' . $id . ' LIMIT 1';
$rsMet = $cn->query($sqlMet);
if (!$rsMet || !$rsMet->num_rows) {
  header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
  exit;
}
$met = $rsMet->fetch_assoc();
$tipo = $hasTipo ? strtolower(trim($met['tipo'] ?? '')) : 'personalizada';

// Debe estar planificada en la iteración ACTUAL (hoy dentro del rango)
$planificado = false;
$ejecutado = false;
$valPlan = null;
$valEjec = null;
$umbral = null;
$idIter = null;
// Buscar iteración activa del usuario para esta métrica (a través del proyecto)
$sqlAct = "SELECT i.id_iteracion, i.fecha_inicio, i.fecha_fin
           FROM iteracion i
           JOIN usuario_proyecto up ON up.id_proyecto = i.id_proyecto
           WHERE up.id_usuario = " . (int)$usr->id . "
           ORDER BY i.fecha_inicio";
if ($rsI = $cn->query($sqlAct)) {
  $hoy = new DateTime('today');
  while ($row = $rsI->fetch_assoc()) {
    try {
      $ini = new DateTime($row['fecha_inicio']);
      $fin = new DateTime($row['fecha_fin']);
      if ($hoy >= $ini && $hoy <= $fin) {
        $idIter = (int)$row['id_iteracion'];
        break;
      }
    } catch (Throwable $e) { /* ignorar parseo */
    }
  }
}
if ($idIter) {
  $rsPlan = $cn->query('SELECT valor_planificado, valor_ejecutado, umbral_desviacion FROM metrica_iteracion WHERE id_metrica=' . (int)$id . ' AND id_iteracion=' . (int)$idIter . ' LIMIT 1');
  if ($rsPlan && $fila = $rsPlan->fetch_assoc()) {
    $planificado = $fila['valor_planificado'] !== null;
    $ejecutado = $fila['valor_ejecutado'] !== null;
    $valPlan = $fila['valor_planificado'];
    $valEjec = $fila['valor_ejecutado'];
    $umbral = $fila['umbral_desviacion'];
  }
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
    <div class="mb-3"><a href="metricas.php" class="btn btn-outline-secondary"><span class="oi oi-arrow-left"></span> Volver</a></div>
    <div class="card">
      <div class="card-header">
        <h3>Registrar Ejecución</h3>
      </div>
      <div class="card-body">
        <p><strong>Métrica:</strong> <?= htmlspecialchars($met['nombre']); ?></p>
        <p><strong>Descripción:</strong> <?= htmlspecialchars($met['descripcion'] ?? ''); ?></p>
        <hr />
        <?php if (!$planificado): ?>
          <div class="alert alert-warning">
            La métrica aún no está planificada. No se puede registrar ejecución.
          </div>

        <?php else: ?>
          <?php if ($ejecutado): ?>
            <div class="alert alert-info">
              La métrica ya tiene un valor ejecutado (<strong><?= htmlspecialchars((string)$valEjec); ?></strong>).
              Si lo desea, puede actualizarlo a continuación.
            </div>
          <?php endif; ?>

          <form method="post" action="metrica.ejecutar.procesar.php" class="mt-2">
            <input type="hidden" name="id_metrica" value="<?= (int)$met['id_metrica']; ?>" />
            <input type="hidden" name="id_iteracion" value="<?= (int)$idIter; ?>" />

            <div class="form-group">
              <label>Valor planificado</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars((string)$valPlan); ?>" readonly />
            </div>

            <div class="form-group">
              <label>Umbral de desviación</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars((string)$umbral); ?>" readonly />
            </div>

            <div class="form-group">
              <label for="valor_ejecutado">Valor ejecutado</label>
              <input
                type="number" step="any" min="0"
                class="form-control"
                id="valor_ejecutado"
                name="valor_ejecutado"
                required
                value="<?= htmlspecialchars((string)$valEjec); ?>"
                placeholder="Ej: 125" />
              <small class="form-text text-muted">
                Ingrese el valor realmente alcanzado por la métrica.
              </small>
            </div>

            <button type="submit" class="btn btn-outline-success">
              <span class="oi oi-check"></span> Guardar Ejecución
            </button>
            <a href="metricas.php" class="btn btn-outline-danger ml-2">
              <span class="oi oi-x"></span> Cancelar
            </a>
          </form>
        <?php endif; ?>

      </div>
    </div>
  </div>
  <?php include_once '../gui/footer.php'; ?>
</body>

</html>