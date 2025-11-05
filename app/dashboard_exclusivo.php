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
JOIN iteracion i         ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = $idProyecto
JOIN fase f ON f.id_fase = i.id_fase
JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
WHERE pf.id_proyecto = $idProyecto
";
$rMetricasPlanificadas = $cn->query($qMetricasPlanificadas);
$totalMetricasPlanificadas = ($rMetricasPlanificadas && $rMetricasPlanificadas->num_rows) ? (int)$rMetricasPlanificadas->fetch_assoc()['total'] : 0;

// Cantidad de tipos de métricas distintas utilizadas en el proyecto
$qMetricasDistintas = "
SELECT COUNT(DISTINCT mi.id_metrica) AS total
FROM metrica_iteracion mi
JOIN iteracion i         ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = $idProyecto
JOIN fase f              ON i.id_fase = f.id_fase
JOIN proyecto_fase pf    ON pf.id_fase = f.id_fase AND pf.id_proyecto = $idProyecto
WHERE pf.id_proyecto = $idProyecto
";
$rMetricasDistintas = $cn->query($qMetricasDistintas);
$totalMetricasDistintas = ($rMetricasDistintas && $rMetricasDistintas->num_rows) ? (int)$rMetricasDistintas->fetch_assoc()['total'] : 0;

$qIters = "SELECT COUNT(DISTINCT i.id_iteracion) AS total
           FROM iteracion i
           JOIN fase f ON f.id_fase = i.id_fase
           JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
           JOIN metrica_iteracion mi ON mi.id_iteracion = i.id_iteracion
           WHERE i.id_proyecto = $idProyecto";
$rIters = $cn->query($qIters);
$totalIteraciones = ($rIters && $rIters->num_rows) ? (int)$rIters->fetch_assoc()['total'] : 0;

// Iteraciones creadas (tengan o no métricas planificadas)
$qItersAll = "SELECT COUNT(*) AS total
           FROM iteracion i
           WHERE i.id_proyecto = $idProyecto";
$rItersAll = $cn->query($qItersAll);
$totalIteracionesCreadas = ($rItersAll && $rItersAll->num_rows) ? (int)$rItersAll->fetch_assoc()['total'] : 0;
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
JOIN metrica m           ON mi.id_metrica = m.id_metrica
JOIN iteracion i         ON mi.id_iteracion = i.id_iteracion AND i.id_proyecto = $idProyecto
JOIN fase f              ON i.id_fase = f.id_fase
JOIN proyecto_fase pf    ON pf.id_fase = f.id_fase AND pf.id_proyecto = $idProyecto
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

    #filterBar {
      position: sticky;
      top: 90px;
      /* 🔹 deja espacio debajo del navbar */
      z-index: 1025;
      /* un poco menos que la navbar */
      background: #fff;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      border-bottom: 1px solid #eee;
      transition: box-shadow 0.3s ease;
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

    .chip-dot {
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

    .card-global-vision {
      margin-top: -10px;
      /* sube la card un poco */
    }

    .card-global-vision .card-body {
      padding-top: 0.15rem !important; /* menos espacio entre título y gráfico */
      padding-bottom: 0.25rem !important;
    }

    #chartDistribucionGlobal {
      height: 340px !important; /* +40px para que entren las leyendas completas */
      /* mantiene proporción visual */
      margin-top: -5px !important;
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

    /* Sticky toolbar for filters */
    .toolbar-sentinel {
      height: 1px;
    }

    .dashboard-toolbar {
      position: sticky;
      top: var(--toolbar-top, 72px);
      /* keep below fixed navbar (dinámico) */
      z-index: 1030;
      /* above charts */
      background: #fff;
      transition: box-shadow .2s ease, border-color .2s ease, background-color .2s ease, padding .2s ease;
      padding: .5rem .75rem;
      /* compacto */
    }

    .dashboard-toolbar.stuck {
      box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
      border-color: rgba(0, 0, 0, .08) !important;
    }

    @media (max-width: 575.98px) {
      .dashboard-toolbar {
        top: var(--toolbar-top, 56px);
      }
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
          <p class="text-muted mb-0">
            No existe un proyecto con el identificador <b>ID <?= (int)$idProyecto ?></b>.<br>
            Verifique el parámetro o cree un nuevo proyecto antes de continuar.
          </p>
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
              <span class="stat-label">Iteraciones con métricas planificadas</span>
              <div id="totalIteracionesValue" class="stat-value"><?= (int)$totalIteraciones ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resumen global (cálculo en JS igual al original) -->
    <div class="card mb-3 shadow-sm border-0 card-global-vision">
      <h6 class="text-primary font-weight-bold mb-1 text-center">
        Visión General del Proyecto
      </h6>

      <div class="card-body p-2">
        <!-- Gráfico de distribución global de métricas -->
  <div id="chartDistribucionGlobal" data-title="Visión General del Proyecto" style="height:340px; margin-top:-12px;"></div>
      </div>
    </div>


    <!-- Toolbar filtros compacta (sticky) con expandible dentro -->
    <div id="toolbarSentinel" class="toolbar-sentinel"></div>
    <div class="card dashboard-toolbar mb-2 rounded bg-white border">
      <div class="d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
          <label for="metricFilterSelect" class="small text-muted mb-0 mr-2">Métrica:</label>
          <select id="metricFilterSelect" class="form-control form-control-sm" style="min-width:190px;">
            <option value="">Todas las métricas</option>
          </select>
          <button id="clearMetricFilter" type="button" class="btn btn-outline-secondary btn-sm ml-2 btn-clear" title="Limpiar filtro">
            <span class="oi oi-x mr-1"></span> Limpiar Filtros
          </button>
          <button id="exportBtn" type="button" class="btn btn-outline-primary btn-sm ml-2" title="Exportar">
            <span class="oi oi-data-transfer-download mr-1"></span> Exportar
          </button>
        </div>
        <div class="d-flex align-items-center mt-2 mt-md-0">
          <button id="modeToggle" class="btn btn-primary btn-sm mr-2" data-mode="donut">
            <i class="oi oi-bar-chart mr-1"></i> Ver como barras
          </button>
          <button id="toggleToolbar" class="btn btn-outline-secondary btn-sm" data-toggle="collapse" data-target="#toolbarExpanded" aria-expanded="true" aria-controls="toolbarExpanded" title="Mostrar/ocultar filtros avanzados">
            <i class="oi oi-chevron-top"></i>
          </button>
        </div>
      </div>
      <div id="toolbarExpanded" class="toolbar-expanded collapse show w-100 mt-2">
        <div class="legend-metricas pt-2 border-top">
          <span class="legend-item"><span class="legend-dot" style="background:#28a745"></span>Se cumplió</span>
          <span class="legend-item"><span class="legend-dot" style="background:#8cdcab"></span>Se superó</span>
          <span class="legend-item"><span class="legend-dot" style="background:#ffc107"></span>Dentro del umbral</span>
          <span class="legend-item"><span class="legend-dot" style="background:#64aaff"></span>Planificación = 0</span>
          <span class="legend-item"><span class="legend-dot" style="background:#dc3545"></span>Debajo del umbral</span>
          <span class="legend-item"><span class="legend-dash"></span>Progreso de la iteración</span>
        </div>
        <!-- Tabs por fase + filtro iteración -->
        <div id="faseTabs" class="mb-1"></div>
        <div id="iterFilter" class="mb-3"></div>
      </div>
    </div>


    <!-- Contenedor de tarjetas por iteración -->
    <div id="iterCards"></div>

    <!-- Modal de Exportación (rediseñado jerárquico con checklists) -->
    <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-1" id="exportModalLabel">Exportar tablero</h5>
              <div class="text-muted small">Seleccione el alcance y formato de exportación según las fases, iteraciones y métricas del proyecto actual.</div>
            </div>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" style="background:#fff;">
            <div id="exportAlert" class="alert alert-warning d-none mb-3"></div>

            <!-- Formato -->
            <div class="border rounded p-2 mb-2">
              <div class="font-weight-bold mb-1">Formato</div>
              <div class="custom-control custom-radio">
                <input type="radio" id="fmtPng" name="exportFmt" class="custom-control-input" value="png" checked>
                <label class="custom-control-label" for="fmtPng"><span class="oi oi-image mr-1"></span> PNG (imagen)</label>
              </div>
              <div class="custom-control custom-radio">
                <input type="radio" id="fmtPdf" name="exportFmt" class="custom-control-input" value="pdf">
                <label class="custom-control-label" for="fmtPdf"><span class="oi oi-document mr-1"></span> PDF</label>
              </div>
            </div>

            <!-- Fases -->
            <div id="exportPhaseGroup" class="border rounded p-2 mb-2" style="display:none;">
              <div class="font-weight-bold mb-1">Fases</div>
              <div id="exportPhaseList" class="d-flex flex-column"></div>
              <div id="exportPhaseEmpty" class="text-muted small pl-3 d-none">Sin datos disponibles</div>
            </div>

            <!-- Iteraciones -->
            <div id="exportIterGroup" class="border rounded p-2 mb-2" style="display:none;">
              <div class="font-weight-bold mb-1">Iteraciones</div>
              <div id="exportIterList" class="d-flex flex-column"></div>
              <div id="exportIterEmpty" class="text-muted small pl-3 d-none">Sin datos disponibles</div>
            </div>

            <!-- Métricas -->
            <div id="exportMetricGroup" class="border rounded p-2 mb-2" style="display:none;">
              <div class="font-weight-bold mb-1">Métricas</div>
              <div id="exportMetricList" class="d-flex flex-column"></div>
              <div id="exportMetricEmpty" class="text-muted small pl-3 d-none">Sin datos disponibles</div>
            </div>

            <!-- Opciones adicionales (solo PNG) -->
            <div id="exportPngOptions" class="border rounded p-2 mb-2">
              <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" class="custom-control-input" id="exportIncluirGlobal" checked>
                <label class="custom-control-label" for="exportIncluirGlobal">Incluir "Visión General del Proyecto"</label>
              </div>
              <div id="exportChartTypeGroup">
                <div class="font-weight-bold mb-1">Tipo de gráficos</div>
                <div class="custom-control custom-radio">
                  <input type="radio" id="chartTypeDonut" name="exportChartType" class="custom-control-input" value="donut" checked>
                  <label class="custom-control-label" for="chartTypeDonut"><span class="oi oi-pie-chart mr-1"></span> Donuts</label>
                </div>
                <div class="custom-control custom-radio">
                  <input type="radio" id="chartTypeBar" name="exportChartType" class="custom-control-input" value="bar">
                  <label class="custom-control-label" for="chartTypeBar"><span class="oi oi-bar-chart mr-1"></span> Barras</label>
                </div>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="exportConfirmBtn">
              <span class="oi oi-data-transfer-download mr-1"></span> Exportar
            </button>
          </div>
        </div>
      </div>
    </div>
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

    // Progreso temporal de iteración en % (0..100)
    function pctTiempoIter(inicio, fin) {
      const s = new Date(inicio).getTime();
      const e = new Date(fin).getTime();
      const now = Date.now();
      if (!isFinite(s) || !isFinite(e) || e <= s) return 100;
      const pct = ((now - s) / (e - s)) * 100;
      return Math.max(0, Math.min(100, pct));
    }

    // Cargador robusto de ECharts con múltiples CDNs + fallback local
    function loadEchartsIfNeeded() {
      return new Promise((resolve) => {
        if (window.echarts) return resolve(true);
        const trySrcs = [
          'https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js',
          'https://unpkg.com/echarts@5/dist/echarts.min.js',
          'https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js',
          '../lib/echarts.min.js'
        ];
        let idx = 0;
        const tryNext = () => {
          if (idx >= trySrcs.length) return resolve(!!window.echarts);
          const src = trySrcs[idx++];
          const s = document.createElement('script');
          s.src = src;
          s.async = true;
          s.onload = () => resolve(true);
          s.onerror = () => tryNext();
          document.head.appendChild(s);
        };
        tryNext();
      });
    }

    function showEchartsWarningOnce() {
      if (window.__echartsWarnShown) return;
      window.__echartsWarnShown = true;
      const toolbar = document.querySelector('.dashboard-toolbar') || document.querySelector('.card.dashboard-toolbar');
      const note = document.createElement('div');
      note.className = 'alert alert-warning mt-2 mb-0';
      note.innerHTML = '<b>Atención:</b> No se pudo cargar ECharts. Verificá Internet o agregá <code>lib/echarts.min.js</code>.';
      if (toolbar && toolbar.parentNode) toolbar.parentNode.insertBefore(note, toolbar.nextSibling);
    }

    // Fallbacks simples cuando ECharts no está disponible
    function renderDonutFallback(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const info = computePctAndNote(metric);
      const pct = Number(info.pct) || 0;
      dom.innerHTML = '';
      const wrap = document.createElement('div');
      wrap.style.display = 'flex';
      wrap.style.flexDirection = 'column';
      wrap.style.alignItems = 'center';
      wrap.style.justifyContent = 'center';
      wrap.style.height = '100%';
      const val = document.createElement('div');
      val.style.fontSize = '28px';
      val.style.fontWeight = '700';
      val.style.color = '#212529';
      val.textContent = `${Math.round(pct)}%`;
      const sub = document.createElement('div');
      sub.style.fontSize = '12px';
      sub.style.color = '#6c757d';
      sub.textContent = 'Cumplimiento';
      wrap.appendChild(val);
      wrap.appendChild(sub);
      dom.appendChild(wrap);
    }

    function renderMiniBarFallback(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const info = computePctAndNote(metric);
      const pct = Number(info.pct) || 0;
      dom.innerHTML = '';
      const barWrap = document.createElement('div');
      barWrap.style.width = '100%';
      barWrap.style.height = '12px';
      barWrap.style.background = '#e9ecef';
      barWrap.style.border = '1px solid #000';
      const bar = document.createElement('div');
      bar.style.height = '100%';
      bar.style.width = `${Math.min(100, Math.max(0, pct))}%`;
      bar.style.background = resolveColor(metric, pct);
      barWrap.appendChild(bar);
      const label = document.createElement('div');
      label.style.textAlign = 'center';
      label.style.marginTop = '6px';
      label.style.fontWeight = '700';
      label.textContent = `${Math.round(pct)}%`;
      dom.appendChild(barWrap);
      dom.appendChild(label);
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
      let html = `<b>${metric?.nombre??''}</b><br>`;
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

    // ===== Registro global de charts + ResizeObserver (con gracia para animaciones) =====
    const __CHARTS = Object.create(null);
    const __CHART_CREATED_AT = Object.create(null);
    let __RO_DEBOUNCE = null;
    const RESIZE_DEBOUNCE_MS = 200;
    const ANIM_GRACE_MS = 800; // no resizes durante la animación inicial

    function resizeChartsSafe() {
      const now = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
      Object.entries(__CHARTS).forEach(([id, ch]) => {
        const createdAt = __CHART_CREATED_AT[id] || 0;
        if (now - createdAt < ANIM_GRACE_MS) return; // evitar cortar animación
        try {
          ch.resize && ch.resize();
        } catch (e) {}
      });
    }

    function debounceResize() {
      if (__RO_DEBOUNCE) clearTimeout(__RO_DEBOUNCE);
      __RO_DEBOUNCE = setTimeout(resizeChartsSafe, RESIZE_DEBOUNCE_MS);
    }

    const __RO = (typeof ResizeObserver !== 'undefined') ? new ResizeObserver(debounceResize) : null;
    window.addEventListener('resize', debounceResize);

    // ===== Datos desde PHP =====
    const DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    const HAY_ITERACIONES = <?= ($totalIteracionesCreadas > 0 ? 'true' : 'false'); ?>;
    const PROYECTO_ID = <?= (int)$idProyecto ?>;
    // Selección global del modal de exportación: arrays vacíos significan "todo"
    const exportSelection = {
      fases: [],
      iteraciones: [],
      metricas: [],
      incluirGlobal: true
    };
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
      sel.innerHTML = '<option value="">Todas las métricas</option>' + uniques.map(m => `<option value="${m.id}">${m.nombre}</option>`).join('');
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

      // VALIDACIONES GENERALES
      // ==========================
      if (!Array.isArray(DATA) || DATA.length === 0) {
        // ⚠️ Caso 2: Hay iteraciones pero sin metricas planificadas
        if (HAY_ITERACIONES) {
          wrap.innerHTML = `
      <div class="col-12">
        <div class="card h-100 d-flex flex-column justify-content-center align-items-center text-center"
             style=\"border: 1px dashed rgba(13,110,253,0.25); background: rgba(13,110,253,0.05); min-height:340px;\">
          <div class="p-4">
            <i class="oi oi-bar-chart mb-3" style="font-size:2rem; color:#0d6efd;"></i>
            <h6 class="text-primary font-weight-bold mb-2">Faltan métricas asociadas</h6>
            <p class="text-muted mb-0" style="max-width:460px;">
              Espere a que el líder del proyecto o gerente de calidad vincule métrica/s a alguna iteración.
            </p>
          </div>
        </div>
      </div>`;
        } else {
          // ❌ Caso 1: No hay iteraciones 
          wrap.innerHTML = `
      <div class=\"col-12\"> 
        <div class=\"card h-100 d-flex flex-column justify-content-center align-items-center text-center\"
             style=\"border: 1px dashed rgba(108,117,125,0.25); background: rgba(248,249,250,0.7); min-height:340px;\">
          <div class=\"p-4\">
            <i class=\"oi oi-clock mb-3\" style=\"font-size:2rem; color:#6c757d;\"></i>
            <h6 class=\"text-secondary font-weight-bold mb-2\">No hay iteraciones cargadas</h6>
            <p class=\"text-muted mb-0\" style=\"max-width:420px;\">
              Espere al líder del proyecto.
            </p>
          </div>
        </div>
      </div>`;
        }
        return;
      }

      // 🧹 Limpiar solo los charts de iteraciones, no el global
      Object.entries(__CHARTS).forEach(([id, ch]) => {
        if (id === 'chartDistribucionGlobal') return; // 🛑 conservar el gráfico de visión general
        try {
          ch.dispose();
        } catch (e) {}
        delete __CHARTS[id];
      });



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
  <div class="metric-name">${m.nombre}</div>
  <div id="${idChart}" class="metric-body-chart" data-fase="${String(it.fase)}" data-iter="${it.iteracion}" data-metric-id="${String(m.id)}" data-title="${m.nombre} | ${it.iteracion}"></div>
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

          // 🧩 Inicialización segura: programar render tras anexar al DOM
          setTimeout(() => {
            try {
              const el = document.getElementById(idChart);
              if (!el) return;
              const rect = el.getBoundingClientRect();
              const doRender = () => {
                if (mode === 'donut') {
                  if (window.echarts) renderDonut(idChart, m);
                  else renderDonutFallback(idChart, m);
                } else {
                  if (window.echarts) renderMiniBar(idChart, m, it.inicio, it.fin);
                  else renderMiniBarFallback(idChart, m);
                }
              };
              if (rect.width > 0 && rect.height > 0) doRender();
              else requestAnimationFrame(doRender);
            } catch (err) {
              console.warn("Error inicializando gráfico", idChart, err);
            }
          }, 150); // breve retardo para asegurar que el grid esté en el DOM


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

      // Ajustes de layout sin interferir con animaciones: no forzar resize inmediato
      requestAnimationFrame(() => {
        document.body.style.height = 'auto';
        document.documentElement.style.height = 'auto';
        const footer = document.querySelector('footer');
        if (footer) {
          const footerH = footer.offsetHeight || 0;
          document.body.style.paddingBottom = footerH + 'px';
        }
        wrap.style.marginBottom = '0';
      });

    }



    function renderDonut(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      if (typeof echarts === 'undefined') {
        renderDonutFallback(domId, metric);
        return;
      }
      let chart;
      try {
        const prev = echarts.getInstanceByDom(dom);
        if (prev) prev.dispose();
        chart = echarts.init(dom);
        __CHARTS[domId] = chart;
        __CHART_CREATED_AT[domId] = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
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
            children: [
              {
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
          animationEasing: 'cubicOut', // igual que las iteraciones
          animationDurationUpdate: 500,
          animationEasingUpdate: 'cubicOut'
        });
      } catch (e) {
        try {
          chart && chart.dispose && chart.dispose();
        } catch (_) {}
        renderDonutFallback(domId, metric);
      }
    }



    function renderMiniBar(domId, metric, ini = null, fin = null) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      if (typeof echarts === 'undefined') {
        renderMiniBarFallback(domId, metric);
        return;
      }
      let chart;
      try {
        const prev = echarts.getInstanceByDom(dom);
        if (prev) prev.dispose();
        chart = echarts.init(dom);
        __CHARTS[domId] = chart;
        __CHART_CREATED_AT[domId] = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
        if (__RO) {
          try {
            __RO.observe(dom);
          } catch (_) {}
        }

        const pctReal = Math.max(0, safePct(metric));
        const executedCol = resolveColor(metric, pctReal);
        const VISIBLE_MAX = 120,
          OVERFLOW_TOP = 130;
        const HEADROOM = 5; // evita recortes de etiquetas en el borde superior
        const yMax = pctReal > VISIBLE_MAX ? OVERFLOW_TOP : VISIBLE_MAX;
        const baseVal = Math.min(100, pctReal);
        const overflowVal = pctReal > 100 ? Math.max(0, Math.min(OVERFLOW_TOP, pctReal) - 100) : 0;
        const minLine = Math.max(0, Math.min(yMax, Number(metric?.min ?? 100)));
        const timePct = Math.max(0, Math.min(yMax, pctTiempoIter(ini, fin)));

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
            left: 64,
            right: 76, // más espacio para etiqueta de línea punteada
            top: 28, // mayor margen superior para evitar clipping
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
              interval: 0, // mostrar SIEMPRE ambas etiquetas (Planificado y Ejecutado)
              // Colorea cada etiqueta para que coincida con Planificado (azul suave) y Ejecutado (color semáforo)
              formatter: function(value, idx) {
                return idx === 0 ? '{plan|Planificado}' : '{exec|Ejecutado}';
              },
              rich: {
                plan: {
                  color: '#000000ff',
                  fontWeight: 500
                },
                exec: {
                  color: '#000000ff',
                  fontWeight: 500
                }
              }
            }
          },
          yAxis: {
            type: 'value',
            min: 0,
            max: yMax + HEADROOM,
            name: 'Cumplimiento (%)',
            nameLocation: 'middle',
            nameGap: 52,
            nameTextStyle: {
              color: '#6c7a92',
              fontWeight: 600
            },
            splitLine: {
              lineStyle: {
                type: 'dashed',
                color: '#e5e9f2'
              }
            },
            axisLabel: {
              color: '#6c7a92',
              margin: 8,
              formatter: '{value}%'
            }
          },
          series: [{ // plan=100% con borde negro
              name: 'Planificado',
              type: 'bar',
              barWidth: 34,
              animationDelay: 0,
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
              animationDelay: 80,
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
              animationDelay: 160,
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
                    yAxis: timePct,
                    lineStyle: {
                      type: 'dashed',
                      color: '#0d6efd',
                      width: 2
                    },
                    label: {
                      show: true,
                      formatter: () => `${Math.round(timePct)}%`,
                      position: 'end', // anclada al fin de la línea
                      distance: 6,
                      color: '#0d6efd',
                      backgroundColor: '#ffffff',
                      padding: [2, 6],
                      borderRadius: 4,
                      fontWeight: 600
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
              animationDelay: 240,
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
          animation: true,
          animationDuration: 700,
          animationEasing: 'cubicOut',
          animationDurationUpdate: 500,
          animationEasingUpdate: 'cubicOut'
        });
      } catch (e) {
        try {
          chart && chart.dispose && chart.dispose();
        } catch (_) {}
        renderMiniBarFallback(domId, metric);
      }
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
        const minLine = Number(m.min ?? 100);

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

      // 🔹 Ajustes de tamaño del canvas
      dom.style.height = '300px'; // altura estable
      // Evitamos offsets "mágicos"; centrado basado en la geometría del gráfico

      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();
      const chart = echarts.init(dom);
      __CHARTS.chartDistribucionGlobal = chart;
      if (__RO) {
        try {
          __RO.observe(dom);
        } catch (_) {}
      }

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

        // 🔹 Leyendas más abajo y sin superponer el gráfico
        legend: {
          bottom: -5, // mantener dentro del canvas y evitar recortes
          itemGap: 12,
          textStyle: {
            color: '#555',
            fontSize: 12
          }
        },

        // 🔹 Bloque único de texto centrado geométricamente
        graphic: [{
          type: 'text',
          left: 'center',
          top: 'middle',
          position: [0, 0],
          // centrado geométrico estable (pantalla y export)
          z: 100,
          silent: true,
          bounding: 'raw',
          style: {
            text: `{val|${avg}%}` + '\n' + `{sub|Cumplimiento}` + '\n' + `{cnt|(${totalMetricas} métricas)}`,
            rich: {
              val: {
                fontSize: 34,
                fontWeight: 700,
                fill: '#212529',
                lineHeight: 36
              },
              sub: {
                fontSize: 14,
                fill: '#6c757d',
                lineHeight: 18
              },
              cnt: {
                fontSize: 12,
                fill: '#6c757d',
                lineHeight: 16
              }
            },
            align: 'center',
            verticalAlign: 'middle'
          }
        }],

        series: [{
          type: 'pie',
          // centro geométrico
          radius: ['45%', '70%'],
          center: ['50%', '50%'],
          label: {
            formatter: '{b}\n{d}%',
            fontSize: 12
          },
          animationDuration: 900,
          animationEasing: 'cubicOut',
          itemStyle: {
            borderColor: '#000',
            borderWidth: 2,
            borderJoin: 'round'
          },
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
          '<i class="oi oi-bar-chart"></i> Ver como barras' :
          '<i class="oi oi-pie-chart"></i> Ver como donuts';
        const active = document.querySelector('#iterFilter .iter-pill.active');
        const iter = active ? active.dataset.iter : null;
        renderIterCards(iter === 'ALL' ? null : iter);
      });
    })();

    document.addEventListener('DOMContentLoaded', async () => {
      const ok = await loadEchartsIfNeeded();
      if (!ok || !window.echarts) showEchartsWarningOnce();

      buildMetricFilter();
      buildPhaseTabs();
      const scoped = (DATA || []).filter(it => !SELECTED_PHASE || it.fase === SELECTED_PHASE);
      buildIterFilter(scoped);
      renderIterCards(null);
      // Exportar: abre modal y maneja envío (checklists jerárquicos)
      (function setupExportModal() {
        const btn = document.getElementById('exportBtn');
        if (!btn) return;

        const $phaseGroup = $('#exportPhaseGroup');
        const $iterGroup = $('#exportIterGroup');
        const $metricGroup = $('#exportMetricGroup');
        const $phaseList = $('#exportPhaseList');
        const $iterList = $('#exportIterList');
        const $metricList = $('#exportMetricList');
        const $alertBox = $('#exportAlert');

        function hideAllGroups() {
          $iterGroup.hide();
          $metricGroup.hide();
        }

        function showAlert(msg) {
          if (!msg) return $alertBox.addClass('d-none').text('');
          $alertBox.removeClass('d-none').text(msg);
        }

        function unique(arr) {
          return Array.from(new Set(arr));
        }

        function sortByTextAsc(a, b) {
          return String(a).localeCompare(String(b));
        }

        function getAllPhases() {
          return unique((DATA || []).map(d => d.fase).filter(Boolean)).sort(sortByTextAsc);
        }

        function getIterationsForPhases(fasesSel) {
          const fases = Array.isArray(fasesSel) && fasesSel.length ? fasesSel : getAllPhases();
          return unique((DATA || [])
            .filter(d => fases.includes(String(d.fase)))
            .map(d => d.iteracion)
            .filter(Boolean)).sort(sortByTextAsc);
        }

        function getMetricsForIterations(fasesSel, itersSel) {
          const fases = Array.isArray(fasesSel) && fasesSel.length ? fasesSel : getAllPhases();
          const iters = Array.isArray(itersSel) && itersSel.length ? itersSel : getIterationsForPhases(fases);
          const scoped = (DATA || [])
            .filter(d => fases.includes(String(d.fase)))
            .filter(d => iters.includes(String(d.iteracion)));
          const map = new Map();
          scoped.forEach(d => (d.metricas || []).forEach(m => {
            if (!map.has(String(m.id))) map.set(String(m.id), m.nombre);
          }));
          return Array.from(map, ([id, nombre]) => ({
              id,
              nombre
            }))
            .sort((a, b) => String(a.nombre).localeCompare(String(b.nombre)));
        }

        function checklistItemHtml(id, label, nameAttr) {
          const inputId = `${nameAttr}-${btoa(unescape(encodeURIComponent(String(id)))).replace(/=/g,'')}`;
          return `
            <div class="custom-control custom-checkbox mb-1">
              <input type="checkbox" class="custom-control-input" id="${inputId}" name="${nameAttr}" value="${String(id)}">
              <label class="custom-control-label" for="${inputId}">${label}</label>
            </div>`;
        }

        function markAllIn($container, checked = true) {
          $container.find('input[type="checkbox"]').prop('checked', checked);
        }

        function readCheckedValues($container) {
          return $container.find('input[type="checkbox"]:checked').map(function() {
            return String(this.value);
          }).get();
        }

        function syncExportSelectionFromUI() {
          const fases = readCheckedValues($phaseList);
          const iters = readCheckedValues($iterList);
          const mets = readCheckedValues($metricList);
          exportSelection.fases = (fases.length && fases.length !== $phaseList.find('input').length) ? fases : [];
          exportSelection.iteraciones = (iters.length && iters.length !== $iterList.find('input').length) ? iters : [];
          exportSelection.metricas = (mets.length && mets.length !== $metricList.find('input').length) ? mets : [];
          const fmt = (document.querySelector('input[name="exportFmt"]:checked')?.value || 'png').toLowerCase();
          exportSelection.incluirGlobal = fmt === 'png' ? !!document.getElementById('exportIncluirGlobal')?.checked : false;
        }

        function buildPhaseChecklist() {
          const fases = getAllPhases();
          $phaseList.empty();
          if (!fases.length) {
            $('#exportPhaseEmpty').removeClass('d-none');
          } else {
            $('#exportPhaseEmpty').addClass('d-none');
            fases.forEach(f => $phaseList.append(checklistItemHtml(f, f, 'fase')));
            // por defecto: todas marcadas
            markAllIn($phaseList, true);
          }
          $phaseGroup.slideDown(120);
        }

        function buildIterChecklist() {
          const fasesSel = readCheckedValues($phaseList); // si vacío => todas
          const iters = getIterationsForPhases(fasesSel);
          $iterList.empty();
          if (!iters.length) {
            $('#exportIterEmpty').removeClass('d-none');
          } else {
            $('#exportIterEmpty').addClass('d-none');
            iters.forEach(i => $iterList.append(checklistItemHtml(i, i, 'iter')));
            markAllIn($iterList, true);
          }
          $iterGroup.stop(true, true).slideDown(120);
        }

        function buildMetricChecklist() {
          const fasesSel = readCheckedValues($phaseList);
          const itersSel = readCheckedValues($iterList);
          const mets = getMetricsForIterations(fasesSel, itersSel);
          $metricList.empty();
          if (!mets.length) {
            $('#exportMetricEmpty').removeClass('d-none');
          } else {
            $('#exportMetricEmpty').addClass('d-none');
            mets.forEach(m => $metricList.append(checklistItemHtml(m.id, `${m.nombre}`, 'met')));
            markAllIn($metricList, true);
          }
          $metricGroup.stop(true, true).slideDown(120);
        }

        function resetSelections() {
          exportSelection.fases = [];
          exportSelection.iteraciones = [];
          exportSelection.metricas = [];
          const fmt = (document.querySelector('input[name="exportFmt"]:checked')?.value || 'png').toLowerCase();
          exportSelection.incluirGlobal = (fmt === 'png');
          const inc = document.getElementById('exportIncluirGlobal');
          if (inc) inc.checked = (fmt === 'png');
        }

        function togglePngOnlyOptions() {
          const fmt = (document.querySelector('input[name="exportFmt"]:checked')?.value || 'png').toLowerCase();
          const box = document.getElementById('exportPngOptions');
          if (box) box.style.display = (fmt === 'png') ? '' : 'none';
          if (fmt !== 'png') {
            const inc = document.getElementById('exportIncluirGlobal');
            if (inc) inc.checked = false;
          }
          syncExportSelectionFromUI();
        }

        btn.addEventListener('click', () => {
          // abrir modal y construir listas
          showAlert('');
          resetSelections();
          hideAllGroups();
          buildPhaseChecklist();
          buildIterChecklist();
          buildMetricChecklist();
          togglePngOnlyOptions();
          $('#exportModal').modal('show');
        });

        // Al abrir por data-API también reconstruir (seguridad)
        $('#exportModal').on('show.bs.modal', function() {
          showAlert('');
          resetSelections();
          hideAllGroups();
          buildPhaseChecklist();
          buildIterChecklist();
          buildMetricChecklist();
          syncExportSelectionFromUI();
          togglePngOnlyOptions();
        });

        // Interacciones jerárquicas
        $phaseList.on('change', 'input[type="checkbox"]', function() {
          buildIterChecklist();
          buildMetricChecklist();
          syncExportSelectionFromUI();
        });
        $iterList.on('change', 'input[type="checkbox"]', function() {
          buildMetricChecklist();
          syncExportSelectionFromUI();
        });
        $metricList.on('change', 'input[type="checkbox"]', function() {
          syncExportSelectionFromUI();
        });
        $('#exportIncluirGlobal').on('change', function() {
          syncExportSelectionFromUI();
        });
        $('#fmtPng, #fmtPdf').on('change', function() {
          togglePngOnlyOptions();
        });

        // Confirmar exportación
        const confirmBtn = document.getElementById('exportConfirmBtn');

        function setModeAndRerender(newMode) {
          const btnToggle = document.getElementById('modeToggle');
          if (!btnToggle) return;
          const prev = btnToggle.dataset.mode || 'donut';
          if (prev === newMode) return;
          btnToggle.dataset.mode = newMode;
          btnToggle.classList.toggle('off', newMode === 'donut');
          btnToggle.innerHTML = newMode === 'donut' ?
            '<i class="oi oi-bar-chart"></i> Ver como barras' :
            '<i class="oi oi-pie-chart"></i> Ver como donuts';
          const active = document.querySelector('#iterFilter .iter-pill.active');
          const iter = active ? active.dataset.iter : null;
          renderIterCards(iter === 'ALL' ? null : iter);
        }

        if (confirmBtn) confirmBtn.addEventListener('click', async () => {
          showAlert('');
          syncExportSelectionFromUI();
          const fmt = (document.querySelector('input[name="exportFmt"]:checked')?.value || 'png').toLowerCase();
          const fases = exportSelection.fases.slice();
          const iteraciones = exportSelection.iteraciones.slice();
          const metricas = exportSelection.metricas.slice();
          const incluirGlobal = !!exportSelection.incluirGlobal;

          try {
            if (fmt === 'png') {
              const chartType = (document.querySelector('input[name="exportChartType"]:checked')?.value || 'donut');
              const btnToggle = document.getElementById('modeToggle');
              const prevMode = btnToggle ? (btnToggle.dataset.mode || 'donut') : 'donut';
              const needSwitch = (chartType !== prevMode);
              if (needSwitch) {
                setModeAndRerender(chartType);
                // esperar un instante para que los charts se preparen
                await new Promise(r => setTimeout(r, 700));
              }
              await exportChartsToServerPNG({
                fases,
                iteraciones,
                metricas,
                incluirGlobal
              });
              if (needSwitch) {
                setModeAndRerender(prevMode);
                setTimeout(() => {}, 0);
              }
              $('#exportModal').modal('hide');
            } else {
              // PDF: construir URL con arrays GET
              const p = new URLSearchParams();
              p.set('proyecto', String(PROYECTO_ID));
              fases.forEach(f => p.append('fase[]', f));
              iteraciones.forEach(i => p.append('iteracion[]', i));
              metricas.forEach(m => p.append('metrica[]', m));
              // No aplica incluirGlobal en PDF
              // compat: si solo hay una fase o métrica, enviar además los parámetros simples
              if (fases.length === 1) p.set('fase', fases[0]);
              if (metricas.length === 1) p.set('metricId', metricas[0]);
              const url = 'api/exportar_pdf.php?' + p.toString();
              const a = document.createElement('a');
              a.href = url;
              a.target = '_blank';
              a.rel = 'noopener';
              document.body.appendChild(a);
              a.click();
              setTimeout(() => {
                try {
                  a.remove();
                } catch (_) {}
              }, 0);
              $('#exportModal').modal('hide');
            }
          } catch (err) {
            showAlert('No se pudo iniciar la exportación: ' + (err && err.message ? err.message : String(err)));
          }
        });

        // Utilidades de exportación (PNG)
        async function exportChartsToServerPNG(opts = {}) {
          const {
            fases = [], iteraciones = [], metricas = [], incluirGlobal = true
          } = opts;
          const canvas = await composeChartsCanvasGrid({
            fases,
            iteraciones,
            metricas,
            incluirGlobal
          });
          const pngDataUrl = canvas.toDataURL('image/png');
          await postDataUrlForDownload('api/exportar_png.php', pngDataUrl, suggestedFileName('png'));
        }

        function loadImage(dataUrl) {
          return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = dataUrl;
          });
        }

        // Esperar 1 o más frames para asegurar pintura de canvas/DOM
        function waitNextFrame(times = 1) {
          return new Promise(resolve => {
            const step = (n) => {
              if (n <= 0) return resolve();
              requestAnimationFrame(() => step(n - 1));
            };
            step(Math.max(1, times));
          });
        }

        // Espera a que un gráfico ECharts termine su render antes de capturar
        function waitChartFinished(instance, timeout = 2000) {
          return new Promise((resolve) => {
            if (!instance || typeof instance.on !== 'function') return resolve();
            let done = false;
            const finish = () => {
              if (!done) {
                done = true;
                try {
                  instance.off('finished', finish);
                } catch (_) {}
                resolve();
              }
            };
            try {
              instance.on('finished', finish);
            } catch (_) {
              return resolve();
            }
            // Fallback por tiempo máximo
            setTimeout(finish, timeout);
            // Un resize suave ayuda a disparar el evento terminado
            try {
              instance.resize && instance.resize();
            } catch (_) {}
          });
        }

        // (duplicado eliminado)

        function suggestedFileName(ext) {
          const name = (document.getElementById('projectName')?.textContent || 'proyecto').replace(/\s+/g, '_');
          const ts = new Date().toISOString().replace(/[:T]/g, '-').slice(0, 16);
          return `${name}_tablero_${ts}.${ext}`;
        }

        async function postDataUrlForDownload(url, dataUrl, filename) {
          return new Promise(resolve => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            form.style.display = 'none';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'image';
            input.value = dataUrl;
            const name = document.createElement('input');
            name.type = 'hidden';
            name.name = 'filename';
            name.value = filename;
            const proj = document.createElement('input');
            proj.type = 'hidden';
            proj.name = 'proyecto';
            proj.value = String(PROYECTO_ID);
            form.appendChild(input);
            form.appendChild(name);
            form.appendChild(proj);
            document.body.appendChild(form);
            form.submit();
            setTimeout(() => {
              try {
                form.remove();
              } catch (_) {}
              resolve();
            }, 250);
          });
        }

        async function composeChartsCanvasGrid(params) {
          // Compat: aceptar nombres antiguos y nuevos
          const fases = (params && (params.fases || params.faseSel)) || [];
          const iteraciones = (params && (params.iteraciones || params.iterSel)) || [];
          const metricas = (params && (params.metricas || params.metSel)) || [];
          const incluirGlobal = !!(params && params.incluirGlobal);

          // 1) Recopilar DOMs de gráficos a capturar
          const chartDoms = [];
          if (incluirGlobal) {
            const globalEl = document.getElementById('chartDistribucionGlobal');
            if (globalEl) chartDoms.push(globalEl);
          }
          const metricEls = Array.from(document.querySelectorAll('.metric-body-chart'));
          metricEls.forEach(el => {
            const fase = el.getAttribute('data-fase');
            const iter = el.getAttribute('data-iter');
            const mid = el.getAttribute('data-metric-id');
            const faseOk = !fases.length || fases.includes(String(fase));
            const iterOk = !iteraciones.length || iteraciones.includes(String(iter));
            const metOk = !metricas.length || metricas.includes(String(mid));
            if (faseOk && iterOk && metOk) chartDoms.push(el);
          });

          if (!chartDoms.length) throw new Error('No hay gráficos coincidentes con la selección.');

          // 2) Utilidad: metadatos de métrica (para etiquetas bajo cada donut)
          const findMetricMeta = (el) => {
            try {
              const iterKey = el.getAttribute('data-iter');
              const mid = el.getAttribute('data-metric-id');
              if (!iterKey || !mid) return null;
              const it = (DATA || []).find(d => String(d.iteracion) === String(iterKey));
              if (!it) return null;
              const m = (it.metricas || []).find(mm => String(mm.id) === String(mid));
              if (!m) return null;
              const plan = Number(m.planned || m.plan || 0);
              const ejec = Number(m.executedReal || m.ejecutado || 0);
              const umbral = Number(m.umbral != null ? m.umbral : (m.min != null ? m.min : 100));
              return {
                plan,
                ejec,
                umbral
              };
            } catch (_) {
              return null;
            }
          };

          // 3) Capturar imágenes de cada gráfico, aplicando ajustes temporales
          const images = [];
          for (const el of chartDoms) {
            const inst = (window.echarts && window.echarts.getInstanceByDom) ? window.echarts.getInstanceByDom(el) : null;
            if (!inst) continue;
            let tweaked = false,
              originalOpt = null,
              originalHStyle = null;

            // Clonar opción y deshabilitar animaciones para captura estable
            try {
              originalOpt = inst.getOption();
            } catch (_) {
              originalOpt = null;
            }
            let cloned = {};
            try {
              cloned = JSON.parse(JSON.stringify(originalOpt || {}));
            } catch (_) {
              cloned = {};
            }
            cloned.animation = false;
            cloned.animationDuration = 0;
            cloned.animationDurationUpdate = 0;
            if (cloned.series && Array.isArray(cloned.series)) {
              cloned.series = cloned.series.map(s => Object.assign({}, s, {
                animation: false,
                animationDuration: 0,
                animationDurationUpdate: 0
              }));
            }

            if (el.id === 'chartDistribucionGlobal') {
              try {
                // Donut global más grande y centrado; ocultar leyenda si existe
                if (cloned.legend) {
                  if (Array.isArray(cloned.legend)) cloned.legend = cloned.legend.map(l => Object.assign({}, l, {
                    show: false
                  }));
                  else cloned.legend.show = false;
                }
                if (cloned.series && cloned.series[0] && cloned.series[0].type === 'pie') {
                  cloned.series[0].radius = ['48%', '80%'];
                  cloned.series[0].center = ['50%', '50%'];
                  cloned.series[0].label = Object.assign({}, cloned.series[0].label, {
                    show: true,
                    formatter: '{b}\n{d}%',
                    fontSize: 12,
                    color: '#333'
                  });
                  cloned.series[0].labelLine = Object.assign({}, cloned.series[0].labelLine, {
                    show: true
                  });
                  cloned.series[0].itemStyle = Object.assign({}, cloned.series[0].itemStyle, {
                    borderWidth: 1.5
                  });
                }
                // === 🔧 RE-CENTRAR TEXTO DEL DONUT GLOBAL (EXPORTACIÓN PNG) ===
                try {
                  const OFFSET_Y = 12; // píxeles hacia abajo solo para el PNG
                  const centerAndOffset = (g) => {
                    if (!g) return;
                    if (g.type === 'text') {
                      g.left = 'center';
                      g.top = 'middle';
                      g.position = [0, OFFSET_Y];
                      g.style = Object.assign({}, g.style, { align: 'center', verticalAlign: 'middle' });
                    } else if (g.type === 'group') {
                      g.left = 'center';
                      g.top = 'middle';
                      g.position = [0, OFFSET_Y];
                      if (Array.isArray(g.children)) g.children.forEach(centerAndOffset);
                    }
                  };
                  if (Array.isArray(cloned.graphic)) {
                    cloned.graphic.forEach(centerAndOffset);
                  } else if (cloned.graphic && Array.isArray(cloned.graphic.elements)) {
                    cloned.graphic.elements.forEach(centerAndOffset);
                  }
                } catch (e) {
                  console.warn('No se pudo ajustar texto central del donut en exportación:', e);
                }




                // Igualar alto al de una métrica para armonizar composición
                const sampleMetricEl = document.querySelector('.metric-body-chart');
                if (sampleMetricEl && sampleMetricEl.clientHeight) {
                  originalHStyle = el.style.height;
                  el.style.height = (sampleMetricEl.clientHeight + 60) + 'px';
                  inst.resize();

                }
              } catch (_) {}
            }

            // Aplicar opciones clonadas sin animaciones
            try {
              inst.setOption(cloned, true);
              tweaked = true;
            } catch (_) {}

            // Esperar render completo antes de capturar
            try {
              await waitChartFinished(inst, 2500);
            } catch (_) {}
            await waitNextFrame(2);

            const url = inst.getDataURL({
              pixelRatio: 2,
              backgroundColor: '#ffffff'
            });
            images.push({
              url,
              w: el.clientWidth,
              h: el.clientHeight,
              title: el.getAttribute('data-title') || (el.id === 'chartDistribucionGlobal' ? 'Visión General del Proyecto' : ''),
              isGlobal: (el.id === 'chartDistribucionGlobal'),
              meta: findMetricMeta(el)
            });

            // Restaurar opciones/alto originales
            if (tweaked && originalOpt) {
              try {
                inst.setOption(originalOpt, true);
              } catch (_) {}
              try {
                if (originalHStyle !== null) {
                  el.style.height = originalHStyle;
                  inst.resize();
                }
              } catch (_) {}
            }
          }
          if (!images.length) throw new Error('No se pudieron generar imágenes de los gráficos.');

          // Separar global (si existe) y resto
          const globalIdx = images.findIndex(im => im.isGlobal || (im.title || '').includes('Visión General'));
          const globalImg = globalIdx >= 0 ? images[globalIdx] : null;
          const others = images.filter((_, i) => i !== globalIdx).sort((a, b) => (a.title || '').localeCompare(b.title || ''));

          // Composición en grilla para el resto: columnas adaptativas para escalar hasta 50 sin solapes
          const othersCount = others.length;
          const cols = (othersCount >= 36) ? 4 : (othersCount >= 25) ? 4 : (othersCount >= 16) ? 4 : (othersCount >= 6) ? 3 : (othersCount >= 2) ? 2 : 1;
          const PADDING = (othersCount >= 36) ? 12 : (othersCount >= 25 ? 14 : 18);
          const GAP = (othersCount >= 36) ? 8 : 12;
          const TITLE_H = 20,
            LABEL_GAP = 6,
            LABEL_H = (othersCount >= 25 ? 14 : 16);
          const titleFontSize = (othersCount >= 36) ? 10 : (othersCount >= 25 ? 11 : 12);
          let cellW;
          if (othersCount >= 36) cellW = 260;
          else if (othersCount >= 25) cellW = 300;
          else if (othersCount >= 16) cellW = 320;
          else if (othersCount >= 6) cellW = 340;
          else cellW = Math.max(360, Math.min(520, Math.max(...images.map(i => i.w))));
          const totalW = PADDING + Math.max(1, cols) * (cellW + PADDING);

          // Calcular alto total: fila global (si hay) + filas del resto
          let globalRowHeight = 0;
          if (globalImg) {
            // agrandar global para ocupar 2 columnas si es posible
            const gCols = cols >= 2 ? 2 : 1;
            const gCellW = (gCols * cellW) + ((gCols - 1) * PADDING);
            const scaledHG = Math.round(globalImg.h * (gCellW / globalImg.w));
            globalRowHeight = TITLE_H + scaledHG + GAP;
            // guardar medidas para dibujo
            globalImg._gCols = gCols;
            globalImg._gCellW = gCellW;
          }
          const rows = cols > 0 ? Math.ceil(othersCount / cols) : 0;
          const rowHeights = new Array(rows).fill(0);
          for (let r = 0; r < rows; r++) {
            let maxH = 0;
            for (let c = 0; c < cols; c++) {
              const idx = r * cols + c;
              if (idx >= othersCount) break;
              const img = others[idx];
              const scaledH = Math.round(img.h * (cellW / img.w));
              maxH = Math.max(maxH, TITLE_H + scaledH + LABEL_GAP + LABEL_H + GAP);
            }
            rowHeights[r] = maxH;
          }
          const totalH = PADDING + globalRowHeight + rowHeights.reduce((a, b) => a + b, 0) + PADDING;

          // alta resolución
          const SCALE = 2; // 2x para mejor nitidez
          const canvas = document.createElement('canvas');
          canvas.width = Math.round(totalW * SCALE);
          canvas.height = Math.round(totalH * SCALE);
          const ctx = canvas.getContext('2d');
          ctx.scale(SCALE, SCALE);
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, totalW, totalH);
          // header
          ctx.fillStyle = '#222';
          ctx.font = 'bold 16px Arial, sans-serif';
          const headerText = (document.getElementById('projectName')?.textContent || 'Proyecto') + ' - Exportación ' + (new Date()).toLocaleString();
          ctx.fillText(headerText, PADDING, PADDING + 6);

          let y = PADDING + GAP;
          // Dibujar fila global centrada si existe
          if (globalImg) {
            const contentW = Math.max(1, cols) * (cellW + PADDING) - PADDING;
            const gCellW = globalImg._gCellW || cellW;
            const x = PADDING + Math.floor((contentW - gCellW) / 2);
            // título (centrado)
            if (globalImg.title) {
              ctx.font = `bold ${titleFontSize}px Arial, sans-serif`;
              ctx.fillStyle = '#333';
              let title = globalImg.title;
              while (ctx.measureText(title).width > gCellW) {
                title = title.slice(0, -1);
              }
              ctx.textAlign = 'center';
              ctx.fillText(title, x + Math.floor(gCellW / 2), y + 14);
              ctx.textAlign = 'left';
            }
            const imgG = await loadImage(globalImg.url);
            const scaledHG = Math.round(globalImg.h * (gCellW / globalImg.w));
            ctx.drawImage(imgG, x, y + TITLE_H, gCellW, scaledHG);
            y += globalRowHeight;
          }

          // Dibujar resto en grilla
          for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
              const idx = r * cols + c;
              if (idx >= othersCount) break;
              const img = others[idx];
              const image = await loadImage(img.url);
              const x = PADDING + c * (cellW + PADDING);
              // título (centrado)
              if (img.title) {
                ctx.font = `bold ${titleFontSize}px Arial, sans-serif`;
                ctx.fillStyle = '#333';
                const maxTitleWidth = cellW;
                let title = img.title;
                // recortar si es demasiado largo
                while (ctx.measureText(title).width > maxTitleWidth) {
                  title = title.slice(0, -1);
                }
                ctx.textAlign = 'center';
                ctx.fillText(title, x + Math.floor(cellW / 2), y + 14);
                ctx.textAlign = 'left';
              }
              const scaledH = Math.round(img.h * (cellW / img.w));
              ctx.drawImage(image, x, y + TITLE_H, cellW, scaledH);
              // etiquetas bajo el donut: Planificado | Ejecutado | Umbral (%)
              if (img.meta) {
                const labelText = `Planificado: ${img.meta.plan}  |  Ejecutado: ${img.meta.ejec}  |  Umbral: ${img.meta.umbral}%`;
                // ajustar tamaño para que quepa
                let fontSize = 12;
                ctx.fillStyle = '#000';
                while (fontSize >= 9) {
                  ctx.font = `normal ${fontSize}px Arial, sans-serif`;
                  if (ctx.measureText(labelText).width <= cellW) break;
                  fontSize -= 1;
                }
                ctx.textAlign = 'center';
                ctx.fillText(labelText, x + Math.floor(cellW / 2), y + TITLE_H + scaledH + LABEL_GAP + (LABEL_H - 4));
                ctx.textAlign = 'left';
              }
            }
            y += rowHeights[r];
          }
          return canvas;
        }
        // (Sin formularios PDF locales: la exportación PDF es 100% server-side)
      })();
      // Ocultar "Visión General del Proyecto" si no hay datos o no hay iteraciones
      const globalCard = document.querySelector('.card-global-vision');
      if (!DATA || !DATA.length || !HAY_ITERACIONES) {
        if (globalCard) globalCard.style.display = 'none';
      } else {
        if (globalCard) globalCard.style.display = '';
        computeAndRenderGlobalSummary();
      }

      // Sticky toolbar + offset dinámico + autocollapse en scroll
      (function setupStickyToolbar() {
        const toolbar = document.querySelector('.dashboard-toolbar');
        const expanded = document.getElementById('toolbarExpanded');
        const toggleBtn = document.getElementById('toggleToolbar');
        if (!toolbar) return;

        const setTopOffset = () => {
          const nav = document.querySelector('.navbar');
          const navH = nav ? Math.ceil(nav.getBoundingClientRect().height) : 56;
          const extra = 8; // pequeño margen
          document.documentElement.style.setProperty('--toolbar-top', (navH + extra) + 'px');
        };

        let threshold = 0;
        const computeThreshold = () => {
          setTopOffset();
          const topVar = parseInt(getComputedStyle(toolbar).top) || 70;
          const rect = toolbar.getBoundingClientRect();
          threshold = (window.pageYOffset || document.documentElement.scrollTop) + rect.top - topVar;
        };

        const onScroll = () => {
          const y = (window.pageYOffset || document.documentElement.scrollTop);
          const stuck = y >= threshold - 1;
          toolbar.classList.toggle('stuck', stuck);
        };

        const onResize = () => {
          computeThreshold();
          onScroll();
        };

        // Chevron: alterna icono segun estado y recomputa threshold (por cambio de altura)
        if (expanded && toggleBtn) {
          $('#toolbarExpanded').on('shown.bs.collapse', function() {
            toggleBtn.setAttribute('aria-expanded', 'true');
            const i = toggleBtn.querySelector('i');
            if (i) i.className = 'oi oi-chevron-top';
            computeThreshold();
            onScroll();
          });
          $('#toolbarExpanded').on('hidden.bs.collapse', function() {
            toggleBtn.setAttribute('aria-expanded', 'false');
            const i = toggleBtn.querySelector('i');
            if (i) i.className = 'oi oi-chevron-bottom';
            computeThreshold();
            onScroll();
          });
        }

        computeThreshold();
        onScroll();
        window.addEventListener('scroll', onScroll, {
          passive: true
        });
        window.addEventListener('resize', onResize);
      })();

      // Botón chevron: aseguro el toggle del collapse por JS (evito doble manejo del data-api)
      (function wireToolbarToggle() {
        const btn = document.getElementById('toggleToolbar');
        if (!btn || typeof $ === 'undefined') return;
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          try {
            $('#toolbarExpanded').collapse('toggle');
          } catch (_) {}
        });
      })();

      // Asegurar que inicia expandido
      (function ensureExpandedOnLoad() {
        if (typeof $ === 'undefined') return;
        try {
          $('#toolbarExpanded').collapse('show');
          const btn = document.getElementById('toggleToolbar');
          if (btn) {
            btn.setAttribute('aria-expanded', 'true');
            const i = btn.querySelector('i');
            if (i) i.className = 'oi oi-chevron-top';
          }
        } catch (_) {}
      })();

      // Ocultar toolbar expandida si no hay datos
      if (!DATA || !DATA.length) {
        document.querySelector('.dashboard-toolbar')?.classList.add('d-none');
        document.getElementById('toolbarExpanded')?.classList.add('d-none');
      }
    });
  </script>

  <?php include_once '../gui/footer.php'; ?>
</body>

</html>