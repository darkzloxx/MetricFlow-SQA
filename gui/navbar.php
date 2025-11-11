<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!class_exists('ControlAcceso')) {
    require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
}
if (!class_exists('BDConexion')) {
    @require_once __DIR__ . '/../modelo/BDConexion.Class.php';
}

$currentPage = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2 fixed-top">
    <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="../lib/img/Logo-UNPA-UARG-azul.png" width="48" height="48" class="d-inline-block align-top mr-2" alt="Logo UNPA UARG">
        MetricFlow-SQA
    </a>

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <?php
        $esAdmin = ControlAcceso::esAdminGlobal();
        $esSuperAdmin = ControlAcceso::esSuperAdminGlobal();

        $tieneProyectos = false;
        try {
            $tieneProyectos = !empty(ControlAcceso::proyectosAsignadosDelUsuario());
        } catch (Throwable $e) {
            $tieneProyectos = false;
        }
        ?>

        <ul class="navbar-nav mr-auto">
            <?php if (ControlAcceso::verificaPermiso(PermisosSistema::ABM_USUARIOS)) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="../app/usuarios.php">
                        <span class="oi oi-people"></span> Usuarios
                    </a>
                </li>
            <?php } ?>

            <!-- 🔹 Proyectos: visible para todos excepto en index o salir -->
            <?php if (!in_array($currentPage, ['index.php', 'salir.php'])) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="../app/proyectos.php">
                        <span class="oi oi-folder"></span> Proyectos
                    </a>
                </li>
            <?php } ?>

            <?php if ($esAdmin || $esSuperAdmin || ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="../app/modelos.php">
                        <span class="oi oi-book"></span> Modelos
                    </a>
                </li>
            <?php } ?>

            <?php
            // Mostrar Métricas si tiene permiso o es admin/superadmin
            $mostrarMetricas = ($esAdmin || $esSuperAdmin);
            if (!$mostrarMetricas) {
                try {
                    $mostrarMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);
                } catch (Throwable $e) {
                    $mostrarMetricas = false;
                }
            }
            if ($mostrarMetricas) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="../app/metricas.php">
                        <span class="oi oi-book"></span> Métricas
                    </a>
                </li>
            <?php } ?>
            <!-- 🔹 Iteraciones (solo Líder / permiso ABM_ITERACIONES) -->
            <?php if (ControlAcceso::verificaPermiso(PermisosSistema::ABM_ITERACIONES)) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="../app/iteracion.php">
                        <span class="oi oi-loop-circular"></span> Iteraciones
                    </a>
                </li>
            <?php } ?>
            <!-- 🔹 Botón Salir: solo si no estamos en index.php ni salir.php -->
            <?php if (!in_array($currentPage, ['index.php', 'salir.php'])) { ?>
                <li class="nav-item">
                    <a class="nav-link" id="btnSalir" href="../app/salir.php">
                        <span class="oi oi-account-logout"></span> Salir
                    </a>
                </li>
            <?php } ?>
        </ul>
    </div>
</nav>

<script>
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