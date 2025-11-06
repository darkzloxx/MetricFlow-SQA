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
// Modelos base disponibles para prefijar métricas
$rsMb = BDConexion::getInstancia()->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre");
$modelosBase = $rsMb ? $rsMb->fetch_all(MYSQLI_ASSOC) : [];

// Proyectos disponibles: Admin/SuperAdmin ve todos, el resto solo los propios
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
if ($esAdmin) {
  $rsP = BDConexion::getInstancia()->query("SELECT id_proyecto, nombre FROM proyecto ORDER BY nombre");
} else {
  $usr = ControlAcceso::usuarioActual();
  $stmt = BDConexion::getInstancia()->prepare(
    "SELECT p.id_proyecto, p.nombre 
         FROM usuario_proyecto up
         JOIN proyecto p ON p.id_proyecto = up.id_proyecto
         WHERE up.id_usuario = ?
         ORDER BY p.nombre"
  );
  $uid = (int)$usr->id;
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $rsP = $stmt->get_result();
}
$proyectos = $rsP ? $rsP->fetch_all(MYSQLI_ASSOC) : [];

// Filtrar proyectos elegibles (sin métricas planificadas) para usuarios no admin
if (!$esAdmin && !empty($proyectos)) {
  $idsAll = array_map(function($r){ return (int)$r['id_proyecto']; }, $proyectos);
  $idsAll = array_filter($idsAll, function($v){ return $v > 0; });
  $mapPlan = [];
  if (!empty($idsAll)) {
    $in = implode(',', $idsAll);
    $sqlPlan = "SELECT i.id_proyecto AS id, COUNT(*) AS c
                FROM metrica_iteracion mi
                JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                WHERE mi.valor_planificado IS NOT NULL AND i.id_proyecto IN ($in)
                GROUP BY i.id_proyecto";
    if ($rs = BDConexion::getInstancia()->query($sqlPlan)) {
      while ($row = $rs->fetch_assoc()) { $mapPlan[(int)$row['id']] = (int)$row['c']; }
    }
  }
  $proyectos = array_values(array_filter($proyectos, function($p) use ($mapPlan){
    $pid = (int)$p['id_proyecto'];
    return empty($mapPlan[$pid]); // elegible si NO tiene planificación
  }));
  if (empty($proyectos)) {
    header('Location: modelos.php?msg=' . urlencode('No hay proyectos elegibles para crear un modelo personalizado (ya tienen métricas planificadas).') . '&type=danger');
    exit;
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
  <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Modelo</title>
  <style>
    .chip {
      display: inline-flex;
      align-items: center;
      padding: 0 .5rem;
      border: 1px solid #ced4da;
      border-radius: 16px;
      margin: 2px;
      background: #f8f9fa;
    }

    .chip .remove {
      border: 0;
      background: transparent;
      color: #6c757d;
      margin-left: .25rem;
      cursor: pointer;
    }

    .btn-outline-secondary {
      border-color: #dee2e6;
      color: #495057;
      background-color: #fff;
    }

    .btn-outline-secondary:hover {
      background-color: #f8f9fa;
      color: #212529;
    }
    .modelo-card { cursor:pointer; transition: box-shadow .2s ease; }
    .modelo-card:hover { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); }
    .modelo-card.active { border: 2px solid #17a2b8; }
    .modelo-card.disabled { opacity:.6; cursor:not-allowed; pointer-events:none; }
  </style>
</head>

<body>
  <?php include_once '../gui/navbar.php'; ?>
  <div class="container">
    <div class="mb-3">
      <a id="btnVolver" href="modelos.php" class="btn btn-outline-secondary">
        <span class="oi oi-arrow-left mr-1"></span> Volver
      </a>
    </div>
    <form action="modelo.nuevo.procesar.php" method="post">
      <div class="card mt-3">
        <div class="card-header">
          <h3>Crear modelo de calidad</h3>
        </div>
        <div class="card-body">
          <?php if ($esAdmin) { ?>
            <div class="form-group form-check">
              <input type="checkbox" class="form-check-input" id="global" name="global" value="1" checked>
              <label class="form-check-label" for="global">Modelo predeterminado (global para todos los proyectos)</label>
            </div>
          <?php } ?>
          <div class="form-group" id="grpProyecto" style="<?= $esAdmin ? '' : '' ?>">
            <label for="proyecto">Asignar al proyecto</label>
            <select class="form-control" id="proyecto" name="proyecto" <?= $esAdmin ? 'disabled' : '' ?>>
              <?php if (empty($proyectos)) { ?>
                <option value="">No tiene proyectos asignados</option>
                <?php } else {
                foreach ($proyectos as $p) { ?>
                  <option value="<?= (int)$p['id_proyecto'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
              <?php }
              } ?>
            </select>
            <small class="form-text text-muted">Si es global, no se asignará automáticamente a un proyecto.</small>
          </div>
          <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required />
          </div>
          <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
          </div>
          <?php if (!empty($modelosBase)) { ?>
          <div class="form-group">
            <label>Modelo base (opcional)</label>
            <?php if ($esAdmin) { ?>
              <div class="mb-2 text-muted small">
                Como Administrador/SuperAdmin, los modelos predeterminados deben crearse desde cero. La selección de un modelo base está deshabilitada.
              </div>
            <?php } else { ?>
              <div class="mb-2 text-muted small">Seleccioná un modelo existente para tomar sus métricas como punto de partida. Luego podés ajustar la lista.</div>
            <?php } ?>
            <div class="row">
              <?php foreach ($modelosBase as $mb): ?>
                <div class="col-md-4 mb-3">
                  <div class="card modelo-card <?= $esAdmin ? 'disabled' : '' ?>" data-id="<?= (int)$mb['id_modelo']; ?>" title="<?= $esAdmin ? 'Solo disponible para Gerentes/Líderes' : 'Click para seleccionar' ?>">
                    <div class="card-body p-3">
                      <h6 class="card-title mb-1"><?= htmlspecialchars($mb['nombre']); ?></h6>
                      <div class="text-muted small" style="min-height:2.5em;">
                        <?= htmlspecialchars(mb_strimwidth($mb['descripcion'] ?? '', 0, 120, '…', 'UTF-8')); ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (!$esAdmin) { ?>
              <div class="mt-1">
                <span id="modeloBaseSeleccionado" class="badge badge-info d-none"></span>
                <button type="button" id="btnLimpiarBase" class="btn btn-sm btn-outline-secondary d-none">Quitar modelo base</button>
              </div>
            <?php } ?>
          </div>
          <?php } ?>
          <div class="form-group">
            <label>Métricas asociadas</label>
            <div class="border rounded p-2" style="max-height: 260px; overflow:auto;">
              <?php if (empty($metricas)) { ?>
                <div class="text-muted">No hay métricas definidas.</div>
                <?php } else {
                foreach ($metricas as $met) { ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="<?= (int)$met['id_metrica'] ?>" id="m<?= (int)$met['id_metrica'] ?>" name="metricas[]">
                    <label class="form-check-label" for="m<?= (int)$met['id_metrica'] ?>" title="<?= htmlspecialchars($met['descripcion']) ?>">
                      <?= htmlspecialchars($met['nombre']) ?>
                    </label>
                  </div>
              <?php }
              } ?>
            </div>
            <small class="form-text text-muted">Podrá ajustar métricas luego desde Configurar modelo (CU07).</small>
            <div class="mt-2">
              <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#modalNuevaMetrica">
                <span class="oi oi-plus"></span> Nueva métrica
              </button>
              <button type="button" id="btnLimpiarMetricas" class="btn btn-outline-secondary ml-2">
                Limpiar selección
              </button>
            </div>
            <div id="metricasNuevasChips" class="mt-2"></div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-outline-success">
            <span class="oi oi-check"></span> Confirmar
          </button>
          <a href="modelos.php"><button type="button" class="btn btn-outline-danger">
              <span class="oi oi-x"></span> Cancelar
            </button></a>
          <?php if ($esAdmin) { ?>
            <a href="modelo.nuevo.predeterminado.procesar.php" class="btn btn-link">Crear rápido un modelo predeterminado</a>
          <?php } ?>
          <!-- inputs ocultos donde agregamos las nuevas métricas -->
          <div id="metricasNuevasInputs"></div>
        </div>
      </div>
    </form>
  </div>
  <!-- Modal Nueva Métrica -->
  <div class="modal fade" id="modalNuevaMetrica" tabindex="-1" role="dialog" aria-labelledby="modalNuevaMetricaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNuevaMetricaLabel">Nueva métrica</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="nmNombre">Nombre</label>
            <input type="text" id="nmNombre" class="form-control" maxlength="120" />
          </div>
          <div class="form-group">
            <label for="nmDescripcion">Descripción</label>
            <textarea id="nmDescripcion" class="form-control" rows="3"></textarea>
          </div>
          <div class="text-muted small">Se agregará al enviar el formulario y quedará vinculada a este modelo.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="button" id="btnAgregarMetrica" class="btn btn-primary">
            <span class="oi oi-check"></span> Agregar
          </button>
        </div>
      </div>
    </div>
  </div>
  <script>
    // Si es admin, alternar campo proyecto según el check de global
    $(function() {
      var ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
      var $global = $('#global');
      var $proy = $('#proyecto');
      if ($global.length) {
        function toggleProyecto() {
          var g = $global.is(':checked');
          $proy.prop('disabled', g);
        }
        $global.on('change', toggleProyecto);
        toggleProyecto();
      }

      $('#btnAgregarMetrica').on('click', function() {
        var nombre = ($('#nmNombre').val() || '').trim();
        var desc = ($('#nmDescripcion').val() || '').trim();
        if (!nombre) {
          $('#nmNombre').focus();
          return;
        }
        // Crear inputs ocultos
        var idx = $('#metricasNuevasInputs .nm-item').length;
        var $wrap = $('<div class="nm-item"></div>');
        $wrap.append('<input type="hidden" name="metricas_nuevas[nombre][]" value="' + $('<div/>').text(nombre).html() + '" />');
        $wrap.append('<input type="hidden" name="metricas_nuevas[descripcion][]" value="' + $('<div/>').text(desc).html() + '" />');
        $('#metricasNuevasInputs').append($wrap);

        // Mostrar chip y permitir quitar
        var $chip = $('<span class="chip" title="' + desc.replace(/\"/g, '&quot;') + '">' + nombre + '<button type="button" class="remove" aria-label="Quitar">&times;</button></span>');
        $chip.find('.remove').on('click', function() {
          var i = $chip.index();
          $('#metricasNuevasInputs .nm-item').eq(i).remove();
          $chip.remove();
        });
        $('#metricasNuevasChips').append($chip);

        // limpiar y cerrar
        $('#nmNombre').val('');
        $('#nmDescripcion').val('');
        $('#modalNuevaMetrica').modal('hide');
      });

      // UI: seleccionar modelo base y pre-chequear métricas
      var baseSeleccionada = null;
      function marcarCard($card, active) {
        $('.modelo-card').removeClass('active');
        if (active) $card.addClass('active');
      }
      function setModeloBase(nombre, id) {
        baseSeleccionada = id;
        $('#modeloBaseSeleccionado').removeClass('d-none').text('Base: ' + nombre);
        $('#btnLimpiarBase').removeClass('d-none');
      }
      function limpiarModeloBase() {
        baseSeleccionada = null;
        $('#modeloBaseSeleccionado').addClass('d-none').text('');
        $('#btnLimpiarBase').addClass('d-none');
        marcarCard($(), false);
      }
      if (!ES_ADMIN) {
        $('.modelo-card').on('click', function() {
          var $c = $(this);
          if ($c.hasClass('disabled')) return;
          var id = parseInt($c.data('id')) || 0;
          var nombre = $.trim($c.find('.card-title').text());
          if (!id) return;
          marcarCard($c, true);
          setModeloBase(nombre, id);
          // Cargar métricas del modelo base
          $.getJSON('api/modelo_metricas.php', { id_modelo: id })
            .done(function(resp) {
              if (!resp || !resp.ok) return;
              // limpiar selección previa
              $('input[name="metricas[]"]').prop('checked', false);
              // marcar métricas
              (resp.metricas || []).forEach(function(m){
                var idm = parseInt(m.id_metrica) || 0;
                if (idm) $('#m' + idm).prop('checked', true);
              });
            });
        });
        $('#btnLimpiarBase').on('click', function(){ limpiarModeloBase(); });
      }
      $('#btnLimpiarMetricas').on('click', function(){ $('input[name="metricas[]"]').prop('checked', false); });
    });
  </script>
  <?php include_once '../gui/footer.php'; ?>
</body>

</html>