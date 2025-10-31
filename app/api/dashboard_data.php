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
if ($proyectoId <= 0 || !ControlAcceso::usuarioPerteneceAProyecto($proyectoId)) {
  http_response_code(403);
  echo json_encode(['error' => 'Acceso denegado al proyecto']);
  exit;
}

$conexion = BDConexion::getConexion();
if ($conexion->connect_error) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed']);
  exit;
}

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
$sqlMetricas = "SELECT COUNT(DISTINCT mi.id_metrica) AS total
                FROM metrica_iteracion mi
                JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
                JOIN fase f ON f.id_fase = i.id_fase
                JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
                WHERE pf.id_proyecto = $proyectoId";
$resMetricas = $conexion->query($sqlMetricas);
$totalMetricas = ($resMetricas && $resMetricas->num_rows > 0) ? (int)$resMetricas->fetch_assoc()['total'] : 0;

$sqlIter = "SELECT COUNT(*) AS total
            FROM iteracion i
            JOIN fase f ON f.id_fase = i.id_fase
            JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
            WHERE pf.id_proyecto = $proyectoId";
$resIter = $conexion->query($sqlIter);
$totalIteraciones = ($resIter && $resIter->num_rows > 0) ? (int)$resIter->fetch_assoc()['total'] : 0;

$sqlHayIter = "SELECT COUNT(*) AS total
               FROM iteracion i
               JOIN fase f ON f.id_fase = i.id_fase
               JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
               WHERE pf.id_proyecto = $proyectoId";
$resHayIter = $conexion->query($sqlHayIter);
$hayIteraciones = ($resHayIter && $resHayIter->num_rows > 0 && (int)$resHayIter->fetch_assoc()['total'] > 0);

// Core data query (same as dashboard.php)
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
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON i.id_fase = f.id_fase
JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
WHERE pf.id_proyecto = $proyectoId
ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;
";
$result = $conexion->query($query);

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

// Determine current/previous iteration similar to dashboard.php
$hoy = date('Y-m-d');
$actualIter = null;
$anteriorIter = null;
if (count($DATA) > 0) {
  $actualIndex = null;
  foreach ($DATA as $idx => $it) {
    if ($it['inicio'] <= $hoy && $it['fin'] >= $hoy) { $actualIndex = $idx; break; }
  }
  if ($actualIndex !== null) { $actualIter = $DATA[$actualIndex]; }
  $fechaReferencia = $actualIter ? $actualIter['inicio'] : $hoy;
  $anteriores = array_filter($DATA, fn($it) => $it['fin'] < $fechaReferencia);
  if (count($anteriores) > 0) { $anteriorIter = end($anteriores); }
}

$conexion->close();

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