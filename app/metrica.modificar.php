<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);
if (!$esAdmin && !$tienePermGestionMetricas) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$cn = BDConexion::getInstancia();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: metricas.php?msg=' . urlencode('Métrica inválida.') . '&type=danger');
    exit;
}

// Cargar métrica
$hasTipo = false; $tipo = null;
try { if ($rsC = $cn->query("SHOW COLUMNS FROM metrica LIKE 'tipo'")) { $hasTipo = (bool)$rsC->num_rows; } } catch (Throwable $e) { $hasTipo = false; }
$sqlMet = $hasTipo
    ? 'SELECT id_metrica, nombre, descripcion, tipo FROM metrica WHERE id_metrica = ' . $id . ' LIMIT 1'
    : 'SELECT id_metrica, nombre, descripcion FROM metrica WHERE id_metrica = ' . $id . ' LIMIT 1';
$rsM = $cn->query($sqlMet);
if (!$rsM || !$rsM->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
    exit;
}
$metrica = $rsM->fetch_assoc();
if ($hasTipo) { $tipo = strtolower(trim($metrica['tipo'] ?? '')); }

// Reglas: si no hay columna tipo, tratamos como base; no-admin no puede editar base
if ((!$hasTipo || $tipo === 'base') && !$esAdmin) {
    http_response_code(403);
    echo 'Acceso denegado (solo lectura)';
    exit;
}

// Cargar asociaciones posibles y actuales
$modelosGlobales = [];
$modelosProyecto = [];
$seleccionadosGlobal = [];
$seleccionadosProyecto = [];

if ($esAdmin) {
    // Admin edita asociaciones globales
    if ($rs = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre")) {
        $modelosGlobales = $rs->fetch_all(MYSQLI_ASSOC);
    }
    if ($rsSel = $cn->query('SELECT id_modelo FROM metrica_modelo_calidad WHERE id_metrica = ' . $id)) {
        while ($r = $rsSel->fetch_assoc()) { $seleccionadosGlobal[] = (int)$r['id_modelo']; }
    }
} else {
    // No admin: asociaciones por proyecto (solo personalizados que pertenecen al usuario)
    $usr = ControlAcceso::usuarioActual();
    $sql = "SELECT pmc.id_proyecto_modelo, pmc.nombre, p.nombre AS proyecto
            FROM proyecto_modelo_calidad pmc
            JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
            JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
            WHERE up.id_usuario = ? AND IFNULL(pmc.es_personalizado,1) = 1
            ORDER BY p.nombre, pmc.nombre";
    if ($stmt = $cn->prepare($sql)) {
        $uid = (int)$usr->id;
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $modelosProyecto = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
    if ($rsSel = $cn->query('SELECT id_proyecto_modelo FROM metrica_proyecto_modelo WHERE id_metrica = ' . $id)) {
        while ($r = $rsSel->fetch_assoc()) { $seleccionadosProyecto[] = (int)$r['id_proyecto_modelo']; }
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
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Editar Métrica</title>
  </head>
  <body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
      <div class="mb-3">
        <a id="btnVolver" href="metricas.php" class="btn btn-outline-secondary">
          <span class="oi oi-arrow-left mr-1"></span> Volver
        </a>
      </div>
      <form action="metrica.modificar.procesar.php" method="post">
        <input type="hidden" name="id" value="<?= (int)$metrica['id_metrica']; ?>" />
        <div class="card">
          <div class="card-header">
            <h3>Editar Métrica <?= $hasTipo ? '<span class="badge badge-' . ($tipo==='base'?'secondary':'info') . '">' . htmlspecialchars(ucfirst($tipo)) . '</span>' : '' ?></h3>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label for="nombre">Nombre</label>
              <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100" value="<?= htmlspecialchars($metrica['nombre']); ?>" />
            </div>
            <div class="form-group">
              <label for="descripcion">Descripción</label>
              <input type="text" class="form-control" id="descripcion" name="descripcion" maxlength="255" value="<?= htmlspecialchars($metrica['descripcion'] ?? ''); ?>" />
            </div>

            <div class="form-group">
              <label>Asociaciones</label><br/>
              <?php if ($esAdmin): ?>
                <?php if (empty($modelosGlobales)): ?>
                  <div class="text-muted">No hay modelos globales.</div>
                <?php else: foreach ($modelosGlobales as $m): $mid=(int)$m['id_modelo']; ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="mg<?= $mid; ?>" name="modelos_globales[]" value="<?= $mid; ?>" <?= in_array($mid, $seleccionadosGlobal, true) ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="mg<?= $mid; ?>"><?= htmlspecialchars($m['nombre']); ?></label>
                  </div>
                <?php endforeach; endif; ?>
              <?php else: ?>
                <?php if (empty($modelosProyecto)): ?>
                  <div class="text-muted">No hay modelos personalizados de tus proyectos.</div>
                <?php else: foreach ($modelosProyecto as $mp): $pid=(int)$mp['id_proyecto_modelo']; ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="mp<?= $pid; ?>" name="modelos_proyecto[]" value="<?= $pid; ?>" <?= in_array($pid, $seleccionadosProyecto, true) ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="mp<?= $pid; ?>"><?= htmlspecialchars($mp['proyecto'] . ' — ' . $mp['nombre']); ?></label>
                  </div>
                <?php endforeach; endif; ?>
                <small class="form-text text-muted">Solo se admiten modelos personalizados a los que pertenecés.</small>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-footer">
            <button type="submit" class="btn btn-outline-success"><span class="oi oi-check"></span> Guardar</button>
            <a href="metricas.php"><button type="button" class="btn btn-outline-danger"><span class="oi oi-x"></span> Cancelar</button></a>
          </div>
        </div>
      </form>
    </div>
    <?php include_once '../gui/footer.php'; ?>
  </body>
  </html>
