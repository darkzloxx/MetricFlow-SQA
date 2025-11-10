<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

ControlAcceso::verificaLogin();

$esAdmin = ControlAcceso::esAdminGlobal();
$esSuper = ControlAcceso::esSuperAdminGlobal();
if (!($esAdmin || $esSuper)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido a administradores.') . '&type=danger');
    exit;
}

$cn = BDConexion::getInstancia();
$idModelo = (int)($_GET['id_modelo'] ?? 0);
if ($idModelo <= 0) {
    header('Location: modelos.php?msg=' . urlencode('Modelo inválido.') . '&type=danger');
    exit;
}

// Verificar que el modelo no esté en uso (para permitir edición estructural)
$enUso = false;
if ($rsU = $cn->query("SELECT COUNT(*) c FROM proyecto WHERE id_modelo = $idModelo")) {
    $rowU = $rsU->fetch_assoc();
    $enUso = ((int)$rowU['c'] > 0);
}
if ($enUso) {
    header('Location: modelos.php?msg=' . urlencode('El modelo está siendo utilizado por proyectos y no puede modificarse.') . '&type=danger');
    exit;
}

// Cargar datos del modelo
$rsM = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad WHERE id_modelo = $idModelo LIMIT 1");
$modelo = $rsM ? $rsM->fetch_assoc() : null;
if (!$modelo) {
    header('Location: modelos.php?msg=' . urlencode('Modelo no encontrado.') . '&type=danger');
    exit;
}

// Métricas existentes y las asignadas a este modelo
$allMetrics = [];
if ($rsA = $cn->query("SELECT id_metrica, nombre, descripcion FROM metrica ORDER BY nombre")) {
    $allMetrics = $rsA->fetch_all(MYSQLI_ASSOC);
}
$metricasSel = [];
if ($rsS = $cn->query("SELECT id_metrica FROM metrica_modelo_calidad WHERE id_modelo = $idModelo")) {
    while ($r = $rsS->fetch_assoc()) {
        $metricasSel[(int)$r['id_metrica']] = true;
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
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Editar Modelo</title>
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

        .chip {
            display: inline-flex;
            align-items: center;
            padding: .25rem .5rem;
            border-radius: 16px;
            background: #f1f3f5;
            margin: .125rem .25rem;
            border: 1px solid #ced4da;
        }

        .chip .remove {
            border: 0;
            background: transparent;
            color: #6c757d;
            margin-left: .25rem;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div id="alertContainer"></div>

        <div class="mb-3">
            <a href="modelos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <form id="formEditarModelo" action="modelo.modificar.procesar.php" method="post">
            <input type="hidden" name="id_modelo" value="<?= (int)$modelo['id_modelo']; ?>" />
            <div class="card">
                <div class="card-header">
                    <h3>Editar modelo</h3>
                    <div class="text-muted small">Solo Administrador / SuperAdmin</div>
                </div>
                <div class="card-body">
                    <?php if ($flash): ?>
                        <div class="alert alert-<?= htmlspecialchars($flash['type']); ?> alert-dismissible fade show mt-3" role="alert">
                            <?= htmlspecialchars($flash['text']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>


                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($modelo['nombre']); ?>" />
                        <div id="nombreError" class="invalid-feedback d-block text-danger mt-1" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($modelo['descripcion'] ?? ''); ?></textarea>
                        <div id="descError" class="invalid-feedback d-block text-danger mt-1" style="display:none;"></div>
                    </div>

                    <hr />
                    <h5 class="mb-2">Métricas del modelo</h5>
                    <div class="mb-2 text-muted small">Marque para incluir. Puede agregar nuevas métricas al final.</div>
                    <div class="border rounded p-2" style="max-height: 300px; overflow:auto;">
                        <?php if (empty($allMetrics)): ?>
                            <div class="text-muted">No hay métricas registradas.</div>
                        <?php else: ?>
                            <?php foreach ($allMetrics as $met): $mid = (int)$met['id_metrica']; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="metricas[]" value="<?= $mid; ?>" id="m<?= $mid; ?>" <?= isset($metricasSel[$mid]) ? 'checked' : ''; ?> />
                                    <label class="form-check-label" for="m<?= $mid; ?>">
                                        <strong><?= htmlspecialchars($met['nombre']); ?></strong>
                                        <span class="text-muted small ml-1"><?= htmlspecialchars($met['descripcion'] ?? ''); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3">
                        <h6>Agregar nuevas métricas</h6>
                        <div class="mt-2 d-flex align-items-center flex-wrap">
                            <button type="button" id="btnAbrirModalMetrica" class="btn btn-outline-primary mr-2">
                                <span class="oi oi-plus"></span> Nueva métrica
                            </button>
                            <button type="button" id="btnLimpiarMetricas" class="btn btn-outline-secondary">Limpiar selección</button>
                            <span id="metricasSeleccionadasCount" class="badge badge-info ml-3 d-none"></span>
                        </div>
                        <div id="metricasNuevasChips" class="mt-2"></div>
                        <div id="metricasNuevasInputs"></div>
                    </div>

                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-outline-success">
                        <span class="oi oi-check"></span> Guardar cambios
                    </button>
                    <a href="modelos.php" class="btn btn-outline-danger" onclick="return confirm('¿Cancelar los cambios?');">
                        <span class="oi oi-x"></span> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Nueva Métrica (Modificación) -->
    <div class="modal fade" id="modalNuevaMetricaMod" tabindex="-1" role="dialog" aria-labelledby="modalNuevaMetricaModLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevaMetricaModLabel">Nueva métrica</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="mnmNombre">Nombre</label>
                        <input type="text" id="mnmNombre" class="form-control" maxlength="120" />
                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                    </div>
                    <div class="form-group">
                        <label for="mnmDescripcion">Descripción</label>
                        <textarea id="mnmDescripcion" class="form-control" rows="3"></textarea>
                        <div class="invalid-feedback">La descripción es obligatoria.</div>
                    </div>
                    <div class="text-muted small">Se agregará al guardar y quedará vinculada a este modelo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnAgregarMetricaMod" class="btn btn-primary">
                        <span class="oi oi-check"></span> Agregar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Si por cualquier motivo Bootstrap genera múltiples backdrops, limpiarlos
        $(document).on('show.bs.modal', '.modal', function() {
            $('.modal-backdrop').not(':first').remove();
        });

        (function() {
            const regexGeneral = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;
            const origNombre = <?= json_encode($modelo['nombre'] ?? ''); ?>;
            const origDesc = <?= json_encode($modelo['descripcion'] ?? ''); ?>;
            const origChecked = (function() {
                const arr = [];
                <?php foreach ($metricasSel as $mid => $_): ?>
                    arr.push(<?= (int)$mid; ?>);
                <?php endforeach; ?>
                return arr.map(String);
            })();

            const metricMap = (function() {
                const m = {};
                <?php foreach ($allMetrics as $met): ?>
                    m['<?= (int)$met['id_metrica']; ?>'] = <?= json_encode($met['nombre']); ?>;
                <?php endforeach; ?>
                return m;
            })();

            // --- Contador dinámico de métricas ---
            function actualizarCount() {
                var total = $('input[name="metricas[]"]:checked').length + $('input[name="new_metric_name[]"]').length;
                var $b = $('#metricasSeleccionadasCount');
                if (total > 0) $b.text(total + ' seleccionadas').removeClass('d-none');
                else $b.addClass('d-none').text('');
            }

            // --- Validaciones generales antes de enviar ---
            $('#formEditarModelo').on('submit', function(e) {
                e.preventDefault();

                const nombre = ($('#nombre').val() || '').trim();
                const desc = ($('#descripcion').val() || '').trim();
                const actuales = $('input[name="metricas[]"]:checked').map(function() {
                    return $(this).val();
                }).get();
                const nuevosNombres = $('input[name="new_metric_name[]"]').map(function() {
                    return $(this).val();
                }).get();
                const nuevosDesc = $('input[name="new_metric_desc[]"]').map(function() {
                    return $(this).val();
                }).get();

                let valido = true;
                $('#nombreError').hide().text('');
                $('#descError').hide().text('');

                // --- Validar nombre del modelo ---
                if (!nombre) {
                    $('#nombreError').text('El nombre del modelo es obligatorio.').show();
                    valido = false;
                } else if (!regexGeneral.test(nombre)) {
                    $('#nombreError').text('Solo se permiten letras (con o sin tilde), números, espacios, puntos, guiones, barras, paréntesis y dos puntos.').show();
                    valido = false;
                }

                // --- Validar descripción ---
                if (!desc) {
                    $('#descError').text('La descripción es obligatoria.').show();
                    valido = false;
                } else if (!regexGeneral.test(desc)) {
                    $('#descError').text('Solo se permiten letras (con o sin tilde), números, espacios, puntos, guiones, barras, paréntesis y dos puntos.').show();
                    valido = false;
                }

                // --- Validar nuevas métricas ---
                for (var i = 0; i < nuevosNombres.length; i++) {
                    const n = (nuevosNombres[i] || '').trim();
                    const d = (nuevosDesc[i] || '').trim();
                    if (!n || !d) {
                        mostrarAlerta('Las nuevas métricas deben tener nombre y descripción.', 'danger');
                        return false;
                    }
                    if (!regexGeneral.test(n)) {
                        mostrarAlerta('El nombre de la métrica "' + n + '" contiene caracteres no permitidos.', 'danger');
                        return false;
                    }
                    if (!regexGeneral.test(d)) {
                        mostrarAlerta('La descripción de la métrica "' + n + '" contiene caracteres no permitidos.', 'danger');
                        return false;
                    }
                }

                const nuevasValidas = (nuevosNombres || []).filter(n => (n || '').trim().length > 0);
                if ((actuales.length + nuevasValidas.length) === 0) {
                    mostrarAlerta('Debe seleccionar al menos una métrica (existente o nueva).', 'danger');
                    return false;
                }

                if (!valido) return false;

                // --- Mostrar resumen de cambios ---
                const cambios = [];
                if (nombre !== origNombre) cambios.push(`- Nombre: "${origNombre}" → "${nombre}"`);
                if (desc !== origDesc) cambios.push('- Descripción: cambiada');
                const setOrig = new Set(origChecked);
                const setAct = new Set(actuales);
                const quitadas = Array.from(setOrig).filter(x => !setAct.has(x));
                const agregadas = Array.from(setAct).filter(x => !setOrig.has(x));

                if (agregadas.length) {
                    const nombresAgregadas = agregadas.map(id => metricMap[id] || ('ID ' + id));
                    cambios.push(`- Métricas agregadas (${agregadas.length}):\n   · ${nombresAgregadas.join('\n   · ')}`);
                }
                if (quitadas.length) cambios.push(`- Métricas quitadas: ${quitadas.length}`);

                const nuevasCount = nuevasValidas.length;
                if (nuevasCount) cambios.push(`- Nuevas métricas a crear (${nuevasCount}):\n   · ${nuevasValidas.join('\n   · ')}`);

                if (cambios.length === 0) {
                    mostrarAlerta('No se detectaron cambios para guardar.', 'warning');
                    return false;
                }

                const resumen = 'Cambios detectados:\n' + cambios.join('\n') + '\n\n¿Desea confirmar y guardar?';
                if (!confirm(resumen)) return false;

                const form = $(this);
                const data = form.serialize() + '&ajax=1';
                $.post(form.attr('action'), data)
                    .done(function(resp) {
                        var json = typeof resp === 'object' ? resp : JSON.parse(resp);
                        if (json && json.success) {
                            // redirigir con mensaje flash, o mostrar alerta de éxito
                            var msg = encodeURIComponent(json.message || 'Modelo actualizado correctamente.');
                            window.location.href = 'modelos.php?msg=' + msg + '&type=success';
                        } else {
                            mostrarAlerta((json && json.error) || 'Error al actualizar el modelo.', 'danger');
                        }
                    })
                    .fail(function(jqXHR) {
                        try {
                            var json = JSON.parse(jqXHR.responseText);
                            mostrarAlerta(json.error || 'Error al actualizar el modelo.', 'danger');
                        } catch (e) {
                            mostrarAlerta('Error al actualizar el modelo.', 'danger');
                        }
                    });

                // y en mostrarAlerta:
                function mostrarAlerta(mensaje, tipo) {
                    const $alert = $(`
    <div class="alert alert-${tipo} alert-dismissible fade show mt-3" role="alert">
      ${mensaje}
      <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
  `);
                    $("#alertContainer").html($alert);
                    $("html, body").animate({
                        scrollTop: 0
                    }, "fast");
                    setTimeout(() => $alert.alert("close"), 4000);
                }

            });

            // --- Modal Nueva Métrica ---
            function limpiarValidacionesModalMod() {
                $('#mnmNombre').removeClass('is-invalid');
                $('#mnmDescripcion').removeClass('is-invalid');
            }
            // ===== APERTURA DEL MODAL (controlada para evitar congelamiento) =====
            $('#btnAbrirModalMetrica').on('click', function(e) {
                e.preventDefault();

                // Si ya hay un backdrop residual, limpiarlo antes de abrir
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');

                // Pequeño retardo para garantizar que el DOM esté listo
                setTimeout(function() {
                    $('#modalNuevaMetricaMod').modal({
                        backdrop: 'static', // evita doble clic accidentales
                        keyboard: false, // evita cierre con ESC mientras abre
                        show: true
                    });
                }, 100);
            });

            $('#btnAgregarMetricaMod').on('click', function() {
                limpiarValidacionesModalMod();
                const regexMetrica = /^[A-Za-zÁÉÍÓÚáéíóúÑñ. ]+$/;
                const n = ($('#mnmNombre').val() || '').trim();
                const d = ($('#mnmDescripcion').val() || '').trim();

                if (!n) {
                    $('#mnmNombre').addClass('is-invalid').focus();
                    return;
                } else if (!regexMetrica.test(n)) {
                    $('#mnmNombre').addClass('is-invalid');
                    $('#mnmNombre').next('.invalid-feedback').text('Solo se permiten letras (con o sin tilde) y puntos.');
                    return;
                }

                if (!d) {
                    $('#mnmDescripcion').addClass('is-invalid').focus();
                    return;
                } else if (!regexMetrica.test(d)) {
                    $('#mnmDescripcion').addClass('is-invalid');
                    $('#mnmDescripcion').next('.invalid-feedback').text('Solo se permiten letras (con o sin tilde) y puntos.');
                    return;
                }
                const $wrap = $('<span class="nm-item mr-2"></span>');
                $wrap.append($('<input type="hidden" name="new_metric_name[]" />').val(n));
                $wrap.append($('<input type="hidden" name="new_metric_desc[]" />').val(d));
                const $chip = $('<span class="chip" title="' + d.replace(/\"/g, '&quot;') + '">' + n + ' <button type="button" class="remove" aria-label="Quitar">&times;</button></span>');
                $chip.find('.remove').on('click', function() {
                    $wrap.remove();
                    $chip.remove();
                    actualizarCount();
                });
                $('#metricasNuevasInputs').append($wrap);
                $('#metricasNuevasChips').append($chip);
                $('#mnmNombre').val('');
                $('#mnmDescripcion').val('');
                actualizarCount();
                // ✅ Cerrar el modal con breve retardo para evitar congelamiento
                setTimeout(function() {
                    $('#modalNuevaMetricaMod').modal('hide');
                }, 150);

                // 🧩 Fallback de seguridad (si el backdrop quedó pegado)
                $('#modalNuevaMetricaMod').on('hidden.bs.modal', function() {
                    $('#mnmNombre').val('');
                    $('#mnmDescripcion').val('');
                    limpiarValidacionesModalMod();
                    setTimeout(function() {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                    }, 300);
                });

            });

            $('#btnLimpiarMetricas').on('click', function() {
                $('input[name="metricas[]"]').prop('checked', false);
                actualizarCount();
            });
            $(document).on('change', 'input[name="metricas[]"]', actualizarCount);
            actualizarCount();
        })();
        $(document).ready(function() {
            const nombreInput = $("#nombre");
            const descInput = $("#descripcion");
            const regexGeneral = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;

            function validarCampo($input, regex, mensaje) {
                const val = $input.val().trim();
                const errorDiv = $input.next(".invalid-feedback");

                if (val === "") {
                    errorDiv.text(mensaje.requerido);
                    $input.addClass("is-invalid");
                } else if (!regex.test(val)) {
                    errorDiv.text(mensaje.invalido);
                    $input.addClass("is-invalid");
                } else {
                    errorDiv.text("");
                    $input.removeClass("is-invalid");
                }
            }

            nombreInput.on("input", () =>
                validarCampo(nombreInput, regexGeneral, {
                    requerido: "El nombre del modelo es obligatorio.",
                    invalido: "Solo se permiten letras, números, espacios, puntos, guiones, barras, paréntesis y dos puntos."
                })
            );

            descInput.on("input", () =>
                validarCampo(descInput, regexGeneral, {
                    requerido: "La descripción es obligatoria.",
                    invalido: "Solo se permiten letras, números, espacios, puntos, guiones, barras, paréntesis y dos puntos."
                })
            );
        });
    </script>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>