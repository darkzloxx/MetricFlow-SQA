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

$archivados = [];
$activos = [];
foreach ($proyectos as $p) {
    $estado = $p['estado'] ?? '';
    if (in_array($estado, ['Cancelado', 'Finalizado'])) {
        $archivados[] = $p;
    } else {
        $activos[] = $p;
    }
}
$proyectos = $activos;

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
        'responsable' => 'Administrador',
        'icono' => 'oi-people',
        'estado' => 'pendiente',
    ];
}

// Paso 3: proyectos sin modelo de calidad seleccionado
$idUsuario = (int)$usr->id;

$proySinModelo = (int)$cn->query("
    SELECT COUNT(*) AS c
    FROM proyecto p
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    WHERE up.id_usuario = $idUsuario
      AND p.id_modelo IS NULL
")->fetch_assoc()['c'];

// Paso 4: proyectos sin iteraciones
$sinIteraciones = (int)$cn->query("
    SELECT COUNT(*) AS c
    FROM proyecto p
    JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
    LEFT JOIN iteracion i ON i.id_proyecto = p.id_proyecto
    WHERE up.id_usuario = $idUsuario
      AND i.id_iteracion IS NULL
")->fetch_assoc()['c'];

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
$esAdminGlobal = ControlAcceso::esAdminGlobal();

foreach ($proyectos as $pr) {
    $idP = (int)$pr['id_proyecto'];
    $nombreP = $pr['nombre'];
    $rolProyecto = $esSuperAdmin ? 'SuperAdmin' : (getRolUsuarioEnProyecto($cn, (int)$usr->id, $idP) ?? '');
    $rolLower = mb_strtolower($rolProyecto, 'UTF-8');
    $esAdminProyecto = $esAdminGlobal || ($rolLower === 'administrador');
    $esGerenteOLider =  in_array($rolLower, ['gerente de calidad', 'líder de proyecto', 'lider de proyecto'], true);
    // Para planificación/ejecución de métricas: solo Gerente/Líder (excluye Admin y SuperAdmin)
    $esGerenteOLiderSolo = in_array($rolLower, ['gerente de calidad', 'líder de proyecto', 'lider de proyecto'], true);
    // Solo líder (sin gerente, sin superadmin) para acceso a edición de iteraciones
    $esLiderProyecto = in_array($rolLower, ['líder de proyecto', 'lider de proyecto'], true);

    // Total de pasos esperados del flujo:
    // Admin proyecto: 2 (usuarios) + 3 (modelo) + 4 (iteraciones) + 5 (planificar métricas) = 4
    // Otros roles (Gerente / Líder): 3 (modelo) + 4 (iteraciones) + 5 (planificar métricas) = 3
    $totalSteps = $esAdminProyecto ? 4 : 3;
    $completados = 0;
    $next = null;
    // Aseguramos que exista la variable $detalle aunque no haya detalle que mostrar
    $detalle = '';

    // Paso 2: usuarios asignados (debe evaluarse siempre, no solo para administradores del proyecto)
    $cantUsuarios = (int)$cn->query("SELECT COUNT(*) AS c FROM usuario_proyecto WHERE id_proyecto=$idP")->fetch_assoc()['c'];
    if ($cantUsuarios === 0) {
        $next = [
            'paso' => 2,
            'texto' => 'Sin usuarios asignados.',
            'accion' => 'Asigná usuarios al proyecto.',
            'responsable' => 'Administrador',
            'icono' => 'oi-people',
            'estado' => 'pendiente'
        ];
    } else {
        $completados++;
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
            $next = ['paso' => 4, 'texto' => 'Sin iteraciones creadas.', 'accion' => 'Definí las iteraciones del proyecto y planificá métricas.', 'responsable' => 'Líder de Proyecto', 'icono' => 'oi-loop-circular', 'estado' => 'pendiente'];
        } else {
            $completados++;
        }
    }

    // Paso 5: planificación de métricas (Sólo se evalúa la ITERACIÓN ACTUAL: debe tener TODAS sus métricas planificadas)
    if ($next === null) {
        // Total de métricas del proyecto = base del modelo + personalizadas asociadas al proyecto
        $totalMetricasBase = 0;
        if (!empty($pr['id_modelo'])) {
            $totalMetricasBase = (int)$cn->query(
                "SELECT COUNT(*) AS c FROM metrica_modelo_calidad WHERE id_modelo=" . (int)$pr['id_modelo']
            )->fetch_assoc()['c'];
        }
        $totalMetricasPers = (int)$cn->query(
            "SELECT COUNT(DISTINCT mpm.id_metrica) AS c
             FROM metrica_proyecto_modelo mpm
             JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
             WHERE pmc.id_proyecto = $idP"
        )->fetch_assoc()['c'];
        $totalMetricas = $totalMetricasBase + $totalMetricasPers;

        // Detectar iteración ACTUAL (hoy dentro del rango)
        $iterActualId = 0;
        $iterActualFase = '';
        $iterActualNumero = '';
        $sqlAct = "SELECT i.id_iteracion, i.numero_iteracion, f.nombre AS fase_nombre
                   FROM iteracion i
                   LEFT JOIN fase f ON f.id_fase = i.id_fase
                   WHERE i.id_proyecto = $idP AND CURDATE() BETWEEN i.fecha_inicio AND i.fecha_fin
                   ORDER BY i.id_fase ASC, i.numero_iteracion ASC LIMIT 1";
        if ($rsAct = $cn->query($sqlAct)) {
            if ($ra = $rsAct->fetch_assoc()) {
                $iterActualId = (int)$ra['id_iteracion'];
                $iterActualFase = (string)($ra['fase_nombre'] ?? '');
                $iterActualNumero = (string)($ra['numero_iteracion'] ?? '');
            }
        }

        // Cantidad efectivamente planificada sólo para la iteración actual
        $planificadasActual = 0;
        if ($iterActualId) {
            $planificadasActual = (int)$cn->query(
                "SELECT COUNT(*) AS c FROM metrica_iteracion WHERE id_iteracion=$iterActualId AND valor_planificado IS NOT NULL"
            )->fetch_assoc()['c'];
        }

        // Preparar detalle para tooltip: sólo la iteración ACTUAL
        $detalle = '';
        if ($iterActualId && $totalMetricas > 0 && $planificadasActual < $totalMetricas) {
            $faltan = $totalMetricas - $planificadasActual;
            $labelActual = trim(($iterActualFase !== '' ? ($iterActualFase . ' ') : '') . $iterActualNumero);
            $detalle = 'Faltan ' . $faltan . ' valor(es) planificado(s) en la iteración actual:<br><b>' . htmlspecialchars($labelActual, ENT_QUOTES, 'UTF-8') . '</b>';
        }

        if ($totalMetricas === 0) {
            $next = [
                'paso' => 5,
                'texto' => 'Sin métricas definidas en el modelo/proyecto.',
                'accion' => 'Asociá métricas al modelo o creá métricas personalizadas.',
                'responsable' => 'Gerente de Calidad o Líder de Proyecto',
                'icono' => 'oi-calendar',
                'estado' => 'pendiente'
            ];
        } elseif (!$iterActualId) {
            $next = [
                'paso' => 5,
                'texto' => 'No hay iteración actual en curso.',
                'accion' => 'Verificá fechas de iteraciones o creá una nueva iteración activa.',
                'responsable' => 'Líder de Proyecto',
                'icono' => 'oi-loop-circular',
                'estado' => 'pendiente'
            ];
        } elseif ($planificadasActual === 0) {
            $next = [
                'paso' => 5,
                'texto' => 'Sin métricas planificadas en la iteración actual.',
                'accion' => 'Asigná valores planificados a todas las métricas de la iteración actual.',
                'responsable' => 'Gerente de Calidad o Líder de Proyecto',
                'icono' => 'oi-calendar',
                'estado' => 'pendiente'
            ];
        } elseif ($planificadasActual < $totalMetricas) {
            $etiquetaIter = trim(($iterActualFase !== '' ? ($iterActualFase . ' ') : '') . $iterActualNumero);
            $next = [
                'paso' => 5,
                'texto' => 'Planificación parcial — Iteración actual: ' . htmlspecialchars($etiquetaIter, ENT_QUOTES, 'UTF-8') . '.',
                'accion' => 'Planificá las métricas restantes para completar esta iteración.',
                'responsable' => 'Gerente de Calidad o Líder de Proyecto',
                'icono' => 'oi-calendar',
                'estado' => 'pendiente'
            ];
        } else {
            // Todas las combinaciones métrica x iteración tienen valor planificado
            $completados++;
        }
    }


    if ($next === null) {
        $next = [
            'paso' => '✓',
            'texto' => 'Planificación finalizada.',
            'accion' => 'Inicia la fase de ejecución y seguimiento de métricas.',
            'responsable' => 'Todos los roles',
            'icono' => 'oi-check',
            'estado' => 'completo'
        ];
    }

    // Link de acción según paso y permisos
    $link = null;
    if ($next['estado'] === 'completo') {
        if (ControlAcceso::verificaPermiso(PermisosSistema::REGISTRO_METRICAS)) {
            $link = "registro_metricas.php?proyecto=$idP";
        } else {
            $link = "dashboard.php?proyecto=$idP";
        }
    } else {
        switch ($next['paso']) {
            case 2: // Usuarios asignados
                if (ControlAcceso::verificaPermiso(PermisosSistema::ABM_PROYECTOS)) {
                    $link = "usuarios.php";
                }
                break;
            case 3: // Modelo de calidad
                if ($esGerenteOLider) $link = "modelos.php";
                break;
            case 4: // Iteraciones (solo líder de proyecto)
                if ($esLiderProyecto) $link = "iteraciones.php";
                break;
            case 5:
                // Ir a la pantalla de métricas para planificar valores → solo Gerente/Líder
                if ($esGerenteOLiderSolo) $link = "metricas.php"; // llevar contexto de proyecto para facilitar planificación
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

        /* ===============================
   📋 Nombre de proyecto (tabla)
   =============================== */
        .table td.nombre-proyecto {
            max-width: 180px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
            font-weight: 500;
        }

        .table td.nombre-proyecto:hover {
            position: relative;
            white-space: normal;
            word-break: break-word;
            overflow: visible;
            z-index: 2;
            background: #f8f9fa;
            border-radius: .25rem;
            padding: .1rem .2rem;
        }

        .wizard-card .card-header .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            flex-wrap: nowrap;
            min-width: 0;
            /* ✅ NECESARIO para que text-overflow funcione dentro del flex */
        }


        /* ============================================
📌 Título del proyecto (con truncado y hover)
=============================================== */
        .wizard-card .project-title {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex: 1 1 0%;
            flex-shrink: 1;
            /* fuerza al título a respetar su límite */
            min-width: 0;
            /* permite truncado dentro de flex */
            max-width: 210px;
            /* define límite visible */
            font-weight: 600;
            color: #007bff;
            font-size: 0.9rem;
            line-height: 1.3;
        }

        /* Texto del título con ellipsis en una sola línea */
        .wizard-card .project-title-text {
            flex: 1 1 auto;
            min-width: 0;
            /* imprescindible para ellipsis en flex */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            /* … */
        }


        .wizard-card .project-title .oi {
            flex-shrink: 0;
            margin-right: .4rem;
            color: #17a2b8;
        }

        /* Hover: mostrar todo el texto (expande sobre el card) */
        .wizard-card .project-title:hover .project-title-text {
            position: relative;
            white-space: normal;
            word-break: break-word;
            overflow: visible;
            z-index: 5;
            background: rgba(248, 249, 250, 0.95);
            border-radius: .25rem;
            padding: .15rem .3rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }


        /* Porcentaje completado */
        .wizard-card .progress-label {
            flex-shrink: 0;
            white-space: nowrap;
            color: #6c757d;
            font-size: .8rem;
            margin-left: auto;
        }

        /* 🧭 Scroll interno para wizard si hay muchos proyectos */
        #wizardFlujo {
            max-height: calc(100vh - 180px);
            /* deja espacio para el header y footer */
            overflow-y: auto;
            padding-right: 4px;
        }

        #wizardFlujo::-webkit-scrollbar {
            width: 6px;
        }

        #wizardFlujo::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }

        #wizardFlujo::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.35);
        }

        .card.archivados {
            background: #f8f9fa;
            /* gris muy suave */
            border-left: 4px solid #adb5bd;
            /* gris medio */
            opacity: 0.95;
            transition: all 0.2s ease-in-out;
        }

        .card.archivados:hover {
            opacity: 1;
            border-left-color: #17a2b8;
            /* azul info al hover */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .card.archivados .card-header {
            background: #e9ecef;
            /* un gris más claro que los activos */
            color: #495057;
            font-weight: 600;
        }

        /* Permitir hover visual, aunque siga sin ser clickeable */
        .card.archivados .btn.disabled,
        .card.archivados .btn:disabled {
            pointer-events: auto !important;
            /* Permite hover visual */
            opacity: 0.8;
        }

        /* Colores hover coherentes */
        .card.archivados .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: #fff;
            border-color: #6c757d;
        }

        .card.archivados .btn-outline-warning:hover {
            background-color: #ffc107;
            color: #212529;
            border-color: #ffc107;
        }

        .card.archivados .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #fff;
            border-color: #dc3545;
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

        @media (max-width: 480px) {
            .wizard-card .card-header .header-row {
                flex-wrap: wrap;
            }

            .wizard-card .project-title {
                width: 100%;
            }

            .wizard-card .project-title-toggle,
            .wizard-card .progress-label {
                margin-top: .25rem;
            }
        }

        /* ===============================
   🔒 Hover visual para botones deshabilitados
   =============================== */
        .btn.disabled,
        .btn:disabled {
            pointer-events: auto !important;
            /* Permite hover visual */
            opacity: 0.8;
            transition: all 0.2s ease-in-out;
        }

        /* Colores hover coherentes con sus variantes */
        .btn-outline-warning.disabled:hover,
        .btn-outline-warning:disabled:hover {
            background-color: #ffc107;
            color: #212529;
            border-color: #ffc107;
        }

        .btn-outline-danger.disabled:hover,
        .btn-outline-danger:disabled:hover {
            background-color: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }

        .btn-outline-secondary.disabled:hover,
        .btn-outline-secondary:disabled:hover {
            background-color: #6c757d;
            color: #fff;
            border-color: #6c757d;
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
                            <div class="card shadow-sm border-0 mb-3 wizard-card" data-id-proyecto="<?= (int)$idP ?>">
                                <div class="card-header bg-white border-bottom-0 py-3">
                                    <div class="header-row">
                                        <div class="project-title" title="<?= htmlspecialchars($wiz['proyecto']); ?>">
                                            <span class="oi oi-list-rich"></span>
                                            <span class="project-title-text"><?= htmlspecialchars($wiz['proyecto']); ?></span>
                                        </div>
                                        <span class="progress-label"><?= (int)$wiz['progreso']; ?>% completado</span>
                                    </div>


                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: <?= (int)$wiz['progreso']; ?>%;" aria-valuenow="<?= (int)$wiz['progreso']; ?>"
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <!-- Texto explicativo sutil -->
                                    <div class="small text-muted mt-2">
                                        Muestra el grado de avance en la planificación y configuración del modelo de métricas del proyecto.
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
                                No tenés proyectos asignados ni rol.
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
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm" disabled data-toggle="tooltip" title="Requiere rol: Administrador" aria-label="Crear proyecto bloqueado">
                                <span class="oi oi-lock-locked"></span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">

                        <?php if (empty($proyectos)): ?>
                            <?php if ($tieneAbmProyectos && $existenProyectos === 0): ?>
                                <div class="card my-4 text-center" style="border:1px dashed #ffc107; background:rgba(255,193,7,0.07);">
                                    <div class="card-body p-4">
                                        <i class="oi oi-plus mb-2" style="font-size:2rem; color:#ffc107;"></i>
                                        <h5 class="text-warning font-weight-bold mb-2">Todavía no existen proyectos</h5>
                                        <p class="text-muted mb-3">Crea un nuevo proyecto para comenzar a gestionar métricas de calidad.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card my-4 text-center"
                                    style="border:1px dashed rgba(23,162,184,0.15); background:rgba(23,162,184,0.03);">
                                    <div class="card-body p-4">
                                        <i class="oi oi-info mb-2" style="font-size:2rem; color:#17a2b8;"></i>
                                        <h5 class="text-info font-weight-bold mb-2">No tenés proyectos asignados ni rol</h5>
                                        <p class="text-muted mb-3">Aún no fuiste asignado a ningún proyecto. Si creés que esto es un error, contactá a un administrador.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
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
                                        <td class="nombre-proyecto" title="<?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?= htmlspecialchars($Proyec['anio'], ENT_QUOTES, 'UTF-8'); ?></td>
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
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-icon" disabled data-toggle="tooltip" title="Requiere rol: Gerente de Calidad o Líder de Proyecto" aria-label="Dashboard de Calidad bloqueado">
                                                    <span class="oi oi-lock-locked" aria-hidden="true"></span>
                                                </button>
                                            <?php endif; ?>

                                            <!-- Modificar / Eliminar: SuperAdmin o Administrador del proyecto -->
                                            <?php if (ControlAcceso::verificaPermiso(PermisosSistema::ABM_PROYECTOS)): ?>
                                                <a title="Modificar" href="proyecto.modificar.php?id=<?= (int)$Proyec['id_proyecto']; ?>"
                                                    class="btn btn-outline-warning" role="button" aria-label="Modificar proyecto <?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span class="oi oi-pencil" aria-hidden="true"></span>
                                                </a>
                                                <button title="Eliminar" class="btn btn-outline-danger btn-eliminar"
                                                    data-id="<?= (int)$Proyec['id_proyecto']; ?>"
                                                    data-nombre="<?= htmlspecialchars($Proyec['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span class="oi oi-trash"></span>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-warning btn-icon disabled" data-toggle="tooltip" title="Solo Administrador puede modificar" aria-label="Modificar bloqueado">
                                                    <span class="oi oi-lock-locked" aria-hidden="true"></span>
                                                </button>
                                                <button class="btn btn-outline-danger btn-icon disabled" data-toggle="tooltip" title="Solo Administrador puede eliminar" aria-label="Eliminar bloqueado">
                                                    <span class="oi oi-lock-locked" aria-hidden="true"></span>
                                                </button>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>

                    </div> <!-- card-body proyectos -->
                </div> <!-- card-body proyectos -->
            </div> <!-- card proyectos -->

            <!-- 🗃️ NUEVO CARD: Proyectos finalizados/cancelados -->
            <?php if ($tieneAbmProyectos && !empty($archivados)): ?>
                <div class="card shadow-sm border-0 mt-4 archivados">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="mb-0 text-secondary">
                            <span class="oi oi-archive mr-1"></span>
                            Proyectos Finalizados/Cancelados
                        </h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-info">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Año</th>
                                    <th>Estado</th>
                                    <th>Rol</th>
                                    <th>Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archivados as $p): ?>
                                    <tr>
                                        <td class="nombre-proyecto"><?= htmlspecialchars($p['nombre']); ?></td>
                                        <td><?= htmlspecialchars($p['anio']); ?></td>
                                        <td><?= htmlspecialchars($p['estado']); ?></td>
                                        <td>
                                            <?php
                                            $rolProyectoArchivado = $esSuperAdmin
                                                ? 'SuperAdmin'
                                                : (getRolUsuarioEnProyecto($cn, (int)$usr->id, (int)$p['id_proyecto']) ?? '—');
                                            ?>
                                            <span class="badge badge-secondary"><?= htmlspecialchars($rolProyectoArchivado); ?></span>
                                        </td>
                                        <td>
                                            <!-- Tus botones de Ver / Dashboard / Bloqueados -->
                                            <a title="Ver" href="proyecto.ver.php?id=<?= (int)$p['id_proyecto']; ?>" class="btn btn-outline-primary btn-icon">
                                                <span class="oi oi-eye"></span>
                                            </a>
                                            <a title="Dashboard Inicial" href="dashboard.php?proyecto=<?= (int)$p['id_proyecto']; ?>" class="btn btn-outline-info btn-icon">
                                                <span class="oi oi-bar-chart"></span>
                                            </a>
                                            <button class="btn btn-outline-secondary btn-icon disabled" title="No disponible">
                                                <span class="oi oi-lock-locked"></span>
                                            </button>
                                            <button class="btn btn-outline-warning btn-icon disabled" title="No disponible">
                                                <span class="oi oi-lock-locked"></span>
                                            </button>
                                            <button class="btn btn-outline-danger btn-icon disabled" title="No disponible">
                                                <span class="oi oi-lock-locked"></span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <!-- 🗃️ FIN NUEVO CARD -->

        </div> <!-- card proyectos -->
    </div> <!-- col-lg-8 -->
    </div> <!-- row -->
    </div>
    <?php include_once '../gui/footer.php'; ?>

    <script>
        // Actualiza visualmente el wizard de un proyecto según el JSON recibido
        function actualizarWizardVisual(idProyecto, data) {
            var $card = $('.wizard-card[data-id-proyecto="' + idProyecto + '"]');
            if ($card.length === 0) return;
            // Actualizar porcentaje
            $card.find('.progress-bar').css('width', (parseInt(data.progreso) || 0) + '%').attr('aria-valuenow', (parseInt(data.progreso) || 0));
            $card.find('.progress-label').text((parseInt(data.progreso) || 0) + '% completado');
            // Actualizar icono y texto del paso
            var $step = $card.find('.wizard-step');
            var $contenido = $step.find('.contenido-paso');
            $step.removeClass('disabled');
            $step.find('.icono-paso').attr('class', 'oi ' + data.icono + ' text-info mr-3 mt-1 icono-paso');
            $contenido.find('.titulo-paso strong').html((data.paso === '✓' ? 'Completado' : ('Paso ' + data.paso)) + ' — ' + data.texto);
            $contenido.find('.descripcion-paso').text(data.accion);
            $contenido.find('.responsable-paso').text('👤 ' + data.responsable);
            // Detalle/tooltip
            $contenido.find('.wiz-info').remove();
            if (data.detalle && data.detalle.length > 0) {
                var $info = $('<span class="ml-2 text-secondary wiz-info" data-toggle="tooltip" data-html="true" title="' + data.detalle + '" aria-label="Más información"><span class="oi oi-info"></span></span>');
                $contenido.find('.titulo-paso').append($info);
                $info.tooltip();
            }
            // Link de acción
            if (data.link && data.link.length > 0 && data.estado !== 'completo') {
                // Si no es completo, el paso es clickable
                if (!$step.is('a')) {
                    // Reemplazar div por <a>
                    var $newStep = $('<a href="' + data.link + '" class="wizard-step d-flex align-items-start"></a>');
                    $newStep.append($contenido);
                    $step.replaceWith($newStep);
                } else {
                    $step.attr('href', data.link);
                }
            } else {
                // Si es completo o no hay link, el paso es div y disabled
                if (!$step.is('div')) {
                    var $newStep = $('<div class="wizard-step d-flex align-items-start disabled"></div>');
                    $newStep.append($contenido);
                    $step.replaceWith($newStep);
                } else {
                    $step.addClass('disabled');
                }
            }
        }

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
                            // Eliminar wizard visual del DOM
                            $('.wizard-card[data-id-proyecto="' + id + '"]').fadeOut(300, function() {
                                $(this).remove();
                            });
                            // Ya no llamar a wizard_estado.php
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