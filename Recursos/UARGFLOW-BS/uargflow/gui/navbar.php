<nav class="navbar navbar-expand-lg navbar-dark bg-dark" style="height:100px; min-height:100px;">
    <div class="d-flex align-items-center" style="height:100px;">
        <a class="navbar-brand" href="#">
            <img src="../lib/img/MetricFlow.png"
                 style="max-width:80px; max-height:80px; width:auto; height:auto; object-fit:contain; display:block; margin-right:16px;"
                 class="d-inline-block align-top"
                 alt="MetricFlow Logo">
        </a>
    </div>

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="../app/usuarios.php">
                    <span class="oi oi-person"></span>
                    Usuarios
                </a>
            </li>

            <?php if (ControlAcceso::verificaPermiso(PermisosSistema::PERMISO_ROLES)) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="../app/roles.php">
                        <span class="oi oi-graph"></span>
                        Roles
                    </a>
                </li>
            <?php } ?>

            <?php if (ControlAcceso::verificaPermiso(PermisosSistema::PERMISO_PERMISOS)) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="../app/permisos.php">
                        <span class="oi oi-lock-locked"></span>
                        Permisos
                    </a>
                </li>
            <?php } ?>

            <li class="nav-item">
                <a class="nav-link" href="../app/salir.php">
                    <span class="oi oi-account-logout"></span>
                    Salir
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="alert alert-info alert-dismissible fade show" role="alert">
    Ud. está conectad@ como <strong><?= $_SESSION['usuario']->nombre; ?></strong>.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>