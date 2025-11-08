<?php
// Asegura sesión y clases disponibles aunque el navbar se incluya directo
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!class_exists('ControlAcceso')) {
    require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
}
// Conexión a BD si se necesita consultar proyectos asignados
if (!class_exists('BDConexion')) {
    @require_once __DIR__ . '/../modelo/BDConexion.Class.php';
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
        <?php
        // Solo mostrar menú si es admin, superadmin o tiene proyectos asignados
        $esAdmin = ControlAcceso::esAdminGlobal();
        $esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
        $tieneProyectos = false;
        try {
            $tieneProyectos = !empty(ControlAcceso::proyectosAsignadosDelUsuario());
        } catch (Throwable $e) {
            $tieneProyectos = false;
        }
        if ($esAdmin || $esSuperAdmin || $tieneProyectos) { ?>
            <ul class="navbar-nav mr-auto">
                <?php if (ControlAcceso::verificaPermiso(PermisosSistema::ABM_USUARIOS)) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../app/usuarios.php">
                            <span class="oi oi-people" />
                            Usuarios
                        </a>
                    </li>
                <?php } ?>
                <?php if ($esSuperAdmin) { ?>
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
                <?php if ($esAdmin || $esSuperAdmin || ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../app/modelos.php">
                            <span class="oi oi-book" />
                            Modelos
                        </a>
                    </li>
                <?php } ?>
                <?php
                $mostrarMetricas = false;
                if ($esAdmin) {
                    $mostrarMetricas = true;
                } else {
                    $tienePermisoMetricas = false;
                    try {
                        $tienePermisoMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);
                    } catch (Throwable $e) {
                        $tienePermisoMetricas = false;
                    }
                    if ($tienePermisoMetricas && class_exists('BDConexion')) {
                        try {
                            $usrActual = method_exists('ControlAcceso', 'usuarioActual') ? ControlAcceso::usuarioActual() : ($_SESSION['usuario'] ?? null);
                            $idUsr = is_object($usrActual) && isset($usrActual->id) ? (int)$usrActual->id : 0;
                            if ($idUsr > 0) {
                                $cnm = BDConexion::getInstancia();
                                $sqlMet = "SELECT 1\n                                           FROM proyecto p\n                                           INNER JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto\n                                           WHERE up.id_usuario = ? AND p.id_modelo IS NOT NULL\n                                           LIMIT 1";
                                if ($stmtMet = $cnm->prepare($sqlMet)) {
                                    $stmtMet->bind_param('i', $idUsr);
                                    $stmtMet->execute();
                                    $stmtMet->store_result();
                                    if ($stmtMet->num_rows > 0) {
                                        $mostrarMetricas = true;
                                    }
                                    $stmtMet->close();
                                }
                            }
                        } catch (Throwable $e) {
                            // Silenciar errores
                        }
                    }
                }
                if ($mostrarMetricas) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../app/metricas.php">
                            <span class="oi oi-book" />
                            Métricas
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
        <?php } else { ?>
            <ul class="navbar-nav mr-auto">
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