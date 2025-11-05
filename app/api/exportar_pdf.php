<?php
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

class PDF extends FPDF
{

    function Header()
    {
        $logoLeft = __DIR__ . '/../../lib/img/Logo-UNPA-UARG-azul.png';
        if (file_exists($logoLeft)) {
            // Nuevo tamaño y posición
            $this->Image($logoLeft, 16, 1, 32); // antes: (14,8,24)
        }
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(0, 12, utf8_decode('INFORME DE RESULTADOS DE MÉTRICAS DE CALIDAD'), 0, 1, 'C');
        $this->Ln(3);

        $this->SetDrawColor(13, 110, 253);
        $this->SetLineWidth(0.8);
        // Trazo superior con el mismo ancho que la tabla (270mm) y alineado a los márgenes actuales
        $tableWidth = 270.0;
        $x0 = $this->lMargin;
        $this->Line($x0, 26, $x0 + $tableWidth, 26);
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(
            0,
            8,
            utf8_decode('Generado automáticamente por MetricFlow-SQA | ') .
                date('d/m/Y') . utf8_decode('  |  Página ') .
                $this->PageNo() . '/{nb}',
            0,
            0,
            'C'
        );
    }

    // Ellipsize helper to keep layout stable
    function cellTextEllipsized($w, $h, $txt, $border = 1, $ln = 0, $align = 'L', $fill = false)
    {
        $margin = 1.6; // small padding
        $max = $w - 2 * $margin;
        $s = $this->GetStringWidth($txt);
        if ($s > $max) {
            $ellipsis = '...';
            $eW = $this->GetStringWidth($ellipsis);
            $cut = '';
            for ($i = 0, $n = strlen($txt); $i < $n; $i++) {
                $cut .= $txt[$i];
                if ($this->GetStringWidth($cut) + $eW > $max) {
                    $cut = rtrim($cut);
                    $txt = $cut . $ellipsis;
                    break;
                }
            }
        }
        $this->Cell($w, $h, utf8_decode($txt), $border, $ln, $align, $fill);
    }

    // Sección de datos generales con ancho total coherente al de las tablas
    /**
     * Bloque de Datos Generales del Proyecto
     * Visual coherente con las tablas inferiores (ancho total 270 mm)
     * Incluye control de textos largos y filtros dinámicos.
     */
    function seccionProyecto($nombre, $fecha, $kpis = [], $filtrosTexto = 'Fase: Todas | Iteraciones: Todas | Métricas: Todas')
    {
        $this->Ln(1);

        // === CONFIG BÁSICA ===
        $TOTAL = 270.0; // ancho exacto de las tablas inferiores
        $grisEtiqueta = [240, 240, 240]; // gris más neutro
        $grisFondo = [245, 247, 250]; // fondo institucional azulado

        // === FONDO GRIS DETRÁS DEL BLOQUE ===
        $altoBloque = 30; // altura aproximada del bloque
        $this->SetFillColor(...$grisFondo);
        $this->Rect($this->lMargin, $this->GetY(), $TOTAL, $altoBloque, 'F');

        // === ENCABEZADO AZUL (con borde inferior y lema institucional) ===
        $this->SetFillColor(13, 110, 253);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 11);
        $this->SetX($this->lMargin);
    // Borde completo como el resto de las tablas (no solo línea inferior)
    $this->Cell($TOTAL, 9, utf8_decode('DATOS GENERALES DEL PROYECTO'), 1, 1, 'C', true);



        // === FILAS DE DATOS ===
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(60, 60, 60); // texto gris medio
        $this->SetFillColor(...$grisEtiqueta);
        $this->SetDrawColor(0);

        // --- FILA 1: Proyecto y Fecha ---
        // 270 = 80 + 105 + 50 + 35
        $this->Cell(80, 7, utf8_decode('Nombre del Proyecto:'), 1, 0, 'L', true);
        $this->Cell(105, 7, utf8_decode(mb_strimwidth($nombre ?: '—', 0, 55, '...')), 1, 0, 'L');
        $this->SetFillColor(...$grisEtiqueta);
        $this->Cell(50, 7, utf8_decode('Fecha del Informe:'), 1, 0, 'L', true);
        $this->Cell(35, 7, utf8_decode($fecha ?: '—'), 1, 1, 'C');

        // --- FILA 2: Filtros aplicados ---
        // 270 = 50 + 220
        $this->SetFillColor(...$grisEtiqueta);
        $this->Cell(50, 7, utf8_decode('Filtros aplicados:'), 1, 0, 'L', true);
        $remaining = $TOTAL - 50;
        if ($this->GetStringWidth($filtrosTexto) > $remaining) {
            $this->MultiCell($remaining, 6, utf8_decode($filtrosTexto), 1, 'L');
        } else {
            $this->Cell($remaining, 7, utf8_decode($filtrosTexto), 1, 1, 'L');
        }

        // --- FILA 3: KPIs ---
        // 270 = 3 * (60 etiqueta + 30 valor)
        $labelW = 60;
        $valueW = 30;
        $this->SetFillColor(...$grisEtiqueta);
        $this->Cell($labelW, 7, utf8_decode('Métricas planificadas:'), 1, 0, 'L', true);
        $this->Cell($valueW, 7, (string)($kpis['metricas_planificadas'] ?? 0), 1, 0, 'C');

        $this->SetFillColor(...$grisEtiqueta);
        $this->Cell($labelW, 7, utf8_decode('Tipos de métricas distintas:'), 1, 0, 'L', true);
        $this->Cell($valueW, 7, (string)($kpis['metricas_distintas'] ?? 0), 1, 0, 'C');

        $this->SetFillColor(...$grisEtiqueta);
        $this->Cell($labelW, 7, utf8_decode('Iteraciones con métricas:'), 1, 0, 'L', true);
        $this->Cell($valueW, 7, (string)($kpis['iteraciones_con_metricas'] ?? 0), 1, 1, 'C');

        // Separación visual con las tablas de métricas
        $this->Ln(6);
    }

    function tablaResultados($data)
    {
        $w = [70, 25, 25, 25, 30, 95];
        $fill = false;
        $currentIter = '';

        foreach ($data as $row) {
            $iterKey = $row['iteracion'] . $row['inicio'] . $row['fin'];

            // Si cambia iteración, nueva cabecera y control de salto
            if ($iterKey !== $currentIter) {
                if ($this->GetY() > 165) $this->AddPage();

                $this->Ln(4);
                $this->SetFont('Arial', 'B', 11);
                $this->SetTextColor(13, 110, 253);
                $this->SetFillColor(235, 243, 255);
                // Encabezado de sección con el mismo ancho exacto de la tabla (270mm)
                $this->SetX($this->lMargin);
                $this->Cell(270.0, 9, utf8_decode("{$row['iteracion']}  ({$row['inicio']} - {$row['fin']})"), 0, 1, 'C', true);

                // Cabecera de subtabla
                $this->SetFont('Arial', 'B', 9);
                $this->SetFillColor(13, 110, 253);
                $this->SetTextColor(255);
                $this->Cell($w[0], 7, utf8_decode('Métrica'), 1, 0, 'L', true);
                $this->Cell($w[1], 7, utf8_decode('Planificación'), 1, 0, 'C', true);
                $this->Cell($w[2], 7, utf8_decode('Ejecución'), 1, 0, 'C', true);
                $this->Cell($w[3], 7, utf8_decode('Umbral (%)'), 1, 0, 'C', true);
                $this->Cell($w[4], 7, utf8_decode('Cumplimiento (%)'), 1, 0, 'C', true);
                $this->Cell($w[5], 7, utf8_decode('Nota'), 1, 1, 'L', true);
                $this->SetFont('Arial', '', 8.5);
                $this->SetTextColor(0);
                $currentIter = $iterKey;
            }

            // Control salto si tabla se corta
            if ($this->GetY() > 185) $this->AddPage();

            $this->SetFillColor($fill ? 248 : 255);
            $this->Cell($w[0], 6.5, utf8_decode($row['metrica']), 1, 0, 'L', $fill);
            $this->Cell($w[1], 6.5, $row['planificado'], 1, 0, 'C', $fill);
            $this->Cell($w[2], 6.5, $row['ejecutado'], 1, 0, 'C', $fill);
            $this->Cell($w[3], 6.5, $row['umbral'], 1, 0, 'C', $fill);
            $this->Cell($w[4], 6.5, $row['cumplimiento'], 1, 0, 'C', $fill);
            $this->Cell($w[5], 6.5, utf8_decode($row['nota']), 1, 1, 'L', $fill);
            $fill = !$fill;
        }
    }
}

// ==================== Lógica ==================== //
$usr = ControlAcceso::usuarioActual();
if (!$usr) {
    http_response_code(401);
    echo 'No autenticado';
    exit;
}

$proyectoId = (int)($_GET['proyecto'] ?? 0);

// Normalizar filtros: aceptar arrays (fase[], iteracion[], metrica[]) y/o parámetros simples
$fases = [];
if (isset($_GET['fase'])) {
    if (is_array($_GET['fase'])) $fases = array_values(array_filter(array_map('strval', $_GET['fase'])));
    else if ($_GET['fase'] !== '') $fases = [(string)$_GET['fase']];
}
$itersInput = [];
if (isset($_GET['iteracion'])) {
    if (is_array($_GET['iteracion'])) $itersInput = array_values(array_filter(array_map('strval', $_GET['iteracion'])));
    else if ($_GET['iteracion'] !== '') $itersInput = [(string)$_GET['iteracion']];
}
$metricIds = [];
if (isset($_GET['metrica'])) {
    if (is_array($_GET['metrica'])) $metricIds = array_values(array_filter(array_map('intval', $_GET['metrica'])));
    else if ($_GET['metrica'] !== '') $metricIds = [(int)$_GET['metrica']];
}
// Compat con parámetro simple metricId
$metricId   = isset($_GET['metricId']) && $_GET['metricId'] !== '' ? (int)$_GET['metricId'] : null;
if ($metricId !== null && $metricId > 0 && !in_array($metricId, $metricIds, true)) $metricIds[] = $metricId;
// Compat con parámetro simple fase
$faseName   = isset($_GET['fase']) && !is_array($_GET['fase']) ? trim((string)$_GET['fase']) : '';
if ($faseName !== '' && !in_array($faseName, $fases, true)) $fases[] = $faseName;
if ($proyectoId <= 0) {
    http_response_code(400);
    echo 'Proyecto inválido';
    exit;
}

$cn = BDConexion::getConexion();

// --- Consultas de nombre y KPIs ---
$proyNombre = '';
$q = $cn->query("SELECT nombre FROM proyecto WHERE id_proyecto = $proyectoId");
if ($q && $r = $q->fetch_assoc()) $proyNombre = (string)$r['nombre'];

// ==== Helpers para filtros y KPIs ====
function parseIterPair($it)
{
    // intenta extraer número al final y nombre de fase previo
    if (preg_match('/^(.*)\s+(\d+)\s*$/u', $it, $m)) {
        return [trim($m[1]), (int)$m[2]];
    }
    return [$it, null];
}

// Construir WHERE dinámico compartido (solo filtros; los ids de proyecto van en los JOINs)
$where = [];
$params = [$proyectoId, $proyectoId];
$types = 'ii';
if (!empty($fases)) {
    $place = implode(',', array_fill(0, count($fases), '?'));
    $where[] = 'f.nombre IN (' . $place . ')';
    foreach ($fases as $fa) {
        $params[] = $fa;
        $types .= 's';
    }
}
if (!empty($itersInput)) {
    // (f.nombre = ? AND i.numero_iteracion = ?) OR ...
    $sub = [];
    foreach ($itersInput as $it) {
        [$fn, $num] = parseIterPair($it);
        if ($num !== null) {
            $sub[] = '(f.nombre=? AND i.numero_iteracion=?)';
            $params[] = $fn;
            $types .= 's';
            $params[] = $num;
            $types .= 'i';
        }
    }
    if ($sub) $where[] = '(' . implode(' OR ', $sub) . ')';
}
if (!empty($metricIds)) {
    $place = implode(',', array_fill(0, count($metricIds), '?'));
    $where[] = 'm.id_metrica IN (' . $place . ')';
    foreach ($metricIds as $mid) {
        $params[] = $mid;
        $types .= 'i';
    }
}

// KPIs usando los mismos filtros
$kpis = ['metricas_planificadas' => 0, 'metricas_distintas' => 0, 'iteraciones_con_metricas' => 0];
$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';
try {
    $sqlK = "SELECT COUNT(*) AS total, COUNT(DISTINCT mi.id_metrica) AS tipos, COUNT(DISTINCT i.id_iteracion) AS iters
             FROM metrica_iteracion mi
             JOIN metrica m ON mi.id_metrica=m.id_metrica
             JOIN iteracion i ON mi.id_iteracion=i.id_iteracion AND i.id_proyecto=?
             JOIN fase f ON i.id_fase=f.id_fase
             JOIN proyecto_fase pf ON pf.id_fase=f.id_fase AND pf.id_proyecto=?" . $whereSql;
    $stmtK = $cn->prepare($sqlK);
    $stmtK->bind_param($types, ...$params);
    $stmtK->execute();
    $resK = $stmtK->get_result();
    if ($resK && ($x = $resK->fetch_assoc())) {
        $kpis['metricas_planificadas'] = (int)$x['total'];
        $kpis['metricas_distintas']    = (int)$x['tipos'];
        $kpis['iteraciones_con_metricas'] = (int)$x['iters'];
    }
} catch (Throwable $e) {
}

// --- Datos tabla ---

$sql = "SELECT i.numero_iteracion,f.nombre AS fase,i.fecha_inicio AS inicio,i.fecha_fin AS fin,
m.nombre AS metrica,mi.valor_planificado AS planificado,mi.valor_ejecutado AS ejecutado,
mi.umbral_desviacion AS umbral
FROM metrica_iteracion mi
JOIN metrica m ON mi.id_metrica=m.id_metrica
JOIN iteracion i ON mi.id_iteracion=i.id_iteracion AND i.id_proyecto=?
JOIN fase f ON i.id_fase=f.id_fase
JOIN proyecto_fase pf ON pf.id_fase=f.id_fase AND pf.id_proyecto=?
" . $whereSql . "
ORDER BY f.id_fase,i.numero_iteracion,m.id_metrica";

$stmt = $cn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

function norm_date($v)
{
    if (!$v) return '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    return $v;
}

$rows = [];
while ($row = $res->fetch_assoc()) {
    $plan = (float)$row['planificado'];
    $ejec = (float)$row['ejecutado'];
    if ($plan == $ejec) {
        $pct = 100;
        $nota = 'Se cumplió';
    } elseif ($plan == 0 && $ejec > 0) {
        $pct = 100;
        $nota = 'Se planificó 0 (' . $ejec . ')';
    } elseif ($ejec > $plan) {
        $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 100;
        $nota = 'Supera planificado (+' . ($ejec - $plan) . ')';
    } elseif ($plan > $ejec) {
        $pct = ($plan > 0) ? (int)round(($ejec / $plan) * 100) : 0;
        $nota = 'Falta (-' . ($plan - $ejec) . ')';
    } else {
        $pct = 0;
        $nota = '';
    }
    $rows[] = [
        'iteracion' => $row['fase'] . ' ' . $row['numero_iteracion'],
        'inicio' => norm_date($row['inicio']),
        'fin' => norm_date($row['fin']),
        'metrica' => $row['metrica'],
        'planificado' => $row['planificado'],
        'ejecutado' => $row['ejecutado'],
        'umbral' => $row['umbral'] . '%',
        'cumplimiento' => $pct . '%',
        'nota' => $nota
    ];
}

// --- Generación PDF ---
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$faseLabel = $faseName !== '' ? $faseName : 'Todas';
// Construir texto de filtros aplicado
$labelFase = !empty($fases) ? implode(', ', $fases) : 'Todas';
$labelIter = !empty($itersInput) ? implode(', ', $itersInput) : 'Todas';
// Si hay IDs de métricas, intentar recuperar nombres para mostrar
$labelMetr = 'Todas';
if (!empty($metricIds)) {
    $place = implode(',', array_fill(0, count($metricIds), '?'));
    $t = '';
    foreach ($metricIds as $_) {
        $t .= 'i';
    }
    $sqlN = 'SELECT id_metrica,nombre FROM metrica WHERE id_metrica IN (' . $place . ')';
    $stN = $cn->prepare($sqlN);
    $stN->bind_param($t, ...$metricIds);
    $stN->execute();
    $rN = $stN->get_result();
    $names = [];
    while ($r = $rN->fetch_assoc()) {
        $names[] = $r['nombre'];
    }
    $labelMetr = $names ? implode(', ', $names) : implode(', ', array_map('strval', $metricIds));
}
$filtrosTexto = 'Fase: ' . $labelFase . ' | Iteraciones: ' . $labelIter . ' | Métricas: ' . $labelMetr;

$pdf->seccionProyecto($proyNombre ?: 'Proyecto ' . $proyectoId, date('d/m/Y'), $kpis, $filtrosTexto);

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, utf8_decode('No hay datos para los filtros seleccionados.'), 0, 1, 'C');
} else {
    $pdf->tablaResultados($rows);
}

$base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $proyNombre ?: 'proyecto_' . $proyectoId);
$pdf->Output('I', $base . '_informe_metricflow.pdf');
exit;
