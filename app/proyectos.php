<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::verificaLogin();
$tieneAbmProyectos = ControlAcceso::verificaPermiso(PermisosSistema::ABM_PROYECTOS);
$usr = ControlAcceso::usuarioActual();

$cn = BDConexion::getInstancia();
// Helper: obtiene el nombre del rol del usuario en un proyecto específico
function getRolUsuarioEnProyecto(mysqli $cn, int $idUsuario, int $idProyecto): ?string {
    $sql = "SELECT r.nombre AS rol_nombre\n            FROM usuario_proyecto up\n            JOIN rol r ON r.id = up.id_rol\n            WHERE up.id_usuario = ? AND up.id_proyecto = ?\n            LIMIT 1";
    if (!$stmt = $cn->prepare($sql)) { return null; }
    $stmt->bind_param('ii', $idUsuario, $idProyecto);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row && !empty($row['rol_nombre']) ? (string)$row['rol_nombre'] : null;
}

// Flag global: si el usuario tiene rol SuperAdmin (global)
$esSuperAdmin = false;
if (isset($usr->roles) && is_array($usr->roles)) {
    foreach ($usr->roles as $r) {
        $name = mb_strtolower(trim($r->nombre ?? ''), 'UTF-8');
        if ($name === 'superadmin') { $esSuperAdmin = true; break; }
    }
}
if ($tieneAbmProyectos) {
    $sql = "SELECT p.* FROM proyecto p ORDER BY p.id_proyecto";
    $proyectos = $cn->query($sql)->fetch_all(MYSQLI_ASSOC);
} else {
    $sql = "SELECT p.*
            FROM proyecto p
            JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
            WHERE up.id_usuario = ?
            ORDER BY p.id_proyecto";
    $stmt = $cn->prepare($sql);
    $stmt->bind_param('i', $usr->id);
    $stmt->execute();
    $res = $stmt->get_result();
    $proyectos = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Proyectos</title>

    <style>
        /* Restaurar comportamiento estándar de Bootstrap para botones outline-secondary
           (evita que un override local haga que parezca diferente a los demás botones) */
        .btn-outline-secondary {
            color: #6c757d;
            /* color del texto/borde */
            background-color: transparent;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        /* Ajuste opcional para botones que solo contienen icono */
        .btn-icon {

            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container">

        <!-- 🔔 Contenedor de alertas dinámicas -->
        <div id="alertContainer" class="mt-3">
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
                <script>
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
            <?php endif; ?>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3>Proyectos</h3>
            </div>
            <div class="card-body">
                <?php if ($tieneAbmProyectos): ?>
                    <p>
                        <a href="proyecto.crear.php">
                            <button type="button" class="btn btn-success">
                                <span class="oi oi-plus"></span> Nuevo Proyecto
                            </button>
                        </a>
                    </p>
                <?php endif; ?>

                <?php if (empty($proyectos)): ?>
                    <div class="card my-4 text-center"
                        style="border:1px dashed rgba(23,162,184,0.15); background:rgba(23,162,184,0.03);">
                        <div class="card-body p-4">
                            <i class="oi oi-info mb-2" style="font-size:2rem; color:#17a2b8;"></i>
                            <h5 class="text-info font-weight-bold mb-2">No tenés proyectos asignados</h5>
                            <p class="text-muted mb-3">Aún no fuiste asignado a ningún proyecto. Si creés que esto es un error, contactá a un administrador.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <table class="table table-hover table-sm">
                        <tr class="table-info">
                            <th>Nombre</th>
                            <th>Año</th>
                            <th>Estado</th>
                            <th>Rol</th>
                            <th>Opciones</th>
                        </tr>
                        <?php foreach ($proyectos as $Proyec): ?>
                            <tr>
                                <td><?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>2025</td>
                                <td><?= htmlspecialchars($Proyec['estado'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php
                                        $rolProyecto = $esSuperAdmin
                                            ? 'SuperAdmin'
                                            : (getRolUsuarioEnProyecto($cn, (int)$usr->id, (int)$Proyec['id_proyecto']) ?? '—');
                                        // Normalizamos para comparaciones
                                        $rolLower = mb_strtolower($rolProyecto, 'UTF-8');
                                        $esGerenteOLider = in_array($rolLower, ['gerente de calidad','líder de proyecto','lider de proyecto'], true);
                                        $esAdminProyecto = ($rolLower === 'administrador');
                                    ?>
                                    <span class="badge badge-secondary" title="Rol en este proyecto"><?= htmlspecialchars($rolProyecto, ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <!-- Ver y Dashboard Inicial: disponibles para todos los roles -->
                                    <a title="Ver" href="proyecto.ver.php?id=<?= (int)$Proyec['id_proyecto']; ?>"
                                        class="btn btn-outline-primary" role="button" aria-label="Ver proyecto <?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="oi oi-eye" aria-hidden="true"></span>
                                    </a>

                                    <a title="Dashboard Inicial" href="dashboard.php?proyecto=<?= (int)$Proyec['id_proyecto']; ?>"
                                        class="btn btn-outline-info" role="button" aria-label="Ver dashboard del proyecto <?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="oi oi-bar-chart" aria-hidden="true"></span>
                                    </a>

                                    <!-- Dashboard exclusivo: SuperAdmin o Gerente de Calidad / Líder de Proyecto -->
                                    <?php if ($esSuperAdmin || $esGerenteOLider): ?>
                                        <a title="Dashboard de Calidad"
                                            href="dashboard_exclusivo.php?proyecto=<?= (int)$Proyec['id_proyecto']; ?>"
                                            class="btn btn-outline-secondary btn-icon"
                                            role="button"
                                            aria-label="Dashboard de Calidad del proyecto <?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="oi oi-pie-chart" aria-hidden="true"></span>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Modificar / Eliminar: SuperAdmin o Administrador del proyecto -->
                                    <?php if ($esSuperAdmin || $esAdminProyecto): ?>
                                        <a title="Modificar" href="proyecto.modificar.php?id=<?= (int)$Proyec['id_proyecto']; ?>"
                                            class="btn btn-outline-warning" role="button" aria-label="Modificar proyecto <?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="oi oi-pencil" aria-hidden="true"></span>
                                        </a>
                                        <button title="Eliminar" class="btn btn-outline-danger btn-eliminar"
                                            data-id="<?= (int)$Proyec['id_proyecto']; ?>"
                                            data-nombre="<?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="oi oi-trash"></span>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include_once '../gui/footer.php'; ?>
    <script>
        (function($) {
            $(document).on('click', '.btn-eliminar', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const id = $btn.data('id');
                const nombre = $btn.data('nombre');

                if (!confirm(`¿Confirma que desea eliminar el proyecto "${nombre}"? Esta operación no puede deshacerse.`)) return;

                $.post('proyecto.eliminar.procesar.php', {
                        id: id,
                        ajax: 1
                    })
                    .done(function(resp) {
                        let json;
                        try {
                            json = (typeof resp === 'object') ? resp : JSON.parse(resp);
                        } catch {
                            mostrarAlerta('Respuesta inesperada del servidor.', 'danger');
                            return;
                        }

                        if (json.success) {
                            $btn.closest('tr').fadeOut(300, function() {
                                $(this).remove();
                            });
                            mostrarAlerta(json.message || 'Proyecto eliminado correctamente.', 'success');
                        } else {
                            mostrarAlerta(json.message || 'No se pudo eliminar el proyecto.', 'danger');
                        }
                    })
                    .fail(function() {
                        mostrarAlerta('⚠️ Error en la comunicación con el servidor.', 'danger');
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
                $('#alertContainer').html($alert);
                $('html, body').animate({
                    scrollTop: 0
                }, 'fast');
                setTimeout(() => $alert.alert('close'), 3000);
            }
        })(jQuery);
    </script>
</body>

</html>