  <?php
  include_once '../lib/ControlAcceso.Class.php';
  include_once '../modelo/BDConexion.Class.php';

  // 🔒 Acceso
  if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido para crear modelos.') . '&type=danger');
    exit;
  }

  $cn = BDConexion::getInstancia();

  // 🔹 Datos base
  $rsM = $cn->query("SELECT id_metrica, nombre, descripcion FROM metrica ORDER BY nombre");
  $metricas = $rsM ? $rsM->fetch_all(MYSQLI_ASSOC) : [];

  $rsMb = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre");
  $modelosBase = $rsMb ? $rsMb->fetch_all(MYSQLI_ASSOC) : [];

  $esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

  // 🔹 Detectar si se accede desde "Cambiar modelo" con ?proyecto=ID
  $proyectoCambioId = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;
  $nombreProyectoActual = null;
  $modeloActual = null;

  if ($proyectoCambioId > 0) {
    $sqlInfo = "SELECT 
    p.nombre AS proyecto,
    mg.nombre AS modelo_global,
    pmc.nombre AS modelo_personalizado
FROM proyecto p
LEFT JOIN modelo_calidad mg 
       ON mg.id_modelo = p.id_modelo_global
LEFT JOIN proyecto_modelo_calidad pmc
       ON pmc.id_proyecto_modelo = p.id_modelo_personalizado
WHERE p.id_proyecto = $proyectoCambioId
LIMIT 1";

    if ($rsInfo = $cn->query($sqlInfo)) {
      if ($info = $rsInfo->fetch_assoc()) {
        $nombreProyectoActual = $info['proyecto'];
        $modeloActual = $info['modelo_global'] ?? $info['modelo_personalizado'] ?? null;
      }
    }
  }
  ?>
  <html>

  <head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Modelo</title>
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

      .btn-outline-danger:hover {
        background-color: #f8d7da;
        color: #721c24;
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

      .btn-toggle.active {
        background-color: #e9f7fd;
        border-color: #17a2b8;
        color: #0c5460;
      }

      .arrow {
        display: inline-block;
        transition: transform .25s ease;
      }

      .arrow.rotated {
        transform: rotate(180deg);
      }
    </style>
  </head>

  <body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
      <div id="alertContainer"></div>
      <div class="mb-3">
        <a id="btnVolver" href="modelos.php" class="btn btn-outline-secondary">
          <span class="oi oi-arrow-left mr-1"></span> Volver
        </a>
      </div>

      <!-- 🔹 Selector inicial -->
      <div class="card mt-3 mb-3">
        <div class="card-header">
          <h5 class="mb-0">Elegí cómo crear el modelo</h5>
        </div>
        <div class="card-body d-flex flex-wrap">
          <button type="button" id="btnOpcionBase" class="btn btn-outline-info btn-toggle mr-3 mb-2">
            <span class="oi oi-layers"></span> Usar modelo base existente
          </button>
          <button type="button" id="btnOpcionCero" class="btn btn-outline-primary btn-toggle mb-2">
            <span class="oi oi-plus"></span> Crear modelo desde cero
          </button>
        </div>
      </div>

      <form action="modelo.nuevo.procesar.php" method="post">
        <input type="hidden" name="modelo_base_id" id="modelo_base_id" value="">
        <input type="hidden" name="editar_base" id="editar_base" value="0">

        <div class="card mt-3">
          <div class="card-header">
            <h3>Crear modelo de calidad</h3>
          </div>
          <div class="card-body">

            <!-- 🔹 MODELOS BASE -->
            <div id="seccionModelosBase" class="d-none">
              <div class="form-group">
                <label>Seleccionar modelo base</label>
                <div class="text-muted small mb-2">
                  Elegí un modelo existente. Si lo editás, se creará un nuevo modelo derivado.
                </div>
                <div class="row">
                  <?php foreach ($modelosBase as $mb): ?>
                    <div class="col-md-4 mb-3">
                      <div class="card modelo-card" data-id="<?= (int)$mb['id_modelo']; ?>">
                        <div class="card-body p-3">
                          <h6 class="card-title mb-1"><?= htmlspecialchars($mb['nombre']); ?></h6>
                          <div class="text-muted small">
                            <?= htmlspecialchars(mb_strimwidth($mb['descripcion'] ?? '', 0, 100, '…')); ?>
                          </div>

                          <!-- Botón Ver Métricas -->
                          <button type="button"
                            class="btn btn-sm btn-link text-info mt-2 btnVerMetricas"
                            data-id="<?= (int)$mb['id_modelo']; ?>">
                            <span class="txt">Ver métricas</span> <span class="arrow">▼</span>
                          </button>

                          <!-- Contenedor colapsable -->
                          <div class="listaMetricas mt-2 d-none small text-dark"></div>
                        </div>

                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="mt-2 d-flex align-items-center">
                  <span id="modeloBaseSeleccionado" class="badge badge-info d-none mr-2"></span>
                  <button type="button" id="btnLimpiarBase" class="btn btn-sm btn-outline-secondary d-none">Quitar modelo base</button>
                  <button type="button" id="btnEditarBase" class="btn btn-sm btn-outline-warning d-none ml-2">
                    <span class="oi oi-pencil"></span> Editar modelo base
                  </button>
                </div>
              </div>
            </div>

            <!-- 🔹 CAMPOS NUEVO MODELO -->
            <div id="camposNuevoModelo" class="d-none mt-3">
              <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100">
                <div class="invalid-feedback d-block text-danger mt-1" id="errorNombre"></div>
              </div>

              <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                <div class="invalid-feedback d-block text-danger mt-1" id="errorDesc"></div>
              </div>

              <hr />
              <h5 class="mb-2">Métricas asociadas</h5>
              <div class="border rounded p-2" style="max-height:300px;overflow:auto;">
                <?php if (empty($metricas)): ?>
                  <div class="text-muted">No hay métricas definidas.</div>
                <?php else: ?>
                  <?php foreach ($metricas as $met): $mid = (int)$met['id_metrica']; ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" value="<?= $mid; ?>" id="m<?= $mid; ?>" name="metricas[]">
                      <label class="form-check-label" for="m<?= $mid; ?>"><strong><?= htmlspecialchars($met['nombre']); ?></strong></label>
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

            <?php
            // =====================================================
            // 🔹 Mostrar contexto si viene desde “Cambiar modelo”
            // =====================================================
            if ($proyectoCambioId > 0 && $nombreProyectoActual): ?>
              <div class="alert alert-info mt-3">
                <span class="oi oi-info mr-2"></span>
                Proyecto: <strong><?= htmlspecialchars($nombreProyectoActual); ?></strong><br>
                Modelo actual: <em><?= htmlspecialchars($modeloActual ?? '—'); ?></em><br>
                <small class="text-muted">Si confirmás la creación, el nuevo modelo reemplazará al actual en este proyecto.</small>
              </div>
              <input type="hidden" name="proyecto" value="<?= $proyectoCambioId; ?>">
              <?php
            // =====================================================
            // 🔹 Si no viene desde cambio, aplicar lógica de proyectos elegibles
            // =====================================================
            else:
              $uid = (int)$_SESSION['usuario']->id;
              $proyectosElegibles = [];

              $sqlProy = "SELECT p.id_proyecto, p.nombre
                FROM usuario_proyecto up
                JOIN proyecto p ON p.id_proyecto = up.id_proyecto
                WHERE up.id_usuario = {$uid}
                ORDER BY p.nombre";
              $resProy = $cn->query($sqlProy);

              if ($resProy) {
                while ($p = $resProy->fetch_assoc()) {
                  $pid = (int)$p['id_proyecto'];
                  $sqlCheck = "SELECT COUNT(*) AS c
                    FROM metrica_iteracion mi
                    JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
                    WHERE i.id_proyecto = {$pid}";
                  $resCheck = $cn->query($sqlCheck);
                  $hasMetrics = false;
                  if ($resCheck && $rowC = $resCheck->fetch_assoc()) {
                    $hasMetrics = ((int)$rowC['c'] > 0);
                  }
                  if (!$hasMetrics) {
                    $proyectosElegibles[] = ['id' => $pid, 'nombre' => $p['nombre']];
                  }
                }
              }

              if (empty($proyectosElegibles)): ?>
                <div class="alert alert-warning mt-3">
                  <span class="oi oi-warning mr-2"></span>
                  No hay proyectos elegibles para asignar un modelo de calidad.<br>
                  Todos los proyectos del usuario ya tienen métricas planificadas.
                </div>
                <script>
                  $(function() {
                    $('form button[type="submit"]').prop('disabled', true);
                  });
                </script>
              <?php elseif (count($proyectosElegibles) === 1):
                $p = $proyectosElegibles[0]; ?>
                <div class="form-group mt-3">
                  <label>Se asigna a proyecto</label>
                  <div class="form-control-plaintext font-weight-bold">
                    <?= htmlspecialchars($p['nombre']); ?>
                  </div>
                  <input type="hidden" name="proyecto" value="<?= (int)$p['id']; ?>">
                </div>
              <?php else: ?>
                <div class="form-group mt-3">
                  <label for="proyecto">Asignar a proyecto</label>
                  <select class="form-control" id="proyecto" name="proyecto" required>
                    <option value="">Seleccione un proyecto...</option>
                    <?php foreach ($proyectosElegibles as $p): ?>
                      <option value="<?= (int)$p['id']; ?>"><?= htmlspecialchars($p['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="text-muted">Solo se muestran los proyectos sin métricas planificadas.</small>
                </div>
            <?php endif;
            endif; ?>

          </div>

          <div class="card-footer">
            <button type="submit" class="btn btn-outline-success"><span class="oi oi-check"></span> Confirmar</button>
            <a href="modelos.php" onclick="return confirm('¿Cancelar la creación del modelo? Se perderán los cambios no guardados.');">
              <button type="button" class="btn btn-outline-danger"><span class="oi oi-x"></span> Cancelar</button>
            </a>
            <div id="metricasNuevasInputs"></div>
          </div>
        </div>
      </form>
    </div>

    <!-- 🔹 Modal Nueva Métrica -->
    <div class="modal fade" id="modalNuevaMetrica" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nueva métrica</h5>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="nmNombre">Nombre</label>
              <input type="text" id="nmNombre" class="form-control" maxlength="120">
              <div class="invalid-feedback">El nombre es obligatorio.</div>
            </div>
            <div class="form-group">
              <label for="nmDescripcion">Descripción</label>
              <textarea id="nmDescripcion" class="form-control" rows="3"></textarea>
              <div class="invalid-feedback">La descripción es obligatoria.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="button" id="btnAgregarMetrica" class="btn btn-primary"><span class="oi oi-check"></span> Agregar</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      $(function() {
        let baseSeleccionada = null;

        // ========================
        //  Mostrar / ocultar secciones
        // ========================
        $('#btnOpcionBase').on('click', function() {
          $('#btnOpcionCero').removeClass('active');
          $(this).addClass('active');
          $('#seccionModelosBase').removeClass('d-none');
          $('#camposNuevoModelo').addClass('d-none');
        });
        $('#btnOpcionCero').on('click', function() {
          $('#btnOpcionBase').removeClass('active');
          $(this).addClass('active');
          $('#seccionModelosBase').addClass('d-none');
          $('#camposNuevoModelo').removeClass('d-none');
          $('#modelo_base_id').val('');
          $('#editar_base').val('0');
        });

        // ========================
        //  Conteo dinámico de métricas
        // ========================
        function actualizarCount() {
          const total = $('input[name="metricas[]"]:checked').length + $('input[name="metricas_nuevas[nombre][]"]').length;
          const $b = $('#metricasSeleccionadasCount');
          if (total > 0) {
            $b.text(total + ' seleccionadas').removeClass('d-none');
          } else {
            $b.addClass('d-none').text('');
          }
        }

        // ========================
        //  Selección modelo base
        // ========================
        $('.modelo-card').on('click', function() {
          const $c = $(this);
          const id = parseInt($c.data('id')) || 0;
          const nombre = $.trim($c.find('.card-title').text());
          if (!id) return;
          $('.modelo-card').removeClass('active');
          $c.addClass('active');
          baseSeleccionada = id;
          $('#modelo_base_id').val(id);
          $('#modeloBaseSeleccionado').removeClass('d-none').text('Base seleccionada: ' + nombre);
          $('#btnLimpiarBase,#btnEditarBase').removeClass('d-none');
        });
        // ========================
        //  Quitar modelo base (versión mejorada)
        // ========================
        $('#btnLimpiarBase').on('click', function() {
          baseSeleccionada = null;
          $('#modelo_base_id').val('');
          $('#editar_base').val('0');
          $('#modeloBaseSeleccionado').addClass('d-none').text('');
          $('#btnLimpiarBase,#btnEditarBase').addClass('d-none');
          $('.modelo-card').removeClass('active');
          $('input[name="metricas[]"]').prop('checked', false);
          $('#nombre,#descripcion').val('');
          actualizarCount();

          // 🔹 Ocultar campos de edición si estaban visibles
          $('#camposNuevoModelo').addClass('d-none');

          // 🔹 Restaurar estado de botones principales
          $('#btnOpcionBase').removeClass('active');
          $('#btnOpcionCero').removeClass('active');

          // 🔹 Ocultar la grilla de modelos base también
          $('#seccionModelosBase').addClass('d-none');

          // 🔹 Mostrar solo el selector inicial (opciones principales)
          $('.card.mt-3.mb-3').removeClass('d-none');

          // 🔔 Alerta visual
          mostrarAlerta("Modelo base quitado correctamente.", "info");
        });


        // ========================
        //  Editar modelo base
        // ========================
        $('#btnEditarBase').on('click', function() {
          const id = baseSeleccionada;
          if (!id) return;
          $('#editar_base').val('1');
          $('#camposNuevoModelo').removeClass('d-none');
          $('input[name="metricas[]"]').prop('checked', false);
          $.getJSON('api/modelo_metricas.php', {
            id_modelo: id
          }, function(r) {
            if (r && r.ok) {
              (r.metricas || []).forEach(function(m) {
                $('#m' + m.id_metrica).prop('checked', true);
              });
              actualizarCount();
            }
          });
          $('#nombre,#descripcion').val('');
        });

        // ========================
        //  Modal Nueva Métrica (estilo predeterminado)
        // ========================
        $('#btnAbrirModalMetrica').on('click', function(e) {
          e.preventDefault();
          $('#modalNuevaMetrica').modal('show');
        });

        $('#btnAgregarMetrica').on('click', function() {
          const n = $('#nmNombre').val().trim();
          const d = $('#nmDescripcion').val().trim();
          const regex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;

          // Validaciones simples
          if (n === '' || !regex.test(n)) {
            $('#nmNombre').addClass('is-invalid');
            return;
          } else {
            $('#nmNombre').removeClass('is-invalid');
          }
          if (d === '' || !regex.test(d)) {
            $('#nmDescripcion').addClass('is-invalid');
            return;
          } else {
            $('#nmDescripcion').removeClass('is-invalid');
          }

          // Crear inputs ocultos
          const $wrap = $('<div class="nm-item"></div>');
          $wrap.append(`<input type="hidden" name="metricas_nuevas[nombre][]" value="${$('<div/>').text(n).html()}" />`);
          $wrap.append(`<input type="hidden" name="metricas_nuevas[descripcion][]" value="${$('<div/>').text(d).html()}" />`);
          $('#metricasNuevasInputs').append($wrap);

          // Crear chip visible con botón eliminar
          const $chip = $(`
    <span class="chip" data-nombre="${n}" title="${d.replace(/\"/g, '&quot;')}">
      ${n}
      <button type="button" class="remove" aria-label="Quitar">&times;</button>
    </span>
  `);
          $chip.find('.remove').on('click', function() {
            $chip.remove();
            $wrap.remove();
            actualizarCount();
          });
          $('#metricasNuevasChips').append($chip);

          // Limpiar modal y cerrar
          $('#nmNombre,#nmDescripcion').val('');
          $('#modalNuevaMetrica').modal('hide');
          actualizarCount();
        });

        $(document).on('change', 'input[name="metricas[]"]', actualizarCount);

        // ========================
        //  Validaciones visuales
        // ========================
        const regex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;
        $('#nombre').on('input', function() {
          const v = $(this).val().trim(),
            $e = $('#errorNombre');
          if (v === '') {
            $e.text('El nombre del modelo es obligatorio.');
            $(this).addClass('is-invalid');
          } else if (!regex.test(v)) {
            $e.text('Solo se permiten letras, números y los símbolos . - _ / \\ ( ) :');
            $(this).addClass('is-invalid');
          } else {
            $e.text('');
            $(this).removeClass('is-invalid');
          }
        });
        $('#descripcion').on('input', function() {
          const v = $(this).val().trim(),
            $e = $('#errorDesc');
          if (v === '') {
            $e.text('La descripción es obligatoria.');
            $(this).addClass('is-invalid');
          } else if (!regex.test(v)) {
            $e.text('Solo se permiten letras, números y los símbolos . - _ / \\ ( ) :');
            $(this).addClass('is-invalid');
          } else {
            $e.text('');
            $(this).removeClass('is-invalid');
          }
        });

        actualizarCount();

        // ========================
        // 🚨 Confirmación antes de reemplazar modelo existente
        // ========================
        const tieneModeloActual = <?php echo json_encode($modeloActual !== null); ?>;
        if (tieneModeloActual) {
          $('form').on('submit', function(e) {
            const ok = confirm("⚠️ Este proyecto ya tiene un modelo asignado.\n\n¿Deseás reemplazarlo por el nuevo modelo?\nEsta acción no se puede deshacer.");
            if (!ok) e.preventDefault();
          });

        }



        // 📌 Ver/ocultar métricas del modelo base  <-- YA NO ESTÁ EN EL IF
        $('.btnVerMetricas').on('click', function(e) {
          e.preventDefault();
          const $btn = $(this);
          const id = $btn.data('id');
          const $lista = $btn.closest('.card-body').find('.listaMetricas');

          if ($lista.hasClass('cargado')) {
            $lista.toggleClass('d-none');
            if (!$lista.hasClass('d-none')) {
              $lista.slideDown(200);
            } else {
              $lista.slideUp(200);
            }
            $btn.find('.txt').text($lista.is(':visible') ? 'Ocultar métricas' : 'Ver métricas');
            $btn.find('.arrow').toggleClass('rotated');
            return;
          }


          $btn.find('.txt').text('Cargando...');
          $btn.find('.arrow').removeClass('rotated').text('…');
$.getJSON('api/modelo_metricas.php', { id_modelo: id }, function(resp) {

    if (!resp || !resp.ok) {
        $lista.html('<em class="text-danger">Error al cargar métricas.</em>');
        $btn.find('.txt').text('Ver métricas');
        $btn.find('.arrow').text('▼').removeClass('rotated');
        return;
    }

    if (!resp.metricas || resp.metricas.length === 0) {
        $lista.html('<span class="text-muted fst-italic">Este modelo no tiene métricas.</span>');
    } else {
        let html = '<ul class="pl-3 mb-1">';
        resp.metricas.forEach(m => {
            html += `
                <li>
                    <strong>${m.nombre}</strong><br>
                    <span class="text-muted small">${m.descripcion}</span>
                </li>`;
        });
        html += '</ul>';
        $lista.html(html);
    }

    // 👇 ESTA LÍNEA ES LA QUE FALTABA
    $lista.removeClass('d-none').addClass('cargado').slideDown(200);

    $btn.find('.txt').text('Ocultar métricas');
    $btn.find('.arrow').text('▲').addClass('rotated');
});


        });
        // ========================
        // ⚙️ Validación final al enviar (duplicados y mensaje al final)
        // ========================
        $('form').on('submit', function(e) {
          e.preventDefault();

          // Detectar modo actual (base o cero)
          const usandoBase = $('#btnOpcionBase').hasClass('active');
          const usandoCero = $('#btnOpcionCero').hasClass('active');
          const editandoBase = $('#editar_base').val() === '1';
          // 🚫 Validar que se haya elegido alguna acción antes de continuar
          if (!usandoBase && !usandoCero) {
            mostrarAlerta("⚠️ Debe elegir una opción: usar un modelo base existente o crear un modelo desde cero.", "danger");
            return;
          }


          // === Caso 1: Usar modelo base existente (sin editar)
          if (usandoBase && !editandoBase) {
            const idBase = parseInt($('#modelo_base_id').val()) || 0;
            if (!idBase) {
              mostrarAlerta("Debe seleccionar un modelo base antes de confirmar.", "danger");
              return;
            }

            // Enviar directamente sin validar campos
            this.submit();
            return;
          }

          // === Caso 2: Crear desde cero o derivado de modelo base editado
          if (usandoCero || editandoBase) {
            const nombreVal = $('#nombre').val().trim();
            const descVal = $('#descripcion').val().trim();
            const regex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;
            let valido = true;

            if (nombreVal === '' || !regex.test(nombreVal)) {
              $('#errorNombre').text('El nombre del modelo es obligatorio o contiene caracteres inválidos.');
              $('#nombre').addClass('is-invalid');
              valido = false;
            } else {
              $('#errorNombre').text('');
              $('#nombre').removeClass('is-invalid');
            }

            if (descVal === '' || !regex.test(descVal)) {
              $('#errorDesc').text('La descripción es obligatoria o contiene caracteres inválidos.');
              $('#descripcion').addClass('is-invalid');
              valido = false;
            } else {
              $('#errorDesc').text('');
              $('#descripcion').removeClass('is-invalid');
            }

            if (!valido) {
              mostrarAlerta("Debe completar correctamente los campos obligatorios antes de confirmar.", "danger");
              return;
            }
          }


          // 🧮 Continuar con la validación de métricas nuevas (solo si crea desde cero)
          const nuevas = $('input[name="metricas_nuevas[nombre][]"]').map(function() {
            return $(this).val().trim();
          }).get().filter(Boolean);

          // 🔒 Bloquear botón y mostrar aviso
          const $btn = $(this).find('button[type="submit"]');
          $btn.prop('disabled', true).text('Procesando...');
          mostrarAlerta("✅ Procesando solicitud, por favor espere...", "info");

          // Si no hay métricas nuevas → enviar directamente
          if (nuevas.length === 0) {
            this.submit();
            return;
          }

          // Validar duplicadas antes de enviar
          $.post('api/validar_metricas.php', {
            nombres: nuevas
          }, function(resp) {
            if (!resp.ok) {
              mostrarAlerta("Error al validar métricas.", "danger");
              $btn.prop('disabled', false).text('Confirmar');
              return;
            }

            const duplicadas = resp.existentes || [];
            if (duplicadas.length > 0) {
              mostrarAlerta(
                "⚠️ Las siguientes métricas ya existen y fueron eliminadas:<br><strong>" +
                duplicadas.join(', ') + "</strong>", "warning"
              );

              $('input[name="metricas_nuevas[nombre][]"]').each(function() {
                const val = $(this).val().trim();
                if (duplicadas.includes(val)) $(this).closest('.nm-item').remove();
              });
              $('#metricasNuevasChips .chip').each(function() {
                if (duplicadas.includes($(this).data('nombre'))) $(this).remove();
              });
              actualizarCount();
              $btn.prop('disabled', false).text('Confirmar');
              return;
            }

            // ✅ Enviar formulario (mensaje final será mostrado tras redirect del servidor)
            e.target.submit();
          }, 'json').fail(() => {
            mostrarAlerta("Error de comunicación con el servidor.", "danger");
            $btn.prop('disabled', false).text('Confirmar');
          });
        });




        // ========================
        // 🔔 Función para alertas Bootstrap
        // ========================
        function mostrarAlerta(mensaje, tipo = "info") {
          $(".alert-dinamica").alert("close");
          const $alert = $(`
      <div class="alert alert-${tipo} alert-dismissible fade show mt-3 alert-dinamica" role="alert" style="opacity: 0;">
        ${mensaje}
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>`);
          $("#alertContainer").append($alert);
          $("html, body").animate({
            scrollTop: 0
          }, "fast");
          $alert.animate({
            opacity: 1
          }, 300);
          setTimeout(() => {
            $alert.animate({
              opacity: 0
            }, 800, function() {
              $(this).alert("close");
            });
          }, 12000);
        }
      });
    </script>
    <?php include_once '../gui/footer.php'; ?>
  </body>

  </html>