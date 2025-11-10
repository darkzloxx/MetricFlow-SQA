<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::verificaLogin();

$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdmin = ControlAcceso::esAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);

if (!($esAdmin || $esSuperAdmin || $tienePermGestionMetricas)) {
    header('Location: metricas.php?msg=' . urlencode('Acceso denegado.') . '&type=danger');
    exit;
}
?>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Nueva Métrica</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

    <style>
        .action-card {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        .icon {
            font-size: 2rem;
        }
        /* 🔹 Evitar subrayado o cambio de color en hover */
        a.text-decoration-none:hover,
        a.text-decoration-none:focus,
        a.text-decoration-none:active {
            text-decoration: none !important;
            color: inherit !important;
        }

        /* 🔹 Card efecto hover sin subrayado */
        .action-card {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            text-decoration: none !important;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-width: 2px !important;
        }
    </style>
</head>

<body>
<?php include_once '../gui/navbar.php'; ?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0">Nueva Métrica</h3>
            <p class="text-muted mb-0 mt-1">Seleccione la acción que desea realizar.</p>
        </div>
        <div class="card-body text-center">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <a href="metrica.crear.php" class="text-decoration-none text-dark">
                        <div class="card action-card h-100 border-success">
                            <div class="card-body">
                                <span class="oi oi-plus icon text-success mb-3 d-block"></span>
                                <h5>Crear nueva métrica</h5>
                                <p class="text-muted mb-0">Defina una métrica nueva y asígnela a uno o más modelos disponibles.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 mb-3">
                    <a href="metrica.vincular.php" class="text-decoration-none text-dark">
                        <div class="card action-card h-100 border-primary">
                            <div class="card-body">
                                <span class="oi oi-link-intact icon text-primary mb-3 d-block"></span>
                                <h5>Vincular métrica existente</h5>
                                <p class="text-muted mb-0">Seleccione una métrica ya creada y asóciela a un modelo de calidad.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="card-footer text-right">
            <a href="metricas.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left"></span> Volver
            </a>
        </div>
    </div>
</div>

<?php include_once '../gui/footer.php'; ?>
</body>
</html>
