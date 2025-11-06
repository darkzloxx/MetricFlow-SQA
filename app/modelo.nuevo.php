<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
// Acceso: Admin/SuperAdmin o permiso de gestión de modelo
if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido para crear modelos.') . '&type=danger');
    exit;
}

$rsM = BDConexion::getInstancia()->query("SELECT id_metrica, nombre, descripcion FROM metrica ORDER BY nombre");
$metricas = $rsM ? $rsM->fetch_all(MYSQLI_ASSOC) : [];
?>
<html>
  <head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Modelo</title>
  </head>
  <body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
      <form action="modelo.nuevo.procesar.php" method="post">
        <div class="card mt-3">
          <div class="card-header">
            <h3>Crear modelo de calidad</h3>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label for="nombre">Nombre</label>
              <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required />
            </div>
            <div class="form-group">
              <label for="descripcion">Descripción</label>
              <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
            </div>
            <div class="form-group">
              <label>Métricas asociadas</label>
              <div class="border rounded p-2" style="max-height: 260px; overflow:auto;">
                <?php if (empty($metricas)) { ?>
                  <div class="text-muted">No hay métricas definidas.</div>
                <?php } else { foreach ($metricas as $met) { ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="<?= (int)$met['id_metrica'] ?>" id="m<?= (int)$met['id_metrica'] ?>" name="metricas[]">
                    <label class="form-check-label" for="m<?= (int)$met['id_metrica'] ?>" title="<?= htmlspecialchars($met['descripcion']) ?>">
                      <?= htmlspecialchars($met['nombre']) ?>
                    </label>
                  </div>
                <?php } } ?>
              </div>
              <small class="form-text text-muted">Podrá ajustar métricas luego desde Configurar modelo (CU07).</small>
            </div>
          </div>
          <div class="card-footer">
            <button type="submit" class="btn btn-outline-success">
              <span class="oi oi-check"></span> Confirmar
            </button>
            <a href="modelos.php"><button type="button" class="btn btn-outline-danger">
              <span class="oi oi-x"></span> Cancelar
            </button></a>
          </div>
        </div>
      </form>
    </div>
    <?php include_once '../gui/footer.php'; ?>
  </body>
</html>
