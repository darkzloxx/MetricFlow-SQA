<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

// 🚫 Admin no ejecuta métricas
if ($esAdmin) {
  header('Location: metricas.php?msg=' . urlencode('Los administradores no registran ejecución de métricas.') . '&type=warning');
  exit;
}
if (!ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
  http_response_code(403);
  echo 'Acceso denegado';
  exit;
}

// 🔍 Validar parámetros
$idMetrica = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idProyecto = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;

if ($idMetrica <= 0 || $idProyecto <= 0) {
  header('Location: metricas.php?msg=' . urlencode('Faltan parámetros de métrica o proyecto.') . '&type=danger');
  exit;
}

$cn = BDConexion::getInstancia();

// ==================================================
// 🔹 Cargar la métrica
// ==================================================
$sqlMet = "SELECT id_metrica, nombre, descripcion, tipo FROM metrica WHERE id_metrica = {$idMetrica} LIMIT 1";
$rsMet = $cn->query($sqlMet);
if (!$rsMet || !$rsMet->num_rows) {
  header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
  exit;
}
$met = $rsMet->fetch_assoc();

// ==================================================
// 🔹 Buscar iteración activa SOLO del proyecto recibido
// ==================================================
$sqlIter = "
  SELECT i.id_iteracion, i.numero_iteracion, i.fecha_inicio, i.fecha_fin,
         f.nombre AS fase, p.nombre AS proyecto
  FROM iteracion i
  JOIN fase f ON f.id_fase = i.id_fase
  JOIN proyecto p ON p.id_proyecto = i.id_proyecto
  WHERE p.id_proyecto = {$idProyecto}
    AND CURRENT_DATE() BETWEEN i.fecha_inicio AND i.fecha_fin
  LIMIT 1";
$rsIter = $cn->query($sqlIter);
$iteracionActual = ($rsIter && $rsIter->num_rows > 0) ? $rsIter->fetch_assoc() : null;

// Si no hay iteración activa, bloquear ejecución
if (!$iteracionActual) {
  header('Location: metricas.php?msg=' . urlencode('No hay una iteración activa actualmente para este proyecto.') . '&type=warning');
  exit;
}

$idIter = (int)$iteracionActual['id_iteracion'];
$rsPlan = $cn->query("SELECT valor_planificado, valor_ejecutado, umbral_desviacion 
                      FROM metrica_iteracion 
                      WHERE id_metrica = {$idMetrica} AND id_iteracion = {$idIter} LIMIT 1");

$planificado = false;
$ejecutado = false;
$valPlan = null;
$valEjec = null;
$umbral = null;

if ($rsPlan && $fila = $rsPlan->fetch_assoc()) {
  $planificado = $fila['valor_planificado'] !== null;
  $ejecutado = $fila['valor_ejecutado'] !== null;
  $valPlan = $fila['valor_planificado'];
  $valEjec = $fila['valor_ejecutado'];
  $umbral = $fila['umbral_desviacion'];
}
?>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
  <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
  <script src="../lib/JQuery/jquery-3.3.1.js"></script>
  <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
  <title><?= Constantes::NOMBRE_SISTEMA; ?> - Ejecutar Métrica</title>
  <style>
    .btn-outline-secondary {
      border-color: #dee2e6;
      color: #495057;
      background: #fff;
    }

    .btn-outline-secondary:hover {
      background: #f8f9fa;
      color: #212529;
    }
  </style>
</head>

<body>
  <?php include_once '../gui/navbar.php'; ?>

  <div class="container mt-3">
    <div class="mb-3">
      <a href="metricas.php" class="btn btn-outline-secondary">
        <span class="oi oi-arrow-left"></span> Volver
      </a>
    </div>

    <div class="card shadow-sm">
      <div class="card-header">
        <h3>Registrar Ejecución</h3>
      </div>

      <div class="card-body">
        <p><strong>Métrica:</strong> <?= htmlspecialchars($met['nombre']); ?></p>
        <p><strong>Descripción:</strong> <?= htmlspecialchars($met['descripcion'] ?? ''); ?></p>
        <hr />

        <?php if (!$planificado): ?>
          <div class="alert alert-warning">
            La métrica aún no está planificada en la iteración actual. No se puede registrar ejecución.
          </div>
        <?php else: ?>
          <?php if ($ejecutado): ?>
            <div class="alert alert-info">
              La métrica ya tiene un valor ejecutado (<strong><?= htmlspecialchars((string)$valEjec); ?></strong>).
              Podés actualizarlo si es necesario.
            </div>
          <?php endif; ?>

          <form method="post" action="metrica.ejecutar.procesar.php" class="mt-2">
            <input type="hidden" name="id_metrica" value="<?= $idMetrica; ?>" />
            <input type="hidden" name="id_iteracion" value="<?= $idIter; ?>" />
            <input type="hidden" name="id_proyecto" value="<?= $idProyecto; ?>" />

            <div class="form-group">
              <label>Iteración actual</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars($iteracionActual['proyecto']); ?> — <?= htmlspecialchars($iteracionActual['fase']); ?> <?= (int)$iteracionActual['numero_iteracion']; ?> (<?= htmlspecialchars($iteracionActual['fecha_inicio']); ?> a <?= htmlspecialchars($iteracionActual['fecha_fin']); ?>)">
            </div>

            <div class="form-group">
              <label>Valor planificado</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars((string)$valPlan); ?>" readonly />
            </div>

            <div class="form-group">
              <label>Umbral de desviación (%)</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars((string)$umbral); ?>" readonly />
            </div>

            <div class="form-group">
              <label for="valor_ejecutado">Valor ejecutado</label>
              <input type="number" step="any" min="0" class="form-control" id="valor_ejecutado" name="valor_ejecutado"
                required value="<?= htmlspecialchars((string)$valEjec); ?>" placeholder="Ej: 125" />
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