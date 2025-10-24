<?php
// ============================
// Conexión a MariaDB
// ============================
$conexion = new mysqli("localhost", "root", "", "bd", 3308);
if ($conexion->connect_error) {
  die("Error al conectar: " . $conexion->connect_error);
}

// ============================
// Proyecto (puedes pasar ?proyecto=ID)
// ============================
$idProyecto = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 1;

// Resumen general
$sqlProyecto = "SELECT nombre, estado FROM proyecto WHERE id_proyecto = $idProyecto";
$resProyecto = $conexion->query($sqlProyecto);
if ($resProyecto && $resProyecto->num_rows > 0) {
  $row = $resProyecto->fetch_assoc();
  $nombreProyecto = $row['nombre'];
  $estadoProyecto = $row['estado'];
} else { 
  $nombreProyecto = "Proyecto";
  $estadoProyecto = "Sin estado";
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

// Desviación promedio calculado como AVG de |(Ejecutado - Planificado) / Planificado * 100|
$sqlDesv = "SELECT AVG(ABS(((mi.valor_ejecutado - mi.valor_planificado)/NULLIF(mi.valor_planificado,0))*100)) AS desv
            FROM metrica_iteracion mi
            JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
            JOIN fase f ON f.id_fase = i.id_fase
            JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
            WHERE pf.id_proyecto = $idProyecto";
$resDesv = $conexion->query($sqlDesv);
$desviacionPromedio = ($resDesv && $resDesv->num_rows > 0) ? round((float)$resDesv->fetch_assoc()['desv'], 2) : 0.0;


// Datos para los gráficos
$query = "
SELECT 
    i.id_iteracion,
    i.numero_iteracion,
    f.nombre AS fase,
    i.fecha_inicio AS inicio,
    i.fecha_fin AS fin,
    m.id_metrica AS id_metrica,     -- << agregado
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

// Armar estructura para ECharts
$iterMap = [];
if ($result) {
  while ($r = $result->fetch_assoc()) {
    // Antes: "Iteración N"
    $key = trim($r['fase'] . ' ' . $r['numero_iteracion']); // Ej: "Elaboración 2"
    if (!isset($iterMap[$key])) {
      $iterMap[$key] = [
        "iteracion" => $key,   // eje X del trend y título de cada card
        "fase"      => $r['fase'],
        "numero"    => $r['numero_iteracion'],
        "inicio"    => $r['inicio'],
        "fin"       => $r['fin'],
        "metrics"   => []
      ];
    }
    $plan = (float)$r['planificado'];
    $ejec = (float)$r['ejecutado'];
    $pct  = $plan > 0 ? round(($ejec / $plan) * 100, 2) : 0;

    $iterMap[$key]["metrics"][] = [
      "id"           => (int)$r['id_metrica'], // agregado (ID en el eje X de barras)
      "nombre"       => $r['metrica'],
      "executed"     => $pct,
      "planned"      => $r['planificado'],
      "executedReal" => $r['ejecutado'],
      "unit"         => "u",
      "min"          => max(0, 100 - (float)$r['umbral']),
      "max"          => 100 + (float)$r['umbral']
    ];
  }
}
$conexion->close();
$DATA = array_values($iterMap);
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

  <!-- Carga única y segura de ECharts -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"
    onerror="this.onerror=null;this.src='../lib/echarts.min.js';"></script>

  <style>
    .chart {
      width: 100%;
      height: 360px;
    }

    @media (max-width: 576px) {
      .chart {
        height: 300px;
      }
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
    <!-- Nombre y estado de proyecto -->
    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <div class="card h-100 text-center">
          <div class="card-body">
            <h6 class="mb-1">Proyecto</h6>
            <div class="fw-bold"><?= htmlspecialchars($nombreProyecto) ?></div>
            <small class="text-muted">Estado: <?= htmlspecialchars($estadoProyecto) ?></small>
          </div>
        </div>
      </div>
           <div class="col-6 col-md-4">
        <div class="card h-100 text-center">
          <div class="card-body">
            <h6 class="mb-1">Métricas utilizadas</h6>
            <div class="display-6"><?= $totalMetricas ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card h-100 text-center">
          <div class="card-body">
            <h6 class="mb-1">Desviación promedio</h6>
            <div class="display-6 text-danger"><?= $desviacionPromedio ?>%</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tendencia -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Tendencia por iteración</h5>
      </div>
      <div class="card-body">
        <div id="trendWrap" style="overflow-x:auto;">
          <div id="trendChart" class="chart"></div>
        </div>
      </div>
    </div>

    <!-- Barras -->
    <div id="barsRow" class="row g-3"></div>
  </div>

  <footer class="footer">UARGFlow BS <span class="oi oi-globe"></span> UNPA-UARG</footer>

  <script>
    const DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;

    // Utilidades
    function clamp(x, a, b) {
      return Math.min(Math.max(x, a), b);
    }

    function colorSemaforo(value, min, max) {
      const margin = Math.max(2, (max - min) * 0.05);
      const inRange = value >= min && value <= max;
      const near = (value >= min && value <= min + margin) || (value <= max && value >= max - margin);
      if (inRange) return near ? "#ffc107" : "#28a745";
      return "#dc3545";
    }

    function pctTiempoIter(inicio, fin) {
      const s = new Date(inicio).getTime();
      const e = new Date(fin).getTime();
      const now = Date.now();
      if (!isFinite(s) || !isFinite(e) || e <= s) return 100;
      return clamp(((now - s) / (e - s)) * 100, 0, 100);
    }

    // Esperar a ECharts cargado
    function initDashboard() {
      if (typeof echarts === "undefined") {
        console.warn("ECharts no disponible aún, reintentando...");
        setTimeout(initDashboard, 200);
        return;
      }

      if (!Array.isArray(DATA) || DATA.length === 0) {
        document.getElementById('trendChart').innerHTML = '<div class="text-muted">No hay datos para mostrar.</div>';
        return;
      }

      // === TENDENCIA ===
      const METRIC_KEYS = [...new Set(DATA.flatMap(it => it.metrics.map(m => m.nombre)))];
      const palette = ["#007bff", "#28a745", "#e83e8c", "#fd7e14", "#6f42c1", "#20c997", "#6610f2", "#17a2b8"];
      const iterLabels = DATA.map(d => d.iteracion);
      // Ajuste de ancho + scroll horizontal
      const perIterPx = 160; // px por iteración
      const trendWrap = document.getElementById("trendWrap");
      const trendChartDom = document.getElementById("trendChart");
      const trendChart = echarts.init(trendChartDom);

      function resizeTrend() {
        const wrapW = trendWrap.clientWidth || 800; // ancho disponible
        const needed = Math.max(wrapW, DATA.length * perIterPx);
        trendChartDom.style.width = needed + "px"; // ocupa 100% o se expande para scroll
        trendChart.resize();
      }
      resizeTrend();
      window.addEventListener("resize", resizeTrend);

const lineSeries = METRIC_KEYS.map((name, idx) => ({
  name,
  type: "line",
  smooth: false,
  showSymbol: true,
  lineStyle: {
    width: 2,
    color: palette[idx % palette.length]
  },
  itemStyle: {
    color: palette[idx % palette.length]
  },
  // << destacar la serie activa y atenuar las demás
  emphasis: { focus: "series" },
  blur: {
    lineStyle: { opacity: 0.25 },
    itemStyle: { opacity: 0.25 }
  },
  data: DATA.map(it => {
    const m = it.metrics.find(mm => mm.nombre === name);
    return m ? m.executed : 0;
  })
}));

      const maxYTrend = Math.max(
        120,
        Math.ceil(Math.max(...DATA.flatMap(it => it.metrics.map(m => Math.max(m.max, m.executed)))) / 10) * 10
      );


trendChart.setOption({
  tooltip: {
    trigger: "axis",
    valueFormatter: v => `${v}%`
  },
  legend: {
    data: METRIC_KEYS,
    top: 8,              // << bajar un poco la leyenda
    itemGap: 18
  },
  grid: {
    left: 48,
    right: 84,
    top: 88,             // << más margen superior para que no toque la leyenda
    bottom: 40,
    containLabel: true
  },
  xAxis: {
    type: "category",
    data: iterLabels
  },
  yAxis: {
    type: "value",
    min: 0,
    max: maxYTrend,
    axisLabel: { formatter: '{value}%' }
  },
  series: [
    ...lineSeries,
    {
      name: "Referencia 100%",
      type: "line",
      silent: true,
      symbol: "none",
      markLine: {
        symbol: "none",
        lineStyle: { type: "dashed", color: "#6c757d" },
        data: [{ yAxis: 100 }]
      }
    }
  ]
});

      // === BARRAS POR ITERACIÓN ===
      const row = document.getElementById("barsRow");

      DATA.forEach((it, i) => {
        const timePct = pctTiempoIter(it.inicio, it.fin);
        const col = document.createElement("div");
        col.className = "col-12 col-xl-6";
        col.innerHTML = `
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">${it.iteracion}</h6>
        <small class="text-muted">Del ${it.inicio} al ${it.fin}</small>
      </div>
      <div class="card-body">
        <div id="bars-${i}" class="chart"></div>
        <div id="legend-${i}" class="small text-muted mt-2"></div>
      </div>
    </div>`;
        row.appendChild(col);

        const dom = document.getElementById(`bars-${i}`);
        const chart = echarts.init(dom);

  const labels = it.metrics.map(m => m.id);
        const maxYBars = 120; // << limite fijo del eje Y

        const execData = it.metrics.map(m => ({
          // pintar solo hasta el 120%
          value: Math.min(m.executed, maxYBars),
          meta: m
        }));
        const colors = it.metrics.map(m => colorSemaforo(m.executed, m.min, m.max));


        chart.setOption({
          tooltip: {
            trigger: "axis",
            axisPointer: { type: "shadow" },
            formatter: params => {
              const p = params[0];
              const m = p.data.meta;
              return `<b>#${p.axisValue} - ${m.nombre}</b><br>
                Rango objetivo: ${m.min}% – ${m.max}%<br>
                Ejecutado: ${m.executed}% (${m.executedReal} de ${m.planned})<br>
                Progreso temporal: ${timePct.toFixed(1)}%`;
            }
          },
          grid: {
            left: 44,
            right: 80,
            top: 20,
            bottom: 64,
            containLabel: true
          },
          xAxis: {
            type: "category",
            data: labels,
            axisLabel: { formatter: v => `#${v}` }
          },
          yAxis: {
            type: "value",
            min: 0,
            max: maxYBars,                 // << eje Y fijo en 120%
            axisLabel: { formatter: '{value}%', margin: 6 }
          },
          series: [{
            name: "Ejecutado",
            type: "bar",
            data: execData,
            itemStyle: { color: p => colors[p.dataIndex] },
            label: {
              show: true,
              position: "top",
              formatter: p => {
                const m = p.data.meta;
                // mostrar el valor real aunque se haya recortado
                return `${m.executed}%\n(${m.executedReal}/${m.planned})`;
              }
            },
            barWidth: 28,
            markLine: {
              symbol: "none",
              label: {
                show: true,
                position: "end",
                align: "left",
                formatter: () => `Tiempo\n${timePct.toFixed(1)}%`, // arriba "Tiempo", abajo el %
                color: "#000",
                backgroundColor: "rgba(255,255,255,.6)",
                padding: [2, 4],
                offset: [8, 0]
              },
              lineStyle: { color: "#000", width: 1.5, type: "dashed" },
              // si el tiempo supera 120, también se recorta visualmente
              data: [{ yAxis: Math.min(timePct, maxYBars) }]
            }
          }]
        });

        // Leyenda “ID = nombre” debajo del gráfico
        const legend = it.metrics
          .map(m => `<span class="me-3"><b>#${m.id}</b> = ${m.nombre}</span>`)
          .join(' ');
        document.getElementById(`legend-${i}`).innerHTML = legend;
      });

      // Redimensionar tras ajustar ancho
      setTimeout(() => {
        try {
          trendChart.resize();
        } catch (e) {}
        window.dispatchEvent(new Event('resize'));
      }, 200);

      // Forzar resize
      setTimeout(() => window.dispatchEvent(new Event('resize')), 500);
    }

    document.addEventListener("DOMContentLoaded", initDashboard);
  </script>
</body>

</html>