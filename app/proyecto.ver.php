<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::verificaLogin();

// ID del proyecto recibido por GET
$idProyecto = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idProyecto <= 0) {
    header('Location: ' . Constantes::HOMEAUTH);
    exit;
}

// Solo permitir ver si el usuario pertenece al proyecto (cualquier rol)
ControlAcceso::requiereProyecto($idProyecto);

// Cargar datos del proyecto (descripcion, estado, nombre)
$cn = BDConexion::getConexion();

$stmt = $cn->prepare('SELECT p.id_proyecto, p.nombre, p.estado, p.descripcion, p.objetivo, p.id_modelo, p.fecha_creacion FROM proyecto p WHERE p.id_proyecto = ?');
$stmt->bind_param('i', $idProyecto);
$stmt->execute();
$res = $stmt->get_result();
$Proyecto = $res->fetch_assoc();
$stmt->close();

// Obtener nombre del modelo asociado si existe
$nombreModelo = null;
if (!empty($Proyecto['id_modelo'])) {
    $stmt = $cn->prepare('SELECT nombre FROM modelo_calidad WHERE id_modelo = ?');
    $stmt->bind_param('i', $Proyecto['id_modelo']);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $nombreModelo = $row ? $row['nombre'] : null;
    $stmt->close();
}

if (!$Proyecto) {
    header('Location: ' . Constantes::HOMEAUTH);
    exit;
}

$urlDashboard = 'dashboard.php?proyecto=' . $idProyecto;
?>


<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Propiedades del Proyecto</title>
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
        <script>
            (function() {
                var btn = document.getElementById('btnVolver');
                if (!btn) return;
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    try {
                        var ref = document.referrer;
                        if (ref && (new URL(ref)).origin === location.origin && history.length > 1) {
                            history.back();
                        } else {
                            location.href = btn.getAttribute('href');
                        }
                    } catch (err) {
                        location.href = btn.getAttribute('href');
                    }
                });
            })();
        </script>
        <p></p>
        <div class="card">
            <div class="card-header">
                <h3>Propiedades del Proyecto</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h5 class="mb-1">Nombre</h5>
                    <div><?= htmlspecialchars($Proyecto['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="mb-3">
                    <h5 class="mb-1">Estado</h5>
                    <div><?= htmlspecialchars($Proyecto['estado'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="mb-3">
                    <h5 class="mb-1">Fecha de Registro</h5>
                    <div><?= htmlspecialchars(date('d/m/Y H:i', strtotime($Proyecto['fecha_creacion'])), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php if (!empty($Proyecto['descripcion'])) { ?>
                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <div><?= nl2br(htmlspecialchars((string)$Proyecto['descripcion'], ENT_QUOTES, 'UTF-8')); ?></div>
                    </div>
                <?php } ?>

                <?php if (!empty($Proyecto['objetivo'])) { ?>
                    <div class="mb-4">
                        <h5 class="mb-1">Objetivo</h5>
                        <div><?= nl2br(htmlspecialchars((string)$Proyecto['objetivo'], ENT_QUOTES, 'UTF-8')); ?></div>
                    </div>
                <?php } ?>
                <?php if (!empty($nombreModelo)) { ?>
                    <div class="mb-3">
                        <h5 class="mb-1">Modelo Asociado</h5>
                        <div><?= htmlspecialchars($nombreModelo, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php } ?>


                <hr />
                <h5 class="card-text mb-3">Dashboard del proyecto</h5>
                <a class="btn btn-primary" href="<?= $urlDashboard; ?>">
                    <span class="oi oi-graph"></span> Abrir Aquí
                </a>
                <a class="btn btn-outline-secondary ml-2" href="<?= $urlDashboard; ?>" target="_blank" rel="noopener">
                    Abrir en otra pestaña
                </a>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>