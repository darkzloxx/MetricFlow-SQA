<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::verificaLogin();
$tieneAbmProyectos = ControlAcceso::verificaPermiso(PermisosSistema::ABM_PROYECTOS);
$usr = ControlAcceso::usuarioActual();

$cn = BDConexion::getInstancia();
// Helper: obtiene el nombre del rol del usuario en un proyecto específico
function getRolUsuarioEnProyecto(mysqli $cn, int $idUsuario, int $idProyecto): ?string
{
    $sql = "SELECT r.nombre AS rol_nombre\n            FROM usuario_proyecto up\n            JOIN rol r ON r.id = up.id_rol\n            WHERE up.id_usuario = ? AND up.id_proyecto = ?\n            LIMIT 1";
    if (!$stmt = $cn->prepare($sql)) {
        return null;
    }
    $stmt->bind_param('ii', $idUsuario, $idProyecto);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
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
        if ($name === 'superadmin') {
            $esSuperAdmin = true;
            break;
        }
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

// ===============================
// 🧭 Construcción del WIZARD antes de usarlo en la vista
// ===============================
// 🧭 Wizard por proyecto: solo el próximo paso de cada proyecto accesible
$wizardsPorProyecto = [];

// Paso 1: verificar si existen proyectos
$existenProyectos = (int)$cn->query("SELECT COUNT(*) AS c FROM proyecto")->fetch_assoc()['c'];
if ($existenProyectos === 0) {
    $wizard[] = [
        'paso' => 1,
        'texto' => 'No existen proyectos en el sistema.',
        'accion' => 'Creá un nuevo proyecto desde esta pantalla.',
        'responsable' => 'Administrador o SuperAdmin',
        'icono' => 'oi-plus',
        'estado' => 'pendiente',
    ];
}

// Paso 2: proyectos sin usuarios asignados
$proySinUsuarios = (int)$cn->query("
    SELECT COUNT(*) AS c FROM proyecto p
    LEFT JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    WHERE up.id_usuario IS NULL
")->fetch_assoc()['c'];
if ($existenProyectos > 0 && $proySinUsuarios > 0) {
    $wizard[] = [
        'paso' => 2,
        'texto' => "$proySinUsuarios proyecto(s) sin usuarios asignados.",
        'accion' => 'Asigná usuarios a cada proyecto creado.',
        'responsable' => 'Administrador o SuperAdmin',
        'icono' => 'oi-people',
        'estado' => 'pendiente',
    ];
}

// Paso 3: proyectos sin modelo de calidad seleccionado
$proySinModelo = (int)$cn->query("
    SELECT COUNT(*) AS c FROM proyecto WHERE id_modelo IS NULL
")->fetch_assoc()['c'];
if ($proySinModelo > 0) {
    $wizard[] = [
        'paso' => 3,
        'texto' => "$proySinModelo proyecto(s) sin modelo de calidad asignado.",
        'accion' => 'Seleccioná o creá un modelo personalizado para cada proyecto.',
        'responsable' => 'Gerente de Calidad o Líder de Proyecto',
        'icono' => 'oi-layers',
        'estado' => 'pendiente',
    ];
}

// Paso 4: proyectos sin iteraciones
$sinIteraciones = (int)$cn->query("
    SELECT COUNT(*) AS c FROM proyecto p
    LEFT JOIN iteracion i ON i.id_proyecto=p.id_proyecto
    WHERE i.id_iteracion IS NULL
")->fetch_assoc()['c'];
if ($sinIteraciones > 0) {
    $wizard[] = [
        'paso' => 4,
        'texto' => "$sinIteraciones proyecto(s) sin iteraciones creadas.",
        'accion' => 'Definí las iteraciones del proyecto.',
        'responsable' => 'Líder de Proyecto',
        'icono' => 'oi-loop-circular',
        'estado' => 'pendiente',
    ];
}

// Paso 5: iteraciones sin métricas planificadas
$sinPlanif = (int)$cn->query("
    SELECT COUNT(*) AS c FROM iteracion i
    LEFT JOIN metrica_iteracion mi ON mi.id_iteracion=i.id_iteracion
    WHERE mi.valor_planificado IS NULL
")->fetch_assoc()['c'];
if ($sinPlanif > 0) {
    $wizard[] = [
        'paso' => 5,
        'texto' => "$sinPlanif iteración(es) sin métricas planificadas.",
        'accion' => 'Planificá los valores iniciales de las métricas.',
        'responsable' => 'Gerente de Calidad o Líder de Proyecto',
        'icono' => 'oi-task',
        'estado' => 'pendiente',
    ];
}

// Si no hay pendientes, mostrar completado
if (empty($wizard)) {
    $wizard[] = [
        'paso' => '✓',
        'texto' => 'Todos los pasos del flujo están completos.',
        'accion' => 'Podés acceder al dashboard de calidad.',
        'responsable' => 'Todos los roles',
        'icono' => 'oi-check',
        'estado' => 'completo',
    ];
}
foreach ($proyectos as $pr) {
    $idP = (int)$pr['id_proyecto'];
    $nombreP = $pr['nombre'];
    $rolProyecto = $esSuperAdmin ? 'SuperAdmin' : (getRolUsuarioEnProyecto($cn, (int)$usr->id, $idP) ?? '');
    $rolLower = mb_strtolower($rolProyecto, 'UTF-8');
    $esAdminProyecto = $esSuperAdmin || ($rolLower === 'administrador');
    $esGerenteOLider = $esSuperAdmin || in_array($rolLower, ['gerente de calidad', 'líder de proyecto', 'lider de proyecto'], true);

    $totalSteps = $esAdminProyecto ? 4 : 3; // Admin: 2-5; otros: 3-5
    $completados = 0;
    $next = null;

    // Paso 2 (solo admin): usuarios asignados
    if ($esAdminProyecto) {
        $cantUsuarios = (int)$cn->query("SELECT COUNT(*) AS c FROM usuario_proyecto WHERE id_proyecto=$idP")->fetch_assoc()['c'];
        if ($cantUsuarios === 0) {
            $next = ['paso' => 2, 'texto' => 'Sin usuarios asignados.', 'accion' => 'Asigná usuarios al proyecto.', 'responsable' => 'Administrador o SuperAdmin', 'icono' => 'oi-people', 'estado' => 'pendiente'];
        } else {
            $completados++;
        }
    }
    // Paso 3: modelo
    if ($next === null) {
        if (empty($pr['id_modelo'])) {
            $next = ['paso' => 3, 'texto' => 'Sin modelo de calidad asignado.', 'accion' => 'Seleccioná o creá un modelo.', 'responsable' => 'Gerente de Calidad o Líder de Proyecto', 'icono' => 'oi-layers', 'estado' => 'pendiente'];
        } else {
            $completados++;
        }
    }
    // Paso 4: iteraciones
    if ($next === null) {
        $cantIter = (int)$cn->query("SELECT COUNT(*) AS c FROM iteracion WHERE id_proyecto=$idP")->fetch_assoc()['c'];
        if ($cantIter === 0) {
            $next = ['paso' => 4, 'texto' => 'Sin iteraciones creadas.', 'accion' => 'Definí las iteraciones del proyecto.', 'responsable' => 'Líder de Proyecto', 'icono' => 'oi-loop-circular', 'estado' => 'pendiente'];
        } else {
            $completados++;
        }
    }
    // Paso 5: métricas planificadas
    $detalle = '';
    if ($next === null) {
        $faltanMetricas = (int)$cn->query("SELECT COUNT(*) AS c FROM iteracion i LEFT JOIN metrica_iteracion mi ON mi.id_iteracion=i.id_iteracion WHERE i.id_proyecto=$idP AND mi.valor_planificado IS NULL")->fetch_assoc()['c'];
        if ($faltanMetricas > 0) {
            // Listado de iteraciones afectadas (nombre si existe, sino #id)
            $sqlDet = "SELECT DISTINCT i.id_iteracion,
                            CONCAT(COALESCE(f.nombre,'?'), ' ', i.numero_iteracion) AS etiqueta
                        FROM iteracion i
                        LEFT JOIN metrica_iteracion mi ON mi.id_iteracion = i.id_iteracion
                        LEFT JOIN fase f ON f.id_fase = i.id_fase
                        WHERE i.id_proyecto = $idP AND mi.valor_planificado IS NULL";
            if ($rsDet = $cn->query($sqlDet)) {
                $items = [];
                while ($row = $rsDet->fetch_assoc()) {
                    $items[] = $row['etiqueta'];
                }
                if ($items) {
                    $max = 8;
                    $mostrar = array_slice($items, 0, $max);
                    $resto = count($items) - count($mostrar);
                    $lis = '';
                    foreach ($mostrar as $et) {
                        $lis .= '<li>' . htmlspecialchars($et, ENT_QUOTES, 'UTF-8') . '</li>';
                    }
                    if ($resto > 0) {
                        $lis .= '<li>+' . (int)$resto . ' más…</li>';
                    }
                    $detalle = '<strong>Iteraciones afectadas:</strong><ul class="mb-0 pl-3">' . $lis . '</ul>';
                }
            }
            $next = ['paso' => 5, 'texto' => 'Iteraciones sin métricas planificadas.', 'accion' => 'Planificá valores iniciales de métricas.', 'responsable' => 'Gerente de Calidad o Líder de Proyecto', 'icono' => 'oi-task', 'estado' => 'pendiente'];
        } else {
            $completados++;
        }
    }

    if ($next === null) {
        $next = ['paso' => '✓', 'texto' => 'Proyecto listo.', 'accion' => 'Podés usar el dashboard de calidad.', 'responsable' => 'Todos los roles', 'icono' => 'oi-check', 'estado' => 'completo'];
    }

    // Link de acción según paso y permisos
    $link = null;
    if ($next['estado'] === 'completo') {
        $link = "dashboard.php?proyecto=$idP";
    } else {
        switch ($next['paso']) {
            case 2:
                if ($esAdminProyecto) $link = "proyecto.modificar.php?id=$idP#usuarios";
                break;
            case 3:
                if ($esGerenteOLider || $esSuperAdmin) $link = "proyecto.modificar.php?id=$idP#modelo";
                break;
            case 4:
                if ($esGerenteOLider || $esSuperAdmin) $link = "proyecto.modificar.php?id=$idP#iteraciones";
                break;
            case 5:
                if ($esSuperAdmin || $esGerenteOLider) $link = "dashboard_exclusivo.php?proyecto=$idP#planificacion";
                break;
        }
    }

    $progreso = max(0, min(100, round(($completados / $totalSteps) * 100)));
    $wizardsPorProyecto[$idP] = ['id' => $idP, 'proyecto' => $nombreP, 'step' => $next, 'progreso' => $progreso, 'detalle' => $detalle, 'link' => $link];
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

        /* Wizard visual tweaks */
        .wizard-card .progress {
            background: rgba(23, 162, 184, .15);
        }

        .wizard-step-wrapper {
            padding: .5rem 1rem 1rem;
        }

        .wizard-step {
            border-left: 4px solid #17a2b8;
            background: #fff;
            padding: .75rem 1rem;
            border-radius: .25rem;
            text-decoration: none;
        }

        .wizard-step+.wizard-step {
            margin-top: .5rem;
        }

        .wizard-step:hover {
            background: #f8f9fa;
            text-decoration: none;
        }

        .wizard-step.disabled {
            border-left-color: #ced4da;
            cursor: default;
        }

        .wizard-step .icono-paso {
            font-size: 1.1rem;
        }

        .wizard-step .titulo-paso {
            color: #117a8b;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .wizard-step .titulo-paso .chevron {
            color: #17a2b8;
            font-weight: 700;
        }

        .wizard-step .descripcion-paso {
            margin-top: .25rem;
        }

        .wizard-step .responsable-paso {
            margin-top: .125rem;
        }

        /* Sidebar wizard styling tweaks */
        #wizardFlujo .list-group-item {
            border-left: 4px solid transparent;
            transition: background .15s, border-color .3s;
        }

        #wizardFlujo .list-group-item:hover {
            background: #f8f9fa;
            border-color: #17a2b8;
        }

        #wizardFlujo .progress {
            background: rgba(23, 162, 184, .15);
        }

        #wizardFlujo .progress-bar {
            box-shadow: 0 0 0.25rem rgba(23, 162, 184, .4);
        }

        @media (max-width: 991.98px) {

            /* stack wizard above on mobile */
            #wizardFlujo {
                margin-bottom: 1rem;
            }
        }

        .tooltip-inner {
            max-width: 360px;
            text-align: left;
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

        <div class="row mt-3">
            <!-- Sidebar Wizard -->
            <div class="col-lg-4 mb-3">
                <div id="wizardFlujo">
                    <?php if (!empty($wizardsPorProyecto)): ?>
                        <?php foreach ($wizardsPorProyecto as $idP => $wiz): $w = $wiz['step']; ?>
                            <div class="card shadow-sm border-0 mb-3 wizard-card">
                                <div class="card-header bg-white border-bottom-0 py-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h6 class="mb-0 text-primary">
                                            <span class="oi oi-list-rich mr-1"></span>
                                            Flujo — <?= htmlspecialchars($wiz['proyecto'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h6>
                                        <span class="small text-muted"><?= (int)$wiz['progreso']; ?>% completado</span>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: <?= (int)$wiz['progreso']; ?>%;" aria-valuenow="<?= (int)$wiz['progreso']; ?>"
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <div class="wizard-step-wrapper">
                                    <?php if (!empty($wiz['link'])): ?>
                                        <a href="<?= htmlspecialchars($wiz['link'], ENT_QUOTES, 'UTF-8'); ?>" class="wizard-step d-flex align-items-start">
                                            <span class="oi <?= htmlspecialchars($w['icono']); ?> text-info mr-3 mt-1 icono-paso"></span>
                                            <div class="contenido-paso">
                                                <div class="titulo-paso">
                                                    <strong><?= $w['paso'] === '✓' ? 'Completado' : ('Paso ' . htmlspecialchars((string)$w['paso'])); ?> — <?= htmlspecialchars($w['texto']); ?></strong>
                                                    <?php if (!empty($wiz['detalle'])): ?>
                                                        <span class="ml-2 text-secondary wiz-info" data-toggle="tooltip" data-html="true" title="<?= htmlspecialchars($wiz['detalle'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Más información"><span class="oi oi-info"></span></span>
                                                        <span class="chevron ml-2">›</span>

                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted descripcion-paso"><?= htmlspecialchars($w['accion']); ?></div>
                                                <div class="small font-italic text-secondary responsable-paso">👤 <?= htmlspecialchars($w['responsable']); ?></div>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div class="wizard-step d-flex align-items-start disabled">
                                            <span class="oi <?= htmlspecialchars($w['icono']); ?> text-info mr-3 mt-1 icono-paso"></span>
                                            <div class="contenido-paso">
                                                <div class="titulo-paso">
                                                    <strong><?= $w['paso'] === '✓' ? 'Completado' : ('Paso ' . htmlspecialchars((string)$w['paso'])); ?> — <?= htmlspecialchars($w['texto']); ?></strong>
                                                    <?php if (!empty($wiz['detalle'])): ?>
                                                        <span class="ml-2 text-secondary wiz-info" data-toggle="tooltip" data-html="true" title="<?= htmlspecialchars($wiz['detalle'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Más información"><span class="oi oi-info"></span></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted descripcion-paso"><?= htmlspecialchars($w['accion']); ?></div>
                                                <div class="small font-italic text-secondary responsable-paso">👤 <?= htmlspecialchars($w['responsable']); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card shadow-sm border-0">
                            <div class="card-body py-3 text-center text-muted">
                                No tenés proyectos asignados.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Main content -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h3 class="mb-0">Proyectos</h3>
                        <?php if ($tieneAbmProyectos): ?>
                            <a href="proyecto.crear.php" class="btn btn-success btn-sm" title="Crear nuevo proyecto">
                                <span class="oi oi-plus"></span> Nuevo
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($tieneAbmProyectos): ?>
                            <!-- Botón ya movido al header -->
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
                                            $esGerenteOLider = in_array($rolLower, ['gerente de calidad', 'líder de proyecto', 'lider de proyecto'], true);
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
                    </div> <!-- card-body proyectos -->
                </div> <!-- card proyectos -->
            </div> <!-- col-lg-8 -->
        </div> <!-- row -->
    </div>
    <?php include_once '../gui/footer.php'; ?>
    <script>
        (function($) {
            // Inicializar tooltips (incluye los de info en cada wizard)
            $('[data-toggle="tooltip"]').tooltip();
            // Evitar navegación al hacer click en el icono de info dentro de un card-link
            $(document).on('click', 'a.wiz-info', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
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