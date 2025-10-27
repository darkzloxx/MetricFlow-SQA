<?php
// ============================a
// Conexión a MariaDB
// ============================
$conexion = new mysqli("localhost", "root", "", "bd_prueba", 3308);
if ($conexion->connect_error) {
  die("Error al conectar: " . $conexion->connect_error);
}
//include __DIR__ . '/../gui/footer.php'; // desde /app a /gui

// ============================
// Proyecto (puedes pasar ?proyecto=ID)
// ============================
$idProyecto = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 1;

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


//cantidad de metricas con datos ejecutados
$sqlMetricas = "SELECT COUNT(DISTINCT mi.id_metrica) AS total
            FROM metrica_iteracion mi
            JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
            JOIN fase f ON f.id_fase = i.id_fase
            JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
            WHERE pf.id_proyecto = $idProyecto";
$resMetricas = $conexion->query($sqlMetricas);
$totalMetricas = ($resMetricas && $resMetricas->num_rows > 0) ? (int)$resMetricas->fetch_assoc()['total'] : 0;

// Total iteraciones
$sqlIter = "SELECT COUNT(*) AS total
            FROM iteracion i
            JOIN fase f ON f.id_fase = i.id_fase
            JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
            WHERE pf.id_proyecto = $idProyecto";
$resIter = $conexion->query($sqlIter);
$totalIteraciones = ($resIter && $resIter->num_rows > 0) ? (int)$resIter->fetch_assoc()['total'] : 0;

// Verificar si hay iteraciones aunque no haya métricas
$sqlHayIter = "SELECT COUNT(*) AS total
               FROM iteracion i
               JOIN fase f ON f.id_fase = i.id_fase
               JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
               WHERE pf.id_proyecto = $idProyecto";
$resHayIter = $conexion->query($sqlHayIter);
$hayIteraciones = ($resHayIter && $resHayIter->num_rows > 0 && (int)$resHayIter->fetch_assoc()['total'] > 0);


// Datos para los gráficos
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
        "metrics"   => []
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
      $pct = round(($ejec / $plan) * 100);
      $nota = "";
    }

    $iterMap[$key]["metrics"][] = [
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
// Lógica de iteraciones actual y anterior
// ============================
$hoy = date('Y-m-d');
$actualIter = null;
$anteriorIter = null;
$mensajeActual = '';
$mensajeAnterior = '';

if (count($DATA) === 0) {
  // Caso 1: No hay fases ni iteraciones
  $mensajeActual = 'No se encontraron fases ni iteraciones registradas en este proyecto.';
  $mensajeAnterior = 'Sin datos históricos disponibles.';
} else {
  // Buscar si hay iteración actual (por fechas)
  $actualIndex = null;
  foreach ($DATA as $idx => $it) {
    if ($it['inicio'] <= $hoy && $it['fin'] >= $hoy) {
      $actualIndex = $idx;
      break;
    }
  }

  if ($actualIndex !== null) {
    // ✅ Hay iteración actual (en curso)
    $actualIter = $DATA[$actualIndex];
  } else {
    // ❌ No hay iteración actual → la última registrada pasa a ser la "más reciente anterior"
    $mensajeActual = 'No hay ninguna iteración activa en la fecha actual.';
  }

  // Buscar anterior (todas con fin < inicio actual o, si no hay actual, todas)
  $fechaReferencia = $actualIter ? $actualIter['inicio'] : $hoy;
  $anteriores = array_filter($DATA, fn($it) => $it['fin'] < $fechaReferencia);

  if (count($anteriores) === 0) {
    if ($actualIter) {
      $mensajeAnterior = 'Esta es la primera iteración registrada del proyecto.';
    } else {
      $mensajeAnterior = 'El proyecto tiene iteraciones registradas, pero ninguna está activa actualmente.';
    }
  } else {
    $anteriorIter = end($anteriores); // la última anterior
  }

  // Caso: iteración actual sin métricas
  if ($actualIter && empty($actualIter['metrics'])) {
    $mensajeActual = "La iteración <b>{$actualIter['iteracion']}</b> no tiene métricas planificadas aún.";
  }
}


$conexion->close();
?>


<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <title>php - Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
  <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/uargflow_footer.css" />
  <script src="../lib/JQuery/jquery-3.3.1.js"></script>
  <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
  <!-- html2canvas para exportar PNG -->
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <!-- Carga única y segura de ECharts -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"
    onerror="this.onerror=null;this.src='../lib/echarts.min.js';"></script>

  <style>
    .chart {
      width: 100%;
      height: 360px;
    }

    /* ======== GLOBAL ======== */
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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

    .stat-icon .oi {
      font-size: 20px;
      line-height: 1;
    }

    .card-header-center {
      text-align: center;
    }

    .card-header-center h5 {
      margin: 0;
      font-weight: 600;
      color: #212529;
    }

    .icon-bg-primary {
      background: linear-gradient(135deg, #007bff 0%, #5aa7ff 100%);
    }

    .icon-bg-success {
      background: linear-gradient(135deg, #28a745 0%, #65d488 100%);
    }

    .icon-bg-danger {
      background: linear-gradient(135deg, #dc3545 0%, #ff6b81 100%);
    }

    .stat-content {
      text-align: left;
      flex: 1 1 auto;
    }

    .stat-label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: .02em;
      margin-bottom: 2px;
    }

    .echarts-legend {
      display: flex !important;
      flex-wrap: wrap !important;
      justify-content: center !important;
      gap: 10px 24px !important;
      max-width: 96% !important;
      margin: 8px auto 0 !important;
    }


    #trendWrap {
      /* evita scroll lateral/vertical temporario */
      position: relative;
    }

    #trendChart {
      overflow: visible !important;
      /* permite que el tooltip y las líneas sobresalgan */
    }

    .stat-value {
      font-size: 1.75rem;
      /* Bootstrap 4 no tiene display-6 */
      font-weight: 700;
      line-height: 1.1;
      color: #212529;
    }

    .status-line {
      display: block;
      font-size: .85rem;
      color: #6c757d;
      margin-top: 2px;
    }

    .card.stat-card {
      transition: box-shadow .2s ease;
    }

    .card.stat-card:hover {
      box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
    }

    /* ======== TARJETAS ======== */
    .card {
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 0.75rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      animation: fadeIn 0.5s ease-in;

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

    .card-header h6 {
      font-weight: 600;
      color: #212529;
    }

    .card-header small {
      font-size: 0.85rem;
      color: #6c757d;
    }

    /* ======== LEYENDAS ======== */
    .legend-top,
    .legend-bottom {
      text-align: center;
      width: 100%;
      font-size: 0.85rem;
      color: #495057;
    }

    /* Solo para las cards de los gráficos */
    .card-iteracion {
      min-height: 620px;
      /* altura unificada entre iteraciones */
    }


    .legend-metrics {
      margin-top: 6px;
      color: #444;
      line-height: 1.5;
      font-size: 0.85rem;
    }


    .legend-colors {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
      margin: 0 auto;
      max-width: 90%;
    }

    /* Línea punteada azul (Tiempo transcurrido) */
    .legend-dash {
      width: 22px;
      height: 0;
      border-top: 2px dashed #0a3bcf;
      margin-right: 6px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      font-weight: 500;
    }

    .legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 2px;
      border: 1px solid rgba(0, 0, 0, 0.2);
      margin-right: 6px;
    }

    .icon-bg-secondary {
      background: linear-gradient(135deg, #6c757d 0%, #a0a4a8 100%);
    }

    .legend-line {
      width: 22px;
      height: 0;
      border-top: 2px solid #000;
      margin-right: 6px;
    }

    .card-header+.card-header {
      border-top: 1px solid rgba(0, 0, 0, 0.05);
    }

    /* ======== CHART ======== */
    .chart-scroll {
      overflow-x: auto;
      overflow-y: hidden;
      -webkit-overflow-scrolling: touch;
    }

    .chart-stage {
      display: inline-block;
      min-width: 100%;
    }

    .chart {
      width: 100%;
      height: 380px;
    }

    .chart-controls {
      position: absolute;
      top: 12px;
      right: 16px;
      z-index: 10;
    }

    .btn-toggle {
      display: flex;
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

    .btn-toggle i {
      font-size: 14px;
      transition: transform 0.3s ease;
    }

    .btn-toggle:hover {
      background: #0b5ed7;
      transform: translateY(-1px);
    }

    .btn-toggle.off {
      background: #f8f9fa;
      color: #333;
      border: 1px solid #ccc;
    }

    .btn-toggle.off:hover {
      background: #e9ecef;
    }

    .btn-toggle.off i {
      transform: rotate(180deg);
    }


    /* === Leyenda mejorada === */
    .echarts-legend {
      display: flex !important;
      flex-wrap: wrap !important;
      justify-content: center !important;
      gap: 10px 20px !important;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 12px;
      box-shadow: 0 1px 5px rgba(0, 0, 0, 0.08);
      padding: 8px 16px !important;
      margin: 12px auto 0 !important;
      width: fit-content !important;
    }

    .echarts-legend-item {
      font-size: 13px;
      color: #444;
    }

    @media (max-width: 768px) {
      .chart {
        height: 300px;
      }
    }

    /* Scrollbar estilizado */
    .chart-scroll::-webkit-scrollbar {
      height: 6px;
    }

    .chart-scroll::-webkit-scrollbar-thumb {
      background: rgba(0, 0, 0, 0.25);
      border-radius: 4px;
    }

    /* ======== TÍTULOS ======== */
    .card-title-main {
      font-size: 1.1rem;
      font-weight: 600;
      color: #212529;
    }

    .card-subtitle-dates {
      font-size: 0.9rem;
      color: #6c757d;
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="#">
        <img src="../lib/img/Logo-UNPA-UARG-azul.png" width="30" height="30" class="d-inline-block align-top" alt="">
        MetricFlow SQA - Dashboard
      </a>
    </div>
  </nav>

  <div class="container my-4">
    <?php
    // ============================
    // Validar existencia de proyecto
    // ============================
    if (!$proyectoExiste) {
      echo '
      <div class="card my-5 text-center"
           style="border:1px dashed rgba(220,53,69,0.25); background:rgba(220,53,69,0.03);">
        <div class="card-body p-5">
          <i class="oi oi-warning mb-3" style="font-size:2rem; color:#dc3545;"></i>
          <h5 class="text-danger font-weight-bold mb-2">Proyecto no encontrado</h5>
          <p class="text-muted mb-0">
            No existe un proyecto con el identificador <b>ID ' . htmlspecialchars($idProyecto) . '</b>.<br>
            Verifique el parámetro o cree un nuevo proyecto antes de continuar.
          </p>
        </div>
      </div>';
      echo '</div>   <footer class="footer">UARGFlow BS <span class="oi oi-globe"></span> UNPA-UARG</footer>
'; // cerrar container

      exit; // ✅ corta la ejecución del resto del dashboard
    }
    ?>
    <?php
    // Colores de estado y desvío
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
    <!-- Nombre y estado de proyecto -->
    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-primary"><span class="oi oi-briefcase"></span></div>
            <div class="stat-content">
              <span class="stat-label">Proyecto</span>
              <div class="stat-value"><?= htmlspecialchars($nombreProyecto) ?></div>
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
              <span class="stat-label">Métricas utilizadas</span>
              <div class="stat-value"><?= (int)$totalMetricas ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">

        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-icon icon-bg-secondary">
              <span class="oi oi-loop-circular"></span>
            </div>
            <div class="stat-content">
              <span class="stat-label">Iteraciones</span>
              <div class="stat-value text-dark"><?= (int)$totalIteraciones ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Barras -->
    <div id="barsRow" class="row g-3"></div>

    <!-- Tendencia -->
    <div class="card mb-4">
      <div class="card-header card-header-center">
        <h5 class="mb-0">Tendencia por iteración</h5>
      </div>

      <div class="card-body">
        <div class="chart-controls">
          <button id="toggleLegendBtn" class="btn-toggle">
            <i class="oi oi-eye"></i> <span>Ocultar todo</span>
          </button>
        </div>


        <div id="trendWrap" class="chart-scroll">
          <div class="chart-stage">
            <div id="trendChart" class="chart"></div>
          </div>
        </div>
        <div id="trendLegend" class="legend-colors mt-2"></div>
      </div>
    </div>
  </div>

  <footer class="footer">UARGFlow BS <span class="oi oi-globe"></span> UNPA-UARG</footer>

  <script>
    // ========= Datos del backend =========
    const DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    const HAY_ITERACIONES = <?= $hayIteraciones ? 'true' : 'false'; ?>;
    const ACTUAL = <?= json_encode($actualIter, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    const ANTERIOR = <?= json_encode($anteriorIter, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    const MSG_ACTUAL = <?= json_encode($mensajeActual, JSON_UNESCAPED_UNICODE); ?>;
    const MSG_ANT = <?= json_encode($mensajeAnterior, JSON_UNESCAPED_UNICODE); ?>;

    // ========= Utilidades =========
    function clamp(x, a, b) {
      return Math.min(Math.max(x, a), b);
    }

    function colorSemaforo(valuePct, minPct, maxPct) {
      // valuePct: % de cumplimiento calculado (ejecutado/planificado*100)
      // minPct:  100 - umbral (límite de desviación)
      // maxPct:  100 + umbral (no se usa para el semáforo del flujo)
      valuePct = Number(valuePct) || 0;
      minPct = Number(minPct) || 0;
      if (valuePct >= 100) return "#28a745"; // Verde
      if (valuePct >= minPct) return "#ffc107"; // Amarillo
      return "#dc3545"; // Rojo
    }

    function pctTiempoIter(inicio, fin) {
      const s = new Date(inicio).getTime();
      const e = new Date(fin).getTime();
      const now = Date.now();
      if (!isFinite(s) || !isFinite(e) || e <= s) return 100;
      return clamp(((now - s) / (e - s)) * 100, 0, 100);
    }
    // Fix inicialización cuando hay muchos datos (scroll)
    window.addEventListener("load", () => {
      const charts = document.querySelectorAll(".chart");
      charts.forEach(c => {
        if (c.clientWidth === 0) {
          c.style.width = (window.innerWidth - 100) + "px";
        }
      });
    });

    //aca se inicializa el dashboard
    function renderIteracionChart(it, chartId, legendId) {
      //si no hay métricas
      if (!it || !it.metrics || it.metrics.length === 0) {
        document.getElementById(chartId).innerHTML = `
    <div class="text-center text-muted mt-5">
      No se planificaron métricas para ${it?.iteracion || 'esta iteración'}.
    </div>`;
        return;
      }
      const dom = document.getElementById(chartId);
      const prev = echarts.getInstanceByDom(dom);
      if (prev) prev.dispose();
      const chart = echarts.init(dom);
      const labels = it.metrics.map(m => m.id);

      // === Configuración de máximos ===
      const BAR_WIDTH = 40; // ancho barra base
      const VISIBLE_MAX = 120; // tope visible
      const OVERFLOW_TOP = 140; // “un poco” por encima

      // Antes: const hasNoPlan = ...
      // Nuevo: levantar eje oculto si hay cualquier overflow
      const hasOverflow = it.metrics.some(m =>
        (m.planned === 0 && m.executedReal > 0) || (Number(m.executed) > VISIBLE_MAX)
      );
      const OVERFLOW_MAX = hasOverflow ? OVERFLOW_TOP : VISIBLE_MAX; // eje oculto si hace falta

      // Para las barras normales seguimos usando 120
      const maxYBars = VISIBLE_MAX;

      const execData = it.metrics.map(m => ({
        value: Math.min(m.executed, maxYBars),
        meta: m
      }));
      const BAR_DURATION = 1500;
      const BAR_DELAY_PER_IDX = idx => idx * 150;
      const OVERFLOW_DURATION = 1500;
      const lastDelay = BAR_DELAY_PER_IDX(it.metrics.length - 1);
      const AFTER_OVERFLOW_ALL = lastDelay + BAR_DURATION + OVERFLOW_DURATION + 150;
      const timePct = pctTiempoIter(it.inicio, it.fin);

      const scroll = dom.closest(".chart-scroll");
      const stage = dom.parentElement; // .chart-stage
      const visibleW = scroll ? scroll.clientWidth : 600;
      const neededW = Math.max(visibleW, it.metrics.length * 90);
      if (stage) stage.style.width = `${neededW}px`;
      dom.style.width = "100%";
      if (scroll) {
        scroll.style.overflowX = neededW > visibleW ? "auto" : "hidden";
        scroll.style.overflowY = "hidden";
      }
      chart.resize();
      // umbrales y tiempo aparecerán después de esto
      chart.setOption({
        tooltip: {
          trigger: "axis",
          appendToBody: true,
          boundaryGap: false,
          backgroundColor: "rgba(255,255,255,0.95)",
          borderColor: "#ccc",
          borderWidth: 1,
          textStyle: {
            color: "#222",
            fontSize: 13,
          },
          extraCssText: "box-shadow: 0 2px 8px rgba(0,0,0,0.2); border-radius: 6px;",
          axisPointer: {
            type: "none"
          },
          formatter: params => {
            const p = params[0];
            const m = p.data.meta;
            let html = `<b>#${p.axisValue} - ${m.nombre}</b><br>`;
            html += `Límite Desviación: ${m.min}%<br>`;
            html += `Ejecutado: ${m.executed}% (${m.executedReal} de ${m.planned})<br>`;

            const pct = (m.planned > 0) ?
              (m.executedReal / m.planned) * 100 :
              (m.executedReal > 0 ? 100 : 100); // ✅ ahora si plan=0 y ejec=0 → 100%

            switch (true) {
              // ✅ planificado 0 y ejecutado > 0 → se planificó 0 pero se hizo algo
              case (m.planned === 0 && m.executedReal > 0):
                html += `<span style="color:#17a2b8;font-weight:bold;">ℹ️ Se planificó 0 (+${m.executedReal})</span><br>`;
                break;

                // ✅ planificado 0 y ejecutado 0 → se cumplió
              case (m.planned === 0 && m.executedReal === 0):
                html += `<span style="color:#198754;font-weight:bold;">✔️ Cumple lo planificado (0/0)</span><br>`;
                break;

                // Superó lo planificado
              case (m.planned > 0 && pct > 100):
                html += `<span style="color:#28a745;font-weight:bold;">▲ Supera lo planificado (+${(m.executedReal - m.planned).toFixed(0)})</span><br>`;
                break;

                // Cumplió exactamente lo planificado
              case (m.planned > 0 && Math.round(pct) === 100):
                html += `<span style="color:#198754;font-weight:bold;">✔️ Cumple lo planificado (=${m.planned})</span><br>`;
                break;

                // Dentro del umbral permitido (amarillo)
              case (m.planned > 0 && pct >= m.min && pct < 100):
                html += `<span style="color:#ffc107;font-weight:bold;">⚠️ Dentro del umbral (${pct.toFixed(1)}%)</span><br>`;
                break;

                // Por debajo del umbral (rojo)
              case (m.planned > 0 && pct > 0 && pct < m.min):
                html += `<span style="color:#dc3545;font-weight:bold;">▼ Por debajo del plan (-${(m.planned - m.executedReal).toFixed(0)})</span><br>`;
                break;

                // Sin ejecución (plan > 0 y ejec = 0)
              case (m.planned > 0 && m.executedReal === 0):
                html += `<span style="color:#6c757d;font-weight:bold;">⛔ Sin ejecución (0/${m.planned})</span><br>`;
                break;

                // Sin datos o casos no contemplados
              default:
                html += `<span style="color:#999;">❔ Sin datos disponibles</span><br>`;
            }

            html += `Progreso temporal: ${timePct.toFixed(1)}%`;
            return html;
          }
        },

        grid: {
          left: 56,
          right: 110,
          top: 30, // antes 20 → deja espacio para dibujar sobre 120%
          bottom: 44,
          containLabel: true
        },
        xAxis: {
          type: "category",
          data: labels,
          name: "Métricas",
          nameLocation: "middle",
          nameGap: 28,
          nameTextStyle: {
            fontSize: 12,
            fontWeight: 600,
            color: "#495057"
          },
          axisLabel: {
            formatter: v => `#${v}`,
            margin: 2
          }
        },
        yAxis: [{
            type: "value",
            min: 0,
            max: VISIBLE_MAX, // 120% visible
            name: "Cumplimiento (%)",
            nameLocation: "middle",
            nameGap: 46,
            nameRotate: 90,
            nameTextStyle: {
              fontSize: 12,
              fontWeight: 600,
              color: "#495057"
            },
            axisLabel: {
              formatter: '{value}%',
              margin: 6
            }
          },
          {
            type: "value",
            min: 0,
            max: OVERFLOW_MAX, // 160% para overflow
            show: false, // oculto
            splitLine: {
              show: false
            },
            axisTick: {
              show: false
            },
            axisLine: {
              show: false
            }
          }
        ],

        series: [{
            name: "Ejecutado",
            type: "bar",
            yAxisIndex: 0, // usa el eje visible (120)
            data: execData.map(d => ({
              value: 100,
              meta: d.meta,
              fill: Math.min(d.value, 100)
            })),
            barWidth: BAR_WIDTH,
            z: 10,
            itemStyle: {
              borderColor: "#000",
              borderWidth: 2,
              color: params => {
                const m = params.data.meta;
                const baseColor = colorSemaforo(m.executed, m.min, m.max);
                const fillPct = Math.min(m.executed, 100) / 100;
                return new echarts.graphic.LinearGradient(0, 1, 0, 0, [{
                    offset: 0,
                    color: baseColor
                  },
                  {
                    offset: fillPct,
                    color: baseColor
                  },
                  {
                    offset: fillPct,
                    color: "rgba(255,255,255,0)"
                  },
                  {
                    offset: 1,
                    color: "rgba(255,255,255,0)"
                  }
                ]);
              }
            },
            label: {
              show: true,
              position: "top",
              align: "center",
              verticalAlign: "bottom",
              distance: 4,
              formatter: p => {
                const m = p.data.meta;
                let label = "";
                label += `{main|${m.executed}%}\n{small|${m.executedReal}/${m.planned}}`;
                return label;
              },
              rich: {
                extra: {
                  color: "#28a745",
                  fontSize: 12,
                  fontWeight: "bold",
                  align: "center"
                },
                main: {
                  color: "#000",
                  fontSize: 14,
                  fontWeight: "bold",
                  align: "center"
                },
                small: {
                  color: "#555",
                  fontSize: 11,
                  align: "center"
                },

              }
            },
            animationDuration: BAR_DURATION,
            animationEasing: "cubicOut",
            animationDelay: BAR_DELAY_PER_IDX,
          },

          // === LÍNEAS DE UMBRAL (mejor contraste y estilo más liviano) ===
          {
            name: "Límites de desviación",
            type: "custom",
            coordinateSystem: "cartesian2d",
            silent: true,
            z: 900,
            renderItem: function(params, api) {
              const idx = api.value(0);
              const m = it.metrics[idx];
              if (!m) return null;
              const isHighThreshold = m.min >= 95;

              // Posición del umbral en Y y centro de la categoría en X
              const yPx = api.coord([idx, m.min])[1];
              const xCenter = api.coord([idx, 0])[0];

              // Ancho EXACTO de la barra "Ejecutado"
              const bandW = api.size([1, 0])[0] || 30;
              let barW;
              if (typeof BAR_WIDTH === 'number') {
                barW = BAR_WIDTH;
              } else if (typeof BAR_WIDTH === 'string' && BAR_WIDTH.endsWith('%')) {
                barW = bandW * (parseFloat(BAR_WIDTH) / 100);
              } else {
                barW = bandW * 0.6; // fallback
              }
              const half = barW / 2;

              return {
                type: "line",
                // +0.5 para nitidez en pantallas 1x (pixel snapping)
                shape: {
                  x1: Math.round(xCenter - half) + 0.5,
                  y1: yPx,
                  x2: Math.round(xCenter + half) + 0.5,
                  y2: yPx
                },
                style: {
                  stroke: "#000000",
                  lineWidth: 1.2,
                  opacity: 0
                },
                keyframeAnimation: {
                  duration: 700,
                  delay: 1800,
                  easing: "cubicOut",
                  keyframes: [{
                      percent: 0,
                      style: {
                        opacity: 0,
                        lineWidth: 0
                      }
                    },
                    {
                      percent: 1,
                      style: {
                        opacity: 0.9,
                        lineWidth: 1.2
                      }
                    }
                  ]
                },
                textContent: {
                  style: {
                    text: `${m.min}%`,
                    fill: "#333",
                    fontSize: 11,
                    fontWeight: 600,
                    backgroundColor: "rgba(255,255,255,0.9)",
                    padding: [1, 4],
                    borderRadius: 3,
                    textShadowColor: "rgba(255,255,255,0.9)",
                    textShadowBlur: 3
                  }
                },
                textConfig: {
                  position: isHighThreshold ? "bottom" : "top",
                  offset: [0, isHighThreshold ? 1 : -4]
                }
              };
            },

            data: it.metrics.map((_, idx) => ({
              value: idx
            }))
          },

          // === LÍNEA DE TIEMPO TRANSCURRIDO ===
          {
            name: "Tiempo transcurrido",
            type: "custom",
            coordinateSystem: "cartesian2d",
            silent: true,
            z: 890,
            renderItem: function(params, api) {
              const pct = Math.min(timePct, 120); // cap 120%
              const y = api.coord([0, pct])[1]; // posición Y de la línea
              const xStart = api.coord([0, 0])[0]; //

              // borde derecho real del área de barras
              const lastCenter = api.coord([it.metrics.length - 1, 0])[0];
              const bandW = api.size([1, 0])[0];
              const plotRight = lastCenter + bandW * 0.5;
              const chartW = api.getWidth();

              // margen dinámico: evita que el texto quede pegado
              const labelX = Math.min(plotRight + 90, chartW - 10);

              return {
                type: "group",
                children: [
                  // Línea punteada elegante (animada)
                  {
                    type: "line",
                    shape: {
                      x1: api.coord([it.metrics[0]?.id || 0, 0])[0] - (api.size([1, 0])[0] * 0.5),
                      y1: y,
                      x2: chartW - 50, // hasta el borde derecho del canvas
                      y2: y
                    },
                    style: {
                      stroke: "#0a3bcfff",
                      lineWidth: 1.2,
                      lineDash: [6, 4],
                      opacity: 0
                    },
                    keyframeAnimation: {
                      duration: 400,
                      delay: AFTER_OVERFLOW_ALL - 100, // aparece después del overflow
                      easing: "cubicOut",
                      keyframes: [{
                          percent: 0,
                          style: {
                            opacity: 0,
                            lineWidth: 0
                          }
                        },
                        {
                          percent: 1,
                          style: {
                            opacity: 0.8,
                            lineWidth: 1.0
                          }
                        }
                      ]
                    }
                  },
                  // Texto a la derecha (animado)
                  {
                    type: "text",
                    style: {
                      x: labelX - 50, // corrige leve desplazamiento a la izquierda
                      y: y - 500,
                      text: `${pct.toFixed(1)}%`,
                      fill: "#0a3bcfff",
                      fontWeight: "bold",
                      fontSize: 11.5,
                      textAlign: "center",
                      textVerticalAlign: "bottom",
                      lineHeight: 16,
                      textShadowColor: "rgba(255,255,255,0.7)",
                      textShadowBlur: 3,
                      opacity: 0
                    },
                    keyframeAnimation: {
                      duration: 500,
                      delay: AFTER_OVERFLOW_ALL, // luego de la línea
                      easing: "cubicOut",
                      keyframes: [{
                          percent: 0,
                          style: {
                            opacity: 0,
                            y: y + 6
                          }
                        },
                        {
                          percent: 1,
                          style: {
                            opacity: 1,
                            y: y - 8
                          }
                        }
                      ]
                    }
                  }
                ]
              };
            },
            data: [{
              value: 0
            }]
          },
          {
            name: "Overflow",
            type: "custom",
            coordinateSystem: "cartesian2d",
            xAxisIndex: 0,
            yAxisIndex: 0, // usar el eje visible; calculamos la parte extra en píxeles
            clip: false, // permite dibujar por arriba del 120%
            zlevel: 1,
            animationEasing: "cubicOut",
            animationDuration: 1500,
            animationDelay: idx => BAR_DELAY_PER_IDX(idx) + BAR_DURATION,
            renderItem: function(params, api) {
              const idx = api.value(0);
              const m = it.metrics[idx];
              if (!m) return null;

              const pct = Number(m.executed) || 0;

              // base en 100% (eje visible)
              const base = api.coord([idx, 100]);
              const baseY = base[1];
              const xCenter = api.coord([idx, 0])[0];
              const barWidth = BAR_WIDTH;

              // altura “fuera de escala” hasta OVERFLOW_TOP (p.ej. 140%)
              const overflowExtraPx = api.size([0, (OVERFLOW_TOP - 100)])[1];
              const yToCanvasTop = baseY - overflowExtraPx; // por encima del 120%

              // 1) plan=0 con ejecución → SIEMPRE al tope del canvas oculto
              if (m.planned === 0 && m.executedReal > 0) {
                const height = Math.max(0, overflowExtraPx);
                return {
                  type: "rect",
                  shape: {
                    x: xCenter - barWidth / 2,
                    y: yToCanvasTop,
                    width: barWidth,
                    height
                  },
                  enterFrom: {
                    shape: {
                      y: baseY,
                      height: 0
                    }
                  },
                  transition: ["shape"],
                  style: {
                    fill: "#007bff",
                    opacity: 0.35,
                    stroke: "#0056b3",
                    lineWidth: 1
                  },
                  z: 25
                };
              }

              // 2) 100 < pct ≤ 120 → pintar hasta ese pct exacto
              if (pct > 100 && pct <= VISIBLE_MAX) {
                const yTop = api.coord([idx, pct])[1];
                const height = Math.max(0, baseY - yTop);
                const baseColor = colorSemaforo(pct, m.min, m.max);
                const lightColor = echarts.color.lift(baseColor, 0.3);
                return {
                  type: "rect",
                  shape: {
                    x: xCenter - barWidth / 2,
                    y: yTop,
                    width: barWidth,
                    height
                  },
                  enterFrom: {
                    shape: {
                      y: baseY,
                      height: 0
                    }
                  },
                  transition: ["shape"],
                  style: {
                    fill: lightColor,
                    opacity: 0.55,
                    stroke: baseColor,
                    lineWidth: 0.8
                  },
                  z: 20
                };
              }

              // 3) pct > 120 → llenar hasta el tope del canvas (por arriba del 120)
              if (pct > VISIBLE_MAX) {
                const height = Math.max(0, overflowExtraPx);
                const baseColor = colorSemaforo(pct, m.min, m.max);
                const lightColor = echarts.color.lift(baseColor, 0.3);
                return {
                  type: "rect",
                  shape: {
                    x: xCenter - barWidth / 2,
                    y: yToCanvasTop,
                    width: barWidth,
                    height
                  },
                  enterFrom: {
                    shape: {
                      y: baseY,
                      height: 0
                    }
                  },
                  transition: ["shape"],
                  style: {
                    fill: lightColor,
                    opacity: 0.55,
                    stroke: baseColor,
                    lineWidth: 0.8
                  },
                  z: 20
                };
              }

              return null;
            },
            data: it.metrics.map((_, idx) => idx)
          },

        ]
      });

      // Leyenda de métricas
      if (legendId) {
        const legendDiv = document.getElementById(legendId);
        legendDiv.innerHTML = it.metrics.map(m => `<span class="me-3"><b>#${m.id}</b> = ${m.nombre}</span>`).join(' ');
      }
    }

    function initDashboard() {
      if (typeof echarts === "undefined") {
        console.warn("ECharts no disponible aún, reintentando...");
        setTimeout(initDashboard, 200);
        return;
      }

      // DOM y datos base
      const trendWrap = document.getElementById("trendWrap");
      const trendStage = trendWrap.querySelector(".chart-stage");
      const trendChartDom = document.getElementById("trendChart");
      const METRIC_KEYS = [...new Set((Array.isArray(DATA) ? DATA : [])
        .flatMap(it => (it.metrics || []).map(m => m.nombre)))];

      const palette = [
        "#007bff", "#1b9437ff", "#dc3545", "#b98b00ff", "#0d8092ff",
        "#6f42c1", "#fd7e14", "#147b5cff", "#6610f2", "#e83e8c",
        "#343a40", "#582349ff", "#00c2ff", "#b07ef2", "#ff9f40"
      ];
      const iterLabels = (Array.isArray(DATA) ? DATA : []).map(d => d.iteracion);
      const compactIterLabels = (Array.isArray(DATA) ? DATA : []).map(d => {
        const full = typeof d.iteracion === "string" ? d.iteracion.trim() : "";
        if (!full) return "";
        const firstChar = full.replace(/^\s+/, "").charAt(0).toUpperCase();
        const numberMatch = full.match(/(\d+)(?!.*\d)/);
        const numberPart = numberMatch ? numberMatch[1] : "";
        return `${firstChar}${numberPart}`;
      });

      // Crear chart + responsive ancho por cantidad de iteraciones
      const oldTrend = echarts.getInstanceByDom(trendChartDom);
      if (oldTrend) oldTrend.dispose();
      const trendChart = echarts.init(trendChartDom);

      function resizeTrend() {
        if (trendStage) {
          trendStage.style.width = "100%";
        }
        if (trendChartDom) {
          trendChartDom.style.width = "100%";
        }
        if (trendWrap) {
          trendWrap.style.overflowX = "hidden";
        }
        trendChart.resize();
      }
      resizeTrend();
      window.addEventListener("resize", resizeTrend);

      // Si no hay datos, mostrar aviso y salir
      if (!Array.isArray(DATA) || DATA.length === 0) {
        trendChart.clear();
        trendChartDom.innerHTML = '<div class="text-muted">No hay datos para mostrar.</div>';
      } else {
        const legendType = "plain";

        // Segmentación del eje Y (axis break por defecto)
        const BREAK_START = 130;
        const BREAK_END = 200;
        const BREAK_COMPRESS = 0.18;

        const allTrendValues = DATA.flatMap(it => (it.metrics || []).map(m => Number(m.executed) || 0));
        const realMax = allTrendValues.length ? Math.max(120, Math.max(...allTrendValues)) : 120;
        const useLogScale = realMax > 500;
        const LOG_MIN = 0.1;

        const candidateTicks = [10, 50, 100, 200, 500, 1000, 2000, 5000, 10000];
        const discreteTicks = [0];
        const upperBound = Math.max(realMax, 130);
        candidateTicks.forEach(tick => {
          if (tick <= upperBound) discreteTicks.push(tick);
        });
        if (discreteTicks[discreteTicks.length - 1] < realMax) {
          const magnitude = Math.pow(10, Math.floor(Math.log10(realMax)));
          const rounded = Math.ceil(realMax / magnitude) * magnitude;
          if (!discreteTicks.includes(rounded)) {
            discreteTicks.push(rounded);
          }
        }
        const topTick = discreteTicks[discreteTicks.length - 1];

        const projectValue = val => {
          const numeric = Number(val) || 0;
          if (useLogScale) {
            return numeric > 0 ? numeric : LOG_MIN;
          }
          if (numeric <= BREAK_START) return numeric;
          if (numeric < BREAK_END) {
            return BREAK_START + (numeric - BREAK_START) * BREAK_COMPRESS;
          }
          const compressedGap = (BREAK_END - BREAK_START) * BREAK_COMPRESS;
          return BREAK_START + compressedGap + (numeric - BREAK_END) * BREAK_COMPRESS;
        };

        const restoreValue = axisVal => {
          const numeric = Number(axisVal) || 0;
          if (useLogScale) {
            if (numeric <= LOG_MIN + 1e-6) return 0;
            return numeric;
          }
          if (numeric <= BREAK_START) return numeric;
          const compressedGap = (BREAK_END - BREAK_START) * BREAK_COMPRESS;
          if (numeric <= BREAK_START + compressedGap) {
            return BREAK_START + (numeric - BREAK_START) / BREAK_COMPRESS;
          }
          return BREAK_END + (numeric - BREAK_START - compressedGap) / BREAK_COMPRESS;
        };

        const projectedMax = useLogScale ?
          topTick * 1.05 :
          projectValue(topTick) + 6;

        const lineSeries = METRIC_KEYS.map((name, sIdx) => ({
          name,
          type: "line",
          smooth: false,
          showSymbol: true,
          symbol: "circle",
          symbolSize: 10,
          lineStyle: {
            width: 3.5,
            color: palette[sIdx % palette.length],
            shadowColor: "rgba(0,0,0,0.08)",
            shadowBlur: 3
          },
          itemStyle: {
            color: palette[sIdx % palette.length],
            borderColor: "#fff",
            borderWidth: 1
          },
          emphasis: {
            focus: "series",
            lineStyle: {
              width: 3.2
            },
            label: {
              show: true,
              position: "top",
              distance: 6,
              backgroundColor: "rgba(255,255,255,0.9)",
              borderColor: "#ddd",
              borderWidth: 1,
              borderRadius: 4,
              padding: [2, 4],
              color: "#111",
              fontSize: 12,
              fontWeight: 600,
              formatter: function(p) {
                const raw = typeof p.data?.realValue === "number" ? p.data.realValue : 0;
                const num = Number(raw);
                if (!Number.isFinite(num)) return "";
                const display = Math.abs(num) >= 100 ? Math.round(num) : Number(num.toFixed(1));
                return display + "%";
              }
            }
          },
          blur: {
            lineStyle: {
              opacity: 0.10
            },
            itemStyle: {
              opacity: 0.10
            }
          },
          data: DATA.map(it => {
            const m = it.metrics.find(mm => mm.nombre === name);
            const real = m ? Number(m.executed) || 0 : 0;
            return {
              value: projectValue(real),
              realValue: real,
              meta: m ? {
                nombre: m.nombre,
                executedReal: m.executedReal,
                planned: m.planned,
                unit: m.unit,
                noPlan: m.noPlan,
                extra: m.extra
              } : null
            };
          }),
          label: {
            show: false
          }
        }));

        trendChart.setOption({
          backgroundColor: "#fff",
          tooltip: {
            trigger: "axis",
            appendToBody: true,
            confine: false,
            backgroundColor: "rgba(255,255,255,0.95)",
            borderColor: "#ddd",
            borderWidth: 1,
            textStyle: {
              color: "#222",
              fontSize: 13
            },
            extraCssText: `
              box-shadow: 0 2px 8px rgba(0,0,0,0.12);
              border-radius: 6px;
              max-width: 340px;
              white-space: normal;
              z-index: 9999;
            `,
            axisPointer: {
              type: "line",
              label: {
                formatter: ({
                  value
                }) => {
                  const restored = restoreValue(value);
                  const num = Number(restored);
                  if (!Number.isFinite(num)) return "—";
                  const display = Math.abs(num) >= 100 ?
                    Math.round(num) :
                    Number(num.toFixed(1));
                  return `${display}%`;
                }
              }
            },
            formatter: function(params) {
              const idx = params[0]?.dataIndex ?? 0;
              const iter = iterLabels[idx] || "";
              let html = `<b>${iter}</b><br/>`;
              params.forEach(p => {
                if (p.seriesName === "Referencia 100%") return;
                const meta = p.data?.meta;
                const pctRaw = typeof p.data?.realValue === "number" ?
                  p.data.realValue :
                  restoreValue(p.value ?? 0);
                const pctNum = Number(pctRaw);
                const pctLabel = Number.isFinite(pctNum) ?
                  `${(Math.abs(pctNum) >= 100 ? Math.round(pctNum) : Number(pctNum.toFixed(1)))}%` :
                  "—";
                const ejec = meta?.executedReal ?? "—";
                const plan = meta?.planned ?? "—";
                const dot = `<span style="display:inline-block;margin-right:6px;width:10px;height:10px;background:${p.color};border-radius:50%"></span>`;
                html += `${dot}${p.seriesName}: <b>${pctLabel}</b> (${ejec}/${plan})<br/>`;
              });
              return html;
            }
          },
          legend: {
            type: legendType,
            data: METRIC_KEYS,
            bottom: 10,
            left: "center",
            width: "96%",
            itemWidth: 10,
            itemHeight: 10,
            icon: "circle",
            itemGap: 18,
            padding: [6, 10, 6, 10],

            selectorLabel: {
              color: "#0d6efd",
              fontWeight: 600
            },
            selectorPosition: "start",
            textStyle: {
              fontSize: 12.5,
              color: "#444"
            },
            animation: false
          },
          grid: {
            left: 70,
            right: 90,
            top: 56,
            bottom: 130,
            containLabel: true
          },
          xAxis: {
            type: "category",
            data: compactIterLabels,
            name: "Iteración",
            nameLocation: "middle",
            nameGap: 40,
            nameTextStyle: {
              fontSize: 12,
              fontWeight: 600,
              color: "#495057"
            },
            axisLine: {
              show: true,
              lineStyle: {
                color: "#6c757d",
                width: 1.2
              }
            },
            axisTick: {
              show: true,
              alignWithLabel: true,
              length: 8,
              lineStyle: {
                color: "#6c757d"
              }
            },
            axisLabel: {
              color: "#555",
              fontWeight: 500,
              margin: 18
            },
            splitLine: {
              show: false
            },
            splitArea: {
              show: false
            }
          },
          yAxis: useLogScale ? {
            type: "log",
            logBase: 10,
            min: LOG_MIN,
            max: topTick,
            name: "Cumplimiento (%)",
            nameLocation: "middle",
            nameGap: 60,
            nameRotate: 90,
            nameTextStyle: {
              fontSize: 12,
              fontWeight: 600,
              color: "#495057"
            },
            axisLine: {
              show: true,
              lineStyle: {
                color: "#6c757d",
                width: 1.2
              }
            },
            axisTick: {
              show: true,
              inside: false,
              length: 4
            },
            splitLine: {
              show: true,
              lineStyle: {
                color: "rgba(0,0,0,0.08)"
              }
            },
            minorTick: {
              show: false
            },
            minorSplitLine: {
              show: false
            },
            axisLabel: {
              margin: 16,
              formatter: function(val) {
                if (val <= LOG_MIN + 1e-6) return "0%";
                const epsilon = val < 10 ? 0.5 : Math.max(1, val * 0.08);
                const match = discreteTicks.find(t => Math.abs(val - t) <= epsilon);
                if (match !== undefined) {
                  const isTop = match === topTick && realMax > match;
                  return `${isTop ? "≥" : ""}${match}%`;
                }
                return "";
              }
            }
          } : {
            type: "value",
            min: 0,
            max: projectedMax,
            name: "Cumplimiento (%)",
            nameLocation: "middle",
            nameGap: 60,
            nameRotate: 90,
            nameTextStyle: {
              fontSize: 12,
              fontWeight: 600,
              color: "#495057"
            },
            axisLine: {
              show: true,
              lineStyle: {
                color: "#6c757d",
                width: 1.2
              }
            },
            axisTick: {
              show: false
            },
            minorTick: {
              show: false
            },
            splitLine: {
              show: true,
              lineStyle: {
                color: "rgba(0,0,0,0.06)"
              }
            },
            minorSplitLine: {
              show: false
            },
            axisLabel: {
              margin: 16,
              formatter: function(val) {
                const real = restoreValue(val);
                if (Math.abs(real - BREAK_START) < 0.5) {
                  return `{tick|${Math.round(real)}%}\n{break|//}`;
                }
                const epsilon = real < 10 ? 0.5 : Math.max(1, real * 0.06);
                const match = discreteTicks.find(t => Math.abs(real - t) <= epsilon);
                if (match !== undefined) {
                  const isTop = match === topTick && realMax > match;
                  return `{tick|${isTop ? "≥" : ""}${match}%}`;
                }
                return "";
              },
              rich: {
                tick: {
                  color: "#666",
                  fontSize: 11,
                  fontWeight: 500
                },
                break: {
                  color: "#888",
                  fontSize: 11,
                  lineHeight: 12
                }
              }
            }
          },
          series: [
            ...lineSeries,
            {
              name: "Referencia 100%",
              type: "line",
              silent: true,
              symbol: "none",
              lineStyle: {
                type: "dashed",
                color: "#6c757d",
                width: 3.5,
                opacity: 0.95
              },
              markLine: {
                symbol: "none",
                lineStyle: {
                  type: "dashed",
                  color: "#6c757d",
                  width: 1.7,
                  opacity: 0.95
                },
                label: {
                  show: true,
                  position: "end",
                  formatter: "100%",
                  color: "#6c757d",
                  backgroundColor: "rgba(255,255,255,.6)",
                  padding: [2, 4],
                  show: false
                },
                data: [{
                  yAxis: projectValue(100)
                }]
              }
            }
          ]
        });

        // Mostrar etiquetas de TODOS los puntos al pasar por encima de la línea o su leyenda
        (function() {
          let lastLabeledSeries = null;

          function setSeriesLabelsVisible(seriesIdx, visible) {
            const opt = trendChart.getOption();
            if (!opt || !opt.series || !opt.series[seriesIdx]) return;
            const s = opt.series[seriesIdx];
            if (s.type !== 'line' || s.name === 'Referencia 100%') return;

            const labelCfg = {
              show: !!visible,
              position: 'top',
              distance: 6,
              backgroundColor: 'rgba(255,255,255,0.9)',
              borderColor: '#ddd',
              borderWidth: 1,
              borderRadius: 4,
              padding: [2, 4],
              color: '#111',
              fontSize: 12,
              fontWeight: 600,
              formatter: function(p) {
                const raw = typeof p.data?.realValue === 'number' ? p.data.realValue : 0;
                const num = Number(raw);
                if (!Number.isFinite(num)) return '';
                const display = Math.abs(num) >= 100 ? Math.round(num) : Number(num.toFixed(1));
                return display + '%';
              }
            };
            s.label = Object.assign({}, s.label || {}, labelCfg);
            trendChart.setOption({
              series: opt.series
            }, {
              lazyUpdate: true
            });
          }

          // Hover sobre segmentos/símbolos de la serie
          trendChart.getZr().on('mousemove', function() {
            /* noop to keep ZR active */ });
          trendChart.on('mouseover', function(params) {
            if (params && params.componentType === 'series' && params.seriesType === 'line' && params.seriesName !== 'Referencia 100%') {
              const idx = params.seriesIndex;
              if (lastLabeledSeries !== idx) {
                if (lastLabeledSeries !== null) setSeriesLabelsVisible(lastLabeledSeries, false);
                setSeriesLabelsVisible(idx, true);
                lastLabeledSeries = idx;
              }
            }
          });
          trendChart.on('mouseout', function(params) {
            if (params && params.componentType === 'series' && params.seriesType === 'line' && params.seriesName !== 'Referencia 100%') {
              const idx = params.seriesIndex;
              setSeriesLabelsVisible(idx, false);
              if (lastLabeledSeries === idx) lastLabeledSeries = null;
            }
          });

          // Hover desde la leyenda (legend hover dispara highlight/downplay)
          trendChart.on('highlight', function(params) {
            if (params && params.seriesType === 'line' && params.seriesName !== 'Referencia 100%') {
              const idx = params.seriesIndex;
              if (lastLabeledSeries !== idx) {
                if (lastLabeledSeries !== null) setSeriesLabelsVisible(lastLabeledSeries, false);
                setSeriesLabelsVisible(idx, true);
                lastLabeledSeries = idx;
              }
            }
          });
          trendChart.on('downplay', function(params) {
            if (params && params.seriesType === 'line' && params.seriesName !== 'Referencia 100%') {
              const idx = params.seriesIndex;
              setSeriesLabelsVisible(idx, false);
              if (lastLabeledSeries === idx) lastLabeledSeries = null;
            }
          });

          // Salida global del lienzo
          trendChart.on('globalout', function() {
            if (lastLabeledSeries !== null) {
              setSeriesLabelsVisible(lastLabeledSeries, false);
              lastLabeledSeries = null;
            }
          });
        })();

        window.__TREND_ALREADY_RENDERED = true;
      }

      const row = document.getElementById("barsRow");
      row.innerHTML = "";
      const trendCard = document.querySelector(".card.mb-4");

      // VALIDACIONES GENERALES
      // ==========================
      if (!Array.isArray(DATA) || DATA.length === 0) {
        if (HAY_ITERACIONES) {
          // ⚠️ Caso 2: Hay iteraciones pero ninguna tiene métricas
          if (trendCard) trendCard.style.display = "none";
          row.innerHTML = `
      <div class="col-12">
        <div class="card h-100 d-flex flex-column justify-content-center align-items-center text-center"
             style="border: 1px dashed rgba(13,110,253,0.25); background: rgba(13,110,253,0.05); min-height:340px;">
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
          // ❌ Caso 1: No hay iteraciones en absoluto
          if (trendCard) trendCard.style.display = "none";
          row.innerHTML = `
      <div class="col-12">
        <div class="card h-100 d-flex flex-column justify-content-center align-items-center text-center"
             style="border: 1px dashed rgba(108,117,125,0.25); background: rgba(248,249,250,0.7); min-height:340px;">
          <div class="p-4">
            <i class="oi oi-clock mb-3" style="font-size:2rem; color:#6c757d;"></i>
            <h6 class="text-secondary font-weight-bold mb-2">No hay iteraciones cargadas</h6>
            <p class="text-muted mb-0" style="max-width:420px;">
              Espere al líder del proyecto.
            </p>
          </div>
        </div>
      </div>`;
        }
        return;
      }

      // ✅ Si pasa validaciones → mostrar la card
      if (trendCard) trendCard.style.display = "";


      // ========== Cards de barras (izquierda/derecha) ==========
      row.innerHTML = "";

      // ---- Card Izquierda: Anterior + selector ----
      // Lista de iteraciones anteriores válidas respecto al inicio de la actual (si existe)
      const anteriorLista = Array.isArray(DATA) ?
        DATA.filter(d => {
          if (ACTUAL && ACTUAL.inicio) return new Date(d.fin).getTime() < new Date(ACTUAL.inicio).getTime();
          return new Date(d.fin).getTime() < Date.now();
        }) : [];

      if (anteriorLista.length === 0) {
        const colLeft = document.createElement("div");
        colLeft.className = "col-12 col-xl-6";
        colLeft.innerHTML = `
  <div class="card card-iteracion h-100">
    <div class="card-header bg-transparent border-0">
      <div class="text-center" style="font-weight:600; color:#0d6efd;">
        <i class="oi oi-layers mr-1"></i> Iteración anterior
      </div>
    </div>

    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
      <i class="oi oi-layers mb-2" style="font-size:1.8rem; color:#6c757d;"></i>
      <p class="mb-1" style="font-weight:600; color:#adb5bd;">
        No existen iteraciones anteriores
      </p>
      <p class="mb-0" style="font-size:0.9rem; color:#868e96; max-width:420px;">
        Las iteraciones previas aparecerán aquí automáticamente cuando el proyecto registre más de una iteración.
      </p>
    </div>
  </div>`;

        row.appendChild(colLeft);
      } else {
        const defIter = ANTERIOR || anteriorLista[anteriorLista.length - 1];
        const defIndex = DATA.findIndex(d => d.iteracion === defIter.iteracion);

        const colLeft = document.createElement("div");
        colLeft.className = "col-12 col-xl-6";
        colLeft.innerHTML = `
    <div class="card card-iteracion h-100">
      <div class="card-header bg-transparent border-0">
        <div class="text-center" style="font-weight:600; color:#0d6efd;">
          <i class="oi oi-layers mr-1"></i> Iteración anterior
        </div>
      </div>
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <div class="card-title-main" id="left-title">${defIter.iteracion}</div>
          <div class="card-subtitle-dates" id="left-dates">Del ${defIter.inicio} al ${defIter.fin}</div>
        </div>
        <select id="hist-select" class="custom-select custom-select-sm" style="width:auto;">
          ${anteriorLista.map(d => {
            const idx = DATA.findIndex(x => x.iteracion === d.iteracion);
            const sel = (idx === defIndex) ? 'selected' : '';
            return `<option value="${idx}" ${sel}>${d.iteracion}</option>`;
          }).join('')}
        </select>
      </div>
      <div class="card-body p-3">
        <div class="chart-scroll mb-2"><div class="chart-stage"><div id="bars-anterior" class="chart"></div></div></div>
        <div class="legend-colors mb-2">
          <span class="legend-item"><span class="legend-dot" style="background:#28a745;"></span>Se cumplió</span>
          <span class="legend-item"><span class="legend-dot" style="background:#ffc107;"></span>Dentro de umbral</span>
          <span class="legend-item"><span class="legend-dot" style="background:#dc3545;"></span>Debajo de límite</span>
          <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span>Planificado=0</span>
          <span class="legend-item"><span class="legend-line"></span>Umbral</span>
          <span class="legend-item"><span class="legend-dash"></span>Progreso iteración</span>
        </div>
        <div class="legend-bottom mt-3">
          <div id="legend-metrics-anterior" class="mb-2"></div>
        </div>
      </div>
    </div>`;
        row.appendChild(colLeft);
        renderIteracionChart(defIter, "bars-anterior", "legend-metrics-anterior");


        // Cambio de selección
        document.addEventListener("change", e => {
          if (e.target && e.target.id === "hist-select") {
            const idx = parseInt(e.target.value, 10);
            const chosen = DATA[idx];
            document.getElementById("left-title").textContent = chosen.iteracion;
            document.getElementById("left-dates").textContent = `Del ${chosen.inicio} al ${chosen.fin}`;
            renderIteracionChart(chosen, "bars-anterior", "legend-metrics-anterior");
          }
        });
      }

      // ---- Card Derecha: Actual o mensaje ----
      // ---- Card Derecha: Actual (3 casos) ----
      const colRight = document.createElement("div");
      colRight.className = "col-12 col-xl-6";

      // ✅ Caso 1: hay iteración actual y métricas cargadas
      if (ACTUAL && Array.isArray(ACTUAL.metrics) && ACTUAL.metrics.length > 0) {
        colRight.innerHTML = `
    <div class="card card-iteracion h-100">
      <div class="card-header bg-transparent border-0">
        <div class="text-center" style="font-weight:600; color:#198754;">
          <i class="oi oi-play-circle mr-1"></i> Iteración actual
        </div>
      </div>
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <div class="card-title-main">${ACTUAL.iteracion}</div>
          <div class="card-subtitle-dates">Del ${ACTUAL.inicio} al ${ACTUAL.fin}</div>
        </div>
      </div>
      <div class="card-body p-3">
        <div class="chart-scroll mb-2"><div class="chart-stage"><div id="bars-actual" class="chart"></div></div></div>
        <div class="legend-colors mb-2">
          <span class="legend-item"><span class="legend-dot" style="background:#28a745;"></span>Se cumplió</span>
          <span class="legend-item"><span class="legend-dot" style="background:#ffc107;"></span>Dentro de umbral</span>
          <span class="legend-item"><span class="legend-dot" style="background:#dc3545;"></span>Debajo de límite</span>
          <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span>Planificado=0</span>
          <span class="legend-item"><span class="legend-line"></span>Umbral</span>
          <span class="legend-item"><span class="legend-dash"></span>Progreso iteración</span>
        </div>
        <div class="legend-bottom mt-3">
          <div id="legend-metrics-actual" class="mb-2"></div>
        </div>
      </div>
    </div>`;
        row.appendChild(colRight);
        renderIteracionChart(ACTUAL, "bars-actual", "legend-metrics-actual");


      }
      // ⚠️ Caso 2: existe iteración actual pero sin métricas planificadas
      else if (ACTUAL && (!ACTUAL.metrics || ACTUAL.metrics.length === 0)) {
        colRight.innerHTML = `
    <div class="card card-iteracion h-100 d-flex flex-column justify-content-center align-items-center text-center"
         style="border: 1px dashed rgba(13,110,253,0.25); background: rgba(13,110,253,0.03);">
      <div class="p-4">
        <i class="oi oi-bar-chart mb-2" style="font-size:1.8rem; color:#0d6efd;"></i>
        <p class="mb-1" style="font-weight:600; color:#adb5bd;">Iteración sin métricas</p>
        <p class="mb-0" style="font-size:0.9rem; color:#868e96;">
          No se han planificado métricas para esta fase (${ACTUAL.iteracion}).
        </p>
      </div>
    </div>`;
        row.appendChild(colRight);
      }

      // 🟥 Caso 3: no existe iteración actual planificada (según fecha)
      else {
        colRight.innerHTML = `
  <div class="card card-iteracion h-100">
    <div class="card-header bg-transparent border-0">
      <div class="text-center" style="font-weight:600; color:#198754;">
          <i class="oi oi-play-circle mr-1"></i> Iteración actual
      </div>
    </div>

    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
<i class="oi oi-calendar mb-2" style="font-size:1.8rem; color:#6c757d;"></i>
      <p class="mb-1" style="font-weight:600; color:#adb5bd;">
        No hay iteración activa
      </p>
      <p class="mb-0" style="font-size:0.9rem; color:#868e96; max-width:420px;">
        ${MSG_ACTUAL || "El proyecto no tiene una iteración planificada para la fecha actual."}
      </p>
    </div>
  </div>`;

        row.appendChild(colRight);
      }
    }

    document.addEventListener("DOMContentLoaded", initDashboard);
    document.addEventListener("DOMContentLoaded", function() {
      const trendChartInstance = echarts.getInstanceByDom(document.getElementById("trendChart"));
      const toggleBtn = document.getElementById("toggleLegendBtn");
      const toggleIcon = toggleBtn.querySelector("i");
      const toggleText = toggleBtn.querySelector("span");
      let allVisible = true;

      if (trendChartInstance && toggleBtn) {
        toggleBtn.addEventListener("click", function() {
          const option = trendChartInstance.getOption();
          if (!option.legend || !option.legend[0]) return;

          const selected = {};
          option.legend[0].data.forEach(name => {
            selected[name] = !allVisible; // alterna visibilidad
          });
          option.legend[0].selected = selected;
          trendChartInstance.setOption(option);
          allVisible = !allVisible;

          // Actualiza texto, icono y estilo
          if (allVisible) {
            toggleText.textContent = "Ocultar todo";
            toggleIcon.className = "oi oi-eye";
            toggleBtn.classList.remove("off");
          } else {
            toggleText.textContent = "Mostrar todo";
            toggleIcon.className = "oi oi-eye-slash";
            toggleBtn.classList.add("off");
          }
        });
      }
    });
  </script>
</body>

</html>