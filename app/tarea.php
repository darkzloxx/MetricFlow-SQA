<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$idUsuario = (int)$usr->id;

// Proyectos asignados al usuario con modelo + iteraciones
$sqlProy = "
    SELECT DISTINCT p.id_proyecto, p.nombre AS proyecto
    FROM proyecto p
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    WHERE up.id_usuario = $idUsuario
      AND (p.id_modelo_global IS NOT NULL OR p.id_modelo_personalizado IS NOT NULL)
      AND EXISTS (SELECT 1 FROM iteracion WHERE id_proyecto = p.id_proyecto)
    ORDER BY p.nombre ASC
";
$resProy = $cn->query($sqlProy);
$proyectosValidos = $resProy ? $resProy->fetch_all(MYSQLI_ASSOC) : [];
?>

<html>

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Gestión de Tareas</title>

    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>

    <style>
        .acciones {
            display: flex;
            gap: 4px;
            justify-content: center;
        }

        .acciones a.btn {
            min-width: 42px;
            height: 30px;
            padding: 6px 12px !important;
        }

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

    <div class="container mt-3">

        <div class="mb-3">
            <a href="proyectos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
        <?php if (isset($_SESSION['flash'])): ?>
            <?php list($tipo, $msg) = $_SESSION['flash'];
            unset($_SESSION['flash']); ?>

            <div id="alertContainer">
                <div class="alert alert-<?= $tipo ?> alert-dismissible fade show" role="alert">
                    <?= $msg ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            </div>

            <script>
                $(function() {
                    const $alert = $('#alertContainer .alert');

                    // Scroll al top para que siempre se vea el mensaje
                    $('html, body').animate({
                        scrollTop: 0
                    }, 'fast');

                    // Cerrar automáticamente después de 3 segundos
                    setTimeout(() => {
                        $alert.alert('close');
                    }, 3000);
                });
            </script>

        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Gestión de Tareas</h3>
            </div>

            <div class="card-body">

                <?php if (empty($proyectosValidos)): ?>

                    <div class="alert alert-warning">
                        ⚠️ No hay ningún proyecto con <strong>modelo asignado</strong> e <strong>iteraciones creadas</strong>.
                    </div>

                <?php else: ?>

                    <?php foreach ($proyectosValidos as $p):
                        $idP = (int)$p['id_proyecto'];
                        $nombreProy = htmlspecialchars($p['proyecto']);

                        $sqlT = "
                        SELECT DISTINCT 
                            t.*, 
                            i.numero_iteracion, 
                            f.nombre AS fase
                        FROM tarea t
                        JOIN iteracion_tarea it ON it.id_tarea = t.id_tarea
                        JOIN iteracion i ON i.id_iteracion = it.id_iteracion
                        JOIN fase f ON f.id_fase = i.id_fase
                        WHERE i.id_proyecto = $idP
                        ORDER BY i.numero_iteracion ASC, t.id_tarea ASC
                    ";
                        $tareas = $cn->query($sqlT)->fetch_all(MYSQLI_ASSOC);

                        // Agrupar por iteración
                        $porIteracion = [];
                        foreach ($tareas as $t) {
                            $porIteracion[$t['numero_iteracion']][] = $t;
                        }
                    ?>

                        <div class="alert alert-info mt-4 d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold">Proyecto: <?= $nombreProy; ?></span>

                            <a href="tarea.crear.php?proyecto=<?= $idP ?>" class="btn btn-success">
                                <span class="oi oi-plus"></span> Nueva Tarea
                            </a>
                        </div>

                        <?php if (empty($tareas)): ?>
                            <div class="text-muted">No hay tareas creadas todavía.</div>

                        <?php else: ?>

                            <?php if (!empty($tareas)): ?>

                               <table class="table table-hover table-sm">
   <?php foreach ($porIteracion as $numIt => $tareasIt): ?>
    <?php $fase = htmlspecialchars($tareasIt[0]['fase']); ?>

    <!-- Título de iteración -->
    <h5 class="mt-3 text-primary font-weight-bold">
        <?= $fase . " " . (int)$numIt ?>
    </h5>

    <!-- Tabla de tareas de esta iteración -->
    <table class="table table-hover table-sm">
            <tr class="table-info">
                <th style="width: 70%;">Nombre</th>
                <th style="width: 30%;" class="text-center">Opciones</th>
            </tr>

        <tbody>
            <?php foreach ($tareasIt as $t): ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($t['nombre']); ?></td>
                    <td class="acciones">
                        <a class="btn btn-outline-primary"
                           href="tarea.ver.php?id=<?= $t['id_tarea']; ?>">
                            <span class="oi oi-eye"></span>
                        </a>

                        <a class="btn btn-outline-warning"
                           href="tarea.modificar.php?id=<?= $t['id_tarea']; ?>">
                            <span class="oi oi-pencil"></span>
                        </a>

                        <a class="btn btn-outline-danger"
                           onclick="return confirm('¿Eliminar tarea?');"
                           href="tarea.eliminar.procesar.php?id=<?= $t['id_tarea']; ?>">
                            <span class="oi oi-trash"></span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endforeach; ?>

</table>


                            <?php else: ?>
                                <div class="text-muted">No hay tareas creadas todavía.</div>
                            <?php endif; ?>


                        <?php endif; ?>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>

    </div>

    <?php include_once '../gui/footer.php'; ?>

</body>

</html>