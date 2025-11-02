<?php

/**
 * Dashboard de Calidad por Iteración/Métrica (versión consistente)
 * - Misma lógica de datos que tu dashboard actual
 * - Agrupación visual por FASE
 * - Tabs de fase coherentes con el diseño del dashboard inicial
 * - Resumen global arriba
 */

require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
require_once __DIR__ . '/../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();

$conexion = BDConexion::getConexion();

// Proyecto
$idProyecto = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;
if ($idProyecto <= 0) {
  $asignados = ControlAcceso::proyectosAsignadosDelUsuario();
  if (!empty($asignados)) {
    header('Location: ' . '/metricflow/app/dashboard_exclusivo.php?proyecto=' . (int)$asignados[0]);
    exit;
  } else {
    exit;
  }
}

ControlAcceso::requiereProyecto($idProyecto);

// Datos del proyecto
$sqlProyecto = "SELECT nombre, estado FROM proyecto WHERE id_proyecto = $idProyecto";
$resProyecto = $conexion->query($sqlProyecto);

if ($resProyecto && $resProyecto->num_rows > 0) {
  $row = $resProyecto->fetch_assoc();
  $nombreProyecto = $row['nombre'];
  $estadoProyecto = $row['estado'];
  $proyectoExiste = true;
} else {
  $proyectoExiste = false;
  $nombreProyecto = "Proyecto no encontrado";
  $estadoProyecto = "No disponible";
}

// cantidad de métricas con datos ejecutados
$sqlMetricas = "SELECT COUNT(DISTINCT mi.id_metrica) AS total
                FROM metrica_iteracion mi
                JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
                JOIN fase f ON f.id_fase = i.id_fase
                JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
                WHERE pf.id_proyecto = $idProyecto";
$resMetricas = $conexion->query($sqlMetricas);
$totalMetricas = ($resMetricas && $resMetricas->num_rows > 0) ? (int)$resMetricas->fetch_assoc()['total'] : 0;

// total iteraciones
$sqlIter = "SELECT COUNT(*) AS total
            FROM iteracion i
            JOIN fase f ON f.id_fase = i.id_fase
            JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
            WHERE pf.id_proyecto = $idProyecto";
$resIter = $conexion->query($sqlIter);
$totalIteraciones = ($resIter && $resIter->num_rows > 0) ? (int)$resIter->fetch_assoc()['total'] : 0;

// QUERY principal
$query = "
SELECT 
    i.id_iteracion,
    i.numero_iteracion,
    f.id_fase,
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
WHERE pf.id_proyecto = $idProyecto
ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;
";
$result = $conexion->query($query);

// armamos DATA por fase → iteración → métricas
$phases = [];   // ['Inicio' => [...iters...], ...]
$flatData = []; // lo mismo que tenías (por compatibilidad JS)
if ($result) {
  while ($r = $result->fetch_assoc()) {
    $faseNombre = $r['fase'];
    $faseId = (int)$r['id_fase'];
    $iterKey  = trim($r['fase'] . ' ' . $r['numero_iteracion']);

    if (!isset($phases[$faseId])) {
      $phases[$faseId] = [
        'id_fase' => $faseId,
        'nombre'  => $faseNombre,
        'iters'   => []
      ];
    }

    if (!isset($phases[$faseId]['iters'][$iterKey])) {
      $phases[$faseId]['iters'][$iterKey] = [
        'iteracion' => $iterKey,
        'fase'      => $faseNombre,
        'numero'    => $r['numero_iteracion'],
        'inicio'    => $r['inicio'],
        'fin'       => $r['fin'],
        'metricas'  => []
      ];
    }

    $plan = (float)($r['planificado'] ?? 0);
    $ejec = (float)($r['ejecutado'] ?? 0);

    if ($plan == 0 && $ejec == 0) {
      $pct = 100;
      $nota = "Se cumplió";
    } elseif ($plan == 0 && $ejec > 0) {
      $pct = 100;
      $nota = "Se planificó 0 ($ejec)";
    } elseif ($ejec > $plan) {
      $pct = round(($ejec / $plan) * 100);
      $nota = "Supera planificado (+" . ($ejec - $plan) . ")";
    } else {
      $pct = ($plan > 0) ? round(($ejec / $plan) * 100) : 0;
      $nota = "";
    }

    $phases[$faseId]['iters'][$iterKey]['metricas'][] = [
      "id"           => (int)$r['id_metrica'],
      "nombre"       => $r['metrica'],
      "executed"     => $pct,
      "planned"      => $plan,
      "executedReal" => $ejec,
      "unit"         => "u",
      "min"          => max(0, 100 - (float)$r['umbral']),
      "max"          => 100 + (float)$r['umbral'],
      "nota"         => $nota,
      "extra"        => ($plan > 0 ? max(0, $ejec - $plan) : $ejec)
    ];
  }
}

// normalizamos para el JS
$DATA = [];
foreach ($phases as $phase) {
  $iters = array_values($phase['iters']);
  foreach ($iters as $it) {
    $DATA[] = $it;
  }
}

// badge estado
$estadoClass = 'badge-secondary';
$estadoKey = strtoupper(str_replace(' ', '_', trim((string)$estadoProyecto)));
switch ($estadoKey) {
  case 'REGISTRADO':
    $estadoClass = 'badge-secondary';
    break;
  case 'EN_PROGRESO':
    $estadoClass = 'badge-primary';
    break;
  case 'FINALIZADO':
    $estadoClass = 'badge-success';
    break;
  case 'CANCELADO':
    $estadoClass = 'badge-danger';
    break;
  default:
    $estadoClass = 'badge-secondary';
}
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <title><?= htmlspecialchars($nombreProyecto, ENT_QUOTES, 'UTF-8'); ?> - Dashboard de Calidad</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
  <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/uargflow_footer.css" />
  <script src="../lib/JQuery/jquery-3.3.1.js"></script>
  <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"
    onerror="this.onerror=null;this.src='../lib/echarts.min.js';"></script>
  <style>
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      padding-top: 70px;
    }

    .card {
      border: 1px solid rgba(0, 0, 0, 0.05);
      border-radius: .75rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
    }

    .card-header {
      background-color: #f8f9fa;
    }

    .stat-card .card-body {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 18px;
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 48px;
      color: #fff;
      box-shadow: 0 2px 6px rgba(0, 0, 0, .12);
    }

    .icon-bg-primary {
      background: linear-gradient(135deg, #007bff 0%, #5aa7ff 100%);
    }

    .icon-bg-success {
      background: linear-gradient(135deg, #28a745 0%, #65d488 100%);
    }

    .icon-bg-secondary {
      background: linear-gradient(135deg, #6c757d 0%, #a0a4a8 100%);
    }

    .stat-label {
      font-size: .75rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      margin-bottom: 2px;
    }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
    }

    /* resumen global (coherente) */

    .summary-mini-title {
      font-size: .7rem;
      text-transform: uppercase;
      color: #6c757d;
      margin-bottom: 2px;
    }

    .summary-mini-value {
      font-size: 1.25rem;
      font-weight: 700;
    }

    .summary-dot {
      width: 14px;
      height: 14px;
      border-radius: 4px;
    }

    /* tabs de fase coherentes con las pills */
    .phase-pills {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      margin-bottom: .75rem;
    }

    .phase-pill {
      border: 1px solid #dee2e6;
      background: #fff;
      border-radius: 999px;
      padding: .25rem .8rem;
      font-size: .75rem;
      color: #495057;
      cursor: pointer;
      transition: .15s;
    }

    .phase-pill.active {
      background: rgba(13, 110, 253, .1);
      border-color: #0d6efd;
      color: #0d6efd;
      font-weight: 600;
    }

    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.25rem;
      padding: 0.75rem 0.25rem;
    }

    .metric-card {
      background: #fff;
      border-radius: 1rem;
      border: 1px solid rgba(0, 0, 0, 0.04);
      height: 280px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      padding: 0.9rem 0.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
      transition: all 0.2s ease-in-out;
    }

    .metric-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
    }

    .metric-name {
      font-size: 0.83rem;
      font-weight: 600;
      color: #212529;
      text-align: center;
      min-height: 38px;
    }

    .metric-foot {
      font-size: 0.72rem;
      color: #6c757d;
      text-align: center;
      margin-top: 0.3rem;
    }

    .card-header {
      background: #fdfdfd;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .card {
      margin-bottom: 1.75rem;
    }

    .summary-mini {
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
    }


    /* leyenda coherente */
    .legend-metricas {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem;
      font-size: .75rem;
      color: #495057;
    }

    .legend-item {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }

    .legend-dot {
      width: 14px;
      height: 14px;
      border-radius: 4px;
      border: 1px solid rgba(0, 0, 0, .15);
    }

    .btn-toggle {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #0d6efd;
      color: #fff;
      border: none;
      font-weight: 500;
      padding: 6px 14px;
      font-size: 13px;
      border-radius: 30px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      transition: all 0.25s ease;
    }

    .btn-toggle.off {
      background: #f8f9fa;
      color: #333;
      border: 1px solid #ccc;
    }

    /* tamaño explícito para los contenedores de gráficos (fix: gráficos invisibles) */
    .metric-body-chart {
      width: 100%;
      height: 165px;
    }

    /* barra de filtros mejor distribuida */
    .filter-bar {
      display: grid;
      grid-template-columns: 1fr;
      gap: .5rem;
    }
    @media (min-width: 576px) {
      .filter-bar {
        grid-template-columns: 1fr auto;
        align-items: center;
      }
    }
    .filter-controls {
      display: grid;
      grid-auto-flow: column;
      grid-auto-columns: max-content;
      gap: .5rem;
      align-items: center;
    }
    .filter-controls .form-control {
      min-width: 220px;
    }
    .export-btn { white-space: nowrap; }

    @media (max-width: 575.98px) {
      .metric-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../gui/navbar.php'; ?>

  <div class="container my-4">

    <!-- Volver -->
    <div class="mb-3">
      <a id="btnVolver" href="proyectos.php" class="btn btn-outline-secondary">
        <span class="oi oi-arrow-left mr-1"></span> Volver
      </a>
    </div>
    <script>
      (function() {
        var btn = document.getElementById('btnVolver');
        if (!btn) return;
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          try {
            var ref = document.referrer;
            if (ref && (new URL(ref)).origin === location.origin && history.length > 1) {
              history.back();
            } else {
              location.href = btn.getAttribute('href');
            }
          } catch (err) {
            location.href = btn.getAttribute('href');
          }
        });
      })();
    </script>

    <?php if (!$proyectoExiste): ?>
      <div class="card my-5 text-center"
        style="border:1px dashed rgba(220,53,69,0.25); background:rgba(220,53,69,0.03);">
        <div class="card-body p-5">
          <i class="oi oi-warning mb-3" style="font-size:2rem; color:#dc3545;"></i>
          <h5 class="text-danger font-weight-bold mb-2">Proyecto no encontrado</h5>
          <p class="text-muted mb-0">
            No existe un proyecto con el identificador <b>ID <?= htmlspecialchars($idProyecto) ?></b>.<br>
            Verifique el parámetro o cree un nuevo proyecto antes de continuar.
          </p>
        </div>
      </div>
      <?php exit; ?>
    <?php endif; ?>

    <!-- TOP CARDS iguales al dashboard inicial -->
    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-primary"><span class="oi oi-briefcase"></span></div>
            <div>
              <span class="stat-label">Proyecto</span>
              <div class="stat-value"><?= htmlspecialchars($nombreProyecto) ?></div>
              <span class="status-line">
                Estado:
                <span class="badge badge-pill <?= $estadoClass ?>"><?= htmlspecialchars($estadoProyecto) ?></span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-success"><span class="oi oi-graph"></span></div>
            <div>
              <span class="stat-label">Métricas utilizadas</span>
              <div class="stat-value"><?= (int)$totalMetricas ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-secondary"><span class="oi oi-loop-circular"></span></div>
            <div>
              <span class="stat-label">Iteraciones</span>
              <div class="stat-value"><?= (int)$totalIteraciones ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resumen Global coherente -->
    <div class="row mb-3">
      <div class="col-md-3 col-6 mb-2">
        <div class="summary-mini">
          <div class="summary-dot" style="background:rgba(40,167,69,.9)"></div>
          <div>
            <div class="summary-mini-title">Cumplimiento promedio</div>
            <div id="resumenPromedio" class="summary-mini-value text-success">--%</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6 mb-2">
        <div class="summary-mini">
          <div class="summary-dot" style="background:rgba(140,220,170,.95)"></div>
          <div>
            <div class="summary-mini-title">Superaron el plan</div>
            <div id="resumenSuperadas" class="summary-mini-value">0</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6 mb-2">
        <div class="summary-mini">
          <div class="summary-dot" style="background:rgba(255,193,7,.85)"></div>
          <div>
            <div class="summary-mini-title">Dentro de umbral</div>
            <div id="resumenUmbral" class="summary-mini-value">0</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6 mb-2">
        <div class="summary-mini">
          <div class="summary-dot" style="background:rgba(220,53,69,.85)"></div>
          <div>
            <div class="summary-mini-title">Bajo umbral</div>
            <div id="resumenBajo" class="summary-mini-value text-danger">0</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Controles / filtros -->
    <div class="filter-bar mb-2">
      <div>
        <h6 class="mb-1">Métricas por fase e iteración</h6>
        <small class="text-muted">
          Usá las fases para agrupar las iteraciones. Podés alternar donuts/barras y filtrar por métrica.
        </small>
        <div class="legend-metricas mt-1">
          <span class="legend-item"><span class="legend-dot" style="background:rgba(40,167,69,.9)"></span>Se cumplió</span>
          <span class="legend-item"><span class="legend-dot" style="background:rgba(140,220,170,.9)"></span>Se superó</span>
          <span class="legend-item"><span class="legend-dot" style="background:rgba(255,193,7,.85)"></span>Dentro del umbral</span>
          <span class="legend-item"><span class="legend-dot" style="background:rgba(100,170,255,.85)"></span>Planificación=0</span>
          <span class="legend-item"><span class="legend-dot" style="background:rgba(220,53,69,.85)"></span>Debajo del umbral</span>
        </div>
      </div>
      <div class="filter-controls">
        <div class="d-inline-flex align-items-center" id="metricFilterControls">
          <label for="metricFilterSelect" class="mb-0 mr-2 small text-muted">Métrica</label>
          <select id="metricFilterSelect" class="form-control form-control-sm">
            <option value="">Todas</option>
          </select>
          <button id="clearMetricFilter" type="button" class="btn btn-outline-secondary btn-sm ml-2">Limpiar</button>
        </div>
        <button id="modeToggle" class="btn-toggle ml-sm-2" data-mode="donut">
          <i class="oi oi-pie-chart"></i> Ver como barras
        </button>
        <form id="exportForm" class="ml-sm-2" method="GET" action="api/dashboard_export.php" target="_blank">
          <input type="hidden" name="proyecto" value="<?= (int)$idProyecto ?>" />
          <input type="hidden" name="fase" id="exportFase" value="" />
          <input type="hidden" name="metricId" id="exportMetric" value="" />
          <input type="hidden" name="format" value="xls" />
          <button type="submit" class="btn btn-success btn-sm export-btn">
            <span class="oi oi-data-transfer-download mr-1"></span> Exportar CSV
          </button>
        </form>
      </div>
    </div>

    <!-- Tabs de Fase -->
    <div id="phaseTabs" class="phase-pills mb-3"></div>

    <!-- contenedor de iteraciones x fase -->
    <div id="phaseContent"></div>

  </div>

  <script>
    const DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    const ID_PROYECTO = <?= (int)$idProyecto ?>;
    let SELECTED_METRIC_ID = null;
    let CURRENT_PHASE = null; // se setea al inicial

    const __CHARTS = Object.create(null);
    let __chartsResizeScheduled = false;

    function __scheduleChartsResize() {
      if (__chartsResizeScheduled) return;
      __chartsResizeScheduled = true;
      requestAnimationFrame(() => {
        try {
          Object.values(__CHARTS).forEach(ch => {
            ch && ch.resize && ch.resize();
          });
        } finally {
          __chartsResizeScheduled = false;
        }
      });
    }
    window.addEventListener('resize', __scheduleChartsResize);

    function colorSemaforo(valorPct, minPct, maxPct) {
      valorPct = Number(valorPct) || 0;
      minPct = Number(minPct) || 0;
      if (valorPct >= 100) return "rgba(40,167,69,0.9)";
      if (valorPct >= minPct) return "rgba(255,193,7,0.85)";
      return "rgba(220,53,69,0.85)";
    }

    function resolveColor(metric, pct) {
      const plan = Number(metric?.planned ?? 0);
      const ejec = Number(metric?.executedReal ?? 0);
      if (plan === 0 && ejec > 0) return "rgba(100,170,255,0.85)";
      if (pct > 100) return "rgba(140,220,170,0.9)";
      return colorSemaforo(pct, metric.min, metric.max);
    }

    function uniqueMetricsFromData(data) {
      const map = new Map();
      (data || []).forEach(it => (it.metricas || []).forEach(m => {
        const id = Number(m.id);
        if (!map.has(id)) map.set(id, m.nombre);
      }));
      return Array.from(map, ([id, nombre]) => ({
        id,
        nombre
      })).sort((a, b) => String(a.nombre).localeCompare(String(b.nombre)));
    }

    function buildMetricFilter() {
      const sel = document.getElementById('metricFilterSelect');
      const btnClear = document.getElementById('clearMetricFilter');
      const uniques = uniqueMetricsFromData(DATA);
      sel.innerHTML = '<option value=\"\">Todas</option>' + uniques.map(m => `<option value=\"${m.id}\">#${m.id} - ${m.nombre}</option>`).join('');
      sel.addEventListener('change', () => {
        const v = sel.value.trim();
        SELECTED_METRIC_ID = v ? Number(v) : null;
        renderCurrentPhase();
        updateExportFields();
      });
      btnClear.addEventListener('click', () => {
        SELECTED_METRIC_ID = null;
        sel.value = '';
        renderCurrentPhase();
        updateExportFields();
      });
      // inicial
      updateExportFields();
    }

    // agrupamos por fase desde DATA
    function groupByPhase() {
      const phases = {};
      (DATA || []).forEach(it => {
        const faseName = it.fase || 'Sin fase';
        if (!phases[faseName]) phases[faseName] = [];
        phases[faseName].push(it);
      });
      return phases;
    }

    function buildPhaseTabs() {
      const container = document.getElementById('phaseTabs');
      const phasesMap = groupByPhase();
      const names = Object.keys(phasesMap);
      container.innerHTML = '';
      names.forEach((name, idx) => {
        const pill = document.createElement('div');
        pill.className = 'phase-pill' + (idx === 0 ? ' active' : '');
        pill.dataset.phase = name;
        pill.textContent = name;
        container.appendChild(pill);
        if (idx === 0) CURRENT_PHASE = name;
      });
      container.addEventListener('click', (e) => {
        const pill = e.target.closest('.phase-pill');
        if (!pill) return;
        [...container.querySelectorAll('.phase-pill')].forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        CURRENT_PHASE = pill.dataset.phase;
        renderCurrentPhase();
        updateExportFields();
      });
      updateExportFields();
    }

    function buildTooltipHtml(metric) {
      const plan = Number(metric?.planned ?? 0);
      const ejec = Number(metric?.executedReal ?? 0);
      let pct;
      if (plan === 0 && ejec === 0) pct = 100;
      else if (plan === 0 && ejec > 0) pct = 100;
      else pct = plan > 0 ? Math.round((ejec / plan) * 100) : 0;
      let html = `<b>#${metric.id} - ${metric.nombre}</b><br>`;
      html += `Planificado: <b>${plan}</b><br>`;
      html += `Ejecutado: <b>${ejec}</b><br>`;
      html += `Cumplimiento: <b>${pct}%</b><br>`;
      return html;
    }

    function renderDonut(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();
      const chart = echarts.init(dom);
      __CHARTS[domId] = chart;

      const plan = Number(metric?.planned ?? 0);
      const ejec = Number(metric?.executedReal ?? 0);
      let pct;
      if (plan === 0 && ejec === 0) pct = 100;
      else if (plan === 0 && ejec > 0) pct = 100;
      else if (ejec > plan) pct = Math.round((ejec / plan) * 100);
      else pct = plan > 0 ? Math.round((ejec / plan) * 100) : 0;
      const color = resolveColor(metric, pct);

      const ringData = pct >= 100 ? [{
        value: 100,
        name: 'Cumplido',
        itemStyle: {
          color
        }
      }] : [{
          value: pct,
          name: 'Cumplido',
          itemStyle: {
            color
          }
        },
        {
          value: 100 - pct,
          name: 'Restante',
          itemStyle: {
            color: '#e9ecef'
          }
        }
      ];

      chart.setOption({
        tooltip: {
          trigger: 'item',
          formatter: () => buildTooltipHtml(metric)
        },
        series: [{
          type: 'pie',
          radius: ['55%', '88%'],
          avoidLabelOverlap: false,
          label: {
            show: false
          },
          labelLine: {
            show: false
          },
          itemStyle: {
            borderWidth: 1,
            borderColor: 'rgba(0,0,0,.08)'
          },
          data: ringData
        }],
        graphic: [{
          type: 'text',
          left: 'center',
          top: 'middle',
          style: {
            text: pct + '%',
            fontSize: 24,
            fontWeight: 700,
            fill: '#212529',
            textAlign: 'center'
          }
        }]
      });
    }

    function renderMiniBar(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();
      const chart = echarts.init(dom);
      __CHARTS[domId] = chart;

      const plan = Number(metric?.planned ?? 0);
      const ejec = Number(metric?.executedReal ?? 0);
      let pct;
      if (plan === 0 && ejec === 0) pct = 100;
      else if (plan === 0 && ejec > 0) pct = 100;
      else if (ejec > plan) pct = Math.round((ejec / plan) * 100);
      else pct = plan > 0 ? Math.round((ejec / plan) * 100) : 0;
      const color = resolveColor(metric, pct);
      const yMax = pct > 120 ? 130 : 120;

      chart.setOption({
        tooltip: {
          trigger: 'axis',
          formatter: () => buildTooltipHtml(metric)
        },
        grid: {
          left: 40,
          right: 10,
          top: 10,
          bottom: 30
        },
        xAxis: {
          type: 'category',
          data: ['Plan', 'Ejec.'],
          axisLabel: {
            color: '#6c757d'
          },
          axisTick: {
            show: false
          }
        },
        yAxis: {
          type: 'value',
          min: 0,
          max: yMax,
          axisLabel: {
            formatter: '{value}%',
            color: '#6c757d'
          },
          splitLine: {
            lineStyle: {
              type: 'dashed',
              color: '#e9ecef'
            }
          }
        },
        series: [{
            name: 'Plan',
            type: 'bar',
            data: [100, null],
            barWidth: 28,
            itemStyle: {
              color: '#cdd4e6',
              borderColor: 'rgba(0,0,0,.05)',
              borderWidth: 1
            },
            label: {
              show: true,
              position: 'top',
              formatter: '100%',
              color: '#495057',
              fontWeight: 600
            }
          },
          {
            name: 'Ejec.',
            type: 'bar',
            data: [null, pct],
            barWidth: 28,
            itemStyle: {
              color,
              borderColor: 'rgba(0,0,0,.05)',
              borderWidth: 1
            },
            label: {
              show: true,
              position: 'top',
              formatter: pct + '%',
              color: '#495057',
              fontWeight: 600
            },
            markLine: {
              symbol: 'none',
              lineStyle: {
                type: 'dashed',
                color: '#0d6efd'
              },
              data: [{
                yAxis: 100
              }]
            }
          }
        ]
      });
    }

    function renderCurrentPhase() {
      const container = document.getElementById('phaseContent');
      container.innerHTML = '';
      if (typeof echarts === 'undefined') {
        container.innerHTML = '<div class="alert alert-warning mb-3">No se pudo cargar la librería de gráficos (ECharts). Verifique su conexión o el acceso al CDN.</div>';
        return;
      }
      const mode = document.getElementById('modeToggle')?.dataset?.mode || 'donut';
      const phases = groupByPhase();
      const iters = phases[CURRENT_PHASE] || [];

      iters.forEach((it, idx) => {
        const card = document.createElement('div');
        card.className = 'card mb-3';
        card.innerHTML = `
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <div class="font-weight-bold">${it.iteracion}</div>
            <div class="text-muted" style="font-size:.75rem;">Del ${it.inicio} al ${it.fin}</div>
          </div>
          <span class="badge badge-light">${(it.metricas||[]).length} métricas</span>
        </div>
        <div class="card-body">
          <div class="metric-grid" id="metric-grid-${idx}"></div>
        </div>
      `;
        container.appendChild(card);

        const grid = card.querySelector('.metric-grid');
        let metrics = Array.isArray(it.metricas) ? it.metricas.slice() : [];
        if (SELECTED_METRIC_ID != null) {
          const found = metrics.find(mm => Number(mm.id) === Number(SELECTED_METRIC_ID));
          metrics = found ? [found] : [];
        }
        if (metrics.length === 0) {
          grid.innerHTML = '<div class="text-muted">No hay métricas para esta iteración o no coincide con el filtro.</div>';
        } else {
          metrics.forEach((m, mIdx) => {
            const mCard = document.createElement('div');
            mCard.className = 'metric-card';
            const chartId = `metric-chart-${idx}-${mIdx}`;
            mCard.innerHTML = `
            <div class="metric-name">#${m.id} - ${m.nombre}</div>
            <div id="${chartId}" class="metric-body-chart"></div>
            <div class="metric-foot">
              Planificado: <b>${m.planned}</b> | Ejecutado: <b>${m.executedReal}</b>
            </div>
          `;
            mCard.addEventListener('click', () => {
              SELECTED_METRIC_ID = Number(m.id);
              const sel = document.getElementById('metricFilterSelect');
              if (sel) sel.value = String(SELECTED_METRIC_ID);
              renderCurrentPhase();
            });
            grid.appendChild(mCard);
            setTimeout(() => {
              if (mode === 'donut') renderDonut(chartId, m);
              else renderMiniBar(chartId, m);
            }, 0);
          });
        }
      });

      calcularResumenGlobal();
      __scheduleChartsResize();
    }

    function calcularResumenGlobal() {
      const all = DATA || [];
      let totalPct = 0,
        count = 0,
        superadas = 0,
        umbral = 0,
        bajo = 0;
      all.forEach(it => {
        (it.metricas || []).forEach(m => {
          const plan = Number(m.planned ?? 0);
          const ejec = Number(m.executedReal ?? 0);
          let pct;
          if (plan === 0 && ejec === 0) pct = 100;
          else if (plan === 0 && ejec > 0) pct = 100;
          else if (ejec > plan) pct = Math.round((ejec / plan) * 100);
          else pct = plan > 0 ? Math.round((ejec / plan) * 100) : 0;
          totalPct += pct;
          count++;
          const min = Number(m.min ?? 0);
          if (pct > 100) superadas++;
          else if (pct >= min) umbral++;
          else bajo++;
        });
      });
      const promedio = count > 0 ? Math.round(totalPct / count) : 0;
      document.getElementById('resumenPromedio').textContent = promedio + '%';
      document.getElementById('resumenSuperadas').textContent = superadas;
      document.getElementById('resumenUmbral').textContent = umbral;
      document.getElementById('resumenBajo').textContent = bajo;
    }

    // toggle modo
    (function() {
      const btn = document.getElementById('modeToggle');
      if (!btn) return;
      btn.addEventListener('click', () => {
        const current = btn.dataset.mode || 'donut';
        const next = current === 'donut' ? 'bar' : 'donut';
        btn.dataset.mode = next;
        btn.classList.toggle('off', next === 'donut');
        if (next === 'donut') {
          btn.innerHTML = '<i class="oi oi-pie-chart"></i> Ver como barras';
        } else {
          btn.innerHTML = '<i class="oi oi-bar-chart"></i> Ver como donuts';
        }
        renderCurrentPhase();
      });
    })();

    document.addEventListener('DOMContentLoaded', () => {
      buildMetricFilter();
      buildPhaseTabs();
      renderCurrentPhase();
    });
    
    // sincroniza filtros -> formulario de exportación
    function updateExportFields() {
      const fase = CURRENT_PHASE || '';
      const metric = SELECTED_METRIC_ID != null ? String(SELECTED_METRIC_ID) : '';
      const faseInput = document.getElementById('exportFase');
      const metricInput = document.getElementById('exportMetric');
      if (faseInput) faseInput.value = fase;
      if (metricInput) metricInput.value = metric;
    }
  </script>

  <?php include_once '../gui/footer.php'; ?>
</body>

</html>