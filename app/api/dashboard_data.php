<?php
// Simple JSON API to provide dashboard data and a version hash for live updates
// Endpoint: /app/api/dashboard_data.php?proyecto=ID
// Returns: { data, actual, anterior, hayIteraciones, totalMetricas, totalIteraciones, version, serverTime }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Seguridad: requiere usuario autenticado con rol en el proyecto solicitado
require_once __DIR__ . '/../../lib/ControlAcceso.Class.php';
$proyectoId = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;
$usr = ControlAcceso::usuarioActual();
if (!$usr) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}
// Autorización consistente con requiereProyecto():
// 1) admin/superadmin globales pasan
// 2) quienes tengan permiso ABM_PROYECTOS pasan
// 3) de lo contrario, deben pertenecer al proyecto
if ($proyectoId <= 0) {
  http_response_code(403);
  echo json_encode(['error' => 'Acceso denegado al proyecto']);
  exit;
}

$esAdminGlobal = false;
if (isset($usr->roles) && is_array($usr->roles)) {
  foreach ($usr->roles as $r) {
    $rolName = mb_strtolower(trim($r->nombre ?? ''), 'UTF-8');
    if (in_array($rolName, ['administrador', 'superadmin'], true)) {
      $esAdminGlobal = true;
      break;
    }
  }
}

if (!$esAdminGlobal && !ControlAcceso::verificaPermiso(PermisosSistema::DASHBOARD)) {
  if (!ControlAcceso::usuarioPerteneceAProyecto($proyectoId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado al proyecto']);
    exit;
  }
}

$conexion = BDConexion::getConexion();

// Detectar compatibilidad: algunas BDs pueden no tener iteracion.id_proyecto
$hasIteracionProyecto = false;
try {
  $chk = $conexion->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iteracion' AND COLUMN_NAME = 'id_proyecto'");
  if ($chk && $row = $chk->fetch_assoc()) { $hasIteracionProyecto = ((int)$row['c'] > 0); }
} catch (Throwable $e) { $hasIteracionProyecto = false; }

// Proyecto existe?
$sqlProyecto = "SELECT nombre, estado FROM proyecto WHERE id_proyecto = $proyectoId";
$resProyecto = $conexion->query($sqlProyecto);
$proyectoExiste = ($resProyecto && $resProyecto->num_rows > 0);
$proyectoInfo = null;
if ($proyectoExiste) {
  $rowProyecto = $resProyecto->fetch_assoc();
  $proyectoInfo = [
    'nombre' => $rowProyecto['nombre'],
    'estado' => $rowProyecto['estado']
  ];
}

// Totales
if ($hasIteracionProyecto) {
  // Estricto: iteraciones del proyecto actual únicamente
  $sqlMetricas = "
    SELECT COUNT(DISTINCT mi.id_metrica) AS total
    FROM metrica_iteracion mi
    JOIN iteracion i ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = $proyectoId
    JOIN fase f ON f.id_fase = i.id_fase
    JOIN proyecto_fase pf ON pf.id_fase = f.id_fase AND pf.id_proyecto = $proyectoId
    WHERE pf.id_proyecto = $proyectoId";
  $resMetricas = $conexion->query($sqlMetricas);
  $totalMetricas = ($resMetricas && $resMetricas->num_rows > 0) ? (int)$resMetricas->fetch_assoc()['total'] : 0;

  $sqlIter = "
    SELECT COUNT(*) AS total
    FROM iteracion i
    JOIN fase f ON f.id_fase = i.id_fase
    JOIN proyecto_fase pf ON pf.id_fase = f.id_fase AND pf.id_proyecto = $proyectoId
    WHERE i.id_proyecto = $proyectoId";
  $resIter = $conexion->query($sqlIter);
  $totalIteraciones = ($resIter && $resIter->num_rows > 0) ? (int)$resIter->fetch_assoc()['total'] : 0;

  $sqlHayIter = "
    SELECT COUNT(*) AS total
    FROM iteracion i
    JOIN fase f ON f.id_fase = i.id_fase
    JOIN proyecto_fase pf ON pf.id_fase = f.id_fase AND pf.id_proyecto = $proyectoId
    WHERE i.id_proyecto = $proyectoId";
  $resHayIter = $conexion->query($sqlHayIter);
  $hayIteraciones = ($resHayIter && $resHayIter->num_rows > 0 && (int)$resHayIter->fetch_assoc()['total'] > 0);
} else {
  // Modo seguro: si no existe i.id_proyecto no podemos garantizar aislamiento → no retornamos datos
  $totalMetricas = 0;
  $totalIteraciones = 0;
  $hayIteraciones = false;
}

// Core data query (same as dashboard.php)
$result = false;
if ($hasIteracionProyecto) {
  $query = "
  SELECT 
    i.id_iteracion,
    i.numero_iteracion,
    f.nombre AS fase,
    i.fecha_inicio AS inicio,
    i.fecha_fin AS fin,
    m.id_metrica AS id_metrica,
    m.nombre AS metrica,
    mi.valor_planificado AS planificado,
    mi.valor_ejecutado AS ejecutado,
    mi.umbral_desviacion AS umbral
  FROM metrica_iteracion mi
  JOIN metrica m ON mi.id_metrica = m.id_metrica
  JOIN iteracion i ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = $proyectoId
  JOIN fase f ON i.id_fase = f.id_fase
  JOIN proyecto_fase pf ON pf.id_fase = f.id_fase AND pf.id_proyecto = $proyectoId
  WHERE pf.id_proyecto = $proyectoId
  ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;
  ";
  $result = $conexion->query($query);
} else {
  // Sin columna i.id_proyecto → no ejecutamos consulta para no mezclar datos entre proyectos
  $result = false;
}

$iterMap = [];
if ($result) {
  while ($r = $result->fetch_assoc()) {
    $key = trim($r['fase'] . ' ' . $r['numero_iteracion']);
    if (!isset($iterMap[$key])) {
      $iterMap[$key] = [
        'iteracion' => $key,
        'fase'      => $r['fase'],
        'numero'    => $r['numero_iteracion'],
        'inicio'    => $r['inicio'],
        'fin'       => $r['fin'],
        'metricas'   => []
      ];
    }

    $plan = (float)($r['planificado'] ?? 0);
    $ejec = (float)($r['ejecutado'] ?? 0);
    if ($plan == 0 && $ejec == 0) {
      $pct = 100; $nota = 'Se cumplió';
    } elseif ($plan == 0 && $ejec > 0) {
      $pct = 100; $nota = 'Se planificó 0 (' . $ejec . ')';
    } elseif ($ejec > $plan) {
      $pct = round(($ejec / $plan) * 100); $nota = 'Supera planificado (+' . ($ejec - $plan) . ')';
    } else {
      $pct = round(($ejec / $plan) * 100); $nota = '';
    }

    $iterMap[$key]['metricas'][] = [
      'id'           => (int)$r['id_metrica'],
      'nombre'       => $r['metrica'],
      'executed'     => $pct,
      'planned'      => $plan,
      'executedReal' => $ejec,
      'unit'         => 'u',
      'min'          => max(0, 100 - (float)$r['umbral']),
      'max'          => 100 + (float)$r['umbral'],
      'nota'         => $nota,
      'extra'        => ($plan > 0 ? max(0, $ejec - $plan) : $ejec)
    ];
  }
}
$DATA = array_values($iterMap);
if (!$hasIteracionProyecto) {
  $DATA = [];
}
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Determine current/previous iteration similar to dashboard.php
$hoy = date('Y-m-d');
$actualIter = null;
$anteriorIter = null;

if (count($DATA) > 0) {
  $actualIndex = null;

  foreach ($DATA as $idx => $it) {
    // ✅ Se suma un día al fin para incluir todo el día de cierre
    $finMas1 = date('Y-m-d', strtotime($it['fin'] . ' +1 day'));
    if ($it['inicio'] <= $hoy && $finMas1 > $hoy) {
      $actualIndex = $idx;
      break;
    }
  }

  if ($actualIndex !== null) {
    $actualIter = $DATA[$actualIndex];
  }

  $fechaReferencia = $actualIter ? $actualIter['inicio'] : $hoy;
  $anteriores = array_filter($DATA, fn($it) => $it['fin'] < $fechaReferencia);
  if (count($anteriores) > 0) {
    $anteriorIter = end($anteriores);
  }
}

// No cerramos la conexión del singleton; el request termina acá.

// Construye el payload y una versión (hash) para detectar cambios
$payload = [
  'data' => $DATA,
  'actual' => $actualIter,
  'anterior' => $anteriorIter,
  'hayIteraciones' => $hayIteraciones,
  'totalMetricas' => $totalMetricas,
  'totalIteraciones' => $totalIteraciones,
  'proyecto' => $proyectoInfo,
];
$version = md5(json_encode($payload));

echo json_encode([
  'data' => $DATA,
  'actual' => $actualIter,
  'anterior' => $anteriorIter,
  'hayIteraciones' => $hayIteraciones,
  'totalMetricas' => $totalMetricas,
  'totalIteraciones' => $totalIteraciones,
  'proyecto' => $proyectoInfo,
  'version' => $version,
  'serverTime' => date('c')
], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
exit;
?>