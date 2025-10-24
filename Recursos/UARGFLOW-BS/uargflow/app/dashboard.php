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
      $nota = "Sin planificación";
      $extra = 0;
    } elseif ($plan == 0 && $ejec > 0) {
      // No se planificó nada pero se hizo trabajo
      $pct = 100;
      $nota = "No planificado (+{$ejec})";
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

    /* Leyenda de colores (semáforo) */
    .legend-colors {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .legend-item {
      font-size: 0.875rem;
      color: #6c757d;
    }
 .legend-dot {
      display: inline-block;
      width: 12px;
      height: 12px;
      margin-right: 6px;
      border-radius: 2px;
      border: 1px solid rgba(0, 0, 0, .2);
      vertical-align: -2px;
    }
/* Línea negra para el umbral en la leyenda */
    .legend-line {
      display: inline-block;
      width: 22px;
      height: 0;
      margin-right: 6px;
      border-top: 2px solid #000; /* negra */
      vertical-align: 2px;
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
        formatter: "100%",             // <-- se muestra como 100%
        color: "#6c757d",
        backgroundColor: "rgba(255,255,255,.6)",
        padding: [2, 4]
      },
      lineStyle: {
        type: "dashed",
        color: "#6c757d"
      },
      data: [{ yAxis: 100 }]           // <-- debe quedar numérico
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
        <div id="legend-colors-${i}" class="small text-muted mb-2"></div>  <!-- colores arriba -->
        <div id="bars-${i}" class="chart"></div>                           <!-- gráfico -->
        <div id="legend-metrics-${i}" class="small text-muted mt-2"></div> <!-- métricas abajo -->
      </div>
    </div>`;
        row.appendChild(col);

        const dom = document.getElementById(`bars-${i}`);
        const chart = echarts.init(dom);

        const labels = it.metrics.map(m => m.id);
        const maxYBars = 120; // límite visual del eje Y
        const execData = it.metrics.map(m => ({
          value: Math.min(m.executed, maxYBars),
          meta: m
        }));
        const colors = it.metrics.map(m => colorSemaforo(m.executed, m.min, m.max));

        // === 🔺 Crear puntos de marca (triángulos) para valores que superan 120% ===
        const markPoints = [];
        it.metrics.forEach((m, idx) => {
          if (m.executed > maxYBars) {
            markPoints.push({
              symbol: 'triangle',
              symbolSize: 18,
              symbolOffset: [0, -5],
              itemStyle: {
                color: colors[idx]
              },
              xAxis: idx,
              //se dibuja a la mitad del maximo para que no quede tan alto
              y: maxYBars/2, 
              label: {
                show: true,
                formatter: `▲ ${m.executed}%`,
                position: 'top',
                color: '#000',
                fontWeight: 'bold'
              }
            });
          }
        });



        chart.setOption({
          tooltip: {
            trigger: "axis",
            axisPointer: {
              type: "shadow"
            },
            formatter: params => {
              const p = params[0];
              const m = p.data.meta;
              let html = `<b>#${p.axisValue} - ${m.nombre}</b><br>`;
              html += `Límite Desviación: ${m.min}%<br>`;
              html += `Ejecutado: ${m.executed}% (${m.executedReal} de ${m.planned})<br>`;
              if (m.planned === 0 && m.executedReal > 0) {
                html += `<span style="color:#17a2b8;font-weight:bold;">No planificado (+${m.executedReal})</span><br>`;
              }
              html += `Progreso temporal: ${timePct.toFixed(1)}%`;
              return html;
            }

          },
          grid: {
            left: 44,
            right: 80,
            top: 20,
            bottom: 28,
            containLabel: true
          },
          xAxis: {
            type: "category",
            data: labels,
            axisLabel: {
              formatter: v => `#${v}`,
              margin: 2
            }
          },
          yAxis: {
            type: "value",
            min: 0,
            max: maxYBars,
            axisLabel: {
              formatter: '{value}%',
              margin: 6
            }
          },
         series: [
  {
    name: "Ejecutado",
    type: "bar",
    data: execData,
    itemStyle: { color: p => colors[p.dataIndex] },
    label: {
      show: true,
      position: "top",
      formatter: p => {
        const m = p.data.meta;
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
        formatter: () => `Tiempo\n${timePct.toFixed(1)}%`,
        color: "#000",
        backgroundColor: "rgba(255,255,255,.6)",
        padding: [2, 4],
        offset: [8, 0]
      },
      lineStyle: { color: "#000", width: 1.5, type: "dashed" },
      data: [{ yAxis: Math.min(timePct, maxYBars) }]
    },
    markPoint: { data: markPoints }
  },

  // 🔸 LÍNEAS DE UMBRAL (encima de las barras)
 {
  name: "Límite de desviación",
  type: "custom",
  silent: true,
  tooltip: { show: false },
  z: 999, // asegura que se pinte sobre las barras
  renderItem: function(params, api) {
    const idx = api.value(0);
    const m = it.metrics[idx];
    if (!m) return null;

    // Coordenadas base de la línea
    const yPx = api.coord([idx, m.min])[1];
    const xCenter = api.coord([idx, m.min])[0];
    const half = (api.size([1, 0])[0] || 30) * 0.32;
    const xStart = xCenter - half;
    const xEnd = xCenter + half;

    // ✅ Detección de superposición
    const diff = Math.abs(m.executed - m.min);
    const close = diff < 5; // Si están a menos de 5% de diferencia
    const textOffsetY = close ? 14 : -4; // Si están muy cerca, movemos la etiqueta hacia abajo

    return {
      type: "line",
      shape: { x1: xStart, y1: yPx, x2: xEnd, y2: yPx },
      style: {
        stroke: "#000",           // línea negra
        lineWidth: 2
      },
      z: 9999,                    // asegura que quede por encima de las barras
      textContent: {
        style: {
          text: `${m.min}%`,
          fill: "#000",
          fontWeight: "bold",
          fontSize: 11,
          align: "center",
          backgroundColor: "rgba(255,255,255,0.85)",
          padding: [1, 3],
          borderRadius: 2
        }
      },
      // 👇 Ajuste dinámico: si está cerca, se mueve hacia abajo
      textConfig: { position: "top", offset: [0, textOffsetY] }
    };
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
      <span class="legend-item"><span class="legend-dot" style="background:#dc3545;"></span>Debajo del límite de desviación</span>
      <span class="legend-item"><span class="legend-line"></span>Límite de desviación</span> <!-- línea negra -->
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