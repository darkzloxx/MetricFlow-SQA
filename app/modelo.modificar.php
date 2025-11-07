<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
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
    while ($r = $rsS->fetch_assoc()) { $metricasSel[(int)$r['id_metrica']] = true; }
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
        .btn-outline-secondary { border-color:#dee2e6; color:#495057; background:#fff; }
        .btn-outline-secondary:hover { background:#f8f9fa; color:#212529; }
        .chip { display:inline-flex; align-items:center; padding:.25rem .5rem; border-radius:16px; background:#f1f3f5; margin:.125rem .25rem; border:1px solid #ced4da; }
        .chip .remove { border:0; background:transparent; color:#6c757d; margin-left:.25rem; cursor:pointer; }
    </style>
</head>
<body>
<?php include_once '../gui/navbar.php'; ?>
<div class="container">
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
                <div id="alertContainer"></div>

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

                <hr/>
                <h5 class="mb-2">Métricas del modelo</h5>
                <div class="mb-2 text-muted small">Marque para incluir. Puede agregar nuevas métricas al final.</div>
                <div class="border rounded p-2" style="max-height: 300px; overflow:auto;">
                    <?php if (empty($allMetrics)): ?>
                        <div class="text-muted">No hay métricas registradas.</div>
                    <?php else: ?>
                        <?php foreach ($allMetrics as $met): $mid=(int)$met['id_metrica']; ?>
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
                        <button type="button" class="btn btn-outline-primary mr-2" data-toggle="modal" data-target="#modalNuevaMetricaMod">
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
(function(){
    const origNombre = <?= json_encode($modelo['nombre'] ?? ''); ?>;
    const origDesc = <?= json_encode($modelo['descripcion'] ?? ''); ?>;
    const origChecked = (function(){
        const arr = [];
        <?php foreach ($metricasSel as $mid => $_): ?>
        arr.push(<?= (int)$mid; ?>);
        <?php endforeach; ?>
        return arr.map(String);
    })();
    // Mapa id->nombre para mostrar en el resumen de cambios
    const metricMap = (function(){
        const m = {};
        <?php foreach ($allMetrics as $met): ?>
        m['<?= (int)$met['id_metrica']; ?>'] = <?= json_encode($met['nombre']); ?>;
        <?php endforeach; ?>
        return m;
    })();

    // Permitir letras (incl. acentos), espacios, guiones y puntos
    const nameRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ .-]+$/;
    
    // Contador dinámico de seleccionadas (existentes + nuevas)
    function actualizarCount(){
        var total = $('input[name="metricas[]"]:checked').length + $('input[name="new_metric_name[]"]').length;
        var $b = $('#metricasSeleccionadasCount');
        if (total>0){ $b.text(total+' seleccionadas').removeClass('d-none'); } else { $b.addClass('d-none').text(''); }
    }

    // Handler confirmar con resumen de cambios
    $('#formEditarModelo').on('submit', function(e){
        e.preventDefault();
        const nuevoNombre = ($('#nombre').val()||'').trim();
        const nuevaDesc = ($('#descripcion').val()||'').trim();
        const actuales = $('input[name="metricas[]"]:checked').map(function(){return $(this).val();}).get();
        const nuevosNombres = $('input[name="new_metric_name[]"]').map(function(){return $(this).val();}).get();
        const nuevosDesc = $('input[name="new_metric_desc[]"]').map(function(){return $(this).val();}).get();

        // Validaciones de campos
        let valido = true;
        $('#nombreError').hide().text('');
        $('#descError').hide().text('');
    if (!nuevoNombre) { $('#nombreError').text('El nombre es obligatorio.').show(); valido = false; }
    else if (!nameRegex.test(nuevoNombre)) { $('#nombreError').text('El nombre solo puede contener letras, espacios, guiones y puntos.').show(); valido = false; }
    if (!nuevaDesc) { $('#descError').text('La descripción es obligatoria.').show(); valido = false; }
    else if (!nameRegex.test(nuevaDesc)) { $('#descError').text('La descripción solo puede contener letras, espacios, guiones y puntos.').show(); valido = false; }

        // Validar nuevas métricas (nombres válidos si existen)
        for (var i=0;i<nuevosNombres.length;i++){
            const nm = (nuevosNombres[i]||'').trim();
            if (nm && !nameRegex.test(nm)) {
                alert('Nombre de nueva métrica inválido: "'+nm+'". Solo letras, espacios, guiones y puntos.');
                return false;
            }
        }

    const nuevasValidas = (nuevosNombres||[]).filter(function(n){ return (n||'').trim().length>0; });
        if ((actuales.length + nuevasValidas.length) === 0){
            alert('Debe seleccionar al menos una métrica (existente o nueva).');
            return false;
        }

        if (!valido) return false;

        const cambios = [];
        if (nuevoNombre !== origNombre) cambios.push(`- Nombre: "${origNombre}" → "${nuevoNombre}"`);
        if (nuevaDesc !== origDesc) cambios.push('- Descripción: cambiada');

        const setOrig = new Set(origChecked);
        const setAct = new Set(actuales);
    const quitadas = Array.from(setOrig).filter(function(x){ return !setAct.has(x); });
    const agregadas = Array.from(setAct).filter(function(x){ return !setOrig.has(x); });
        if (agregadas.length) {
            const nombresAgregadas = agregadas.map(function(id){ return metricMap[id] || ('ID '+id); });
            cambios.push(`- Métricas agregadas (${agregadas.length}):\n   · ${nombresAgregadas.join('\n   · ')}`);
        }
        if (quitadas.length) cambios.push(`- Métricas quitadas: ${quitadas.length}`);

        const nuevasCount = nuevasValidas.length;
        if (nuevasCount) cambios.push(`- Nuevas métricas a crear (${nuevasCount}):\n   · ${nuevasValidas.join('\n   · ')}`);

        if (cambios.length === 0) {
            alert('No se detectaron cambios para guardar.');
            return false;
        }

        const resumen = 'Cambios detectados:\n' + cambios.join('\n') + '\n\n¿Desea confirmar y guardar?';
        if (!confirm(resumen)) return false;

        const form = $(this);
        const data = form.serialize() + '&ajax=1';
        $.post(form.attr('action'), data)
         .done(function(resp){
            let json; try{ json = typeof resp==='object'? resp : JSON.parse(resp);} catch{ json=null; }
            if (json && json.success) {
                const msg = encodeURIComponent(json.message || 'Modelo actualizado correctamente.');
                window.location.href = 'modelos.php?msg=' + msg + '&type=success';
            } else {
                const msg = (json && (json.error||json.message)) || 'Error al actualizar el modelo';
                mostrarAlerta(msg, 'danger');
            }
         })
         .fail(function(xhr){
            const msg = xhr.responseJSON?.error || 'Error en la comunicación con el servidor.';
            mostrarAlerta(msg, 'danger');
         });
    });

    // Modal Nueva Métrica (misma UX que en crear predeterminado)
    function limpiarValidacionesModalMod(){
        $('#mnmNombre').removeClass('is-invalid');
        $('#mnmDescripcion').removeClass('is-invalid');
    }
    $('#btnAgregarMetricaMod').on('click', function(){
        limpiarValidacionesModalMod();
        const n = ($('#mnmNombre').val()||'').trim();
        const d = ($('#mnmDescripcion').val()||'').trim();
        if (!n) { $('#mnmNombre').addClass('is-invalid').focus(); return; }
        if (!nameRegex.test(n)) { $('#mnmNombre').addClass('is-invalid'); alert('El nombre de la métrica solo puede contener letras, espacios, guiones y puntos.'); return; }
        if (!d) { $('#mnmDescripcion').addClass('is-invalid').focus(); return; }
        const $wrap = $('<span class="nm-item mr-2"></span>');
        $wrap.append($('<input type="hidden" name="new_metric_name[]" />').val(n));
        $wrap.append($('<input type="hidden" name="new_metric_desc[]" />').val(d));
    const $chip = $('<span class="chip" title="'+d.replace(/\"/g,'&quot;')+'">'+n+' <button type="button" class="remove" aria-label="Quitar">&times;</button></span>');
    $chip.find('.remove').on('click', function(){ $wrap.remove(); $chip.remove(); actualizarCount(); });
        $('#metricasNuevasInputs').append($wrap);
        $('#metricasNuevasChips').append($chip);
    $('#mnmNombre').val(''); $('#mnmDescripcion').val(''); actualizarCount();
        $('#modalNuevaMetricaMod').one('hidden.bs.modal', function(){ $('body').removeClass('modal-open'); $('.modal-backdrop').remove(); }).modal('hide');
        setTimeout(function(){ $('body').removeClass('modal-open'); $('.modal-backdrop').remove(); }, 250);
    });
    $('#btnLimpiarMetricas').on('click', function(){ $('input[name="metricas[]"]').prop('checked', false); actualizarCount(); });
    $(document).on('change','input[name="metricas[]"]', actualizarCount);

    // Inicializar contador al cargar
    actualizarCount();

    function mostrarAlerta(mensaje, tipo){
        const $alert = $(`<div class="alert alert-${tipo} alert-dismissible fade show mt-3" role="alert">${mensaje}<button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>`);
        $('#alertContainer').html($alert);
        $('html, body').animate({scrollTop:0}, 'fast');
        setTimeout(()=> $alert.alert('close'), 3500);
    }
})();
</script>
<?php include_once '../gui/footer.php'; ?>
</body>
</html>
