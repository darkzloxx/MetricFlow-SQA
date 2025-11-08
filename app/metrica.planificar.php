<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
// Admin no planifica métricas
if ($esAdmin) {
    header('Location: metricas.php?msg=' . urlencode('Los administradores no planifican métricas.') . '&type=warning');
    exit;
}
if (!ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
    http_response_code(403); echo 'Acceso denegado'; exit;
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: metricas.php?msg=' . urlencode('ID inválido.') . '&type=danger'); exit; }
$cn = BDConexion::getInstancia();

// Cargar métrica
$hasTipo=false; try{ if($rsC=$cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")){ $hasTipo=(bool)$rsC->num_rows; } }catch(Throwable $e){ $hasTipo=false; }
$sqlMet = $hasTipo ? 'SELECT id_metrica,nombre,descripcion,tipo FROM metrica WHERE id_metrica='.$id.' LIMIT 1' : 'SELECT id_metrica,nombre,descripcion FROM metrica WHERE id_metrica='.$id.' LIMIT 1';
$rsMet = $cn->query($sqlMet);
if(!$rsMet||!$rsMet->num_rows){ header('Location: metricas.php?msg='.urlencode('La métrica no existe.').'&type=danger'); exit; }
$met = $rsMet->fetch_assoc();
$tipo = $hasTipo ? strtolower(trim($met['tipo'] ?? '')) : 'personalizada';
if ($tipo === 'base') { header('Location: metricas.php?msg='.urlencode('No se puede planificar una métrica base.').'&type=warning'); exit; }

// Comprobar si ya está planificada
// Cargar iteraciones de proyectos del usuario
$iteraciones = [];
if ($usr) {
  $sqlI = "SELECT i.id_iteracion, i.numero_iteracion, i.fecha_inicio, i.fecha_fin, p.nombre AS proyecto
       FROM iteracion i
       JOIN proyecto p ON p.id_proyecto = i.id_proyecto
       JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
       WHERE up.id_usuario = ?
       ORDER BY p.nombre, i.fecha_inicio";
  if ($stI = $cn->prepare($sqlI)) {
    $stI->bind_param('i', $usr->id);
    $stI->execute();
    $resI = $stI->get_result();
    $iteraciones = $resI ? $resI->fetch_all(MYSQLI_ASSOC) : [];
    $stI->close();
  }
}

// Ya planificada para alguna iteración?
$rsPlan = $cn->query('SELECT 1 FROM metrica_iteracion WHERE id_metrica='.$id.' AND valor_planificado IS NOT NULL LIMIT 1');
$yaPlanificada = (bool)($rsPlan && $rsPlan->num_rows);
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
      <div class="mb-3"><a href="metricas.php" class="btn btn-outline-secondary"><span class="oi oi-arrow-left"></span> Volver</a></div>
      <div class="card">
        <div class="card-header"><h3>Planificar Métrica</h3></div>
        <div class="card-body">
          <p><strong>Métrica:</strong> <?= htmlspecialchars($met['nombre']); ?></p>
          <p><strong>Descripción:</strong> <?= htmlspecialchars($met['descripcion'] ?? ''); ?></p>
          <hr />
          <?php if ($yaPlanificada): ?>
            <div class="alert alert-info">Esta métrica ya tiene al menos una planificación registrada. Podés continuar para planificar otra iteración, si corresponde.</div>
          <?php endif; ?>

          <?php if (empty($iteraciones)): ?>
            <div class="alert alert-warning">No tenés iteraciones disponibles en tus proyectos.</div>
          <?php else: ?>
            <form method="post" action="metrica.planificar.procesar.php" class="mt-2">
              <input type="hidden" name="id_metrica" value="<?= (int)$met['id_metrica']; ?>" />
              <div class="form-group">
                <label for="iteracion">Iteración</label>
                <select id="iteracion" name="id_iteracion" class="form-control" required>
                  <option value="">Seleccione una iteración…</option>
                  <?php foreach ($iteraciones as $it): ?>
                  <option value="<?= (int)$it['id_iteracion']; ?>">
                    <?= htmlspecialchars($it['proyecto']); ?> — Iteración <?= (int)$it['numero_iteracion']; ?> (<?= htmlspecialchars($it['fecha_inicio']); ?> a <?= htmlspecialchars($it['fecha_fin']); ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="valor_planificado">Valor planificado</label>
                <input type="number" step="any" min="0" class="form-control" id="valor_planificado" name="valor_planificado" required placeholder="Ej: 125" />
              </div>
              <div class="form-group">
                <label for="umbral">Umbral de desviación</label>
                <input type="number" step="any" min="0" class="form-control" id="umbral" name="umbral" required placeholder="Ej: 10" />
                <small class="form-text text-muted">El umbral de desviación define el porcentaje o valor permitido de diferencia entre lo planificado y lo ejecutado.</small>
              </div>
              <button type="submit" class="btn btn-outline-success"><span class="oi oi-check"></span> Confirmar planificación</button>
              <a href="metricas.php" class="btn btn-outline-danger ml-2"><span class="oi oi-x"></span> Cancelar</a>
            </form>
          <?php endif; ?>
        </div>
        <div class="card-footer"><a href="metricas.php" class="btn btn-outline-primary"><span class="oi oi-x"></span> Cancelar</a></div>
      </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
  </body>
</html>
