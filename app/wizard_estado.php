<?php
// Endpoint para obtener el estado actual del wizard de flujo de un proyecto
header('Content-Type: application/json; charset=utf-8');
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

$idProyecto = isset($_REQUEST['id_proyecto']) ? (int)$_REQUEST['id_proyecto'] : 0;
if ($idProyecto <= 0) {
    echo json_encode(['error' => 'ID de proyecto inválido']);
    exit;
}

$usr = ControlAcceso::usuarioActual();
$cn = BDConexion::getInstancia();

// Helper: obtiene el nombre del rol del usuario en un proyecto específico
function getRolUsuarioEnProyecto(mysqli $cn, int $idUsuario, int $idProyecto): ?string {
    $sql = "SELECT r.nombre AS rol_nombre FROM usuario_proyecto up JOIN rol r ON r.id = up.id_rol WHERE up.id_usuario = ? AND up.id_proyecto = ? LIMIT 1";
    if (!$stmt = $cn->prepare($sql)) return null;
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
$idUsuario = (int)$usr->id;

// Obtener datos del proyecto
$pr = $cn->query("SELECT * FROM proyecto WHERE id_proyecto = $idProyecto")->fetch_assoc();
if (!$pr) {
    echo json_encode(['error' => 'Proyecto no encontrado']);
    exit;
}
$nombreP = $pr['nombre'];
$rolProyecto = $esSuperAdmin ? 'SuperAdmin' : (getRolUsuarioEnProyecto($cn, $idUsuario, $idProyecto) ?? '');
$rolLower = mb_strtolower($rolProyecto, 'UTF-8');
$esAdminProyecto =  ($rolLower === 'administrador');
$esGerenteOLider =  in_array($rolLower, ['gerente de calidad', 'líder de proyecto', 'lider de proyecto'], true);
$esGerenteOLiderSolo = in_array($rolLower, ['gerente de calidad', 'líder de proyecto', 'lider de proyecto'], true);
$esLiderProyecto = in_array($rolLower, ['líder de proyecto', 'lider de proyecto'], true);
$totalSteps = $esAdminProyecto ? 4 : 3;
$completados = 0;
$next = null;
$detalle = '';

// Paso 2: usuarios asignados (debe evaluarse siempre, no solo para administradores del proyecto)
$cantUsuarios = (int)$cn->query("SELECT COUNT(*) AS c FROM usuario_proyecto WHERE id_proyecto=$idP")->fetch_assoc()['c'];
if ($cantUsuarios === 0) {
    $next = [
        'paso' => 2,
        'texto' => 'Sin usuarios asignados.',
        'accion' => 'Asigná usuarios al proyecto.',
        'responsable' => 'Administrador o SuperAdmin',
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
    $cantIter = (int)$cn->query("SELECT COUNT(*) AS c FROM iteracion WHERE id_proyecto=$idProyecto")->fetch_assoc()['c'];
    if ($cantIter === 0) {
        $next = ['paso' => 4, 'texto' => 'Sin iteraciones creadas.', 'accion' => 'Definí las iteraciones del proyecto y planificá métricas.', 'responsable' => 'Líder de Proyecto', 'icono' => 'oi-loop-circular', 'estado' => 'pendiente'];
    } else {
        $completados++;
    }
}
// Paso 5: planificación de métricas (solo iteración actual)
if ($next === null) {
    $totalMetricasBase = 0;
    if (!empty($pr['id_modelo'])) {
        $totalMetricasBase = (int)$cn->query("SELECT COUNT(*) AS c FROM metrica_modelo_calidad WHERE id_modelo=" . (int)$pr['id_modelo'])->fetch_assoc()['c'];
    }
    $totalMetricasPers = (int)$cn->query("SELECT COUNT(DISTINCT mpm.id_metrica) AS c FROM metrica_proyecto_modelo mpm JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo WHERE pmc.id_proyecto = $idProyecto")->fetch_assoc()['c'];
    $totalMetricas = $totalMetricasBase + $totalMetricasPers;
    $iterActualId = 0; $iterActualFase = ''; $iterActualNumero = '';
    $sqlAct = "SELECT i.id_iteracion, i.numero_iteracion, f.nombre AS fase_nombre FROM iteracion i LEFT JOIN fase f ON f.id_fase = i.id_fase WHERE i.id_proyecto = $idProyecto AND CURDATE() BETWEEN i.fecha_inicio AND i.fecha_fin ORDER BY i.id_fase ASC, i.numero_iteracion ASC LIMIT 1";
    if ($rsAct = $cn->query($sqlAct)) {
        if ($ra = $rsAct->fetch_assoc()) {
            $iterActualId = (int)$ra['id_iteracion'];
            $iterActualFase = (string)($ra['fase_nombre'] ?? '');
            $iterActualNumero = (string)($ra['numero_iteracion'] ?? '');
        }
    }
    $planificadasActual = 0;
    if ($iterActualId) {
        $planificadasActual = (int)$cn->query("SELECT COUNT(*) AS c FROM metrica_iteracion WHERE id_iteracion=$iterActualId AND valor_planificado IS NOT NULL")->fetch_assoc()['c'];
    }
    $detalle = '';
    if ($iterActualId && $totalMetricas > 0 && $planificadasActual < $totalMetricas) {
        $faltan = $totalMetricas - $planificadasActual;
        $labelActual = trim(($iterActualFase !== '' ? ($iterActualFase . ' ') : '') . $iterActualNumero);
        $detalle = 'Faltan ' . $faltan . ' valor(es) planificado(s) en la iteración actual:<br><b>' . htmlspecialchars($labelActual, ENT_QUOTES, 'UTF-8') . '</b>';
    }
    if ($totalMetricas === 0) {
        $next = ['paso' => 5, 'texto' => 'Sin métricas definidas en el modelo/proyecto.', 'accion' => 'Asociá métricas al modelo o creá métricas personalizadas.', 'responsable' => 'Gerente de Calidad o Líder de Proyecto', 'icono' => 'oi-calendar', 'estado' => 'pendiente'];
    } elseif (!$iterActualId) {
        $next = ['paso' => 5, 'texto' => 'No hay iteración actual en curso.', 'accion' => 'Verificá fechas de iteraciones o creá una nueva iteración activa.', 'responsable' => 'Líder de Proyecto', 'icono' => 'oi-loop-circular', 'estado' => 'pendiente'];
    } elseif ($planificadasActual === 0) {
        $next = ['paso' => 5, 'texto' => 'Sin métricas planificadas en la iteración actual.', 'accion' => 'Asigná valores planificados a todas las métricas de la iteración actual.', 'responsable' => 'Gerente de Calidad o Líder de Proyecto', 'icono' => 'oi-calendar', 'estado' => 'pendiente'];
    } elseif ($planificadasActual < $totalMetricas) {
        $etiquetaIter = trim(($iterActualFase !== '' ? ($iterActualFase . ' ') : '') . $iterActualNumero);
        $next = ['paso' => 5, 'texto' => 'Planificación parcial — Iteración actual: ' . htmlspecialchars($etiquetaIter, ENT_QUOTES, 'UTF-8') . '.', 'accion' => 'Planificá las métricas restantes para completar esta iteración.', 'responsable' => 'Gerente de Calidad o Líder de Proyecto', 'icono' => 'oi-calendar', 'estado' => 'pendiente'];
    } else {
        $completados++;
    }
}
if ($next === null) {
    $next = ['paso' => '✓', 'texto' => 'Planificación finalizada.', 'accion' => 'Inicia la fase de ejecución y seguimiento de métricas.', 'responsable' => 'Todos los roles', 'icono' => 'oi-check', 'estado' => 'completo'];
}
$link = null;
if ($next['estado'] === 'completo') {
    if (ControlAcceso::verificaPermiso(PermisosSistema::REGISTRO_METRICAS)) {
        $link = "registro_metricas.php?proyecto=$idProyecto";
    } else {
        $link = "dashboard.php?proyecto=$idProyecto";
    }
} else {
    switch ($next['paso']) {
        case 2:
            if ($esAdminProyecto) $link = "usuarios.php";
            break;
        case 3:
            if ($esGerenteOLider) $link = "modelos.php";
            break;
        case 4:
            if ($esLiderProyecto) $link = "iteraciones.php";
            break;
        case 5:
            if ($esGerenteOLiderSolo) $link = "metricas.php";
            break;
    }
}
$progreso = max(0, min(100, round(($completados / $totalSteps) * 100)));

// Respuesta JSON
$respuesta = [
    'paso' => $next['paso'],
    'texto' => $next['texto'],
    'accion' => $next['accion'],
    'responsable' => $next['responsable'],
    'icono' => $next['icono'],
    'estado' => $next['estado'],
    'progreso' => $progreso,
    'detalle' => $detalle,
    'link' => $link
];
echo json_encode($respuesta);
exit;
