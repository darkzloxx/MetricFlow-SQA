<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Solo Admin/SuperAdmin pueden crear modelos predeterminados (globales)
if (!ControlAcceso::esAdminGlobal()) {
  header('Location: modelos.php?msg=' . urlencode('Acceso restringido: solo administradores pueden crear modelos predeterminados.') . '&type=danger');
  exit;
}

// Este formulario permite crear un modelo predeterminado desde cero o tomando como base otro modelo.
// Se listan métricas existentes para seleccionarlas y también se pueden agregar nuevas desde el modal.
// Cargar métricas existentes y modelos base
$rsM = BDConexion::getInstancia()->query("SELECT id_metrica, nombre, descripcion FROM metrica ORDER BY nombre");
$metricas = $rsM ? $rsM->fetch_all(MYSQLI_ASSOC) : [];
$rsMb = BDConexion::getInstancia()->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre");
$modelosBase = $rsMb ? $rsMb->fetch_all(MYSQLI_ASSOC) : [];
?>
<html>

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
  <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
  <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
  <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
  <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Modelo Predeterminado</title>
  <style>
    .btn-outline-secondary {
      border-color: #dee2e6;
      color: #495057;
      background-color: #fff;
    }

    .btn-outline-secondary:hover {
      background-color: #f8f9fa;
      color: #212529;
    }
    .chip { display: inline-flex; align-items: center; padding: 0 .5rem; border: 1px solid #ced4da; border-radius: 16px; margin: 2px; background: #f8f9fa; }
    .chip .remove { border: 0; background: transparent; color: #6c757d; margin-left: .25rem; cursor: pointer; }
    .modelo-card { cursor:pointer; transition: box-shadow .2s ease; }
    .modelo-card:hover { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); }
    .modelo-card.active { border: 2px solid #17a2b8; }
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
      <input type="hidden" name="global" value="1" />
      <div class="card mt-3">
        <div class="card-header">
          <h3>Crear modelo predeterminado (global)</h3>
          <div class="text-muted small">Como administrador podés partir de un modelo base (opcional) o crear desde cero. Este modelo quedará disponible para todos los proyectos.</div>
        </div>
  <div class="card-body">
          <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required />
          </div>
          <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required></textarea>
          </div>

          <?php if (!empty($modelosBase)) { ?>
          <div class="form-group">
            <label class="d-block">Modelo base (opcional)</label>
            <button id="btnToggleBase" class="btn btn-sm btn-outline-secondary mb-2" type="button" aria-expanded="false" aria-controls="collapseModeloBase">
              <span id="iconToggleBase" class="oi oi-chevron-bottom mr-1"></span> <span class="txt-toggle-base">Seleccionar modelo base</span>
            </button>
            <div id="collapseModeloBase" class="collapse">
              <div class="mb-2 text-muted small">Elegí un modelo para precargar sus métricas. Podrás ajustar la lista.</div>
              <div class="row">
                <?php foreach ($modelosBase as $mb): ?>
                  <div class="col-md-4 mb-3">
                    <div class="card modelo-card" data-id="<?= (int)$mb['id_modelo']; ?>" title="Click para seleccionar">
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
              
            </div>
            <div class="mt-1">
                <span id="modeloBaseChip" class="chip d-none" title="Modelo base seleccionado">
                  <span class="mr-1">Base:</span>
                  <strong id="modeloBaseNombre"></strong>
                  <button type="button" id="btnChipQuitarBase" class="remove" aria-label="Quitar modelo base">&times;</button>
                </span>
            </div>
          </div>
          <?php } ?>

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
            <div class="mt-2 d-flex align-items-center flex-wrap">
              <button type="button" class="btn btn-outline-primary mr-2" data-toggle="modal" data-target="#modalNuevaMetrica">
                <span class="oi oi-plus"></span> Nueva métrica
              </button>
              <button type="button" id="btnLimpiarMetricas" class="btn btn-outline-secondary">Limpiar selección</button>
              <span id="metricasSeleccionadasCount" class="badge badge-info ml-3 d-none"></span>
            </div>
            <div id="metricasNuevasChips" class="mt-2"></div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-outline-success">
            <span class="oi oi-check"></span> Confirmar
          </button>
          <a href="modelos.php" onclick="return confirm('¿Cancelar la creación del modelo? Se perderán los cambios no guardados.');"><button type="button" class="btn btn-outline-danger">
              <span class="oi oi-x"></span> Cancelar
            </button></a>
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
            <div class="invalid-feedback">El nombre es obligatorio.</div>
          </div>
          <div class="form-group">
            <label for="nmDescripcion">Descripción</label>
            <textarea id="nmDescripcion" class="form-control" rows="3"></textarea>
            <div class="invalid-feedback">La descripción es obligatoria.</div>
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
    $(function(){
      // Contador dinámico: métrica(s) existentes marcadas + nuevas agregadas
      function actualizarCount(){
        var total = $('input[name="metricas[]"]:checked').length + $('input[name="metricas_nuevas[nombre][]"]').length;
        var $b = $('#metricasSeleccionadasCount');
        if (total>0){ $b.text(total+' seleccionadas').removeClass('d-none'); } else { $b.addClass('d-none').text(''); }
      }
      // Agregar nueva métrica a inputs ocultos + chip
      function limpiarValidacionesModal(){
        $('#nmNombre').removeClass('is-invalid');
        $('#nmDescripcion').removeClass('is-invalid');
      }

      $('#btnAgregarMetrica').on('click', function() {
        limpiarValidacionesModal();
        var nombre = ($('#nmNombre').val() || '').trim();
        var desc = ($('#nmDescripcion').val() || '').trim();

        // Validaciones obligatorias
        if (!nombre) { $('#nmNombre').addClass('is-invalid').focus(); return; }
        if (!desc) { $('#nmDescripcion').addClass('is-invalid').focus(); return; }

        var $wrap = $('<div class="nm-item"></div>');
        $wrap.append('<input type="hidden" name="metricas_nuevas[nombre][]" value="' + $('<div/>').text(nombre).html() + '" />');
        $wrap.append('<input type="hidden" name="metricas_nuevas[descripcion][]" value="' + $('<div/>').text(desc).html() + '" />');
        $('#metricasNuevasInputs').append($wrap);
  var $chip = $('<span class="chip" title="' + desc.replace(/\"/g, '&quot;') + '">' + nombre + '<button type="button" class="remove" aria-label="Quitar">&times;</button></span>');
  $chip.find('.remove').on('click', function() { var i = $chip.index(); $('#metricasNuevasInputs .nm-item').eq(i).remove(); $chip.remove(); actualizarCount(); });
        $('#metricasNuevasChips').append($chip);
  $('#nmNombre').val(''); $('#nmDescripcion').val(''); actualizarCount();
        // Cerrar el modal limpiamente (a veces queda el backdrop si no se fuerza)
        $('#modalNuevaMetrica').one('hidden.bs.modal', function(){
          $('body').removeClass('modal-open');
          $('.modal-backdrop').remove();
        }).modal('hide');
        // Fallback por si algún tema/JS impide el evento anterior
        setTimeout(function(){ $('body').removeClass('modal-open'); $('.modal-backdrop').remove(); }, 250);
      });

      // En Enter dentro de inputs, intentar agregar
      $('#nmNombre, #nmDescripcion').on('keypress', function(e){ if (e.which === 13) { e.preventDefault(); $('#btnAgregarMetrica').click(); } });

      // Selección de modelo base para pre-chequear métricas
      function marcarCard($card, active) { $('.modelo-card').removeClass('active'); if (active) $card.addClass('active'); }
      function setModeloBase(nombre, id) { $('#modeloBaseNombre').text(nombre); $('#modeloBaseChip').removeClass('d-none'); }
      function limpiarModeloBaseUI() { $('#modeloBaseNombre').text(''); $('#modeloBaseChip').addClass('d-none'); marcarCard($(), false); }
      function quitarModeloBase(){
        // Destildar solo las métricas que fueron marcadas por el modelo base
        baseMetricIds.forEach(function(idm){ $('#m'+idm).prop('checked', false); });
        baseMetricIds = [];
        limpiarModeloBaseUI();
      }
  // Guardar métricas seleccionadas automáticamente por el modelo base
  var baseMetricIds = [];
      // Toggler explícito por si el data-toggle no actúa en algunos navegadores
      var $collapseBase = $('#collapseModeloBase');
      var $btnToggleBase = $('#btnToggleBase');
      var $iconToggleBase = $('#iconToggleBase');
      $btnToggleBase.on('click', function(){
        $collapseBase.collapse('toggle');
      });
      $collapseBase.on('show.bs.collapse', function(){
        $btnToggleBase.attr('aria-expanded','true');
        $iconToggleBase.removeClass('oi-chevron-bottom').addClass('oi-chevron-top');
      });
      $collapseBase.on('hide.bs.collapse', function(){
        $btnToggleBase.attr('aria-expanded','false');
        $iconToggleBase.removeClass('oi-chevron-top').addClass('oi-chevron-bottom');
      });
      $('.modelo-card').on('click', function(){
        var $c = $(this); var id = parseInt($c.data('id')) || 0; var nombre = $.trim($c.find('.card-title').text()); if (!id) return;
        marcarCard($c, true); setModeloBase(nombre, id);
        $.getJSON('api/modelo_metricas.php', { id_modelo: id }).done(function(resp){
          if (!resp || !resp.ok) return; $('input[name="metricas[]"]').prop('checked', false);
          baseMetricIds = [];
          (resp.metricas || []).forEach(function(m){ var idm = parseInt(m.id_metrica) || 0; if (idm){ $('#m'+idm).prop('checked', true); baseMetricIds.push(idm); } });
          actualizarCount();
        });
        // Ocultar el panel una vez seleccionada la base
        $collapseBase.collapse('hide');
      });
  $('#btnChipQuitarBase').on('click', function(){ quitarModeloBase(); actualizarCount(); });
  $('#btnLimpiarMetricas').on('click', function(){ $('input[name="metricas[]"]').prop('checked', false); actualizarCount(); });
  $(document).on('change','input[name="metricas[]"]', actualizarCount);

  // Inicializar badge al cargar
  actualizarCount();

  // Actualizar contador tras seleccionar un modelo base (preselecciona métricas)
  // Nota: el AJAX marca/desmarca, luego actualizamos el badge
  // (inyectamos un hook al final de done)
    });
  </script>
  <?php include_once '../gui/footer.php'; ?>
</body>

</html>