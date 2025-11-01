<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/ColeccionUsuarios.php';
$ColeccionUsuarios = new ColeccionUsuarios();
?>

<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Usuarios</title>
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
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container">
        <div class="mb-3">
            <a id="btnVolver" href="proyectos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Usuarios</h3>
            </div>
            <div class="card-body">

                <!-- Contenedor de alertas dinámicas -->
                <div id="alertContainer">
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="alert alert-<?= ($_GET['type'] === 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_GET['msg']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <script>
                            $('html, body').animate({
                                scrollTop: 0
                            }, 'fast');
                            setTimeout(() => $('.alert').alert('close'), 3500);
                        </script>
                    <?php endif; ?>
                </div>

                <p>
                    <a href="usuario.crear.php">
                        <button type="button" class="btn btn-success">
                            <span class="oi oi-plus"></span> Nuevo Usuario
                        </button>
                    </a>
                </p>

                <table class="table table-hover table-sm">
                    <tr class="table-info">
                        <th>Usuario</th>
                        <th>Opciones</th>
                    </tr>
                    <?php foreach ($ColeccionUsuarios->getUsuarios() as $Usuario): ?>
                        <tr>
                            <td><?= $Usuario->getNombre(); ?><br /><?= $Usuario->getEmail(); ?></td>
                            <td>
                                <a title="Ver detalle" href="usuario.ver.php?id=<?= $Usuario->getId(); ?>"
                                    class="btn btn-outline-info" role="button" aria-label="Ver usuario <?= htmlspecialchars($Usuario->getNombre(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="oi oi-zoom-in" aria-hidden="true"></span>
                                </a>

                                <a title="Modificar" href="usuario.modificar.php?id=<?= $Usuario->getId(); ?>"
                                    class="btn btn-outline-warning" role="button" aria-label="Modificar usuario <?= htmlspecialchars($Usuario->getNombre(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="oi oi-pencil" aria-hidden="true"></span>
                                </a>

                                <a title="Eliminar" href="#" class="btn btn-outline-danger btn-eliminar"
                                    data-id="<?= $Usuario->getId(); ?>"
                                    data-nombre="<?= htmlspecialchars($Usuario->getNombre(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="oi oi-trash" aria-hidden="true"></span>
                                </a>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <?php include_once '../gui/footer.php'; ?>

    <script>
        (function($) {
            $(document).on('click', '.btn-eliminar', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var id = $btn.data('id');
                var nombre = $btn.data('nombre') || '';

                if (!confirm('¿Confirma que desea eliminar al usuario "' + nombre + '"? Esta operación no puede deshacerse.')) return;

                $.post('usuario.eliminar.procesar.php', {
                        id: id,
                        ajax: 1
                    })
                    .done(function(resp) {
                        let json;
                        try {
                            json = (typeof resp === 'object') ? resp : JSON.parse(resp);
                        } catch (err) {
                            mostrarAlerta('Respuesta inesperada del servidor.', 'danger');
                            return;
                        }

                        if (json.success) {
                            $btn.closest('tr').fadeOut(300, function() {
                                $(this).remove();
                            });
                            mostrarAlerta('Usuario eliminado correctamente.', 'success');
                        } else {
                            mostrarAlerta('No se pudo eliminar el usuario: ' + (json.error || 'Error desconocido.'), 'danger');
                        }
                    })
                    .fail(function() {
                        mostrarAlerta('⚠️ Error en la comunicación con el servidor.', 'danger');
                    });
            });

            // Mostrar alerta arriba
            function mostrarAlerta(mensaje, tipo) {
                const $alert = $(`
            <div class="alert alert-${tipo} alert-dismissible fade show mt-3" role="alert">
                ${mensaje}
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `);
                $('#alertContainer').html($alert);
                $('html, body').animate({
                    scrollTop: 0
                }, 'fast');
                setTimeout(() => $alert.alert('close'), 3000);
            }
        })(jQuery);
        (function() {
            if (!window.history || !window.history.replaceState) return;
            const params = new URLSearchParams(window.location.search);
            if (!params.has('msg')) return;
            params.delete('msg');
            params.delete('type');
            const newSearch = params.toString();
            const newUrl = window.location.pathname + (newSearch ? ('?' + newSearch) : '');
            window.history.replaceState({}, document.title, newUrl);
        })();
    </script>

</body>

</html>