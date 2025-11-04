</html>
<?php
/**
 * Dashboard de Calidad por Iteración/Métrica
 * - MISMO look&feel del original: donuts con borde, línea de umbral, barras con marco 100% + línea 100% punteada
 * - Tooltips idénticos (mensajes y colores)
 * - Filtros por fase, iteración y métrica
 * - Toggle donuts/barras
 */

require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
require_once __DIR__ . '/../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();
$cn = BDConexion::getConexion();

// -------- Proyecto
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

$sqlProyecto = "SELECT nombre, estado FROM proyecto WHERE id_proyecto = $idProyecto";
$rp = $cn->query($sqlProyecto);
if ($rp && $rp->num_rows) {
  $rowP          = $rp->fetch_assoc();
  $nombreProyecto = $rowP['nombre'];
  $estadoProyecto = $rowP['estado'];
  $proyectoOk    = true;
} else {
  $proyectoOk    = false;
  $nombreProyecto = 'Proyecto no encontrado';
  $estadoProyecto = 'No disponible';
}

// -------- KPIs
// Total de métricas planificadas (todas las filas de metrica_iteracion del proyecto)
$qMetricasPlanificadas = "
SELECT COUNT(*) AS total
FROM metrica_iteracion mi
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON f.id_fase = i.id_fase
JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
WHERE pf.id_proyecto = $idProyecto
";
$rMetricasPlanificadas = $cn->query($qMetricasPlanificadas);
$totalMetricasPlanificadas = ($rMetricasPlanificadas && $rMetricasPlanificadas->num_rows) ? (int)$rMetricasPlanificadas->fetch_assoc()['total'] : 0;

// Cantidad de tipos de métricas distintas utilizadas en el proyecto
$qMetricasDistintas = "SELECT COUNT(DISTINCT mi.id_metrica) AS total
              FROM metrica_iteracion mi
              JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
              JOIN fase f ON f.id_fase = i.id_fase
              JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
              WHERE pf.id_proyecto = $idProyecto";
$rMetricasDistintas = $cn->query($qMetricasDistintas);
$totalMetricasDistintas = ($rMetricasDistintas && $rMetricasDistintas->num_rows) ? (int)$rMetricasDistintas->fetch_assoc()['total'] : 0;

$qIters = "SELECT COUNT(*) AS total
           FROM iteracion i
           JOIN fase f ON f.id_fase = i.id_fase
           JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
           WHERE pf.id_proyecto = $idProyecto";
$rIters = $cn->query($qIters);
$totalIteraciones = ($rIters && $rIters->num_rows) ? (int)$rIters->fetch_assoc()['total'] : 0;

// -------- DATA (misma del dashboard principal)
$q = "
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
WHERE pf.id_proyecto = $idProyecto
ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;
";
$rs = $cn->query($q);

$iterMap = [];
if ($rs) {
  while ($r = $rs->fetch_assoc()) {
    $key = trim($r['fase'] . ' ' . $r['numero_iteracion']);
    if (!isset($iterMap[$key])) {
      $iterMap[$key] = [
        "iteracion" => $key,
        "fase"      => $r['fase'],
        "numero"    => $r['numero_iteracion'],
        "inicio"    => $r['inicio'],
        "fin"       => $r['fin'],
        "metricas"  => []
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

    $iterMap[$key]["metricas"][] = [
      "id"           => (int)$r['id_metrica'],
      "nombre"       => $r['metrica'],
      "executed"     => $pct,
      "planned"      => $plan,
      "executedReal" => $ejec,
      "unit"         => "u",
      "min"          => max(0, 100 - (float)$r['umbral']),   // 1 - umbral
      "max"          => 100 + (float)$r['umbral'],           // 1 + umbral
      "nota"         => $nota,
      "extra"        => ($plan > 0 ? max(0, $ejec - $plan) : $ejec)
    ];
  }
}
$DATA = array_values($iterMap);

// -------- Estado badge
$estadoClass = 'badge-secondary';
switch (strtoupper(str_replace(' ', '_', trim((string)$estadoProyecto)))) {
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
      margin-bottom: 0
    }

    html,
    body {
      height: 100%;
      min-height: 100%;
    }



    .card {
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: .75rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, .05);
      background: #fff;
      animation: fadeIn .5s ease-in
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(8px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .card-header {
      background: #f8f9fa;
      border-bottom: 1px solid rgba(0, 0, 0, .05);
      font-weight: 600
    }

    .stat-card .card-body {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 18px
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
      box-shadow: 0 2px 6px rgba(0, 0, 0, .12)
    }

    .icon-bg-primary {
      background: linear-gradient(135deg, #007bff 0%, #5aa7ff 100%)
    }

    .icon-bg-success {
      background: linear-gradient(135deg, #28a745 0%, #65d488 100%)
    }

    .icon-bg-secondary {
      background: linear-gradient(135deg, #6c757d 0%, #a0a4a8 100%)
    }

    .btn-outline-secondary {
      border-color: #dee2e6;
      color: #495057;
      background-color: #fff;
    }

    .btn-outline-secondary:hover {
      background-color: #f8f9fa;
      color: #212529;
    }

    .stat-content {
      flex: 1 1 auto
    }

    .stat-label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      margin-bottom: 2px
    }

    .stat-value {
      font-size: 1.75rem;
      font-weight: 700;
      line-height: 1.1;
      color: #212529
    }

    ¿ .chip-dot {
      width: 14px;
      height: 14px;
      border-radius: 4px;
      border: 1px solid rgba(0, 0, 0, .15);
      flex: 0 0 14px;
    }

    .chip-text {
      line-height: 1;
    }
.text-center.text-primary {
  font-size: 1.05rem;
  letter-spacing: 0.3px;
}

    .chip-label {
      display: block;
      font-size: .72rem;
      font-weight: 600;
      color: #6c757d;
    }

    .chip-value {
      font-size: 1.25rem;
      font-weight: 700;
      color: #212529;
    }

    .dot-green {
      background: #28a745;
    }

    .dot-lightgreen {
      background: #8cdcab;
    }

    .dot-yellow {
      background: #ffc107;
    }

    .dot-red {
      background: #dc3545;
    }

    .dot-blue {
      background: #64aaff;
    }

    .dot-gray {
      background: #6c757d;
    }

    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1rem;
      padding-bottom: .5rem;
      align-items: stretch
    }

    .metric-card {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .05);
      border-radius: .75rem;
      box-shadow: 0 2px 6px rgba(0, 0, 0, .05);
      height: 300px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: .75rem
    }

    .metric-name {
      font-size: .85rem;
      font-weight: 600;
      color: #212529;
      text-align: center;
      margin-bottom: .5rem;
      min-height: 38px
    }

    .metric-body-chart {
      width: 100%;
      height: 210px
    }

    .metric-foot {
      font-size: .75rem;
      text-align: center;
      color: #495057;
      margin-top: .35rem
    }

    .legend-metricas {
      background: #f8f9fa;
      border-radius: .5rem;
      padding: .5rem .75rem;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 10px 18px;
      margin: .35rem 0;
      color: #495057;
      font-size: .9rem
    }

    .legend-item {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap
    }

    .legend-dot {
      width: 14px;
      height: 14px;
      border-radius: 3px;
      border: 1px solid rgba(0, 0, 0, .2);
      display: inline-block
    }

    .legend-dash {
      width: 24px;
      height: 0;
      border-top: 2px dashed #0d6efd;
      display: inline-block;
      vertical-align: middle
    }

    #faseTabs,
    #iterFilter {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
      margin: .5rem 0 .75rem
    }

    .iter-pill {
      border: 1px solid #dee2e6;
      background: #fff;
      border-radius: 999px;
      padding: .25rem .65rem;
      font-size: .75rem;
      cursor: pointer;
      transition: .15s
    }

    .iter-pill.active {
      background: rgba(13, 110, 253, .1);
      border-color: #0d6efd;
      color: #0d6efd;
      font-weight: 600
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
      box-shadow: 0 2px 5px rgba(0, 0, 0, .1)
    }

    .btn-toggle.off {
      background: #f8f9fa;
      color: #333;
      border: 1px solid #ccc
    }

    #iterCards {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.25rem;
      margin-bottom: 0 !important;
      padding-bottom: 0 !important;
    }

    @media(max-width:575.98px) {
      #iterCards {
        display: block
      }

      .card-iteracion {
        margin-bottom: 1rem
      }
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../gui/navbar.php'; ?>

  <div id="pageContainer" class="container my-4">

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
          } catch (_) {
            location.href = btn.getAttribute('href');
          }
        });
      })();
    </script>

    <?php if (!$proyectoOk): ?>
      <div class="card my-5 text-center" style="border:1px dashed rgba(220,53,69,.25);background:rgba(220,53,69,.03);">
        <div class="card-body p-5">
          <i class="oi oi-warning mb-3" style="font-size:2rem;color:#dc3545;"></i>
          <h5 class="text-danger font-weight-bold mb-2">Proyecto no encontrado</h5>
          <p class="text-muted mb-0">No existe un proyecto con el identificador <b>ID <?= (int)$idProyecto ?></b>.</p>
        </div>
      </div>
      <?php exit; ?>
    <?php endif; ?>

    <!-- TOP CARDS -->
    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-primary"><span class="oi oi-briefcase"></span></div>
            <div class="stat-content">
              <span class="stat-label">Proyecto</span>
              <div id="projectName" class="stat-value"><?= htmlspecialchars($nombreProyecto) ?></div>
              <span class="status-line">Estado:
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
            <div class="stat-content">
              <span class="stat-label">Métricas planificadas</span>
              <div id="totalMetricasValue" class="stat-value"><?= (int)$totalMetricasPlanificadas ?></div>
              <small class="text-muted"><?= (int)$totalMetricasDistintas ?> tipos distintas</small>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-secondary"><span class="oi oi-loop-circular"></span></div>
            <div class="stat-content">
              <span class="stat-label">Iteraciones</span>
              <div id="totalIteracionesValue" class="stat-value"><?= (int)$totalIteraciones ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resumen global (cálculo en JS igual al original) -->
    <div class="card mb-4 shadow-sm border-0">
      <h6 class="text-primary font-weight-bold mb-3 text-center">
        Visión General del Proyecto
      </h6>
      <div class="card-body">

        <!-- Gráfico de distribución global de métricas -->
        <div id="chartDistribucionGlobal" style="height:340px; margin-top:1rem;"></div>


      </div>
    </div>

    <!-- Toolbar filtros -->
    <div class="card dashboard-toolbar mb-3 p-3 rounded bg-white border">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex flex-column">
          <h6 class="mb-1 font-weight-bold text-primary">Métricas por Fase e Iteración</h6>
          <small class="text-muted">Filtrá por fase, iteración o métrica; alterná entre <b>dona</b> y <b>barras</b>.</small>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-md-0">
          <div class="d-flex align-items-center mr-2">
            <label for="metricFilterSelect" class="small text-muted mb-0 mr-2">Métrica:</label>
            <select id="metricFilterSelect" class="form-control form-control-sm" style="min-width:190px;">
              <option value="">Todas</option>
            </select>
            <button id="clearMetricFilter" type="button" class="btn btn-light btn-sm ml-2 border">
              <span class="oi oi-x mr-1"></span> Limpiar
            </button>
          </div>
          <button id="modeToggle" class="btn btn-primary btn-sm" data-mode="donut">
            <i class="oi oi-pie-chart mr-1"></i> Ver como barras
          </button>
        </div>
      </div>
      <div class="legend-metricas mt-3 pt-2 border-top">
        <span class="legend-item"><span class="legend-dot" style="background:#28a745"></span>Se cumplió</span>
        <span class="legend-item"><span class="legend-dot" style="background:#8cdcab"></span>Se superó</span>
        <span class="legend-item"><span class="legend-dot" style="background:#ffc107"></span>Dentro del umbral</span>
        <span class="legend-item"><span class="legend-dot" style="background:#64aaff"></span>Planificación = 0</span>
        <span class="legend-item"><span class="legend-dot" style="background:#dc3545"></span>Debajo del umbral</span>
        <span class="legend-item"><span class="legend-dash"></span>Progreso de la iteración</span>
      </div>
    </div>

    <!-- Tabs por fase + filtro iteración -->
    <div id="faseTabs" class="mb-1"></div>
    <div id="iterFilter" class="mb-3"></div>

    <!-- Contenedor de tarjetas por iteración -->
    <div id="iterCards"></div>
  </div>

  <script>
    // ===== Helpers visuales iguales al original =====
    function colorSemaforo(valorPct, minPct) {
      valorPct = Number(valorPct) || 0;
      minPct = Number(minPct) || 0;
      if (valorPct >= 100) return "#28a745"; // Verde
      if (valorPct >= minPct) return "#ffc107"; // Amarillo
      return "#dc3545"; // Rojo
    }

    function safePct(m) {
      const v = Number(m?.executed) || 0;
      return v < 0 ? 0 : v;
    }

    // Colores especiales (plan=0)
    function resolveColor(metric, pct) {
      if ((metric.planned ?? 0) === 0 && (metric.executedReal ?? 0) > 0) return "rgba(100,170,255,0.95)"; // azul translúcido
      if (pct > 100) return "rgba(140,220,170,0.9)"; // sobrecumple translúcido
      return colorSemaforo(pct, metric.min);
    }

    function computePctAndNote(metric) {
      const plan = Number(metric?.planned ?? 0),
        ejec = Number(metric?.executedReal ?? 0);
      let pct = 0,
        nota = '';
      if (plan === 0 && ejec === 0) {
        pct = 100;
        nota = 'Se cumplió';
      } else if (plan === 0 && ejec > 0) {
        pct = 100;
        nota = `Se planificó 0 (${ejec})`;
      } else if (ejec > plan) {
        pct = Math.round((ejec / plan) * 100);
        nota = `Supera planificado (+${(ejec-plan)})`;
      } else if (plan > 0 && ejec === 0) {
        pct = 0;
        nota = `Sin ejecución (0/${plan})`;
      } else {
        pct = plan > 0 ? Math.round((ejec / plan) * 100) : 0;
      }
      return {
        pct,
        plan,
        ejec,
        nota
      };
    }

    function buildTooltipHtml(metric) {
      const {
        pct,
        plan,
        ejec
      } = computePctAndNote(metric);
      const min = Math.max(0, Number(metric?.min ?? 100));
      let html = `<b>#${metric?.id??''} - ${metric?.nombre??''}</b><br>`;
      html += `Planificado: <b>${plan}</b><br>`;
      html += `Ejecutado: <b>${ejec}</b><br>`;
      html += `Cumplimiento: <b>${pct}%</b><br>`;
      switch (true) {
        case (plan === 0 && ejec > 0):
          html += `<span style="color:#17a2b8;font-weight:bold;">ℹ️ Se planificó 0 (+${ejec})</span><br>`;
          break;
        case (plan === 0 && ejec === 0):
          html += `<span style="color:#198754;font-weight:bold;">✔️ Cumple lo planificado (0/0)</span><br>`;
          break;
        case (plan > 0 && pct > 100):
          html += `<span style="color:#28a745;font-weight:bold;">▲ Supera lo planificado (+${(ejec-plan).toFixed(0)})</span><br>`;
          break;
        case (plan > 0 && Math.round(pct) === 100):
          html += `<span style="color:#198754;font-weight:bold;">✔️ Cumple lo planificado (=${plan})</span><br>`;
          break;
        case (plan > 0 && pct >= min && pct < 100):
          html += `<span style="color:#ffc107;font-weight:bold;">⚠️ Dentro del umbral (${pct.toFixed(1)}%)</span><br>`;
          break;
        case (plan > 0 && pct > 0 && pct < min):
          html += `<span style="color:#dc3545;font-weight:bold;">▼ Por debajo del plan (-${(plan-ejec).toFixed(0)})</span><br>`;
          break;
        case (plan > 0 && ejec === 0):
          html += `<span style="color:#6c757d;font-weight:bold;">⛔ Sin ejecución (0/${plan})</span><br>`;
          break;
        default:
          html += `<span style="color:#999;">❔ Sin datos disponibles</span><br>`;
      }
      html += `Límite de desviación (1 − umbral): <b>${min}%</b>`;
      return html;
    }

    // ===== Registro global de charts + ResizeObserver =====
    const __CHARTS = Object.create(null);
    const __RO = (typeof ResizeObserver !== 'undefined') ?
      new ResizeObserver(() => {
        Object.values(__CHARTS).forEach(ch => {
          try {
            ch.resize && ch.resize();
          } catch (e) {}
        });
      }) :
      null;

    window.addEventListener('resize', () => {
      Object.values(__CHARTS).forEach(ch => {
        try {
          ch.resize && ch.resize();
        } catch (e) {}
      });
    });

    // ===== Datos desde PHP =====
    const DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    let SELECTED_METRIC_ID = null;
    let SELECTED_PHASE = null;

    // ===== UI builders =====
    function uniquePhasesFromData(data) {
      const s = new Set();
      (data || []).forEach(it => it?.fase && s.add(String(it.fase)));
      return [...s];
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

    function buildPhaseTabs() {
      const cont = document.getElementById('faseTabs');
      if (!cont) return;
      cont.innerHTML = '';
      const bAll = document.createElement('button');
      bAll.type = 'button';
      bAll.className = 'iter-pill' + (!SELECTED_PHASE ? ' active' : '');
      bAll.dataset.fase = 'ALL';
      bAll.textContent = 'Todas las fases';
      cont.appendChild(bAll);
      uniquePhasesFromData(DATA).forEach(f => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'iter-pill' + (SELECTED_PHASE === f ? ' active' : '');
        b.dataset.fase = f;
        b.textContent = f;
        cont.appendChild(b);
      });
      cont.addEventListener('click', e => {
        const btn = e.target.closest('.iter-pill');
        if (!btn) return;
        [...cont.querySelectorAll('.iter-pill')].forEach(x => x.classList.remove('active'));
        btn.classList.add('active');
        SELECTED_PHASE = btn.dataset.fase === 'ALL' ? null : btn.dataset.fase;
        buildIterFilter((DATA || []).filter(it => !SELECTED_PHASE || it.fase === SELECTED_PHASE));
        renderIterCards();
      });
    }

    function buildIterFilter(list) {
      const cont = document.getElementById('iterFilter');
      cont.innerHTML = '';
      const allBtn = document.createElement('button');
      allBtn.type = 'button';
      allBtn.className = 'iter-pill active';
      allBtn.dataset.iter = 'ALL';
      allBtn.textContent = 'Todas las iteraciones';
      cont.appendChild(allBtn);
      list.forEach(it => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'iter-pill';
        b.dataset.iter = it.iteracion;
        b.textContent = it.iteracion;
        cont.appendChild(b);
      });
      cont.addEventListener('click', e => {
        const btn = e.target.closest('.iter-pill');
        if (!btn) return;
        [...cont.querySelectorAll('.iter-pill')].forEach(x => x.classList.remove('active'));
        btn.classList.add('active');
        renderIterCards(btn.dataset.iter === 'ALL' ? null : btn.dataset.iter);
      });
    }

    function buildMetricFilter() {
      const sel = document.getElementById('metricFilterSelect'),
        btn = document.getElementById('clearMetricFilter');
      const uniques = uniqueMetricsFromData(DATA);
      sel.innerHTML = '<option value="">Todas las métricas</option>' + uniques.map(m => `<option value="${m.id}">#${m.id} - ${m.nombre}</option>`).join('');
      sel.value = SELECTED_METRIC_ID != null ? String(SELECTED_METRIC_ID) : '';
      sel.addEventListener('change', () => {
        const v = sel.value.trim();
        SELECTED_METRIC_ID = v ? Number(v) : null;
        const active = document.querySelector('#iterFilter .iter-pill.active');
        const iter = active ? active.dataset.iter : null;
        renderIterCards(iter === 'ALL' ? null : iter);
      });
      btn.addEventListener('click', () => {
        SELECTED_METRIC_ID = null;
        sel.value = '';
        const active = document.querySelector('#iterFilter .iter-pill.active');
        const iter = active ? active.dataset.iter : null;
        renderIterCards(iter === 'ALL' ? null : iter);
      });
    }

    function renderIterCards(filterIter = null) {
      const wrap = document.getElementById('iterCards');
      if (!wrap) return;

      // 🧹 1. Limpiar gráficos previos (evita residuos de altura)
      Object.values(__CHARTS).forEach(ch => {
        try {
          ch.dispose();
        } catch (e) {}
      });
      for (const k in __CHARTS) delete __CHARTS[k];

      // 🧼 2. Vaciar contenedor
      wrap.innerHTML = '';

      // 🧮 3. Filtros aplicados
      const mode = document.getElementById('modeToggle')?.dataset?.mode || 'donut';
      const source = (DATA || [])
        .filter(it => !SELECTED_PHASE || it.fase === SELECTED_PHASE)
        .filter(it => !filterIter || it.iteracion === filterIter);

      // 🧩 4. Generar tarjetas dinámicas
      source.forEach((it, idx) => {
        const card = document.createElement('div');
        card.className = 'card card-iteracion mb-4';

        // Header
        const header = document.createElement('div');
        header.className = 'card-header d-flex justify-content-between align-items-center';
        header.innerHTML = `
      <div>
        <div class="iter-header-title font-weight-bold">${it.iteracion}</div>
        <div class="iter-subtitle text-muted">Del ${it.inicio} al ${it.fin}</div>
      </div>
      <div class="text-right">
        <span class="badge badge-light">${(it.metricas || []).length} métricas</span>
      </div>`;
        card.appendChild(header);

        // Body
        const body = document.createElement('div');
        body.className = 'card-body';
        const grid = document.createElement('div');
        grid.className = 'metric-grid';

        let metrics = Array.isArray(it.metricas) ? it.metricas.slice() : [];
        if (SELECTED_METRIC_ID != null) {
          const found = metrics.find(mm => Number(mm.id) === Number(SELECTED_METRIC_ID));
          metrics = found ? [found] : [];
        }

        metrics.forEach((m, mIdx) => {
          const mCard = document.createElement('div');
          mCard.className = 'metric-card';
          const idChart = `metric-chart-${idx}-${mIdx}`;
          const umbralPct = Math.round(Math.max(0, 100 - (Number(m.min ?? 100))));

          mCard.innerHTML = `
        <div class="metric-name">#${m.id} - ${m.nombre}</div>
        <div id="${idChart}" class="metric-body-chart"></div>
        <div class="metric-foot">
          Planificado: <b>${m.planned}</b> | Ejecutado: <b>${m.executedReal}</b><br>
          <span class="legend-threshold">
            <span class="legend-dot" style="background:#343a40;width:20px;height:3px;border-radius:0;border:0"></span>
            Umbral: <b>${100 - umbralPct}%</b>
          </span>
        </div>`;

          // Click para filtrar métrica específica
          mCard.addEventListener('click', () => {
            SELECTED_METRIC_ID = Number(m.id);
            const sel = document.getElementById('metricFilterSelect');
            if (sel) sel.value = String(SELECTED_METRIC_ID);
            const active = document.querySelector('#iterFilter .iter-pill.active');
            const iter = active ? active.dataset.iter : null;
            renderIterCards(iter === 'ALL' ? null : iter);
          });

          grid.appendChild(mCard);

          // Render diferido del gráfico
          setTimeout(() => {
            if (mode === 'donut') renderDonut(idChart, m);
            else renderMiniBar(idChart, m);
          }, 0);
        });

        // Mensaje si no hay métricas
        if (!metrics.length) {
          const emp = document.createElement('div');
          emp.className = 'text-muted';
          emp.textContent =
            (SELECTED_METRIC_ID != null) ?
            'Esta iteración no contiene la métrica seleccionada.' :
            'No hay métricas planificadas para esta iteración.';
          body.appendChild(emp);
        } else {
          body.appendChild(grid);
        }

        card.appendChild(body);
        wrap.appendChild(card);
      });

      // 🧾 5. Recalcular layout + forzar resize de todos los gráficos
      requestAnimationFrame(() => {
        // Ajuste visual global
        document.body.style.height = 'auto';
        document.documentElement.style.height = 'auto';

        // Redimensionar todos los ECharts actuales
        Object.values(__CHARTS).forEach(ch => {
          try {
            ch.resize && ch.resize();
          } catch (e) {}
        });

        // Si el footer es fijo, compensar su altura
        const footer = document.querySelector('footer');
        if (footer) {
          const footerH = footer.offsetHeight || 0;
          document.body.style.paddingBottom = footerH + 'px';
        }

        // Evita márgenes colapsados
        wrap.style.marginBottom = '0';
      });
    }

    function renderDonut(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();
      const chart = echarts.init(dom);
      __CHARTS[domId] = chart;
      if (__RO) {
        try {
          __RO.observe(dom);
        } catch (_) {}
      }

      const pctReal = Math.max(0, safePct(metric));
      const ringPct = Math.min(100, pctReal);
      const color = resolveColor(metric, pctReal);
      const restColor = "#e9ecef";
      let ringData;
      if (ringPct >= 100) {
        ringData = [{
          value: 100,
          name: "Cumplido",
          itemStyle: {
            color: new echarts.graphic.RadialGradient(0.5, 0.5, 0.9, [{
                offset: 0,
                color: echarts.color.lift(color, 0.25)
              },
              {
                offset: 1,
                color: color
              }
            ])
          }
        }];
      } else if (ringPct <= 0) {
        ringData = [{
          value: 100,
          name: "Restante",
          itemStyle: {
            color: new echarts.graphic.RadialGradient(0.5, 0.5, 0.9, [{
                offset: 0,
                color: echarts.color.lift(restColor, 0.25)
              },
              {
                offset: 1,
                color: restColor
              }
            ])
          }
        }];
      } else {
        ringData = [{
            value: ringPct,
            name: "Cumplido",
            itemStyle: {
              color: new echarts.graphic.RadialGradient(0.5, 0.5, 0.9, [{
                  offset: 0,
                  color: echarts.color.lift(color, 0.25)
                },
                {
                  offset: 1,
                  color: color
                }
              ]),
              borderColor: '#000',
              borderWidth: 1.5
            }
          },
          {
            value: 100 - ringPct,
            name: "Restante",
            itemStyle: {
              color: new echarts.graphic.RadialGradient(0.5, 0.5, 0.9, [{
                  offset: 0,
                  color: echarts.color.lift(restColor, 0.25)
                },
                {
                  offset: 1,
                  color: restColor
                }
              ]),
              borderColor: '#000',
              borderWidth: 1.5
            }
          }
        ];
      }


      chart.setOption({
        tooltip: {
          trigger: 'item',
          appendToBody: true,
          backgroundColor: 'rgba(255,255,255,0.95)',
          borderColor: '#ccc',
          borderWidth: 1,
          textStyle: {
            color: '#222',
            fontSize: 13
          },
          extraCssText: 'box-shadow:0 2px 8px rgba(0,0,0,.2);border-radius:6px;',
          formatter: () => buildTooltipHtml(metric)
        },
        backgroundColor: 'transparent',
        graphic: [{
          type: 'group',
          left: 'center',
          top: 'center',
          children: [{
              type: 'text',
              z: 100,
              style: {
                text: `${Math.round(pctReal)}%`,
                fontSize: 28,
                fontWeight: 700,
                fill: '#212529',
                textAlign: 'center'
              }
            },
            {
              type: 'text',
              top: 26,
              z: 100,
              style: {
                text: 'Cumplimiento',
                fontSize: 12,
                fill: '#6c757d',
                textAlign: 'center'
              }
            }
          ]
        }],
        series: [{
            type: 'pie',
            radius: ['52%', '90%'],
            startAngle: 90,
            avoidLabelOverlap: true,
            label: {
              show: false
            },
            labelLine: {
              show: false
            },
            itemStyle: {
              borderWidth: 1.5,
              borderColor: '#000'
            },
            data: ringData
          },
          // línea de umbral que "corta" el donut en (1-umbral)
          {
            type: 'pie',
            radius: ['52%', '90%'],
            startAngle: 90,
            silent: true,
            animation: false,
            label: {
              show: false
            },
            labelLine: {
              show: false
            },
            z: 10,
            zlevel: 2,
            data: (function() {
              const threshold = Math.max(0, Math.min(100, Number(metric?.min ?? 100)));
              const wedgeWidth = 1,
                half = wedgeWidth / 2;
              const before = Math.max(0, threshold - half);
              const wedge = Math.min(wedgeWidth, 100 - before);
              const after = Math.max(0, 100 - before - wedge);
              return [{
                  value: before,
                  itemStyle: {
                    color: 'rgba(0,0,0,0)'
                  }
                },
                {
                  value: wedge,
                  itemStyle: {
                    color: '#343a40',
                    shadowColor: 'rgba(0,0,0,0.25)',
                    shadowBlur: 5
                  }
                },
                {
                  value: after,
                  itemStyle: {
                    color: 'rgba(0,0,0,0)'
                  }
                }
              ];
            })()
          }
        ],
        animation: true,
        animationDuration: 800,
        animationEasing: 'cubicOut' // igual que las iteraciones
      });
    }



    function renderMiniBar(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();
      const chart = echarts.init(dom);
      __CHARTS[domId] = chart;
      if (__RO) {
        try {
          __RO.observe(dom);
        } catch (_) {}
      }

      const pctReal = Math.max(0, safePct(metric));
      const executedCol = resolveColor(metric, pctReal);
      const VISIBLE_MAX = 120,
        OVERFLOW_TOP = 130;
      const yMax = pctReal > VISIBLE_MAX ? OVERFLOW_TOP : VISIBLE_MAX;
      const baseVal = Math.min(100, pctReal);
      const overflowVal = pctReal > 100 ? Math.max(0, Math.min(OVERFLOW_TOP, pctReal) - 100) : 0;
      const minLine = Math.max(0, Math.min(yMax, Number(metric?.min ?? 100)));

      chart.setOption({
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          },
          appendToBody: true,
          backgroundColor: 'rgba(255,255,255,0.95)',
          borderColor: '#ccc',
          borderWidth: 1,
          textStyle: {
            color: '#222',
            fontSize: 13
          },
          extraCssText: 'box-shadow:0 2px 8px rgba(0,0,0,.2);border-radius:6px;',
          formatter: () => buildTooltipHtml(metric)
        },
        grid: {
          left: 40,
          right: 60,
          top: 20,
          bottom: 28,
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: ['Planificado', 'Ejecutado'],
          axisLine: {
            lineStyle: {
              color: '#d7dbe8'
            }
          },
          axisTick: {
            show: false
          },
          axisLabel: {
            color: '#6c7a92',
            fontWeight: 600
          }
        },
        yAxis: {
          type: 'value',
          min: 0,
          max: yMax,
          splitLine: {
            lineStyle: {
              type: 'dashed',
              color: '#e5e9f2'
            }
          },
          axisLabel: {
            color: '#6c7a92',
            formatter: '{value}%'
          }
        },
        series: [{ // plan=100% con borde negro
            name: 'Planificado',
            type: 'bar',
            barWidth: 34,
            itemStyle: {
              borderRadius: 0,
              color: '#a8b5d7',
              borderColor: '#000',
              borderWidth: 1.5
            },
            label: {
              show: true,
              position: 'top',
              fontWeight: 700,
              color: '#1b2533',
              formatter: (p) => p.dataIndex === 0 ? '100%' : ''
            },
            data: [100, null]
          },
          { // marco para 100% de ejecutado
            name: 'Marco 100',
            type: 'bar',
            barWidth: 34,
            barGap: '30%',
            itemStyle: {
              color: 'rgba(0,0,0,0)',
              borderColor: '#000',
              borderWidth: 1.5,
              borderRadius: 0
            },
            emphasis: {
              disabled: true
            },
            silent: true,
            data: [null, 100]
          },
          { // ejecutado base (0..100)
            name: 'Ejecutado',
            type: 'bar',
            barWidth: 34,
            barGap: '-100%',
            stack: 'exec',
            itemStyle: {
              borderRadius: 0,
              color: executedCol,
              borderColor: '#000',
              borderWidth: 1.5
            },
            label: {
              show: true,
              position: 'top',
              fontWeight: 700,
              color: '#1b2533',
              formatter: (p) => p.dataIndex === 1 ? `${pctReal}%` : ''
            },
            markLine: {
              symbol: 'none',
              lineStyle: {
                type: 'dashed',
                color: '#0d6efd',
                width: 2
              },
              data: [{
                  yAxis: 100,
                  lineStyle: {
                    type: 'dashed',
                    color: '#0d6efd',
                    width: 2
                  },
                  label: {
                    show: true,
                    formatter: '100%',
                    position: 'end',
                    distance: 6,
                    color: '#0d6efd',
                    backgroundColor: '#fff',
                    padding: [1, 4],
                    borderRadius: 3
                  }
                },
                {
                  yAxis: minLine,
                  lineStyle: {
                    type: 'solid',
                    color: '#343a40',
                    width: 1.5
                  },
                  label: {
                    show: false
                  }
                }
              ]
            },
            data: [null, baseVal]
          },
          { // overflow >100%
            name: 'Overflow',
            type: 'bar',
            barWidth: 34,
            barGap: '-100%',
            stack: 'exec',
            itemStyle: {
              color: executedCol,
              opacity: .35,
              borderRadius: 0,
              borderColor: '#000',
              borderWidth: 1.5
            },
            emphasis: {
              disabled: true
            },
            data: [null, overflowVal]
          }
        ],
        animation: true
      });
    }

    // ===== Resumen global igual al original =====
    function computeAndRenderGlobalSummary() {
      // Evitar duplicados: métrica+iteración
      const seen = new Set();
      const all = [];
      (DATA || []).forEach(it => {
        (it.metricas || []).forEach(m => {
          const key = `${it.iteracion}-${m.id}`;
          if (!seen.has(key)) {
            seen.add(key);
            all.push(m);
          }
        });
      });

      const setText = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = v;
      };

      if (!all.length) {
        setText('avgComplianceValue', '--%');
        ['countOverPlanValue', 'countInThresholdValue', 'countNoExecValue', 'countExact100Value', 'countBelowThresholdValue']
        .forEach(id => setText(id, '0'));
        return;
      }

      let totalPct = 0,
        over = 0,
        exact = 0,
        inRange = 0,
        below = 0,
        noExec = 0;

      all.forEach(m => {
        const plan = Number(m.planned) || 0;
        const ejec = Number(m.executedReal) || 0;
        const pct = Number(m.executed) || 0;
        const minLine = 100 - (Number(m.umbral_desviacion) || 0); // si preferís derivarlo directo

        totalPct += pct;
        if (plan > 0 && ejec === 0) noExec++;
        else if (pct === 100) exact++;
        else if (pct > 100) over++;
        else if (pct >= minLine) inRange++;
        else below++;
      });

      const avg = Math.round(totalPct / all.length);

      setText('avgComplianceValue', `${avg}%`);
      setText('countOverPlanValue', over);
      setText('countExact100Value', exact);
      setText('countInThresholdValue', inRange);
      setText('countBelowThresholdValue', below);
      setText('countNoExecValue', noExec);

      renderGlobalPieChart({
          over,
          exact,
          inRange,
          below,
          noExec
        },
        avg,
        <?= (int)$totalMetricasPlanificadas ?>
      );

    }

    function renderGlobalPieChart(stats, avg, totalMetricas) {
      const dom = document.getElementById('chartDistribucionGlobal');
      if (!dom) return;

      const chart = echarts.init(dom);

      const total = Object.values(stats).reduce((a, b) => a + b, 0);

      chart.setOption({
        tooltip: {
          trigger: 'item',
          backgroundColor: 'rgba(255,255,255,0.95)',
          borderColor: '#ccc',
          borderWidth: 1,
          textStyle: {
            color: '#222',
            fontSize: 13
          },
          extraCssText: 'box-shadow:0 2px 8px rgba(0,0,0,.2);border-radius:6px;',
          formatter: (p) => `
  <b>${p.name}</b><br>
  ${p.percent.toFixed(1)}% del total<br>
  <span style="color:#6c757d;">(${p.value} métricas)</span>
`

        },
        legend: {
          bottom: 0,
          textStyle: {
            color: '#555',
            fontSize: 12
          }
        },
        graphic: [{
          type: 'group',
          left: 'center',
          top: '30%',
          children: [{
              type: 'text',
              top: -20,
              style: {
                text: `${avg}%`,
                fontSize: 30,
                fontWeight: 700,
                fill: '#212529',
                textAlign: 'center',
                textVerticalAlign: 'middle',
                textShadowColor: 'rgba(0,0,0,0.1)',
                textShadowBlur: 2
              }
            },
            {
              type: 'text',
              top: 8,
              style: {
                text: 'Cumplimiento',
                fontSize: 14,
                fill: '#6c757d',
                textAlign: 'center',
                textVerticalAlign: 'middle'
              }
            },
            {
              type: 'text',
              top: 28,
              style: {
                text: `(${totalMetricas} métricas)`,
                fontSize: 12,
                fill: '#6c757d', // en lugar de '#adb5bd'
                textAlign: 'center',
                textVerticalAlign: 'middle'
              }
            }
          ]
        }],

        series: [{
          type: 'pie',
          radius: ['42%', '75%'],
            center: ['50%', '38%'],
          label: {
            formatter: '{b}\n{d}%',
            fontSize: 12
          },
          animationDuration: 900,
          animationEasing: 'cubicOut',
          data: [{
              value: stats.over,
              name: 'Supera plan',
              itemStyle: {
                color: 'rgba(140,220,170,0.9)'
              }
            },
            {
              value: stats.exact,
              name: 'Cumple (=100%)',
              itemStyle: {
                color: 'rgba(40,167,69,0.95)'
              }
            },
            {
              value: stats.inRange,
              name: 'Dentro del umbral',
              itemStyle: {
                color: 'rgba(255,193,7,0.9)'
              }
            },
            {
              value: stats.below,
              name: 'Fuera del umbral',
              itemStyle: {
                color: 'rgba(220,53,69,0.9)'
              }
            },
            {
              value: stats.noExec,
              name: 'Sin ejecución',
              itemStyle: {
                color: '#6c757d'

              }
            }
          ]
        }]
      });
    }


    // ===== Toggle modo =====
    (function() {
      const btn = document.getElementById('modeToggle');
      if (!btn) return;
      btn.addEventListener('click', () => {
        const current = btn.dataset.mode || 'donut';
        const next = current === 'donut' ? 'bar' : 'donut';
        btn.dataset.mode = next;
        btn.classList.toggle('off', next === 'donut');
        btn.innerHTML = next === 'donut' ?
          '<i class="oi oi-pie-chart"></i> Ver como barras' :
          '<i class="oi oi-bar-chart"></i> Ver como donuts';
        const active = document.querySelector('#iterFilter .iter-pill.active');
        const iter = active ? active.dataset.iter : null;
        renderIterCards(iter === 'ALL' ? null : iter);
      });
    })();

    // ===== Init =====
    document.addEventListener('DOMContentLoaded', () => {
      buildMetricFilter();
      buildPhaseTabs();
      const scoped = (DATA || []).filter(it => !SELECTED_PHASE || it.fase === SELECTED_PHASE);
      buildIterFilter(scoped);
      renderIterCards(null);
      computeAndRenderGlobalSummary();
    });
  </script>

  <?php include_once '../gui/footer.php'; ?>
</body>

</html>