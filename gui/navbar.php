<?php
// Asegura sesión y clases disponibles aunque el navbar se incluya directo
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!class_exists('ControlAcceso')) {
    require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
}
// Página actual (basename) para poder adaptar la UI según la vista
$currentPage = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
?>
<!-- Los estilos de navbar son definidos en la libreria css de Bootstrap -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2 fixed-top">

    <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="../lib/img/Logo-UNPA-UARG-azul.png" width="48" height="48" class="d-inline-block align-top mr-2" alt="Logo UNPA UARG">
        MetricFlow-SQA
    </a>

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <?php if ($currentPage !== 'index.php' && $currentPage !== 'salir.php') { ?>
            <ul class="navbar-nav mr-auto">

                <?php if (ControlAcceso::verificaPermiso(PermisosSistema::ABM_USUARIOS)) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../app/usuarios.php">
                            <span class="oi oi-people" />
                            Usuarios
                        </a>
                    </li>
                <?php } ?>
                <?php if (ControlAcceso::esSuperAdminGlobal()) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../app/roles.php">
                            <span class="oi oi-graph" />
                            Roles
                        </a>
                    </li>
                <?php } ?>
                <?php
                $mostrarProyectos = ControlAcceso::verificaPermiso(PermisosSistema::ABM_PROYECTOS);
                if (!$mostrarProyectos && class_exists('ControlAcceso')) {
                    try {
                        $mostrarProyectos = !empty(ControlAcceso::proyectosAsignadosDelUsuario());
                    } catch (Throwable $e) {
                        $mostrarProyectos = false;
                    }
                }
                if ($mostrarProyectos) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../app/proyectos.php">
                            <span class="oi oi-folder" />
                            Proyectos
                        </a>
                    </li>
                <?php } ?>
                <?php if (ControlAcceso::esAdminGlobal() || ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) { ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../app/modelos.php">
                                <span class="oi oi-book" /> 
                                Modelos
                            </a>
                        </li>
                    <?php } ?>

                <li class="nav-item">
                    <a class="nav-link" id="btnSalir" href="../app/salir.php">
                        <span class="oi oi-account-logout" />
                        Salir
                    </a>
                </li>
            </ul>
        <?php } ?>
    </div>
</nav>
<script>
    // Confirmación antes de cerrar sesión
    document.addEventListener('DOMContentLoaded', function() {
        var btnSalir = document.getElementById('btnSalir');
        if (btnSalir) {
            btnSalir.addEventListener('click', function(e) {
                if (!confirm('¿Está seguro que desea cerrar sesión?')) {
                    e.preventDefault();
                }
            });
        }
    });
</script>
<?php /*
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <?php $nom = isset($_SESSION['usuario']) ? ($_SESSION['usuario']->nombre ?? 'Usuario') : 'Invitado'; ?>
    Ud. est&aacute; conectad@ como <strong><?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?></strong>.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
*/ ?>