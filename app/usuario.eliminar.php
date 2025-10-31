<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/Usuario.Class.php';
$id = $_GET["id"];
$Usuario = new Usuario($id);
?>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Eliminar Usuario</title>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <form action="usuario.eliminar.procesar.php" method="post">
            <div class="card">
                <div class="card-header">
                    <h3>Eliminar Usuario</h3>
                </div>
                <div class="card-body">
                    <p class="alert alert-warning ">
                        <span class="oi oi-warning"></span> ATENCI&Oacute;N. Esta operaci&oacute;n no puede deshacerse.
                    </p>
                    <p>¿Est&aacute; seguro que desea eliminar el usuario <b><?= $Usuario->getNombre(); ?></b>?
                </div>
                <div class="card-footer">
                    <input type="hidden" name="id" class="form-control" id="id" value="<?= $Usuario->getId(); ?>">
                    <button type="submit" class="btn btn-outline-success" id="btn_confirmar_eliminar" data-confirm="¿Confirma que desea eliminar al usuario <?= htmlspecialchars($Usuario->getNombre(), ENT_QUOTES, 'UTF-8'); ?>? Esta operación no puede deshacerse.">
                        <span class="oi oi-check"></span> Sí, deseo eliminar
                    </button>
                    <a href="usuarios.php" id="btn_cancelar" class="btn btn-outline-danger" data-confirm="¿Está seguro que desea cancelar? Se perderán los cambios no guardados.">
                        <span class="oi oi-x"></span> NO (Salir de esta pantalla)
                    </a>
                </div>
            </div>
        </form>
    </div>
    <?php include_once '../gui/footer.php'; ?>
    <script>
        // Maneja data-confirm de forma consistente (evita duplicar con onclick inline)
        (function($) {
            $(document).on('click', 'a[data-confirm], button[data-confirm]', function(e) {
                var msg = $(this).attr('data-confirm') || '¿Está seguro?';
                if (!confirm(msg)) {
                    e.preventDefault();
                }
            });
        })(jQuery);
    </script>
</body>

</html>