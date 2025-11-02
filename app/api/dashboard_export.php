<?php
// Exporta a CSV los datos del dashboard, respetando filtros actuales.
// GET params: proyecto (req), fase (opt, nombre exacto), metricId (opt)

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../lib/ControlAcceso.Class.php';
require_once __DIR__ . '/../../modelo/BDConexion.Class.php';

$proyectoId = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;
$faseName   = isset($_GET['fase']) ? trim((string)$_GET['fase']) : '';
$metricId   = isset($_GET['metricId']) && $_GET['metricId'] !== '' ? (int)$_GET['metricId'] : null;
$format     = isset($_GET['format']) ? strtolower(trim((string)$_GET['format'])) : 'csv';
if ($format !== 'xls') { $format = 'csv'; }

$usr = ControlAcceso::usuarioActual();
if (!$usr) {
  http_response_code(401);
  echo "No autenticado"; exit;
}

if ($proyectoId <= 0) {
  http_response_code(400);
  echo "Proyecto inválido"; exit;
}

$esAdminGlobal = false;
if (isset($usr->roles) && is_array($usr->roles)) {
  foreach ($usr->roles as $r) {
    $rolName = mb_strtolower(trim($r->nombre ?? ''), 'UTF-8');
    if (in_array($rolName, ['administrador', 'superadmin'], true)) { $esAdminGlobal = true; break; }
  }
}

if (!$esAdminGlobal && !ControlAcceso::verificaPermiso(PermisosSistema::DASHBOARD)) {
  if (!ControlAcceso::usuarioPerteneceAProyecto($proyectoId)) {
    http_response_code(403);
    echo "Acceso denegado al proyecto"; exit;
  }
}

$conexion = BDConexion::getConexion();

// verificar si existe iteracion.id_proyecto para filtrar correctamente
$hasIteracionProyecto = false;
try {
  $chk = $conexion->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iteracion' AND COLUMN_NAME = 'id_proyecto'");
  if ($chk && $row = $chk->fetch_assoc()) { $hasIteracionProyecto = ((int)$row['c'] > 0); }
} catch (Throwable $e) { $hasIteracionProyecto = false; }

if (!$hasIteracionProyecto) {
  // Por seguridad no exportamos si no podemos aislar por proyecto
  http_response_code(409);
  echo "Exportación no disponible por incompatibilidad de esquema"; exit;
}

// Construcción dinámica del WHERE
$where = [];
$where[] = 'pf.id_proyecto = ?';
$where[] = 'i.id_proyecto = ?';
$params = [$proyectoId, $proyectoId];
$types  = 'ii';

if ($faseName !== '') {
  $where[] = 'f.nombre = ?';
  $params[] = $faseName; $types .= 's';
}
if ($metricId !== null) {
  $where[] = 'm.id_metrica = ?';
  $params[] = $metricId; $types .= 'i';
}

$sql = "
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
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = ?
JOIN fase f ON i.id_fase = f.id_fase
JOIN proyecto_fase pf ON pf.id_fase = f.id_fase AND pf.id_proyecto = ?
WHERE ".implode(' AND ', $where)."
ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica";

// Como ya incluimos i.id_proyecto y pf.id_proyecto en $where (los dos primeros), 
// duplicamos en JOIN para optimizar y en WHERE para seguridad, manteniendo orden de parámetros.

$stmt = $conexion->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo "Error preparando exportación"; exit;
}

// Los dos primeros placeholders corresponden a los del JOIN, luego los del WHERE
$bindTypes = $types . '';
$bindParams = $params;

// Para el JOIN ya tenemos 2 ints más al principio
array_unshift($bindParams, $proyectoId, $proyectoId);
$bindTypes = 'ii' . $bindTypes;

$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$res = $stmt->get_result();

// El nombre base del archivo se definirá luego de obtener el nombre del proyecto


// obtener nombre del proyecto para imprimirlo arriba
$proyNombre = '';
try {
  $q = $conexion->query("SELECT nombre FROM proyecto WHERE id_proyecto = ".(int)$proyectoId);
  if ($q && $r = $q->fetch_assoc()) { $proyNombre = (string)$r['nombre']; }
} catch (Throwable $e) { /* noop */ }

// Se define el nombre base del archivo (nombre de proyecto + filtros + fecha dd_mm_aaaa; la extensión se agrega según formato)
$fechaStr = date('d_m_Y');
$proySafe = $proyNombre !== '' ? preg_replace('/[^a-zA-Z0-9_-]+/', '_', $proyNombre) : ('proyecto_'.$proyectoId);
$baseFilename = $proySafe;
if ($faseName !== '') {
  $baseFilename .= '_fase_'.preg_replace('/[^a-zA-Z0-9_-]+/', '_', $faseName);
}
if ($metricId !== null) {
  $baseFilename .= '_metrica_'.(int)$metricId;
}
$baseFilename .= '_'.$fechaStr;

// Preparar datos (encabezado y filtros se escribirán según formato más abajo)

// Normaliza distintos formatos de fecha a YYYY-MM-DD para el CSV
function __normalize_csv_date($v) {
  if ($v === null || $v === '') return '';
  // Si viene como timestamp (segundos) razonable
  if (is_numeric($v)) {
    $n = (int)$v;
    if ($n > 946684800 && $n < 4102444800) { // entre 2000-01-01 y 2100-01-01
      return date('Y-m-d', $n);
    }
    // Si viene como YYYYMMDD (8 dígitos)
    $s = (string)$v;
    if (strlen($s) === 8) {
      return substr($s,0,4).'-'.substr($s,4,2).'-'.substr($s,6,2);
    }
  }
  // Si viene como 'YYYY-MM-DD HH:MM:SS' o 'YYYY-MM-DD'
  if (is_string($v)) {
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) {
      return $m[1];
    }
  }
  // fallback: devolver como string
  return (string)$v;
}

// Formatea valores como porcentaje con símbolo % (0 o 2 decimales según corresponda)
function __format_percent_points($v) {
  if ($v === null || $v === '') return '';
  $num = (float)$v;
  if (abs($num - round($num)) < 0.0000001) {
    return (string)(int)round($num) . '%';
  }
  return number_format($num, 2, '.', '') . '%';
}

// Construye filas en memoria para exportar luego
$rows = [];
while ($row = $res->fetch_assoc()) {
  $plan = (float)($row['planificado'] ?? 0);
  $ejec = (float)($row['ejecutado'] ?? 0);
  if ($plan == $ejec) {
    // Cumplimiento exacto, incluye el caso 0/0
    $pct = 100; $nota = 'Se cumplió';
  } elseif ($plan == 0 && $ejec > 0) {
    // Caso especial: no se planificó y hubo ejecución
    $pct = 100; $nota = 'Se planificó 0 ('.$ejec.')';
  } elseif ($ejec > $plan) {
    // Superó lo planificado
    $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 100;
    $nota = 'Supera planificado (+'.($ejec-$plan).')';
  } elseif ($plan > $ejec) {
    // Falta para llegar a lo planificado (incluye ejecutado = 0)
    $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 0;
    $nota = 'Falta (-'.($plan - $ejec).')';
  } else {
    // Fallback
    $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 0; $nota = '';
  }

  $rows[] = [
    'iter'    => $row['fase'].' '.$row['numero_iteracion'],
    'inicio'  => __normalize_csv_date($row['inicio']),
    'fin'     => __normalize_csv_date($row['fin']),
    'metrica' => $row['metrica'],
    'plan'    => $row['planificado'],
    'ejec'    => $row['ejecutado'],
    'umbral'  => $row['umbral'],
    'pct'     => $pct,
    'nota'    => $nota,
  ];
}
// Exportación según formato
if ($format === 'xls') {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$baseFilename.'.xls"');
  echo "<html><head><meta charset=\"utf-8\"><style>";
  echo "table{border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px;font-family:Segoe UI,Arial,sans-serif;font-size:12px;}";
  echo ".w-iter{width:220px;} .w-date{width:120px;} .w-metrica{width:300px;} .w-num{width:120px;text-align:right;} .w-pct{width:140px;text-align:right;} .w-nota{width:300px;}";
  echo "</style></head><body>";
  $faseLabel = ($faseName !== '') ? htmlspecialchars($faseName, ENT_QUOTES, 'UTF-8') : 'Todas';
  $metricaLabel = ($metricId !== null) ? ('#'.(int)$metricId) : 'Todas';
  echo '<div style="margin-bottom:8px"><b>Proyecto:</b> '.htmlspecialchars($proyNombre, ENT_QUOTES, 'UTF-8').'</div>';
  echo '<div style="margin-bottom:12px"><b>Filtros:</b> Fase='.$faseLabel.'; Métrica='.$metricaLabel.'</div>';
  echo '<table><thead><tr>';
  echo '<th class="w-iter">Iteración</th><th class="w-date">Inicio</th><th class="w-date">Fin</th><th class="w-metrica">Métrica</th><th class="w-num">Planificado</th><th class="w-num">Ejecutado</th><th class="w-pct">Umbral (%)</th><th class="w-pct">Cumplimiento (%)</th><th class="w-nota">Nota</th>';
  echo '</tr></thead><tbody>';
  foreach ($rows as $r) {
    $umbralPct = is_numeric($r['umbral']) ? (float)$r['umbral'] : 0;
    $umbralStr = (abs($umbralPct - round($umbralPct)) < 1e-7) ? (string)(int)round($umbralPct).'%' : number_format($umbralPct, 2, '.', '').'%';
    $pctStr    = (abs($r['pct'] - round($r['pct'])) < 1e-7) ? (string)(int)round($r['pct']).'%' : number_format($r['pct'], 2, '.', '').'%';
    echo '<tr>'
      .'<td>'.htmlspecialchars($r['iter'], ENT_QUOTES, 'UTF-8').'</td>'
      .'<td>'.htmlspecialchars($r['inicio'], ENT_QUOTES, 'UTF-8').'</td>'
      .'<td>'.htmlspecialchars($r['fin'], ENT_QUOTES, 'UTF-8').'</td>'
      .'<td>'.htmlspecialchars($r['metrica'], ENT_QUOTES, 'UTF-8').'</td>'
      .'<td style="text-align:right">'.htmlspecialchars((string)$r['plan'], ENT_QUOTES, 'UTF-8').'</td>'
      .'<td style="text-align:right">'.htmlspecialchars((string)$r['ejec'], ENT_QUOTES, 'UTF-8').'</td>'
      .'<td style="text-align:right">'.$umbralStr.'</td>'
      .'<td style="text-align:right">'.$pctStr.'</td>'
      .'<td>'.htmlspecialchars($r['nota'], ENT_QUOTES, 'UTF-8').'</td>'
      .'</tr>';
  }
  echo '</tbody></table></body></html>';
  exit;
}

// CSV por defecto
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$baseFilename.'.csv"');
$out = fopen('php://output', 'w');
// BOM para Excel/UTF-8
fwrite($out, "\xEF\xBB\xBF");

// encabezado superior con proyecto y filtros aplicados
$faseLabel = ($faseName !== '') ? $faseName : 'Todas';
$metricaLabel = ($metricId !== null) ? ('#'.$metricId) : 'Todas';
fputcsv($out, ['Proyecto:', $proyNombre]);
fputcsv($out, ['Filtros:', 'Fase='.$faseLabel, 'Métrica='.$metricaLabel]);
fputcsv($out, []); // línea en blanco

// encabezados de tabla
fputcsv($out, [
  'Iteración','Inicio','Fin','Métrica','Planificado','Ejecutado','Umbral (%)','Cumplimiento (%)','Nota'
]);

foreach ($rows as $r) {
  fputcsv($out, [
    $r['iter'],
    $r['inicio'],
    $r['fin'],
    $r['metrica'],
    $r['plan'],
    $r['ejec'],
    __format_percent_points($r['umbral']),
    __format_percent_points($r['pct']),
    $r['nota']
  ]);
}

fclose($out);
exit;
