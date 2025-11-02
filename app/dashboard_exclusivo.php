<?php
  // Removed stray JavaScript fragments
/**
 * Dashboard de Calidad por Iteración/Métrica
 * - Misma obtención de datos que el dashboard principal
 * - Cada FASE + ITERACIÓN => una card
 * - Dentro: una card por MÉTRICA con donut o barra
 * - Filtro por FASE+ITERACIÓN
 *
  // Removed stray itemStyle line
 * @copyright   2025 CoDevIt
 * @license     MIT
 * @version     1.0.0
 * @since       1.0.0
 */

require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
require_once __DIR__ . '/../modelo/BDConexion.Class.php';

// Debe estar logueado
ControlAcceso::verificaLogin();

// Conexión centralizada
$conexion = BDConexion::getConexion();

// Obtiene id de proyecto por GET; si no viene, redirige al primer proyecto asignado
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

// Verifica pertenencia al proyecto
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

// hay iteraciones?
$sqlHayIter = "SELECT COUNT(*) AS total
               FROM iteracion i
               JOIN fase f ON f.id_fase = i.id_fase
               JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
               WHERE pf.id_proyecto = $idProyecto";
$resHayIter = $conexion->query($sqlHayIter);
$hayIteraciones = ($resHayIter && $resHayIter->num_rows > 0 && (int)$resHayIter->fetch_assoc()['total'] > 0);

// datos para los gráficos (MISMA CONSULTA QUE EL DASHBOARD PRINCIPAL)
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
WHERE pf.id_proyecto = $idProyecto
ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;
";
$result = $conexion->query($query);

// ============================
// Construcción de $DATA
// ============================
$iterMap = [];
if ($result) {
  while ($r = $result->fetch_assoc()) {
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
      "min"          => max(0, 100 - (float)$r['umbral']),
      "max"          => 100 + (float)$r['umbral'],
      "nota"         => $nota,
      "extra"        => ($plan > 0 ? max(0, $ejec - $plan) : $ejec)
    ];
  }
}
$DATA = array_values($iterMap);

// ============================
// Lógica de iteraciones actual y anterior (igual que el dashboard)
// ============================
$hoy = date('Y-m-d');
$actualIter = null;
$anteriorIter = null;
$mensajeActual = '';
$mensajeAnterior = '';

if (count($DATA) === 0) {
  $mensajeActual = 'No se encontraron fases ni iteraciones registradas en este proyecto.';
  $mensajeAnterior = 'Sin datos históricos disponibles.';
} else {
  $actualIndex = null;
  foreach ($DATA as $idx => $it) {
    if ($it['inicio'] <= $hoy && $it['fin'] >= $hoy) {
      $actualIndex = $idx;
      break;
    }
  }

  if ($actualIndex !== null) {
    $actualIter = $DATA[$actualIndex];
  } else {
    $mensajeActual = 'No hay ninguna iteración activa en la fecha actual o no tiene métricas asociadas.';
  }

  $fechaReferencia = $actualIter ? $actualIter['inicio'] : $hoy;
  $anteriores = array_filter($DATA, fn($it) => $it['fin'] < $fechaReferencia);

  if (count($anteriores) === 0) {
    if ($actualIter) {
      $mensajeAnterior = 'Esta es la primera iteración registrada del proyecto.';
    } else {
      $mensajeAnterior = 'El proyecto tiene iteraciones registradas, pero ninguna está activa actualmente.';
    }
  } else {
    $anteriorIter = end($anteriores);
  }

  if ($actualIter && empty($actualIter['metricas'])) {
    $mensajeActual = "La iteración <b>{$actualIter['iteracion']}</b> no tiene métricas planificadas aún.";
  }
}

// $conexion es singleton; no cerramos
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
  <!-- ECharts -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"
    onerror="this.onerror=null;this.src='../lib/echarts.min.js';"></script>
  <style>
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      /* Compensa la navbar fixed-top para que el primer contenido no quede escondido */
      padding-top: 70px;
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

    /* ==== DISTRIBUCIÓN DE MÉTRICAS === */
    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1rem;
      padding-bottom: 0.5rem;
      align-items: stretch;
    }

    .metric-card {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, 0.05);
      border-radius: 0.75rem;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
      height: 300px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 0.75rem;
    }

    .metric-name {
      font-size: 0.85rem;
      font-weight: 600;
      color: #212529;
      text-align: center;
      margin-bottom: 0.5rem;
      min-height: 38px;
    }

    .metric-body-chart {
      width: 100%;
      height: 210px;
    }

    .metric-foot {
      font-size: 0.75rem;
      text-align: center;
      color: #495057;
      margin-top: 0.35rem;
    }

    /* Ajustes responsivos */
    @media (max-width: 991.98px) {
      .metric-grid { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    }
    @media (max-width: 575.98px) {
      .metric-grid { grid-template-columns: 1fr; }
    }

    .icon-bg-success {
      background: linear-gradient(135deg, #28a745 0%, #65d488 100%);
    }

    .icon-bg-secondary {
      background: linear-gradient(135deg, #6c757d 0%, #a0a4a8 100%);
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
      flex: 1 1 auto;
    }

    .stat-label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      margin-bottom: 2px;
    }

    .stat-value {
      font-size: 1.75rem;
      font-weight: 700;
      line-height: 1.1;
      color: #212529;
    }

    .status-line {
      font-size: .85rem;
      color: #6c757d;
      margin-top: 2px;
    }

    .card {
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 0.75rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      animation: fadeIn 0.5s ease-in;
      background: #fff;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(8px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card-header {
      background-color: #f8f9fa;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      font-weight: 600;
    }

    /* grid de iteraciones: una iteración por fila */
    #iterCards {
      display: grid;
      grid-template-columns: 1fr; /* fuerza una iteración por fila */
      gap: 1.25rem; 
      margin-top: 1rem;
      margin-bottom: 4rem;
    }

    .card-iteracion {
      min-height: 280px;
    }

    .iter-header-title {
      font-weight: 600;
      font-size: 1rem;
    }

    .iter-subtitle {
      font-size: .8rem;
      color: #6c757d;
    }


    .badge-state {
      font-size: .7rem;
      padding: .18rem .45rem;
      border-radius: .5rem;
    }

    /* filtro de iteraciones */
    #iterFilter {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
      margin-bottom: .75rem;
    }

    .iter-pill {
      border: 1px solid #dee2e6;
      background: #fff;
      border-radius: 999px;
      padding: .25rem .65rem;
      font-size: .75rem;
      cursor: pointer;
      transition: .15s;
    }

    .iter-pill.active {
      background: rgba(13, 110, 253, .1);
      border-color: #0d6efd;
      color: #0d6efd;
      font-weight: 600;
    }

    /* toggle de modo */
    .chart-controls {
      display: flex;
      gap: .5rem;
      align-items: center;
      justify-content: flex-end;
      margin-bottom: .5rem;
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

    @media (max-width: 575.98px) {
      #iterCards {
        display: block;
      }

      .card-iteracion {
        margin-bottom: 1rem;
      }
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../gui/navbar.php';  ?>
  <div class="container my-4">
    <!-- Botón Volver -->
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

    <?php
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

    <!-- TOP CARDS (igual dashboard) -->
    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-primary"><span class="oi oi-briefcase"></span></div>
            <div class="stat-content">
              <span class="stat-label">Proyecto</span>
              <div id="projectName" class="stat-value"><?= htmlspecialchars($nombreProyecto) ?></div>
              <span class="status-line">
                Estado:
                <span id="projectStatusBadge" class="badge badge-pill <?= $estadoClass ?>">
                  <?= htmlspecialchars($estadoProyecto) ?>
                </span>
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
              <span class="stat-label">Métricas utilizadas</span>
              <div id="totalMetricasValue" class="stat-value"><?= (int)$totalMetricas ?></div>
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
              <div id="totalIteracionesValue" class="stat-value text-dark"><?= (int)$totalIteraciones ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CONTROLES -->
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
      <div>
        <h6 class="mb-1">FASE + Iteración</h6>
        <small class="text-muted">Podés filtrar las iteraciones para ver solo las métricas de esa fase.</small>
      </div>
      <div class="chart-controls mt-2 mt-md-0">
        <button id="modeToggle" class="btn-toggle" data-mode="donut">
          <i class="oi oi-pie-chart"></i> Ver como barras
        </button>
      </div>
    </div>

    <div id="iterFilter" class="mb-3"></div>

    <div id="iterCards"></div>
  </div>

  <script>
    // Registro global de charts para poder redimensionarlos
    const __CHARTS = Object.create(null);
    let __chartsResizeScheduled = false;
    function __scheduleChartsResize() {
      if (__chartsResizeScheduled) return;
      __chartsResizeScheduled = true;
      requestAnimationFrame(() => {
        try {
          Object.values(__CHARTS).forEach(ch => { try { ch.resize && ch.resize(); } catch(_){} });
        } finally {
          __chartsResizeScheduled = false;
        }
      });
    }
    // ResizeObserver para redimensionar cuando cambie el tamaño del contenedor
    const __RO = (typeof ResizeObserver !== 'undefined') ? new ResizeObserver(() => __scheduleChartsResize()) : null;
    window.addEventListener('resize', __scheduleChartsResize);
    // Datos PHP -> JS
    const DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    const ID_PROYECTO = <?= (int)$idProyecto ?>;

    // misma lógica de semáforo
    function colorSemaforo(valorPct, minPct, maxPct) {
      valorPct = Number(valorPct) || 0;
      minPct = Number(minPct) || 0;
      if (valorPct >= 100) return "#28a745"; // Verde
      if (valorPct >= minPct) return "#ffc107"; // Amarillo
      return "#dc3545"; // Rojo
    }

    // helpers
    function safePct(m) {
      const v = Number(m?.executed) || 0;
      return v < 0 ? 0 : v;
    }

    // genera filtro
    function buildIterFilter(list) {
      const cont = document.getElementById('iterFilter');
      cont.innerHTML = '';
      const allBtn = document.createElement('button');
      allBtn.type = 'button';
      allBtn.className = 'iter-pill active';
      allBtn.dataset.iter = 'ALL';
      allBtn.textContent = 'Todas';
      cont.appendChild(allBtn);

      list.forEach((it, idx) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'iter-pill';
        b.dataset.iter = it.iteracion;
        b.textContent = it.iteracion;
        cont.appendChild(b);
      });

      cont.addEventListener('click', (e) => {
        const btn = e.target.closest('.iter-pill');
        if (!btn) return;
        // activar solo ese
        [...cont.querySelectorAll('.iter-pill')].forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        const val = btn.dataset.iter;
        renderIterCards(val === 'ALL' ? null : val);
      });
    }

    // render cards de iteración
    function renderIterCards(filterIter = null) {
      const wrap = document.getElementById('iterCards');
      wrap.innerHTML = '';

      const mode = document.getElementById('modeToggle')?.dataset?.mode || 'donut';

      const source = Array.isArray(DATA) ? DATA : [];
      source
        .filter(it => !filterIter || it.iteracion === filterIter)
        .forEach((it, idx) => {
          const card = document.createElement('div');
          card.className = 'card card-iteracion';

          const header = document.createElement('div');
          header.className = 'card-header d-flex justify-content-between align-items-center';
          header.innerHTML = `
              <div>
                <div class="iter-header-title">${it.iteracion}</div>
                <div class="iter-subtitle">Del ${it.inicio} al ${it.fin}</div>
              </div>
              <div class="text-right">
                <span class="badge badge-light">${it.metricas ? it.metricas.length : 0} métricas</span>
              </div>
          `;
          card.appendChild(header);

          const body = document.createElement('div');
          body.className = 'card-body';
          const grid = document.createElement('div');
          grid.className = 'metric-grid';

          (it.metricas || []).forEach((m, mIdx) => {
            const mCard = document.createElement('div');
            mCard.className = 'metric-card';

            const idChart = `metric-chart-${idx}-${mIdx}`;
            const pct = safePct(m);
            const color = colorSemaforo(pct, m.min, m.max);

            mCard.innerHTML = `
              <div class="metric-name">#${m.id} - ${m.nombre}</div>
              <div id="${idChart}" class="metric-body-chart"></div>
              <div class="metric-foot">
                Planificado: <b>${m.planned}</b> | Ejecutado: <b>${m.executedReal}</b><br>
              </div>
            `;

            grid.appendChild(mCard);

            // defer chart render
            setTimeout(() => {
              if (mode === 'donut') {
                renderDonut(idChart, m);
              } else {
                renderMiniBar(idChart, m);
              }
            }, 0);
          });

          if (!it.metricas || it.metricas.length === 0) {
            const emp = document.createElement('div');
            emp.className = 'text-muted';
            emp.textContent = 'No hay métricas planificadas para esta iteración.';
            body.appendChild(emp);
          } else {
            body.appendChild(grid);
          }

          card.appendChild(body);
          wrap.appendChild(card);
        });

      if (filterIter && (!DATA || DATA.length === 0)) {
        wrap.innerHTML = '<div class="text-muted">No hay datos para el filtro seleccionado.</div>';
      }
    }
    // Función auxiliar: color traslúcido según caso
    function resolveColor(metric, pct) {
      const baseColor = colorSemaforo(pct, metric.min, metric.max);

      // Caso: planificado = 0 y ejecutado > 0
      if ((metric.planned ?? 0) === 0 && (metric.executedReal ?? 0) > 0) {
        return "rgba(100, 170, 255, 0.95)"; // azul translúcido
      }
      //Caso: planificado = 0 y ejecutado = 0
      if ((metric.planned ?? 0) === 0 && (metric.executedReal ?? 0) === 0) {
        return "rgba(200, 208, 227, 0.8)"; // gris translúcido
      }
      // Caso: sobrecumple >100%
      if (pct > 100) {
        // helper para convertir #hex a rgba
        return "rgba(140, 220, 170, 0.9)";
      };



      return baseColor;
    }

    // === Donut Circular ===
    function renderDonut(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();

      const chart = echarts.init(dom);
      __CHARTS[domId] = chart;
      if (__RO) { try { __RO.observe(dom); } catch(_){} }
      const pctReal = Math.max(0, safePct(metric)); // puede ser >100
      const ringPct = Math.min(100, pctReal); // el anillo siempre suma 100
      const color = resolveColor(metric, pctReal); // mantiene semáforo original
      const restColor = "#e9ecef";

      // Construye data sin costuras: a 100% usa un solo sector para evitar línea en el tope
      let ringData;
      if (ringPct >= 100) {
        ringData = [{
          value: 100,
          name: "Cumplido",
          itemStyle: {
            color
          }
        }];
      } else if (ringPct <= 0) {
        ringData = [{
          value: 100,
          name: "Restante",
          itemStyle: {
            color: restColor
          }
        }];
      } else {
        ringData = [{
            value: ringPct,
            name: "Cumplido",
            itemStyle: {
              color
            }
          },
          {
            value: 100 - ringPct,
            name: "Restante",
            itemStyle: {
              color: restColor
            }
          }
        ];
      }

      chart.setOption({
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
            type: "pie",
            radius: ["52%", "90%"],
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
          // Marca de umbral: línea que "corta" todo el grosor del donut en (100 - umbral) => metric.min
          {
            type: 'pie',
            radius: ['52%', '90%'], // mismo grosor que el donut para que parezca una línea que lo atraviesa
            startAngle: 90,
            silent: true,
            animation: false,
            label: {
              show: false
            },
            labelLine: {
              show: false
            },
            itemStyle: { borderWidth: 0 },
            z: 10,
            zlevel: 2,
            data: (function() {
              const threshold = Math.max(0, Math.min(100, Number(metric?.min ?? 100)));
              const wedgeWidth = 1; // ancho de la "línea" en % del círculo (más grande para que se vea bien)
              const half = wedgeWidth / 2;
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
                    color: '#000000ff',
                    shadowColor: 'rgba(0, 0, 0, 0.35)',
                    shadowBlur: 6
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
        animation: true
      });
    }

    // === Barras verticales: Planificado vs Ejecutado con etiqueta arriba ===
    function renderMiniBar(domId, metric) {
      const dom = document.getElementById(domId);
      if (!dom) return;
      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();

      const chart = echarts.init(dom);
      __CHARTS[domId] = chart;
      if (__RO) { try { __RO.observe(dom); } catch(_){} }
      const pctReal = Math.max(0, safePct(metric)); // % real ejecutado vs plan
      const executedColor = resolveColor(metric, pctReal);
      const plannedPct = 100; // baseline 100%
      const yMax = pctReal > 120 ? Math.min(300, Math.ceil(pctReal / 10) * 10 + 10) : 120; // escala dinámica
      const capHeight = pctReal > 0 ? 1 : 0; // tapa blanca finita

      chart.setOption({
        grid: {
          left: 40,
          right: 60, // más espacio para que se vea el label del markLine
          top: 20,
          bottom: 28,
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: ['Planificado', 'Ejecutado'],
          axisLine: { lineStyle: { color: '#d7dbe8' } },
          axisTick: { show: false },
          axisLabel: { color: '#6c7a92', fontWeight: 600 }
        },
        yAxis: {
          type: 'value',
          min: 0,
          max: yMax,
          splitLine: { lineStyle: { type: 'dashed', color: '#e5e9f2' } },
          axisLabel: { color: '#6c7a92', formatter: '{value}%' }
        },
        series: [
              // barra plan = 100%
              {
                name: 'Planificado',
                type: 'bar',
                barWidth: 34,
                itemStyle: { borderRadius: 0, color: '#a8b5d7' },
                label: {
                  show: true,
                  position: 'top',
                  fontWeight: 700,
                  color: '#1b2533',
                  formatter: (p) => p.dataIndex === 0 ? '100%' : ''
                },
                data: [plannedPct, null]
              },
              // barra ejecutado = pct
              {
                name: 'Ejecutado',
                type: 'bar',
                barWidth: 34,
                barGap: '30%',
                itemStyle: { borderRadius: 0, color: executedColor },
                label: {
                  show: true,
                  position: 'top',
                  fontWeight: 700,
                  color: '#1b2533',
                  formatter: (p) => p.dataIndex === 1 ? `${pctReal}%` : ''
                },
                markLine: {
                  symbol: 'none',
                  lineStyle: { type: 'dashed', color: '#0d6efd', width: 2 },
                  label: {
                    show: true,
                    formatter: '100%',
                    position: 'end',
                    distance: 6,
                    color: '#0d6efd',
                    backgroundColor: '#ffffff',
                    padding: [1, 4],
                    borderRadius: 3
                  },
                  data: [{ yAxis: 100 }]
                },
                markArea: (pctReal > 100) ? {
                  itemStyle: { color: 'rgba(13,110,253,0.15)' },
                  data: [[{ yAxis: 100 }, { yAxis: Math.min(pctReal, yMax) }]]
                } : undefined,
                data: [null, Math.min(pctReal, yMax)]
              },
              // tapa blanca arriba de ejecutado para simular cap
              {
                type: 'bar',
                barWidth: 34,
                barGap: '-100%',
                itemStyle: { color: '#ffffff', borderRadius: 0 },
                silent: true,
                data: [null, capHeight]
              }
            ],
        animation: true
      });
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
        // texto
        if (next === 'donut') {
          btn.innerHTML = '<i class="oi oi-pie-chart"></i> Ver como barras';
        } else {
          btn.innerHTML = '<i class="oi oi-bar-chart"></i> Ver como donuts';
        }
        // re-render
        const active = document.querySelector('#iterFilter .iter-pill.active');
        const iter = active ? active.dataset.iter : null;
        renderIterCards(iter === 'ALL' ? null : iter);
      });
    })();

    // init
    document.addEventListener('DOMContentLoaded', () => {
      buildIterFilter(Array.isArray(DATA) ? DATA : []);
      renderIterCards(null);
      __scheduleChartsResize();
    });
  </script>
  <?php include_once '../gui/footer.php'; ?>

</body>

</html>