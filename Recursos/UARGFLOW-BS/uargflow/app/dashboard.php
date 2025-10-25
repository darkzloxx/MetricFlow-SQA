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
    $plan = is_null($r['planificado']) ? 0.0 : (float)$r['planificado'];
    $ejec = is_null($r['ejecutado']) ? 0.0 : (float)$r['ejecutado'];
    if ($plan == 0 && $ejec == 0) {
      // Nada planificado y nada hecho
      $pct = 100;
      $nota = "Se cumplió";
      $extra = 0;
    } elseif ($plan == 0 && $ejec > 0) {
      // No se planificó nada pero se hizo trabajo
      $pct = 100;
      $nota = "Se planificó 0 ($ejec)";
      $extra = $ejec;
    } elseif ($ejec > $plan) {
      // Se hizo más de lo planificado
      $pct = round(($ejec / $plan) * 100, 2);
      $nota = "Supera planificado (+" . ($ejec - $plan) . ")";
      $extra = $ejec - $plan;
    } else {
      // Caso normal
      $pct = round(($ejec / $plan) * 100, 2);
      $nota = "";
      $extra = 0;
    }

    // “Cuánto más que lo planificado” (si plan=0, es todo ejecutado)
    $extra = $plan > 0 ? max(0, $ejec - $plan) : $ejec;

    $iterMap[$key]["metrics"][] = [
      "id"           => (int)$r['id_metrica'],
      "nombre"       => $r['metrica'],
      "executed"     => $pct,
      "planned"      => $r['planificado'],
      "executedReal" => $r['ejecutado'],
      "unit"         => "u",
      "min"          => max(0, 100 - (float)$r['umbral']),
      "max"          => 100 + (float)$r['umbral'],
      "nota"         => $nota,   // 👈 agregado
      "extra"        => $extra   // 👈 agregado
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

    /* ======== GLOBAL ======== */
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    /* ======== TARJETAS ======== */
    .card {
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 0.75rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
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

    .legend-line {
      width: 22px;
      height: 0;
      border-top: 2px solid #000;
      margin-right: 6px;
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

    function colorSemaforo(valuePct, minPct, maxPct) {
      // valuePct: % de cumplimiento calculado (ejecutado/planificado*100)
      // minPct:  100 - umbral (límite de desviación)
      // maxPct:  100 + umbral (no se usa para el semáforo del flujo)
      valuePct = Number(valuePct) || 0;
      minPct = Number(minPct) || 0;

      if (valuePct >= 100) return "#28a745"; // Verde: ejecutado >= planificado
      if (valuePct >= minPct) return "#ffc107"; // Amarillo: dentro del límite de desviación
      return "#dc3545"; // Rojo: fuera del límite
    }

    // Porcentaje de tiempo transcurrido entre inicio y fin
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
        emphasis: {
          focus: "series"
        },
        blur: {
          lineStyle: {
            opacity: 0.25
          },
          itemStyle: {
            opacity: 0.25
          }
        },
        data: DATA.map(it => {
          const m = it.metrics.find(mm => mm.nombre === name);
          return m ? {
            value: m.executed,
            meta: {
              nombre: m.nombre,
              executedReal: m.executedReal,
              planned: m.planned,
              unit: m.unit,
              noPlan: m.noPlan,
              extra: m.extra
            }
          } : {
            value: 0,
            meta: null
          };
        })
      }));

      const maxYTrend = Math.max(
        120,
        Math.ceil(Math.max(...DATA.flatMap(it => it.metrics.map(m => Math.max(m.max, m.executed)))) / 10) * 10
      );
      // Configuración del gráfico de tendencia
      trendChart.setOption({
        tooltip: {
          trigger: "axis",
          formatter: function(params) {
            const idx = params[0]?.dataIndex ?? 0;
            const iter = iterLabels[idx] || "";
            let html = `<b>${iter}</b><br/>`;
            params.forEach(p => {
              if (p.seriesName === "Referencia 100%") return;
              const meta = p.data?.meta;
              const pct = typeof p.value === "number" ? p.value : (p.data?.value ?? 0);
              const ejec = meta?.executedReal ?? "—";
              const plan = meta?.planned ?? "—";
              const dot = `<span style="display:inline-block;margin-right:6px;width:10px;height:10px;background:${p.color};border-radius:50%"></span>`;
              let detalle = "";
              if (meta) {
                if (meta.noPlan && ejec > 0) {
                  detalle = `( +${meta.extra} ${meta.unit || 'u'} sin plan )`;
                } else {
                  detalle = `(${ejec} / ${plan})`;
                }
              }
              html += `${dot}${p.seriesName}: ${pct}% ${detalle}<br/>`;
            });
            return html;
          }
        },
        legend: {
          data: METRIC_KEYS,
          top: 8,
          itemGap: 18
        },
        grid: {
          left: 48,
          right: 84,
          top: 88,
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
          axisLabel: {
            formatter: '{value}%'
          }
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
              label: {
                show: true,
                position: "end",
                formatter: "100%", // <-- se muestra como 100%
                color: "#6c757d",
                backgroundColor: "rgba(255,255,255,.6)",
                padding: [2, 4]
              },
              lineStyle: {
                type: "dashed",
                color: "#6c757d"
              },
              data: [{
                yAxis: 100
              }] // <-- debe quedar numérico
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
      <div class="card-title-main">${it.iteracion}</div>
      <div class="card-subtitle-dates">Del ${it.inicio} al ${it.fin}</div>
    </div>

    <div class="card-body p-3">
      <!-- Leyenda superior fija -->
      <div id="legend-colors-${i}" class="legend-top mb-2"></div>

      <!-- Área scrolleable solo para el gráfico -->
      <div class="chart-scroll">
        <div class="chart-stage">
          <div id="bars-${i}" class="chart"></div>
        </div>
      </div>

      <!-- Leyenda inferior fija -->
      <div id="legend-metrics-${i}" class="legend-bottom mt-3"></div>
    </div>
  </div>
`;
        row.appendChild(col);

        const dom = document.getElementById(`bars-${i}`);
        const chart = echarts.init(dom);

        const labels = it.metrics.map(m => m.id);
        const maxYBars = 120;
        const execData = it.metrics.map(m => ({
          value: Math.min(m.executed, maxYBars),
          meta: m
        }));
        const colors = it.metrics.map(m => colorSemaforo(m.executed, m.min, m.max));
        const BAR_DURATION = 1500; // ya lo usás
        const BAR_DELAY_PER_IDX = idx => idx * 150; // ya lo usás
        const OVERFLOW_DURATION = 1500; // el rect “extra” que aparece arriba
        const lastDelay = BAR_DELAY_PER_IDX(it.metrics.length - 1);
        const AFTER_OVERFLOW_ALL = lastDelay + BAR_DURATION + OVERFLOW_DURATION + 150;
        // --- SCROLL HORIZONTAL Y ANCHO DINÁMICO ---
        const container = dom.parentElement; // el <div> que contiene la gráfica
        container.style.overflowX = "auto"; // habilita scroll horizontal

        const perMetricPx = 110; // ancho mínimo por barra (ajustable)
        const minWidth = container.clientWidth || 600;
        dom.style.width = Math.max(minWidth, it.metrics.length * perMetricPx) + "px";

        // si hay scroll, ECharts necesita recalcular
        chart.resize();
        // umbrales y tiempo aparecerán después de esto
        chart.setOption({
          tooltip: {
            trigger: "axis",
            appendToBody: true,
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
              if (m.planned === 0 && m.executedReal > 0) { //si se planificó 0 y se ejecutó algo
                html += `<span style="color:#17a2b8;font-weight:bold;">ℹ️ Se planificó 0 (+${m.executedReal})</span><br>`;
              } else if (m.planned > 0 && m.executedReal > m.planned) { //si planificado es mayor a 0 y se superó lo planificado
                html += `<span style="color:#28a745;font-weight:bold;">▲ Supera lo planificado (+${m.executedReal - m.planned})</span><br>`;
              } else if (m.planned > 0 && m.executedReal === m.planned) { //si se cumplió exactamente lo planificado
                html += `<span style="color:#198754;font-weight:bold;">✔️ Cumple lo planificado (=${m.planned})</span><br>`;
              } else if (m.planned > 0 && m.executedReal > 0 && m.executedReal < m.planned) { //si se quedó por debajo de lo planificado
                html += `<span style="color:#dc3545;font-weight:bold;">▼ Por debajo del plan (-${m.planned - m.executedReal})</span><br>`;
              } else if (m.planned > 0 && m.executedReal === 0) { //si no se ejecutó nada y se planifico mas de 0
                html += `<span style="color:#6c757d;font-weight:bold;">⛔ Sin ejecución (0/${m.planned})</span><br>`;
              } else {
                html += `<span style="color:#999;">❔ Sin datos disponibles</span><br>`;
              }
              html += `Progreso temporal: ${timePct.toFixed(1)}%`;
              return html;
            }
          },

          grid: {
  left: 56,          // antes 44
  right: 110,
  top: 20,
  bottom: 44,        // antes 28
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
        yAxis: {
  type: "value",
  min: 0,
  max: maxYBars,
  name: "Cumplimiento (%)",
  nameLocation: "middle",
  nameGap: 46,       // distancia del eje
  nameRotate: 90,    // rotado
  nameTextStyle: { fontSize: 12, fontWeight: 600, color: "#495057" },
  axisLabel: { formatter: '{value}%', margin: 6 }
},
          series: [
            // === BARRAS PRINCIPALES ===
            {
              name: "Ejecutado",
              type: "bar",
              data: execData.map(d => ({
                value: 100, // todas llenan hasta 100%
                meta: d.meta,
                fill: Math.min(d.value, 100) // % realmente ejecutado
              })),
              barWidth: 40,
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

                const yPx = api.coord([idx, m.min])[1];
                const xCenter = api.coord([idx, m.min])[0];
                const half = (api.size([1, 0])[0] || 30) * 0.35;

                return {
                  type: "line",
                  shape: {
                    x1: xCenter - half,
                    y1: yPx,
                    x2: xCenter + half,
                    y2: yPx
                  },
                  style: {
                    stroke: "#000000",
                    lineWidth: 1.5,
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
                    position: "top",
                    offset: [0, -5]
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
                const y = api.coord([0, pct])[1];
                const xStart = api.coord([0, 0])[0];

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
                        x1: xStart - 20, // empieza un poco antes
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


            //(OVERFLOW)
            {
              name: "Overflow",
              type: "custom",
              animationEasing: "cubicOut",
              // Variante A: encadenado por barra (empieza al terminar su barra)
              animationDuration: 1500,
              animationDelay: idx => BAR_DELAY_PER_IDX(idx) + BAR_DURATION,
              renderItem: function(params, api) {
                const idx = api.value(0);
                const m = it.metrics[idx];
                if (!m) return null;


                const barWidth = api.size([1, 0])[0] * 0.5;
                const base = api.coord([idx, 100]); // base en 100%

                // === CASO 1: SIN PLANIFICACIÓN (plan=0 y ejecutado>0)
                // Azul infinito — sube más allá del 120% (representa trabajo fuera del plan)
                if (m.planned === 0 && m.executedReal > 0) {
                  const yTop = api.coord([idx, 160])[1]; // "infinito" visual (160%)
                  const height = base[1] - yTop;
                  return {
                    type: "rect",
                    shape: {
                      x: base[0] - barWidth / 2,
                      y: yTop,
                      width: barWidth,
                      height: height
                    },
                    enterFrom: {
                      shape: {
                        y: base[1],
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


                //CASO 2: se superó el 100% (normal, color verde)
                if (m.executed > 100) {
                  const base = api.coord([idx, 100]);
                  const barWidth = api.size([1, 0])[0] * 0.5;
                  const baseColor = colorSemaforo(m.executed, m.min, m.max);
                  const lightColor = echarts.color.lift(baseColor, 0.3);
                  const extraPct = Math.min((m.executed - 100) / 100, 0.5);
                  const height = api.size([0, extraPct * 100])[1];
                  const y = base[1] - height;

                  return {
                    type: "rect",
                    shape: {
                      x: base[0] - barWidth / 2,
                      y,
                      width: barWidth,
                      height
                    },
                    enterFrom: {
                      shape: {
                        y: base[1],
                        height: 0
                      }
                    },
                    transition: ["shape"],
                    style: {
                      fill: lightColor,
                      opacity: 0.6,
                      stroke: baseColor,
                      lineWidth: 0.5
                    },
                    z: 20
                  };
                }

                return null;
              },
              data: it.metrics.map((_, idx) => idx)
            }
          ]
        });

        // Leyenda de colores (arriba)
        const legendColorsHtml = `
    <div class="legend-colors">
      <span class="legend-item"><span class="legend-dot" style="background:#28a745;"></span>Ejecutado ≥ Planificado</span>
      <span class="legend-item"><span class="legend-dot" style="background:#ffc107;"></span>Dentro del límite de desviación</span>
      <span class="legend-item"><span class="legend-dot" style="background:#dc3545;"></span>Cumplimiento < límite de desviación</span>
      <span class="legend-item"><span class="legend-dot" style="background:#007bff;"></span>Ejecutado sin planificación (plan=0)</span>
      <span class="legend-item"><span class="legend-line"></span>Límite de desviación</span> <!-- línea negra -->
      <span class="legend-item"><span class="legend-dash"></span>Progreso de iteración</span>

      </div>
  `;
        document.getElementById(`legend-colors-${i}`).innerHTML = legendColorsHtml;
        // Lista de métricas (abajo)
        const metricsHtml = it.metrics
          .map(m => `<span class="me-3"><b>#${m.id}</b> = ${m.nombre}</span>`)
          .join(' ');
        document.getElementById(`legend-metrics-${i}`).innerHTML = metricsHtml;
      });

      // Redimensionamiento y actualización de gráficos
      setTimeout(() => {
        try {
          trendChart.resize();
        } catch (e) {}
        window.dispatchEvent(new Event('resize'));
      }, 200);

      setTimeout(() => window.dispatchEvent(new Event('resize')), 500);
    };
    document.addEventListener("DOMContentLoaded", initDashboard);
  </script>
</body>

</html>