<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

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

    .modelo-card {
      cursor: pointer;
      transition: box-shadow .2s ease;
    }

    .modelo-card:hover {
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, .075);
    }

    .modelo-card.active {
      border: 2px solid #17a2b8;
    }
  </style>
</head>

<body>
  <?php include_once '../gui/navbar.php'; ?>
  <div class="container">
    <div id="alertContainer"></div> <!-- 🔹 Contenedor dinámico para mensajes -->

    <?php if ($flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type']); ?> alert-dismissible fade show mt-3" role="alert">
        <?= htmlspecialchars($flash['text']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>


    <div class="mb-3">
      <a id="btnVolver" href="modelos.php" class="btn btn-outline-secondary">
        <span class="oi oi-arrow-left mr-1"></span> Volver
      </a>
    </div>

    <form action="modelo.nuevo.predeterminado.procesar.php" method="post">
      <input type="hidden" name="global" value="1" />
      <div class="card mt-3">
        <div class="card-header">
          <h3>Crear modelo predeterminado (global)</h3>
          <div class="text-muted small">Se puede partir de un modelo base (opcional) o crear desde cero. Este modelo quedará disponible para todos los proyectos.</div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100"
              value="<?= htmlspecialchars($formData['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
          <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($formData['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
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

          <hr />
          <h5 class="mb-2">Métricas asociadas</h5>
          <div class="mb-2 text-muted small">
            Marque las métricas que desea incluir en el modelo. También puede agregar nuevas métricas al final.
          </div>

          <div class="border rounded p-2" style="max-height: 300px; overflow:auto;">
            <?php if (empty($metricas)): ?>
              <div class="text-muted">No hay métricas definidas.</div>
            <?php else: ?>
              <?php foreach ($metricas as $met): $mid = (int)$met['id_metrica']; ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox"
                    name="metricas[]"
                    value="<?= $mid; ?>"
                    id="m<?= $mid; ?>"
                    <?= in_array($mid, $formData['metricas'] ?? []) ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="m<?= $mid; ?>">
                    <strong><?= htmlspecialchars($met['nombre']); ?></strong>
                    <span class="text-muted small ml-1"><?= htmlspecialchars($met['descripcion'] ?? ''); ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="mt-2 d-flex align-items-center flex-wrap">
            <button type="button" id="btnAbrirModalMetrica" class="btn btn-outline-primary mr-2">
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
    $(function() {
      // ===== FUNCIONES AUXILIARES =====
      function actualizarCount() {
        var total = $('input[name="metricas[]"]:checked').length +
          $('input[name="metricas_nuevas[nombre][]"]').length;
        var $b = $('#metricasSeleccionadasCount');
        if (total > 0) {
          $b.text(total + ' seleccionadas').removeClass('d-none');
        } else {
          $b.addClass('d-none').text('');
        }
      }

      function limpiarValidacionesModal() {
        $('#nmNombre').removeClass('is-invalid');
        $('#nmDescripcion').removeClass('is-invalid');
      }

      // ===== EVENTOS DEL MODAL =====
      $('#modalNuevaMetrica').on('shown.bs.modal', function() {
        $('#nmNombre').trigger('focus');
      });

      $('#btnAgregarMetrica').on('click', function() {
        limpiarValidacionesModal();
        const regexMetrica = /^[A-Za-zÁÉÍÓÚáéíóúÑñ. ]+$/;
        var nombre = ($('#nmNombre').val() || '').trim();
        var desc = ($('#nmDescripcion').val() || '').trim();

        // === Validaciones ===
        if (!nombre) {
          $('#nmNombre').addClass('is-invalid').focus();
          return;
        } else if (!regexMetrica.test(nombre)) {
          $('#nmNombre').addClass('is-invalid');
          $('#nmNombre').next('.invalid-feedback').text('Solo se permiten letras (con o sin tilde) y puntos.');
          return;
        }

        if (!desc) {
          $('#nmDescripcion').addClass('is-invalid').focus();
          return;
        } else if (!regexMetrica.test(desc)) {
          $('#nmDescripcion').addClass('is-invalid');
          $('#nmDescripcion').next('.invalid-feedback').text('Solo se permiten letras (con o sin tilde) y puntos.');
          return;
        }


        // Crear inputs ocultos
        var $wrap = $('<div class="nm-item"></div>');
        $wrap.append('<input type="hidden" name="metricas_nuevas[nombre][]" value="' +
          $('<div/>').text(nombre).html() + '" />');
        $wrap.append('<input type="hidden" name="metricas_nuevas[descripcion][]" value="' +
          $('<div/>').text(desc).html() + '" />');
        $('#metricasNuevasInputs').append($wrap);

        // Crear chip visible
        // Crear chip visible con data-nombre para trazabilidad exacta
        var $chip = $('<span class="chip" data-nombre="' + nombre + '" title="' + desc.replace(/\"/g, '&quot;') + '">' +
          nombre + '<button type="button" class="remove" aria-label="Quitar">&times;</button></span>');
        $chip.find('.remove').on('click', function() {
          var i = $chip.index();
          $('#metricasNuevasInputs .nm-item').eq(i).remove();
          $chip.remove();
          actualizarCount();
        });
        $('#metricasNuevasChips').append($chip);

        actualizarCount();

        // ✅ Cerrar el modal con breve retardo para evitar que se congele el backdrop
        setTimeout(function() {
          $('#modalNuevaMetrica').modal('hide');
        }, 150);
      });

      $('#modalNuevaMetrica').on('hidden.bs.modal', function() {
        $('#nmNombre').val('');
        $('#nmDescripcion').val('');
        limpiarValidacionesModal();

        // 🧩 Fallback de seguridad (si el backdrop quedó pegado)
        setTimeout(function() {
          $('.modal-backdrop').remove();
          $('body').removeClass('modal-open');
        }, 300);
      });


      $('#nmNombre, #nmDescripcion').on('keypress', function(e) {
        if (e.which === 13) {
          e.preventDefault();
          $('#btnAgregarMetrica').click();
        }
      });

      // ===== MODELO BASE =====
      var baseMetricIds = [];

      function marcarCard($card, active) {
        $('.modelo-card').removeClass('active');
        if (active) $card.addClass('active');
      }

      function setModeloBase(nombre, id) {
        $('#modeloBaseNombre').text(nombre);
        $('#modeloBaseChip').removeClass('d-none');
      }

      function limpiarModeloBaseUI() {
        $('#modeloBaseNombre').text('');
        $('#modeloBaseChip').addClass('d-none');
        marcarCard($(), false);
      }

      function quitarModeloBase() {
        baseMetricIds.forEach(function(idm) {
          $('#m' + idm).prop('checked', false);
        });
        baseMetricIds = [];
        limpiarModeloBaseUI();
      }

      var $collapseBase = $('#collapseModeloBase');
      var $btnToggleBase = $('#btnToggleBase');
      var $iconToggleBase = $('#iconToggleBase');

      $btnToggleBase.on('click', function() {
        $collapseBase.collapse('toggle');
      });
      $collapseBase.on('show.bs.collapse', function() {
        $btnToggleBase.attr('aria-expanded', 'true');
        $iconToggleBase.removeClass('oi-chevron-bottom').addClass('oi-chevron-top');
      });
      $collapseBase.on('hide.bs.collapse', function() {
        $btnToggleBase.attr('aria-expanded', 'false');
        $iconToggleBase.removeClass('oi-chevron-top').addClass('oi-chevron-bottom');
      });

      $('.modelo-card').on('click', function() {
        var $c = $(this);
        var id = parseInt($c.data('id')) || 0;
        var nombre = $.trim($c.find('.card-title').text());
        if (!id) return;
        marcarCard($c, true);
        setModeloBase(nombre, id);

        $.getJSON('api/modelo_metricas.php', {
            id_modelo: id
          })
          .done(function(resp) {
            if (!resp || !resp.ok) return;
            $('input[name="metricas[]"]').prop('checked', false);
            baseMetricIds = [];
            (resp.metricas || []).forEach(function(m) {
              var idm = parseInt(m.id_metrica) || 0;
              if (idm) {
                $('#m' + idm).prop('checked', true);
                baseMetricIds.push(idm);
              }
            });
            actualizarCount();
          });

        $collapseBase.collapse('hide');
      });

      $('#btnChipQuitarBase').on('click', function() {
        quitarModeloBase();
        actualizarCount();
      });

      $('#btnLimpiarMetricas').on('click', function() {
        $('input[name="metricas[]"]').prop('checked', false);
        actualizarCount();
      });

      $(document).on('change', 'input[name="metricas[]"]', actualizarCount);

      // ===== INICIALIZACIÓN =====
      actualizarCount();
      // ===== APERTURA DEL MODAL (controlada para evitar congelamiento) =====
      $('#btnAbrirModalMetrica').on('click', function(e) {
        e.preventDefault();

        // Si ya hay un backdrop residual, limpiarlo antes de abrir
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');

        // Pequeño retardo para garantizar que el DOM esté listo
        setTimeout(function() {
          $('#modalNuevaMetrica').modal({
            backdrop: 'static', // evita doble clic accidentales
            keyboard: false, // evita cierre con ESC mientras abre
            show: true
          });
        }, 100);
      });

    });



    $(document).ready(function() {

      // === Referencias a inputs ===
      const nombreInput = $("#nombre");
      const descInput = $("#descripcion");

      // === Crear los divs para errores debajo de cada input ===
      const errorNombre = $("<div class='invalid-feedback d-block text-danger mt-1'></div>");
      nombreInput.after(errorNombre);

      const errorDesc = $("<div class='invalid-feedback d-block text-danger mt-1'></div>");
      descInput.after(errorDesc);

      // === Validación reactiva mientras escribe ===
      nombreInput.on("input", function() {
        const val = nombreInput.val().trim();
        const nombreRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;
        if (val === "") {
          errorNombre.text("El nombre del modelo es obligatorio.");
          nombreInput.addClass("is-invalid");
        } else if (!nombreRegex.test(val)) {
          errorNombre.text("Solo se permiten letras, números, espacios, puntos, guiones, barras, paréntesis y dos puntos.");
          nombreInput.addClass("is-invalid");
        } else {
          errorNombre.text("");
          nombreInput.removeClass("is-invalid");
        }
      });

      descInput.on("input", function() {
        const val = descInput.val().trim();
        const descRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 .,()_\-\/\\:]+$/u;
        if (val === "") {
          errorDesc.text("La descripción es obligatoria.");
          descInput.addClass("is-invalid");
        } else if (!descRegex.test(val)) {
          errorDesc.text("La descripción contiene caracteres no permitidos. " +
        "Permitidos: letras (con o sin tilde), números, espacios, comas, puntos, guiones, paréntesis, barras y dos puntos.");
          descInput.addClass("is-invalid");
        } else {
          errorDesc.text("");
          descInput.removeClass("is-invalid");
        }

      });

      // === Validación general al enviar (con validación de métricas AJAX) ===
      $("form").on("submit", function(e) {
        e.preventDefault();

        let valid = true;
        const nombre = nombreInput.val().trim();
        const desc = descInput.val().trim();
        const nombreRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;
        const descRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 .,()_\-\/\\:]+$/u;

        // Validar nombre y descripción
        if (nombre === "") {
          errorNombre.text("El nombre del modelo es obligatorio.");
          nombreInput.addClass("is-invalid");
          valid = false;
        } else if (!nombreRegex.test(nombre)) {
          errorNombre.text("Solo se permiten letras (con o sin tilde), puntos y guiones.");
          nombreInput.addClass("is-invalid");
          valid = false;
        } else {
          errorNombre.text("");
          nombreInput.removeClass("is-invalid");
        }

        if (desc === "") {
          errorDesc.text("La descripción es obligatoria.");
          descInput.addClass("is-invalid");
          valid = false;
        } else if (!descRegex.test(desc)) {
          errorDesc.text("La descripción contiene caracteres no permitidos. " +
        "Permitidos: letras (con o sin tilde), números, espacios, comas, puntos, guiones, paréntesis, barras y dos puntos.");
          descInput.addClass("is-invalid");
          valid = false;
        } else {
          errorDesc.text("");
          descInput.removeClass("is-invalid");
        }

        if (!valid) {
          mostrarAlerta("Debe completar todos los campos obligatorios.", "danger");
          return;
        }

        // === Validación de métricas nuevas vía AJAX ===
        const nuevas = $('input[name="metricas_nuevas[nombre][]"]').map(function() {
          return $(this).val().trim();
        }).get().filter(Boolean);

        if (nuevas.length === 0) {
          // No hay métricas nuevas → enviar el formulario normalmente
          this.submit();
          return;
        }

        // Consultar duplicadas
        $.post('api/validar_metricas.php', {
            nombres: nuevas
          })
          .done(function(resp) {
            if (!resp.ok) {
              mostrarAlerta("Error al validar métricas.", "danger");
              return;
            }

            const duplicadas = resp.existentes || [];
            if (duplicadas.length > 0) {
              mostrarAlerta(
                "Las siguientes métricas ya existen en el sistema y fueron omitidas:<br>" +
                "<strong>" + duplicadas.join(", ") + "</strong><br>" +
                "<div class='mt-2 text-muted small'>El modelo aún no se ha guardado. Revise la lista actualizada y vuelva a presionar <strong>Confirmar</strong> para continuar con las métricas válidas.</div>",
              );

              // Eliminar solo las duplicadas del formulario
              $('input[name="metricas_nuevas[nombre][]"]').each(function() {
                const val = $(this).val().trim();
                if (duplicadas.includes(val)) {
                  $(this).closest('.nm-item').remove();
                }
              });

              // Eliminar los chips correspondientes por data-nombre
              $('#metricasNuevasChips .chip').each(function() {
                const chipNombre = $(this).data('nombre');
                if (duplicadas.includes(chipNombre)) {
                  $(this).remove();
                }
              });

              actualizarCount();

              // 🚫 No reenviamos automáticamente. El usuario debe volver a confirmar manualmente.
              return;
            }

            // ✅ Si no hubo duplicadas, enviamos el formulario normalmente
            e.target.submit();

          })
          .fail(function(err) {
            mostrarAlerta("Error de comunicación con el servidor al validar métricas.", "danger");
          });
      });


      // === Función para mostrar alertas Bootstrap ===
      function mostrarAlerta(mensaje, tipo = "info") {
        // Elimina cualquier alerta previa
        $(".alert-dinamica").alert("close");

        // Crea una alerta con misma estructura y animación que las del servidor
        const $alert = $(`
    <div class="alert alert-${tipo} alert-dismissible fade show mt-3 alert-dinamica" role="alert" style="opacity: 0;">
      ${mensaje}
      <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
  `);

        $("#alertContainer").append($alert);

        // Desplazamiento suave hacia arriba
        $("html, body").animate({
          scrollTop: 0
        }, "fast");
        // Suave aparición y desaparición sincronizada con Bootstrap
        $alert.animate({
          opacity: 1
        }, 300); // fade-in

        // Mantener visible más tiempo para textos largos
        setTimeout(() => {
          $alert.animate({
            opacity: 0
          }, 800, function() {
            $(this).alert("close");
          });
        }, 15000); // ⏳ permanece 15 segundos totalmente visible
      }

    });
  </script>
  <?php include_once '../gui/footer.php'; ?>
</body>

</html>