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

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();

// ============================================================
// 🔒 Validación de permisos
// ============================================================
if (ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal()) {
    header('Location: modelos.php?msg=' . urlencode('Los modelos base no se editan desde esta pantalla.') . '&type=warning');
    exit;
}

// ============================================================
// 🔍 Cargar modelo personalizado del usuario
// ============================================================
$idModelo = (int)($_GET['id'] ?? 0);
if ($idModelo <= 0) {
    header('Location: modelos.php?msg=' . urlencode('Modelo inválido.') . '&type=danger');
    exit;
}

$sql = "
    SELECT pmc.*, p.nombre AS nombre_proyecto
    FROM proyecto_modelo_calidad pmc
    JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    WHERE pmc.id_proyecto_modelo = {$idModelo}
      AND up.id_usuario = {$usr->id}
";
$res = $cn->query($sql);
if (!$res || $res->num_rows === 0) {
    header('Location: modelos.php?msg=' . urlencode('No tiene permisos para editar este modelo o no existe.') . '&type=danger');
    exit;
}
$modelo = $res->fetch_assoc();
?>

<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Editar Modelo Personalizado - <?= Constantes::NOMBRE_SISTEMA; ?></title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
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

    <div class="container mt-4">
        <div id="alertContainer"></div>

        <div class="mb-3">
            <a href="modelos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <form id="formEditarModeloPers" action="modelo.editar.personalizado.procesar.php" method="post">
            <input type="hidden" name="id" value="<?= (int)$modelo['id_proyecto_modelo']; ?>" />

            <div class="card shadow-sm">
                <div class="card-header">
                    <h3>✏️ Editar Modelo Personalizado</h3>
                    <div class="text-muted small">Proyecto: <?= htmlspecialchars($modelo['nombre_proyecto']); ?></div>
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
                        <label for="nombre">Nombre del Modelo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre"
                            value="<?= htmlspecialchars($modelo['nombre']); ?>" />
                        <div id="nombreError" class="invalid-feedback d-block text-danger mt-1" style="display:none;"></div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($modelo['descripcion']); ?></textarea>
                        <div id="descError" class="invalid-feedback d-block text-danger mt-1" style="display:none;"></div>
                    </div>

                    <div class="alert alert-info">
                        <span class="oi oi-info mr-2"></span>
                        Las métricas del modelo pueden gestionarse desde el módulo de <strong>Métricas</strong>.
                    </div>
                </div>

                <div class="card-footer text-right">
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

    <?php include_once '../gui/footer.php'; ?>

    <!-- ✅ Librerías JS -->
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

    <!-- ✅ Script -->
    <script>
        $(function() {
            const nombreRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;
            const descRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 .,()_\-\/\\:]+$/;
            const nombreActual = <?= json_encode($modelo['nombre'] ?? ""); ?>;
            const descActual = <?= json_encode($modelo['descripcion'] ?? ""); ?>;

            $('#formEditarModeloPers').on('submit', function(e) {
                e.preventDefault();

                const nombre = ($('#nombre').val() || '').trim();
                const desc = ($('#descripcion').val() || '').trim();
                let valido = true;

                $('#nombreError, #descError').hide().text('');

                if (!nombre) {
                    $('#nombreError').text('El nombre del modelo es obligatorio.').show();
                    valido = false;
                } else if (!nombreRegex.test(nombre)) {
                    $('#nombreError').text('Solo se permiten letras (con o sin tilde), números, espacios, puntos, guiones, barras, paréntesis y dos puntos.').show();
                    valido = false;
                }

                if (!desc) {
                    $('#descError').text('La descripción es obligatoria.').show();
                    valido = false;
                } else if (!descRegex.test(desc)) {
                    $('#descError').text('Solo se permiten letras, números, espacios, comas, puntos, guiones, paréntesis, barras y dos puntos.').show();
                    valido = false;
                }

                if (!valido) return false;

                const cambios = [];
                if (nombre !== nombreActual) cambios.push(`- Nombre: "${nombreActual}" → "${nombre}"`);
                if (desc !== descActual) cambios.push(`- Descripción: "${descActual}" → "${desc}"`);

                if (cambios.length === 0) {
                    alert("No se detectaron cambios para guardar.");
                    return false;
                }


                const resumen = `Está a punto de modificar el modelo personalizado.\n\nCambios detectados:\n${cambios.join("\n")}\n\n¿Desea confirmar los cambios?`;
                if (!confirm(resumen)) return false;

                const form = $(this);
                const data = form.serialize() + '&ajax=1';
                
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: data,
                    dataType: 'json', // 🔹 Esto hace que jQuery parsee el JSON automáticamente
                    success: function(json) {
                        if (json.success) {
                            // ✅ Redirigir con flash message de sesión
                            window.location.href = json.redirect || 'modelos.php';
                        } else {
                            mostrarAlerta(json.error || 'Error al actualizar el modelo.', 'danger');
                        }
                    },
                    error: function() {
                        mostrarAlerta('Error de comunicación con el servidor.', 'danger');
                    }
                });
            });

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
    </script>
</body>

</html>