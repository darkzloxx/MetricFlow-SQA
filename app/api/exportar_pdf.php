<?php
// PDF tabular con la misma información que dashboard_export.php (sin gráficos)
// GET: proyecto (req), fase (opt nombre exacto), metricId (opt)

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../lib/ControlAcceso.Class.php';
require_once __DIR__ . '/../../modelo/BDConexion.Class.php';

// FPDF
$fpdfPath = __DIR__ . '/../../lib/fpdf186/fpdf.php';
if (!file_exists($fpdfPath)) {
    http_response_code(500);
    echo 'FPDF no encontrado en lib/fpdf186/fpdf.php';
    exit;
}
require_once $fpdfPath;

class PDF extends FPDF {
    function Header() {
        // Logos si existen
        $logoLeft = __DIR__ . '/../../lib/img/Logo-UNPA-UARG-azul.png';
        if (file_exists($logoLeft)) {
            $this->Image($logoLeft, 12, 10, 25);
        }
        // Título
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('INFORME DE RESULTADOS DE MÉTRICAS DE CALIDAD'), 0, 1, 'C');
        $this->Ln(2);
        // Línea decorativa
        $this->SetDrawColor(13, 110, 253);
        $this->SetLineWidth(0.7);
        $this->Line(12, 28, 285, 28);
        $this->Ln(8);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, utf8_decode('Generado automáticamente por MetricFlow-SQA | ') . date('d/m/Y') . '  |  Página ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function seccionProyecto($nombre, $siglas, $fase, $fecha, $kpis = []) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(0, 8, utf8_decode('DATOS GENERALES DEL PROYECTO'), 1, 1, 'C', true);
        $this->SetFont('Arial', '', 9);

        $this->Cell(60, 7, utf8_decode('Nombre del Proyecto:'), 1);
        $this->Cell(90, 7, utf8_decode($nombre), 1);
        $this->Cell(50, 7, utf8_decode('Siglas del Proyecto:'), 1);
        $this->Cell(75, 7, utf8_decode($siglas), 1, 1);

        $this->Cell(60, 7, utf8_decode('Fase (filtro):'), 1);
        $this->Cell(90, 7, utf8_decode($fase), 1);
        $this->Cell(50, 7, utf8_decode('Fecha del Informe:'), 1);
        $this->Cell(75, 7, utf8_decode($fecha), 1, 1);

        // KPIs
        if (!empty($kpis)) {
            $this->Ln(3);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(0, 7, utf8_decode('KPI del Proyecto'), 0, 1, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell(75, 7, utf8_decode('Métricas planificadas:'), 1);
            $this->Cell(20, 7, (string)($kpis['metricas_planificadas'] ?? 0), 1, 0, 'C');
            $this->Cell(75, 7, utf8_decode('Tipos de métricas distintas:'), 1);
            $this->Cell(20, 7, (string)($kpis['metricas_distintas'] ?? 0), 1, 0, 'C');
            $this->Cell(75, 7, utf8_decode('Iteraciones con métricas:'), 1);
            $this->Cell(20, 7, (string)($kpis['iteraciones_con_metricas'] ?? 0), 1, 1, 'C');
        }

        $this->Ln(6);
    }

    function tablaResultados($header, $data) {
        // Encabezados
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(13, 110, 253);
        $this->SetTextColor(255);
        $w = [40, 22, 22, 68, 22, 22, 22, 28, 55];
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 8, utf8_decode($header[$i]), 1, 0, 'C', true);
        }
        $this->Ln();

        // Contenido
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(0);
        foreach ($data as $row) {
            $this->Cell($w[0], 7, utf8_decode($row['iteracion']), 1);
            $this->Cell($w[1], 7, $row['inicio'], 1, 0, 'C');
            $this->Cell($w[2], 7, $row['fin'], 1, 0, 'C');
            $this->Cell($w[3], 7, utf8_decode($row['metrica']), 1);
            $this->Cell($w[4], 7, $row['planificado'], 1, 0, 'C');
            $this->Cell($w[5], 7, $row['ejecutado'], 1, 0, 'C');
            $this->Cell($w[6], 7, $row['umbral'], 1, 0, 'C');
            $this->Cell($w[7], 7, $row['cumplimiento'], 1, 0, 'C');
            $this->Cell($w[8], 7, utf8_decode($row['nota']), 1, 1);
        }
    }
}

// Autenticación y permisos
$usr = ControlAcceso::usuarioActual();
if (!$usr) { http_response_code(401); echo 'No autenticado'; exit; }

$proyectoId = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;
$faseName   = isset($_GET['fase']) ? trim((string)$_GET['fase']) : '';
$metricId   = isset($_GET['metricId']) && $_GET['metricId'] !== '' ? (int)$_GET['metricId'] : null;

if ($proyectoId <= 0) { http_response_code(400); echo 'Proyecto inválido'; exit; }

$esAdminGlobal = false;
if (isset($usr->roles) && is_array($usr->roles)) {
    foreach ($usr->roles as $r) {
        $rolName = mb_strtolower(trim($r->nombre ?? ''), 'UTF-8');
        if (in_array($rolName, ['administrador', 'superadmin'], true)) { $esAdminGlobal = true; break; }
    }
}
if (!$esAdminGlobal && !ControlAcceso::usuarioPerteneceAProyecto($proyectoId)) { http_response_code(403); echo 'Acceso denegado al proyecto'; exit; }

$cn = BDConexion::getConexion();

// Nombre del proyecto
$proyNombre = '';
try {
    $q = $cn->query("SELECT nombre FROM proyecto WHERE id_proyecto = ".(int)$proyectoId);
    if ($q && $r = $q->fetch_assoc()) { $proyNombre = (string)$r['nombre']; }
} catch (Throwable $e) {}

// KPI del proyecto
$kpis = [ 'metricas_planificadas' => 0, 'metricas_distintas' => 0, 'iteraciones_con_metricas' => 0 ];
try {
    $q1 = $cn->query("SELECT COUNT(*) AS total FROM metrica_iteracion mi JOIN iteracion i ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = $proyectoId JOIN fase f ON f.id_fase = i.id_fase JOIN proyecto_fase pf ON pf.id_fase = f.id_fase WHERE pf.id_proyecto = $proyectoId");
    if ($q1 && $x = $q1->fetch_assoc()) $kpis['metricas_planificadas'] = (int)$x['total'];
    $q2 = $cn->query("SELECT COUNT(DISTINCT mi.id_metrica) AS total FROM metrica_iteracion mi JOIN iteracion i ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = $proyectoId JOIN fase f ON i.id_fase = f.id_fase JOIN proyecto_fase pf ON pf.id_fase = f.id_fase AND pf.id_proyecto = $proyectoId WHERE pf.id_proyecto = $proyectoId");
    if ($q2 && $x = $q2->fetch_assoc()) $kpis['metricas_distintas'] = (int)$x['total'];
    $q3 = $cn->query("SELECT COUNT(DISTINCT i.id_iteracion) AS total FROM iteracion i JOIN fase f ON f.id_fase = i.id_fase JOIN proyecto_fase pf ON pf.id_fase = f.id_fase JOIN metrica_iteracion mi ON mi.id_iteracion = i.id_iteracion WHERE i.id_proyecto = $proyectoId");
    if ($q3 && $x = $q3->fetch_assoc()) $kpis['iteraciones_con_metricas'] = (int)$x['total'];
} catch (Throwable $e) {}

// Construcción de consulta de detalles (igual a dashboard_export)
$where = [];
$where[] = 'pf.id_proyecto = ?';
$where[] = 'i.id_proyecto = ?';
$params = [$proyectoId, $proyectoId];
$types  = 'ii';
if ($faseName !== '') { $where[] = 'f.nombre = ?'; $params[] = $faseName; $types .= 's'; }
if ($metricId !== null) { $where[] = 'm.id_metrica = ?'; $params[] = $metricId; $types .= 'i'; }

$sql = "
SELECT 
    i.numero_iteracion,
    f.nombre AS fase,
    i.fecha_inicio AS inicio,
    i.fecha_fin AS fin,
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

$stmt = $cn->prepare($sql);
if (!$stmt) { http_response_code(500); echo 'Error preparando informe'; exit; }

// Añadir los dos primeros params para JOIN
array_unshift($params, $proyectoId, $proyectoId);
$types = 'ii' . $types;

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

function norm_date($v) {
    if ($v === null || $v === '') return '';
    if (is_numeric($v)) {
        $n = (int)$v; if ($n > 946684800 && $n < 4102444800) return date('d/m/Y', $n);
        $s = (string)$v; if (strlen($s) === 8) return substr($s,6,2).'/'.substr($s,4,2).'/'.substr($s,0,4);
    }
    if (is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) return $m[3].'/'.$m[2].'/'.$m[1];
    return (string)$v;
}

// Armar datos filas con mismo criterio de cumplimiento
$rows = [];
while ($row = $res->fetch_assoc()) {
    $plan = (float)($row['planificado'] ?? 0);
    $ejec = (float)($row['ejecutado'] ?? 0);
    if ($plan == $ejec) {
        $pct = 100; $nota = 'Se cumplió';
    } elseif ($plan == 0 && $ejec > 0) {
        $pct = 100; $nota = 'Se planificó 0 ('.$ejec.')';
    } elseif ($ejec > $plan) {
        $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 100; $nota = 'Supera planificado (+'.($ejec-$plan).')';
    } elseif ($plan > $ejec) {
        $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 0; $nota = 'Falta (-'.($plan-$ejec).')';
    } else { $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 0; $nota = ''; }

    $rows[] = [
        'iteracion'     => ($row['fase'].' '.$row['numero_iteracion']),
        'inicio'        => norm_date($row['inicio']),
        'fin'           => norm_date($row['fin']),
        'metrica'       => (string)$row['metrica'],
        'planificado'   => (string)$row['planificado'],
        'ejecutado'     => (string)$row['ejecutado'],
        'umbral'        => is_numeric($row['umbral']) ? (rtrim(rtrim(number_format((float)$row['umbral'], 2, '.', ''), '0'), '.')).'%' : (string)$row['umbral'],
        'cumplimiento'  => (string)$pct.'%',
        'nota'          => $nota,
    ];
}

$pdf = new PDF('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Sección Proyecto + KPI
$faseLabel = ($faseName !== '') ? $faseName : 'Todas';
$pdf->seccionProyecto(
    $proyNombre !== '' ? $proyNombre : ('Proyecto '.$proyectoId),
    '',
    $faseLabel,
    date('d/m/Y'),
    $kpis
);

// Tabla
$header = ['Iteración','Inicio','Fin','Métrica','Planif.','Ejec.','Umbral (%)','Cumplimiento (%)','Nota'];
if (empty($rows)) {
    $pdf->SetFont('Arial','I',10);
    $pdf->Cell(0,8,utf8_decode('No hay datos para los filtros seleccionados.'),0,1,'C');
} else {
    $pdf->tablaResultados($header, $rows);
}

// Salida
$base = ($proyNombre !== '' ? preg_replace('/[^a-zA-Z0-9_-]+/','_', $proyNombre) : ('proyecto_'.$proyectoId));
$pdf->Output('I', $base.'_informe_metricflow.pdf');
exit;
?>
