<?php

/**
 * Genera la estructura $DATA consumida por el frontend (ECharts) para
 * tableros de métricas, manteniendo compatibilidad con claves existentes.
 *
 * @author      Lorenzo Teppa
 * @copyright   2025 CoDevIt
 * @license     MIT
 * @version     1.0.0
 * @since       1.0.0
 * 
 * --------------------------------------------------------------------------
 */

//require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
// Todos los roles con el permiso del dashboard pueden acceder
//ControlAcceso::requierePermiso(PermisosSistema::DASHBOARD);
//conexión a la base de datos para probar
$conexion = new mysqli("localhost", "root", "", "bd_codevit", 3306);
// Si algo falla aquí, no hay dashboard: aborta con un mensaje explícito.
if ($conexion->connect_error) {
  die("Error al conectar: " . $conexion->connect_error);
}
// Conexión centralizada
//$conexion = BDConexion::getConexion();

//OBTENER EL ID DEL PROYECTO POR 
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
        "metricas"   => []
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
    // Hay iteración actual (en curso)
    $actualIter = $DATA[$actualIndex];
  } else {
    // No hay iteración actual la última registrada pasa a ser la "más reciente anterior"
    $mensajeActual = 'No hay ninguna iteración activa en la fecha actual o no tiene métricas planificadas.';
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
  if ($actualIter && empty($actualIter['metricas'])) {
    $mensajeActual = "La iteración <b>{$actualIter['iteracion']}</b> no tiene métricas planificadas aún.";
  }
}


// $conexion es singleton; no cerramos aquí para reuso.
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
  <?php /* include __DIR__ . '/../gui/navbar.php'; */ ?>

  <style>
    .chart {
      width: 100%;
      height: 360px;
    }

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


    .legend-metricas {
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

    button:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
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

    /* ======== LEYENDA DE FASES (arriba del gráfico de tendencia) ======== */
    .legend-phases {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
      color: #495057;
      margin-bottom: 12px;
    }

    .legend-phases .phase-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      background: #ffffff;
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 999px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
      cursor: pointer;
      transition: all .15s ease-in-out;
      color: #334155;
    }

    .legend-phases .phase-pill.active {
      border-color: #0d6efd;
      background: rgba(13, 110, 253, 0.06);
      color: #0d6efd;
    }

    .phase-pill.no-filter {
      background: #f8f9fa !important;
      border: 1px solid #dee2e6 !important;
      color: #6c757d !important;
      pointer-events: none;
      opacity: 0.8;
      cursor: default !important;
    }

    .legend-phases .phase-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      background: #ffffff;
      border: 1px solid rgba(0, 0, 0, 0.06);
      border-radius: 10px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
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

    .legend-phases .phase-letter {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 22px;
      height: 22px;
      border-radius: 6px;
      background: #0d6efd;
      color: #fff;
      font-weight: 700;
      font-size: 0.9rem;
    }
  </style>
</head>

<body>


  <div class="container my-4">
    <!-- Botón Volver a Proyectos -->
    <div class="mb-3">
      <a href="proyectos.php" class="btn btn-outline-secondary">
        <span class="oi oi-arrow-left mr-1"></span> Volver
      </a>
    </div>
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
      echo '</div>        <footer class="footer">
            MetricFlow-SQA
            <span class="oi oi-globe"></span> 
            CoDevIt
        </footer>
'; // cerrar container

      exit; //  corta la ejecución del resto del dashboard
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
              <div id="projectName" class="stat-value"><?= htmlspecialchars($nombreProyecto) ?></div>
              <span class="status-line">Estado:
                <span id="projectStatusBadge" class="badge badge-pill <?= $estadoClass ?>"><?= htmlspecialchars($estadoProyecto) ?></span>
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
            <div class="stat-icon icon-bg-secondary">
              <span class="oi oi-loop-circular"></span>
            </div>
            <div class="stat-content">
              <span class="stat-label">Iteraciones</span>
              <div id="totalIteracionesValue" class="stat-value text-dark"><?= (int)$totalIteraciones ?></div>
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
          <button id="toggleLegendBtn" class="btn-toggle" title="Mostrar u ocultar todas las métricas del gráfico">
            <i class="oi oi-eye"></i> <span>Ocultar métricas</span>
          </button>

        </div>

        <!-- Leyenda de fases (arriba del gráfico) -->
        <div id="phaseLegend" class="legend-phases" aria-hidden="true"></div>

        <div id="trendWrap" class="chart-scroll">
          <div class="chart-stage">
            <div id="trendChart" class="chart"></div>
          </div>
        </div>
        <div id="trendLegend" class="legend-colors mt-2"></div>
      </div>
    </div>
  </div>

  <footer class="footer">MetricFlow-SQA <span class="oi oi-globe"></span> UNPA-UARG</footer>

  <script>
    // ========= Datos del backend =========
    let DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    let HAY_ITERACIONES = <?= $hayIteraciones ? 'true' : 'false'; ?>;
    let ACTUAL = <?= json_encode($actualIter, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    let ANTERIOR = <?= json_encode($anteriorIter, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    const MSG_ACTUAL = <?= json_encode($mensajeActual, JSON_UNESCAPED_UNICODE); ?>;
    const MSG_ANT = <?= json_encode($mensajeAnterior, JSON_UNESCAPED_UNICODE); ?>;
    const ID_PROYECTO = <?= (int)$idProyecto ?>; // para live updates
    let __dashVersion = null; // versión de datos del último render

    // ===== Persistencia de estado en localStorage (sobrevive recargas) =====
    const STORAGE_KEY = (id => `uargflow:dashboard:v1:proyecto:${id}`)(ID_PROYECTO);

    function loadStateFromStorage() {
      try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
      } catch (_) {
        return null;
      }
    }

    function saveStateToStorage(state) {
      try {
        if (!state) return;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
      } catch (_) {
      }
    }

    // ========= Utilidades =========
    function clamp(x, a, b) { //esto limita un valor entre a y b
      return Math.min(Math.max(x, a), b);
    }

    function colorSemaforo(valorPct, minPct, maxPct) {
      // valorPct: % de cumplimiento calculado (ejecutado/planificado*100)
      // minPct:  100 - umbral (límite de desviación)
      // maxPct:  100 + umbral (no se usa para el semáforo del flujo)
      valorPct = Number(valorPct) || 0;
      minPct = Number(minPct) || 0;
      if (valorPct >= 100) return "#28a745"; // Verde
      if (valorPct >= minPct) return "#ffc107"; // Amarillo
      return "#dc3545"; // Rojo
    }

    // Mapea el estado del proyecto a la clase de Bootstrap usada en el badge
    function estadoToBadgeClass(estado) {
      if (!estado) return 'badge-secondary';
      const key = String(estado).trim().toUpperCase().replace(/\s+/g, '_');
      switch (key) {
        case 'REGISTRADO':
          return 'badge-secondary';
        case 'EN_PROGRESO':
          return 'badge-primary';
        case 'FINALIZADO':
          return 'badge-success';
        case 'CANCELADO':
          return 'badge-danger';
        default:
          return 'badge-secondary';
      }
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
      if (!it || !it.metricas || it.metricas.length === 0) {
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
      const labels = it.metricas.map(m => m.id);

      // === Configuración de máximos ===
      const BAR_WIDTH = 40; // ancho barra base
      const VISIBLE_MAX = 120; // tope visible
      const OVERFLOW_TOP = 140; // “un poco” por encima

      // levantar eje oculto si hay cualquier overflow
      const hasOverflow = it.metricas.some(m =>
        (m.planned === 0 && m.executedReal > 0) || (Number(m.executed) > VISIBLE_MAX)
      );
      const OVERFLOW_MAX = hasOverflow ? OVERFLOW_TOP : VISIBLE_MAX; // eje oculto si hace falta

      // Para las barras normales seguimos usando 120
      const maxYBars = VISIBLE_MAX;

      const execData = it.metricas.map(m => ({
        value: Math.min(m.executed, maxYBars),
        meta: m
      }));
      const BAR_DURATION = 1500;
      const BAR_DELAY_PER_IDX = idx => idx * 150;
      const OVERFLOW_DURATION = 1500;
      const lastDelay = BAR_DELAY_PER_IDX(it.metricas.length - 1);
      const AFTER_OVERFLOW_ALL = lastDelay + BAR_DURATION + OVERFLOW_DURATION + 150;
      const timePct = pctTiempoIter(it.inicio, it.fin);

      const scroll = dom.closest(".chart-scroll");
      const stage = dom.parentElement; // .chart-stage
      const visibleW = scroll ? scroll.clientWidth : 600;
      const neededW = Math.max(visibleW, it.metricas.length * 90);
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
              const m = it.metricas[idx];
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

            data: it.metricas.map((_, idx) => ({
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
              // Coordenadas exactas del grid para alinear con el eje Y (x=0)
              const cs = params.coordSys; // { x, y, width, height }
              const xLeft = Math.round(cs.x) + 0.5; // borde izquierdo del grid (eje X)
              const xRight = Math.round(cs.x + cs.width) + 0.5; // borde derecho del grid
              const chartW = api.getWidth();
              const labelX = Math.min(xRight + 12, chartW - 10);

              return {
                type: "group",
                children: [
                  // Línea punteada desde el eje Y
                  {
                    type: "line",
                    shape: {
                      x1: xLeft,
                      y1: y,
                      x2: xRight + 15,
                      y2: y
                    },
                    style: {
                      stroke: "#0a3bcfff",
                      lineWidth: 2.4,
                      lineDash: [10, 6],
                      shadowColor: "rgba(10,59,207,0.35)",
                      shadowBlur: 3,
                      opacity: 0
                    },
                    keyframeAnimation: {
                      duration: 400,
                      delay: AFTER_OVERFLOW_ALL - 100,
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
                            lineWidth: 2.4
                          }
                        }
                      ]
                    }
                  },
                  // Texto al extremo derecho
                  {
                    type: "text",
                    style: {
                      x: labelX,
                      y: y - 8,
                      text: `${pct.toFixed(1)}%`,
                      fill: "#0a3bcfff",
                      fontWeight: "bold",
                      fontSize: 11.5,
                      textAlign: "left",
                      textVerticalAlign: "bottom",
                      lineHeight: 16,
                      textShadowColor: "rgba(255,255,255,0.7)",
                      textShadowBlur: 3,
                      opacity: 0
                    },
                    keyframeAnimation: {
                      duration: 500,
                      delay: AFTER_OVERFLOW_ALL,
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
              const m = it.metricas[idx];
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
            data: it.metricas.map((_, idx) => idx)
          },

        ]
      });

      // Leyenda de métricas
      if (legendId) {
        const legendDiv = document.getElementById(legendId);
        legendDiv.innerHTML = it.metricas.map(m => `<span class="me-3"><b>#${m.id}</b> = ${m.nombre}</span>`).join(' ');
      }
    }

    // ===== Helpers para el gráfico de tendencia =====
    // Calcula filas estimadas de la leyenda y ajusta la altura del contenedor del chart
    function adjustTrendHeightForLegend(chart, chartDom, legendKeys) {
      try {
        const base = 380; // altura base del gráfico (sin contar leyenda)
        const w = (chart && typeof chart.getWidth === 'function') ? chart.getWidth() : (chartDom?.clientWidth || 800);
        const n = Array.isArray(legendKeys) ? legendKeys.length : 0;
        if (n === 0) {
          if (chartDom) chartDom.style.height = base + 'px';
          if (chart) chart.resize();
          return 0;
        }
        // Ancho promedio por item (marker + texto + gap); conservador para nombres largos
        const avgItem = 120; // px
        const usable = Math.max(200, Math.floor(w * 0.92));
        const cols = Math.max(1, Math.floor(usable / avgItem));
        const rows = Math.max(1, Math.ceil(n / cols));
        // Altura extra por fila (alto texto ~18-20 + márgenes)
        const extraPerRow = 26; // px
        const padding = 16; // holgura adicional
        const extra = Math.max(0, (rows * extraPerRow) + padding);
        if (chartDom) chartDom.style.height = (base + extra) + 'px';
        if (chart) chart.resize();
        return rows;
      } catch (e) {
        // fallback seguro
        if (chartDom) chartDom.style.height = '380px';
        if (chart) chart.resize();
        return 0;
      }
    }

    // ===== Live updates (polling) =====
    function collectUiState() {
      try {
        const selPhases = Array.from(document.querySelectorAll('#phaseLegend .phase-pill.active'))
          .map(el => el.getAttribute('data-phase'))
          .filter(Boolean);
        const histSel = (document.getElementById('hist-select') || {}).value || null;
        const histIdx = histSel !== null && histSel !== '' ? parseInt(histSel, 10) : null;
        const histKey = Number.isInteger(histIdx) && Array.isArray(DATA) && DATA[histIdx] ?
          (DATA[histIdx].iteracion || null) :
          null;
        let legendSelected = null;
        try {
          const inst = echarts.getInstanceByDom(document.getElementById('trendChart'));
          const opt = inst ? inst.getOption() : null;
          legendSelected = opt && opt.legend && opt.legend[0] ? (opt.legend[0].selected || null) : null;
        } catch (_) {
        }
        const state = {
          phases: selPhases,
          hist: histSel,
          histKey,
          legend: legendSelected
        };
        saveStateToStorage(state);
        return state;
      } catch (e) {
        return {
          phases: null,
          hist: null,
          legend: null
        };
      }
    }

    function applyUiState(saved) {
      try {
        if (!saved) saved = loadStateFromStorage();
        if (saved && (saved.histKey || saved.hist)) {
          const sel = document.getElementById('hist-select');
          if (sel) {
            let idx = null;
            if (saved.histKey && Array.isArray(DATA)) {
              const found = DATA.findIndex(d => d && d.iteracion === saved.histKey);
              if (found >= 0) idx = found;
            }
            if (idx === null && saved.hist !== undefined) {
              const n = parseInt(saved.hist, 10);
              if (Number.isInteger(n)) idx = n;
            }
            if (idx !== null) {
              sel.value = String(idx);
              // Disparar evento y, además, forzar render por si el listener aún no está ligado
              sel.dispatchEvent(new Event('change', {
                bubbles: true
              }));
              try {
                const chosen = DATA[idx];
                if (chosen) {
                  const t = document.getElementById('left-title');
                  const d = document.getElementById('left-dates');
                  if (t) t.textContent = chosen.iteracion;
                  if (d) d.textContent = `Del ${chosen.inicio} al ${chosen.fin}`;
                  renderIteracionChart(chosen, 'bars-anterior', 'legend-metricas-anterior');
                }
              } catch (_) {
              }
            }
          }
        }
        if (saved && saved.legend) {
          try {
            const inst = echarts.getInstanceByDom(document.getElementById('trendChart'));
            if (inst) inst.setOption({
              legend: [{
                selected: saved.legend
              }]
            }, {
              lazyUpdate: true
            });
          } catch (_) {
          }
        }
      } catch (_) {
      }
    }
    async function fetchDashboardData() {
      const url = `api/dashboard_data.php?proyecto=${encodeURIComponent(ID_PROYECTO)}&_=${Date.now()}`;
      try {
        const res = await fetch(url, {
          cache: 'no-store'
        });
        if (!res.ok) return null;
        return await res.json();
      } catch (e) {
        console.warn('No se pudo obtener datos (polling):', e);
        return null;
      }
    }

    // Aplicar un payload del servidor y re-renderizar todo preservando estado de UI
    function renderWithPayload(payload) {
      if (!payload) return;
      const saved = collectUiState();
      window.__PHASE_RESTORE = saved.phases || null;
      DATA = Array.isArray(payload.data) ? payload.data : [];
      HAY_ITERACIONES = !!payload.hayIteraciones;
      ACTUAL = payload.actual || null;
      ANTERIOR = payload.anterior || null;
      __dashVersion = payload.version || null;

      // Actualizar contadores superiores sin recargar
      try {
        const tm = document.getElementById('totalMetricasValue');
        if (tm && typeof payload.totalMetricas !== 'undefined') tm.textContent = String(payload.totalMetricas);
        const ti = document.getElementById('totalIteracionesValue');
        if (ti && typeof payload.totalIteraciones !== 'undefined') ti.textContent = String(payload.totalIteraciones);
        // Proyecto: nombre y estado
        if (payload.proyecto) {
          const pName = document.getElementById('projectName');
          if (pName && typeof payload.proyecto.nombre !== 'undefined') {
            pName.textContent = String(payload.proyecto.nombre);
          }
          const pBadge = document.getElementById('projectStatusBadge');
          if (pBadge && typeof payload.proyecto.estado !== 'undefined') {
            // limpiar clases previas badge-*
            pBadge.className = `badge badge-pill ${estadoToBadgeClass(payload.proyecto.estado)}`;
            pBadge.textContent = String(payload.proyecto.estado);
          }
        }
      } catch (_) {
      }

      try {
        const t = document.getElementById('trendChart');
        const inst = t && echarts.getInstanceByDom(t);
        if (inst) inst.dispose();
      } catch (_) {}
      try {
        const a = document.getElementById('bars-actual');
        const ia = a && echarts.getInstanceByDom(a);
        if (ia) ia.dispose();
      } catch (_) {}
      try {
        const b = document.getElementById('bars-anterior');
        const ib = b && echarts.getInstanceByDom(b);
        if (ib) ib.dispose();
      } catch (_) {}
      const row = document.getElementById('barsRow');
      if (row) row.innerHTML = '';
      const phaseLegend = document.getElementById('phaseLegend');
      if (phaseLegend) phaseLegend.innerHTML = '';
      initDashboard();
      applyUiState(saved);
    }

    // Refresco bajo demanda (ej. al cambiar de fase)
    async function refreshFromServer() {
      const payload = await fetchDashboardData();
      if (!payload || !payload.version) return;
      if (!__dashVersion || payload.version !== __dashVersion) {
        renderWithPayload(payload);
      }
    }

    async function startLiveUpdates(intervalMs = 10000) {
      if (window.__LIVE_POLLING) return; // evita múltiples intervalos
      window.__LIVE_POLLING = true;

      // Primera sincronización: asegura paridad con BD tras la carga inicial
      const first = await fetchDashboardData();
      if (first && first.version && first.version !== __dashVersion) {
        // Solo re-renderizamos si la versión es distinta para evitar flicker
        renderWithPayload(first);
      }

      // Polling periódico
      setInterval(async () => {
        // Evita trabajo innecesario y flicker si la pestaña no está visible
        if (document.hidden) return;
        const payload = await fetchDashboardData();
        if (!payload || !payload.version) return;
        if (__dashVersion && payload.version === __dashVersion) return; // sin cambios

        // Cambios detectados → actualizar y reconstruir preservando estado
        renderWithPayload(payload);
      }, intervalMs);
    }

    // ===== Live updates via Server-Sent Events (preferido) =====
    function startLiveUpdatesSSE() {
      if (!('EventSource' in window)) return false;
      if (window.__LIVE_SSE) return true;
      try {
        const url = `api/dashboard_sse.php?proyecto=${encodeURIComponent(ID_PROYECTO)}`;
        const es = new EventSource(url, {
          withCredentials: false
        });
        window.__LIVE_SSE = es;
        let gotFirstUpdate = false;
        let lastUpdateAt = Date.now();
        let closed = false;

        // Startup watchdog: if no update within 7s, fallback to polling
        const startupTimer = setTimeout(() => {
          if (!gotFirstUpdate && !closed) {
            try {
              es.close();
            } catch (_) {}
            window.__LIVE_SSE = null;
            closed = true;
            startLiveUpdates(5000);
          }
        }, 7000);

        // Health watchdog: if no updates for 75s, fallback to polling
        const healthTimer = setInterval(() => {
          if (closed) {
            clearInterval(healthTimer);
            return;
          }
          if (Date.now() - lastUpdateAt > 75000) {
            try {
              es.close();
            } catch (_) {}
            window.__LIVE_SSE = null;
            closed = true;
            clearInterval(healthTimer);
            startLiveUpdates(5000);
          }
        }, 20000);

        const handlePayload = (payload) => {
          if (!payload || !payload.version) return;
          if (__dashVersion && payload.version === __dashVersion) return; // sin cambios
          renderWithPayload(payload);
        };

        es.addEventListener('update', (e) => {
          try {
            handlePayload(JSON.parse(e.data));
          } catch (_) {}
          gotFirstUpdate = true;
          lastUpdateAt = Date.now();
          clearTimeout(startupTimer);
        });
        // Mantener vivo el watchdog con eventos 'ping' del servidor
        es.addEventListener('ping', () => {
          lastUpdateAt = Date.now();
        });
        es.onmessage = (e) => { // fallback default event
          try {
            handlePayload(JSON.parse(e.data));
          } catch (_) {}
          gotFirstUpdate = true;
          lastUpdateAt = Date.now();
          clearTimeout(startupTimer);
        };
        es.addEventListener('open', () => {
          // mark as connected; we still wait for first update
        });
        es.addEventListener('error', () => {
          try {
            es.close();
          } catch (_) {}
          window.__LIVE_SSE = null;
          closed = true;
          clearTimeout(startupTimer);
          // Fallback a polling tras un breve retardo
          setTimeout(() => {
            startLiveUpdates(5000);
          }, 1500);
        });
        return true;
      } catch (e) {
        window.__LIVE_SSE = null;
        return false;
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
        .flatMap(it => (it.metricas || []).map(m => m.nombre)))];

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

      // Construye la leyenda de letras (interactiva para filtrar fases)
      try {
        const phaseLegend = document.getElementById("phaseLegend");
        if (phaseLegend) {
          // Mapa: letra -> nombre base de fase
          const letterToPhase = new Map();
          (Array.isArray(DATA) ? DATA : []).forEach(d => {
            const faseRaw = (d && d.fase ? String(d.fase) : "").trim();
            if (!faseRaw) return;
            const baseName = faseRaw.split(/\s+/)[0]; // p.ej. "Elaboración I" -> "Elaboración"
            const letter = baseName.charAt(0).toUpperCase();
            if (!letterToPhase.has(letter)) {
              letterToPhase.set(letter, baseName);
            }
          });

          // Si no hubiera datos, mostramos las fases estándar de RUP como ayuda
          if (letterToPhase.size === 0) {
            [
              ["I", "Inicio"],
              ["E", "Elaboración"],
              ["C", "Construcción"],
              ["T", "Transición"]
            ].forEach(([l, n]) => letterToPhase.set(l, n));
          }

          // Estado de selección de fases (restaura si hay guardado)
          const allPhases = Array.from(letterToPhase.values());
          const storageState = loadStateFromStorage();
          const savedSel = (Array.isArray(window.__PHASE_RESTORE) && window.__PHASE_RESTORE.length) ?
            window.__PHASE_RESTORE :
            (storageState && Array.isArray(storageState.phases) ? storageState.phases : null);
          const selectedPhases = new Set(savedSel ? allPhases.filter(p => savedSel.includes(p)) : allPhases);

          // Render de los botones de fase (chips)
          const renderPhaseChips = () => {
            phaseLegend.innerHTML = Array.from(letterToPhase.entries())
              .map(([letter, name]) => {
                const active = selectedPhases.has(name);
                return `
                  <button type="button" class="phase-pill ${active ? 'active' : ''}" data-phase="${name}">
                    <span class="phase-letter">${letter}</span>
                    <span class="phase-name">= ${name}</span>
                  </button>
                `;
              })
              .join("\n");
          };

          // Helpers
          const basePhase = (fase) => (String(fase || '').trim().split(/\s+/)[0] || '').trim();
          const filterDataByPhase = (data) => {
            // Si no hay fases seleccionadas, devolver conjunto vacío → gráfico vacío
            if (!(selectedPhases && selectedPhases.size)) return [];
            return (data || []).filter(d => selectedPhases.has(basePhase(d.fase)));
          };

          // Re-render del gráfico de tendencia con datos filtrados (sin eliminar la lógica original)
          const rebuildTrendChart = (filtered) => {
            const chartDom = document.getElementById('trendChart');
            const chart = echarts.getInstanceByDom(chartDom);
            if (!chart) return;

            // Variables específicas según datos filtrados (basadas en la lógica actual)
            const localMetricKeys = [...new Set((filtered || []).flatMap(it => (it.metricas || []).map(m => m.nombre)))]
              .filter(Boolean);
            const iterLabelsLocal = (filtered || []).map(d => d.iteracion);
            const compactIterLabelsLocal = (filtered || []).map(d => {
              const full = typeof d.iteracion === 'string' ? d.iteracion.trim() : '';
              if (!full) return '';
              const firstChar = full.replace(/^\s+/, '').charAt(0).toUpperCase();
              const numberMatch = full.match(/(\d+)(?!.*\d)/);
              const numberPart = numberMatch ? numberMatch[1] : '';
              return `${firstChar}${numberPart}`;
            });

            const allTrendValues = (filtered || []).flatMap(it => (it.metricas || []).map(m => Number(m.executed) || 0));
            const realMaxValue = allTrendValues.length ? Math.max(...allTrendValues) : 0;

            const BASE_MAX = 200;
            const EXT_PLOT = 20;
            const hasOverflow = realMaxValue > BASE_MAX;
            const yMaxPlot = hasOverflow ? (BASE_MAX + EXT_PLOT) : BASE_MAX;
            const yStep = 20;

            const mapRealToPlot = v => {
              const num = Number(v) || 0;
              if (!hasOverflow || num <= BASE_MAX) return Math.max(0, num);
              const k = EXT_PLOT / Math.max(1e-6, (realMaxValue - BASE_MAX));
              return BASE_MAX + (num - BASE_MAX) * k;
            };
            const mapPlotToReal = vPlot => {
              const num = Number(vPlot) || 0;
              if (!hasOverflow || num <= BASE_MAX) return Math.max(0, num);
              const kInv = Math.max(1e-6, (realMaxValue - BASE_MAX) / EXT_PLOT);
              return BASE_MAX + (num - BASE_MAX) * kInv;
            };

            const formatPctLocal = (val, {
              allowDash = true
            } = {}) => {
              const num = Number(val);
              if (!Number.isFinite(num)) {
                return allowDash ? '—' : '';
              }
              if (Math.abs(num) >= 100) return `${Math.round(num)}%`;
              return `${Number(num.toFixed(1))}%`;
            };

            const seriesColors = localMetricKeys.map((_, sIdx) => palette[sIdx % palette.length]);
            const valuesMatrix = (filtered || []).map(it =>
              localMetricKeys.map(name => {
                const mm = (it.metricas || []).find(m => m.nombre === name);
                return mm ? (Number(mm.executed) || 0) : 0;
              })
            );
            const OUTLIER_STEP = 12;
            const outlierRanks = valuesMatrix.map(row => {
              const entries = row.map((v, idx) => ({
                  idx,
                  v
                }))
                .filter(e => e.v > BASE_MAX)
                .sort((a, b) => b.v - a.v);
              const map = {};
              entries.forEach((e, rank) => {
                map[e.idx] = rank;
              });
              return map;
            });

            const lineSeries = localMetricKeys.map((name, sIdx) => ({
              name,
              type: 'line',
              smooth: false,
              showSymbol: true,
              symbol: 'circle',
              symbolSize: 10,
              lineStyle: {
                width: 3.5,
                color: palette[sIdx % palette.length],
                shadowColor: 'rgba(0,0,0,0.08)',
                shadowBlur: 3
              },
              itemStyle: {
                color: palette[sIdx % palette.length],
                borderColor: '#fff',
                borderWidth: 1
              },
              emphasis: {
                focus: 'series',
                lineStyle: {
                  width: 3.2
                },
                label: {
                  show: true,
                  position: 'top',
                  distance: 6,
                  backgroundColor: 'rgba(255,255,255,0.9)',
                  borderColor: '#ddd',
                  borderWidth: 1,
                  borderRadius: 4,
                  padding: [2, 4],
                  color: palette[sIdx % palette.length],
                  fontSize: 12,
                  fontWeight: 600,
                  formatter: function(p) {
                    const raw = typeof p.data?.realValue === 'number' ? p.data.realValue : (typeof p.value === 'number' ? mapPlotToReal(p.value) : null);
                    return formatPctLocal(raw, {
                      allowDash: false
                    });
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
              data: (filtered || []).map((it, di) => {
                const m = (it.metricas || []).find(mm => mm.nombre === name);
                const real = m ? Number(m.executed) || 0 : 0;
                const isOverflowPoint = real > BASE_MAX;
                const plotVal = mapRealToPlot(real);
                const item = {
                  value: plotVal,
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
                if (isOverflowPoint) {
                  item.symbol = 'diamond';
                  item.symbolSize = 11;
                  item.itemStyle = {
                    color: seriesColors[sIdx],
                    borderColor: '#ffffff',
                    borderWidth: 1,
                    shadowColor: 'rgba(0,0,0,0.15)',
                    shadowBlur: 4
                  };
                  const rank = (outlierRanks[di] && outlierRanks[di][sIdx]) || 0;
                }
                return item;
              }),
              label: {
                show: false
              }
            }));

            const overflowMarkers = {
              name: 'Marcador overflow',
              type: 'scatter',
              symbol: 'none',
              data: iterLabelsLocal.map((iter, di) => {
                const hasOv = (valuesMatrix[di] || []).some(v => v > BASE_MAX);
                if (!hasOv) return null;
                return {
                  value: [compactIterLabelsLocal[di], yMaxPlot],
                  label: {
                    show: true,
                    formatter: '200%+',
                    color: '#0d6efd',
                    fontWeight: 700,
                    fontSize: 11,
                    backgroundColor: 'rgba(255,255,255,0.9)',
                    borderColor: '#0d6efd',
                    borderWidth: 1,
                    borderRadius: 4,
                    padding: [2, 5],
                    shadowColor: 'rgba(0,0,0,0.1)',
                    shadowBlur: 2
                  }
                };
              }).filter(Boolean),
              z: 99
            };

            // Limpiar eventos previos y opciones
            chart.off('mouseover');
            chart.off('mouseout');
            chart.off('highlight');
            chart.off('downplay');
            chart.off('globalout');
            chart.off('mousemove');
            chart.clear();

            // Ajuste dinámico de altura según cantidad de series en la leyenda
            const legendRowsLocal = adjustTrendHeightForLegend(chart, chartDom, localMetricKeys);
            const dynamicBottomLocal = Math.max(90, 70 + (legendRowsLocal * 26));

            chart.setOption({
              backgroundColor: '#fff',
              tooltip: {
                trigger: 'axis',
                appendToBody: true,
                confine: false,
                backgroundColor: 'rgba(255,255,255,0.95)',
                borderColor: '#ddd',
                textStyle: {
                  color: '#222',
                  fontSize: 13
                },
                extraCssText: `border-radius: 6px; max-width: 340px; white-space: normal; z-index: 9999;`,
                axisPointer: {
                  type: 'line',
                  label: {
                    formatter: ({
                      value
                    }) => formatPctLocal(mapPlotToReal(value))
                  }
                },
                formatter: function(params) {
                  const idx = params[0]?.dataIndex ?? 0;
                  const iter = iterLabelsLocal[idx] || '';
                  let html = `<b>${iter}</b><br/>`;
                  params.forEach(p => {
                    if (p.seriesName === 'Referencia y zona' || p.seriesName === 'Marcador overflow') return;
                    const meta = p.data?.meta;
                    const pctRaw = typeof p.data?.realValue === 'number' ? p.data.realValue : (typeof p.value === 'number' ? mapPlotToReal(p.value) : 0);
                    const pctLabel = formatPctLocal(pctRaw);
                    const ejec = meta?.executedReal ?? '—';
                    const plan = meta?.planned ?? '—';
                    const dot = `<span style=\"display:inline-block;margin-right:6px;width:10px;height:10px;background:${p.color};border-radius:50%\"></span>`;
                    html += `${dot}${p.seriesName}: <b>${pctLabel}</b> (${ejec}/${plan})<br/>`;
                  });
                  return html;
                }
              },
              legend: {
                type: 'plain',
                data: localMetricKeys,
                bottom: 10,
                left: 'center',
                width: '96%',
                itemWidth: 10,
                itemHeight: 10,
                itemGap: 18,
                selectorPosition: 'start',
                textStyle: {
                  fontSize: 12.5,
                  color: '#444'
                },
                animation: false
              },
              grid: {
                left: 70,
                right: 90,
                top: 56,
                bottom: dynamicBottomLocal,
                containLabel: true
              },
              xAxis: {
                type: 'category',
                data: compactIterLabelsLocal,
                name: 'Iteración',
                nameLocation: 'middle',
                nameGap: 40,
                nameTextStyle: {
                  fontSize: 12,
                  fontWeight: 600,
                  color: '#495057'
                },
                axisLine: {
                  show: true,
                  lineStyle: {
                    color: '#6c757d',
                    width: 1.2
                  }
                },
                axisTick: {
                  show: true,
                  alignWithLabel: true,
                  length: 8,
                  lineStyle: {
                    color: '#6c757d'
                  }
                },
                axisLabel: {
                  color: '#555',
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
              yAxis: {
                type: 'value',
                min: 0,
                max: yMaxPlot,
                interval: yStep,
                name: 'Cumplimiento (%)',
                nameLocation: 'middle',
                nameGap: 60,
                nameRotate: 90,
                nameTextStyle: {
                  fontSize: 12,
                  fontWeight: 600,
                  color: '#495057'
                },
                axisLine: {
                  show: true,
                  lineStyle: {
                    color: '#6c757d',
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
                    color: 'rgba(0,0,0,0.06)'
                  }
                },
                axisLabel: {
                  margin: 16,
                  formatter: value => formatPctLocal(mapPlotToReal(value))
                }
              },
              series: [
                ...lineSeries,
                overflowMarkers,
                {
                  name: 'Referencia y zona',
                  type: 'line',
                  silent: true,
                  symbol: 'none',
                  lineStyle: {
                    opacity: 0
                  },
                  markLine: {
                    symbol: 'none',
                    lineStyle: {
                      type: 'dashed',
                      color: '#6c757d',
                      width: 1.7,
                      opacity: 0.95
                    },
                    label: {
                      show: true,
                      position: 'end',
                      formatter: (p) => `${p.value}%`,
                      color: '#6c757d',
                      backgroundColor: 'rgba(255,255,255,.6)',
                      padding: [2, 4]
                    },
                    data: [{
                      yAxis: 100
                    }, {
                      yAxis: BASE_MAX
                    }]
                  },
                  markArea: hasOverflow ? {
                    silent: true,
                    itemStyle: {
                      color: 'rgba(13,110,253,0.06)'
                    },
                    label: {
                      show: true,
                      color: '#0d6efd',
                      fontWeight: 600,
                      formatter: '200%+'
                    },
                    data: [
                      [{
                        yAxis: BASE_MAX
                      }, {
                        yAxis: yMaxPlot
                      }]
                    ]
                  } : undefined
                }
              ]
            });

            // Re-vincular eventos de hover de etiquetas
            (function() {
              let lastLabeledSeries = null;

              function setSeriesLabelsVisible(seriesIdx, visible) {
                const opt = chart.getOption();
                if (!opt || !opt.series || !opt.series[seriesIdx]) return;
                const s = opt.series[seriesIdx];
                if (s.type !== 'line' || s.name === 'Referencia y zona' || s.name === 'Marcador overflow') return;
                const serieColor = palette[seriesIdx % palette.length];
                const labelCfg = {
                  show: !!visible,
                  position: 'top',
                  distance: 6,
                  backgroundColor: 'rgba(255,255,255,0.9)',
                  borderColor: '#ddd',
                  borderWidth: 1,
                  borderRadius: 4,
                  padding: [2, 4],
                  color: serieColor,
                  fontSize: 12,
                  fontWeight: 600,
                  formatter: function(p) {
                    const raw = typeof p.data?.realValue === 'number' ? p.data.realValue : (typeof p.value === 'number' ? mapPlotToReal(p.value) : null);
                    return formatPctLocal(raw, {
                      allowDash: false
                    });
                  }
                };
                s.label = Object.assign({}, s.label || {}, labelCfg);
                chart.setOption({
                  series: opt.series
                }, {
                  lazyUpdate: true
                });
              }
              chart.on('mouseover', function(params) {
                if (params && params.seriesType === 'line' && params.seriesName !== 'Referencia y zona' && params.seriesName !== 'Marcador overflow') {
                  const idx = params.seriesIndex;
                  if (lastLabeledSeries !== idx) {
                    if (lastLabeledSeries !== null) setSeriesLabelsVisible(lastLabeledSeries, false);
                    setSeriesLabelsVisible(idx, true);
                    lastLabeledSeries = idx;
                  }
                }
              });
              chart.on('mouseout', function(params) {
                if (params && params.seriesType === 'line' && params.seriesName !== 'Referencia y zona' && params.seriesName !== 'Marcador overflow') {
                  const idx = params.seriesIndex;
                  setSeriesLabelsVisible(idx, false);
                  if (lastLabeledSeries === idx) lastLabeledSeries = null;
                }
              });
              chart.on('highlight', function(params) {
                if (params && params.seriesType === 'line' && params.seriesName !== 'Referencia y zona' && params.seriesName !== 'Marcador overflow') {
                  const idx = params.seriesIndex;
                  if (lastLabeledSeries !== idx) {
                    if (lastLabeledSeries !== null) setSeriesLabelsVisible(lastLabeledSeries, false);
                    setSeriesLabelsVisible(idx, true);
                    lastLabeledSeries = idx;
                  }
                }
              });
              chart.on('downplay', function(params) {
                if (params && params.seriesType === 'line' && params.seriesName !== 'Referencia y zona' && params.seriesName !== 'Marcador overflow') {
                  const idx = params.seriesIndex;
                  setSeriesLabelsVisible(idx, false);
                  if (lastLabeledSeries === idx) lastLabeledSeries = null;
                }
              });
              chart.on('globalout', function() {
                if (lastLabeledSeries !== null) {
                  setSeriesLabelsVisible(lastLabeledSeries, false);
                  lastLabeledSeries = null;
                }
              });
            })();
          };

          // Render inicial de chips y comportamiento
          renderPhaseChips();
          phaseLegend.addEventListener('click', (e) => {
            const btn = e.target.closest('button.phase-pill');
            if (!btn) return;
            const phaseName = btn.getAttribute('data-phase');
            if (!phaseName) return;
            if (selectedPhases.has(phaseName)) selectedPhases.delete(phaseName);
            else selectedPhases.add(phaseName);
            renderPhaseChips();
            const filtered = filterDataByPhase(Array.isArray(DATA) ? DATA : []);
            rebuildTrendChart(filtered);
            window.__PHASE_RESTORE = Array.from(selectedPhases);
            saveStateToStorage(Object.assign(loadStateFromStorage() || {}, {
              phases: window.__PHASE_RESTORE
            }));
            // Al cambiar de fase, intentamos traer datos frescos del servidor
            refreshFromServer();
          });

          // Desactivar interactividad si solo hay una fase
          const totalFases = letterToPhase.size;
          if (totalFases <= 1) {
            document.querySelectorAll('.phase-pill').forEach(chip => {
              chip.classList.add('no-filter');
              chip.style.cursor = 'default';
              chip.removeAttribute('data-phase');
              chip.title = 'Solo existe una fase; el filtro no aplica.';
            });
          }

          // Render inicial del gráfico acorde a selección (todas activas)
          const initialFiltered = filterDataByPhase(Array.isArray(DATA) ? DATA : []);
          if (initialFiltered.length !== (Array.isArray(DATA) ? DATA.length : 0)) {
            rebuildTrendChart(initialFiltered);
          }
        }
      } catch (e) {
        console.warn("No se pudo construir la leyenda de fases", e);
      }

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
        // Recalcular altura según leyenda actual en cada resize
        try {
          const instance = echarts.getInstanceByDom(trendChartDom);
          if (instance) {
            const opt = instance.getOption();
            const keys = (opt && opt.legend && opt.legend[0] && opt.legend[0].data) ? opt.legend[0].data : [];
            const rows = adjustTrendHeightForLegend(instance, trendChartDom, keys);
            const dynamicBottom = Math.max(90, 70 + (rows * 26));
            instance.setOption({
              grid: {
                bottom: dynamicBottom
              }
            }, {
              lazyUpdate: true
            });
          } else {
            trendChart.resize();
          }
        } catch (e) {
          trendChart.resize();
        }
      }
      resizeTrend();
      if (!window.__DASH_RESIZE_BOUND) {
        window.addEventListener("resize", resizeTrend);
        window.__DASH_RESIZE_BOUND = true;
      }

      // Si no hay datos, mostrar aviso y salir
      if (!Array.isArray(DATA) || DATA.length === 0) {
        trendChart.clear();
        trendChartDom.innerHTML = '<div class="text-muted">No hay datos para mostrar.</div>';
      } else {
        const legendType = "plain";

        const allTrendValues = DATA.flatMap(it => (it.metricas || []).map(m => Number(m.executed) || 0));
        const realMaxValue = allTrendValues.length ? Math.max(...allTrendValues) : 0;

        // ===== Escala con banda extendida comprimida por arriba de 200% =====
        const BASE_MAX = 200; // rango principal 0–200%
        const EXT_PLOT = 20; // altura visual (en unidades de eje) para la zona extendida
        const hasOverflow = realMaxValue > BASE_MAX;
        const yMaxPlot = hasOverflow ? (BASE_MAX + EXT_PLOT) : BASE_MAX;
        const yStep = 20; // ticks legibles cada 20%

        // Mapea valor real (pct) a valor de eje "plot" (con compresión en overflow)
        const mapRealToPlot = v => {
          const num = Number(v) || 0;
          if (!hasOverflow || num <= BASE_MAX) return Math.max(0, num);
          // Distribuye (BASE_MAX, realMax] dentro de (BASE_MAX, yMaxPlot]
          const k = EXT_PLOT / Math.max(1e-6, (realMaxValue - BASE_MAX));
          return BASE_MAX + (num - BASE_MAX) * k;
        };

        // Inversa: de valor "plot" a valor real (para etiquetar eje/axisPointer correctamente)
        const mapPlotToReal = vPlot => {
          const num = Number(vPlot) || 0;
          if (!hasOverflow || num <= BASE_MAX) return Math.max(0, num);
          const kInv = Math.max(1e-6, (realMaxValue - BASE_MAX) / EXT_PLOT);
          return BASE_MAX + (num - BASE_MAX) * kInv;
        };

        const formatPct = (val, {
          allowDash = true
        } = {}) => {
          const num = Number(val);
          if (!Number.isFinite(num)) {
            return allowDash ? "—" : "";
          }
          if (Math.abs(num) >= 100) return `${Math.round(num)}%`;
          return `${Number(num.toFixed(1))}%`;
        };

        // Colores por serie para reutilizar en etiquetas
        const seriesColors = METRIC_KEYS.map((_, sIdx) => palette[sIdx % palette.length]);

        // Matriz de valores reales por iteración y serie (para detectar outliers por iteración)
        const valuesMatrix = (Array.isArray(DATA) ? DATA : []).map(it =>
          METRIC_KEYS.map(name => {
            const mm = (it.metricas || []).find(m => m.nombre === name);
            return mm ? (Number(mm.executed) || 0) : 0;
          })
        );

        // Rankeamos outliers (>200%) por iteración para escalonar etiquetas sin superposición
        const OUTLIER_STEP = 12; // px por nivel
        const outlierRanks = valuesMatrix.map(row => {
          const entries = row.map((v, idx) => ({
              idx,
              v
            })).filter(e => e.v > BASE_MAX)
            .sort((a, b) => b.v - a.v); // mayores arriba
          const map = {};
          entries.forEach((e, rank) => {
            map[e.idx] = rank;
          });
          return map; // { serieIndex: rank }
        });

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
              color: palette[sIdx % palette.length],
              fontSize: 12,
              fontWeight: 600,
              formatter: function(p) {
                const raw = typeof p.data?.realValue === "number" ?
                  p.data.realValue :
                  (typeof p.value === "number" ? mapPlotToReal(p.value) : null);
                return formatPct(raw, {
                  allowDash: false
                });
              }
            }
          },
          blur: {
            lineStyle: { //lineas del grafico
              opacity: 0.10
            },
            itemStyle: { //puntos del grafico
              opacity: 0.10,
            },

          },
          data: DATA.map((it, di) => {
            const m = it.metricas.find(mm => mm.nombre === name);
            const real = m ? Number(m.executed) || 0 : 0;
            const isOverflowPoint = real > BASE_MAX;
            const plotVal = mapRealToPlot(real);
            const item = {
              value: plotVal,
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
            if (isOverflowPoint) {
              // Puntos > 200% como rombos, con etiqueta compacta y color de la serie
              item.symbol = 'diamond';
              item.symbolSize = 11;
              item.itemStyle = {
                color: seriesColors[sIdx], // color de la métrica
                borderColor: '#ffffff',
                borderWidth: 1,
                shadowColor: 'rgba(0,0,0,0.15)',
                shadowBlur: 4
              };
              const rank = (outlierRanks[di] && outlierRanks[di][sIdx]) || 0;
              const seriesColor = seriesColors[sIdx];

            }
            return item;
          }),
          label: {
            show: false
          }
        }));
        const overflowMarkers = {
          name: "Marcador overflow",
          type: "scatter",
          symbol: "none",
          data: iterLabels.map((iter, di) => {
            const hasOverflow = (valuesMatrix[di] || []).some(v => v > BASE_MAX);
            if (!hasOverflow) return null;
            return {
              value: [compactIterLabels[di], yMaxPlot],
              label: {
                show: true,
                formatter: "200%+",
                color: "#0d6efd",
                fontWeight: 700,
                fontSize: 11,
                backgroundColor: "rgba(255,255,255,0.9)",
                borderColor: "#0d6efd",
                borderWidth: 1,
                borderRadius: 4,
                padding: [2, 5],
                shadowColor: "rgba(0,0,0,0.1)",
                shadowBlur: 2
              }
            };
          }).filter(Boolean),
          z: 99
        };

        // Ajustar altura para que la leyenda no se superponga (calcula filas estimadas)
        const legendRows = adjustTrendHeightForLegend(trendChart, trendChartDom, METRIC_KEYS);
        const dynamicBottom = Math.max(90, 70 + (legendRows * 26));

        trendChart.setOption({
          backgroundColor: "#fff",
          tooltip: {
            trigger: "axis",
            appendToBody: true,
            confine: false,
            backgroundColor: "rgba(255,255,255,0.95)",
            borderColor: "#ddd",
            textStyle: {
              color: "#222",
              fontSize: 13
            },
            extraCssText: `
                    color: seriesColor,
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
                }) => formatPct(mapPlotToReal(value))
              }
            },
            formatter: function(params) {
              const idx = params[0]?.dataIndex ?? 0;
              const iter = iterLabels[idx] || "";
              let html = `<b>${iter}</b><br/>`;
              params.forEach(p => {
                if (p.seriesName === "Referencia y zona" || p.seriesName === "Marcador overflow") return;
                const meta = p.data?.meta;
                const pctRaw = typeof p.data?.realValue === "number" ?
                  p.data.realValue :
                  (typeof p.value === "number" ? mapPlotToReal(p.value) : 0);
                const pctLabel = formatPct(pctRaw);
                const ejec = meta?.executedReal ?? "—";
                const plan = meta?.planned ?? "—";
                const dot = `<span style="display:inline-block;margin-right:6px;width:10px;height:10px;background:${p.color};border-radius:50%"></span>`;
                html += `${dot}${p.seriesName}: <b>${pctLabel}</b> (${ejec}/${plan})<br/>`;
              });
              return html;
            }
          },
          legend: {
            // En muchos ítems dejamos que haga varias filas; si prefieres paginación, cambia a 'scroll'
            type: legendType,
            data: METRIC_KEYS,
            bottom: 10,
            left: "center",
            width: "96%",
            itemWidth: 10,
            itemHeight: 10,
            itemGap: 18,
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
            bottom: dynamicBottom,
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
          yAxis: {

            type: "value",
            min: 0,
            max: yMaxPlot,
            interval: yStep,
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
                color: "rgba(0,0,0,0.06)"
              }
            },
            axisLabel: {
              margin: 16,
              formatter: value => formatPct(mapPlotToReal(value))
            }
          },
          series: [
            ...lineSeries,
            overflowMarkers, // ← agregado aquí
            {
              name: "Referencia y zona",
              type: "line",
              silent: true,
              symbol: "none",
              lineStyle: {
                opacity: 0
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
                  formatter: (p) => `${p.value}%`,
                  color: "#6c757d",
                  backgroundColor: "rgba(255,255,255,.6)",
                  padding: [2, 4]
                },
                data: [{
                  yAxis: 100
                }, {
                  yAxis: BASE_MAX
                }]
              },
              markArea: hasOverflow ? {
                silent: true,
                itemStyle: {
                  color: 'rgba(13,110,253,0.06)'
                },
                label: {
                  show: true,
                  color: '#0d6efd',
                  fontWeight: 600,
                  formatter: '200%+'
                },
                data: [
                  [{
                    yAxis: BASE_MAX
                  }, {
                    yAxis: yMaxPlot
                  }]
                ]
              } : undefined
            }
          ]
        });

        // Mostrar etiquetas de TODOS los puntos al pasar por encima de la línea o su leyenda
        // Mostrar etiquetas SOLO de la serie sobre la que está el puntero
        // Mostrar etiquetas SOLO de la serie sobre la que está el puntero (línea o punto)
        (function() {
          let lastLabeledSeries = null;

          function setSeriesLabelsVisible(seriesIdx, visible) {
            const opt = trendChart.getOption();
            if (!opt || !opt.series || !opt.series[seriesIdx]) return;
            const s = opt.series[seriesIdx];
            if (s.type !== "line" || s.name === "Referencia y zona" || s.name === "Marcador overflow") return;

            const serieColor = palette[seriesIdx % palette.length];
            const labelCfg = {
              show: !!visible,
              position: "top",
              distance: 6,
              backgroundColor: "rgba(255,255,255,0.9)",
              borderColor: "#ddd",
              borderWidth: 1,
              borderRadius: 4,
              padding: [2, 4],
              color: serieColor,
              fontSize: 12,
              fontWeight: 600,
              formatter: function(p) {
                const raw = typeof p.data?.realValue === "number" ?
                  p.data.realValue :
                  (typeof p.value === "number" ? mapPlotToReal(p.value) : null);
                return formatPct(raw, {
                  allowDash: false
                });
              }
            };

            s.label = Object.assign({}, s.label || {}, labelCfg);
            trendChart.setOption({
              series: opt.series
            }, {
              lazyUpdate: true
            });
          }

          // --- Nueva detección ampliada: detecta línea, símbolo o área sensible ---
          trendChart.getZr().on("mousemove", function(e) {
            const pointInPixel = [e.offsetX, e.offsetY];
            const pointInGrid = trendChart.convertFromPixel({
              seriesIndex: 0
            }, pointInPixel);
            if (!pointInGrid) return;

            const found = trendChart.convertToPixel({
              seriesIndex: 0
            }, pointInGrid);
            if (!found) return;

            const hoverSeries = trendChart.containPixel({
              seriesIndex: 0
            }, pointInPixel);
            if (!hoverSeries) return;
          });

          // Hover sobre puntos o símbolos
          trendChart.on("mouseover", function(params) {
            if (
              params &&
              params.seriesType === "line" &&
              params.seriesName !== "Referencia y zona" &&
              params.seriesName !== "Marcador overflow"
            ) {
              const idx = params.seriesIndex;
              if (lastLabeledSeries !== idx) {
                if (lastLabeledSeries !== null)
                  setSeriesLabelsVisible(lastLabeledSeries, false);
                setSeriesLabelsVisible(idx, true);
                lastLabeledSeries = idx;
              }
            }
          });

          // Salida del hover (línea o punto)
          trendChart.on("mouseout", function(params) {
            if (
              params &&
              params.seriesType === "line" &&
              params.seriesName !== "Referencia y zona" &&
              params.seriesName !== "Marcador overflow"
            ) {
              const idx = params.seriesIndex;
              setSeriesLabelsVisible(idx, false);
              if (lastLabeledSeries === idx) lastLabeledSeries = null;
            }
          });

          // Hover desde la leyenda
          trendChart.on("highlight", function(params) {
            if (params && params.seriesType === "line" && params.seriesName !== "Referencia y zona" && params.seriesName !== "Marcador overflow") {
              const idx = params.seriesIndex;
              if (lastLabeledSeries !== idx) {
                if (lastLabeledSeries !== null)
                  setSeriesLabelsVisible(lastLabeledSeries, false);
                setSeriesLabelsVisible(idx, true);
                lastLabeledSeries = idx;
              }
            }
          });

          // Salida desde la leyenda o fuera del canvas
          trendChart.on("downplay", function(params) {
            if (params && params.seriesType === "line" && params.seriesName !== "Referencia y zona" && params.seriesName !== "Marcador overflow") {
              const idx = params.seriesIndex;
              setSeriesLabelsVisible(idx, false);
              if (lastLabeledSeries === idx) lastLabeledSeries = null;
            }
          });

          trendChart.on("globalout", function() {
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
          <div id="legend-metricas-anterior" class="mb-2"></div>
        </div>
      </div>
    </div>`;
        row.appendChild(colLeft);
        renderIteracionChart(defIter, "bars-anterior", "legend-metricas-anterior");


        // Cambio de selección
        if (!window.__DASH_HIST_CHANGE_BOUND) {
          document.addEventListener("change", function(e) {
            if (e.target && e.target.id === "hist-select") {
              const idx = parseInt(e.target.value, 10);
              const chosen = DATA[idx];
              const t = document.getElementById("left-title");
              const d = document.getElementById("left-dates");
              if (t) t.textContent = chosen.iteracion;
              if (d) d.textContent = `Del ${chosen.inicio} al ${chosen.fin}`;
              renderIteracionChart(chosen, "bars-anterior", "legend-metricas-anterior");
              // persistir selección
              const st = loadStateFromStorage() || {};
              st.hist = String(idx);
              st.histKey = chosen && chosen.iteracion ? chosen.iteracion : null;
              saveStateToStorage(st);
              // Cada cambio de historial puede aprovechar para verificar si hay datos nuevos
              refreshFromServer();
            }
          });
          window.__DASH_HIST_CHANGE_BOUND = true;
        }
      }

      // ---- Card Derecha: Actual o mensaje ----
      // ---- Card Derecha: Actual (3 casos) ----
      const colRight = document.createElement("div");
      colRight.className = "col-12 col-xl-6";

      // ✅ Caso 1: hay iteración actual y métricas cargadas
      if (ACTUAL && Array.isArray(ACTUAL.metricas) && ACTUAL.metricas.length > 0) {
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
          <div id="legend-metricas-actual" class="mb-2"></div>
        </div>
      </div>
    </div>`;
        row.appendChild(colRight);
        renderIteracionChart(ACTUAL, "bars-actual", "legend-metricas-actual");


      }
      // ⚠️ Caso 2: existe iteración actual pero sin métricas planificadas
      else if (ACTUAL && (!ACTUAL.metricas || ACTUAL.metricas.length === 0)) {
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
        No hay iteración activa o no tiene métricas planificadas
      </p>
      <p class="mb-0" style="font-size:0.9rem; color:#868e96; max-width:420px;">
        ${MSG_ACTUAL || "El proyecto no tiene una iteración planificada para la fecha actual."}
      </p>
    </div>
  </div>`;

        row.appendChild(colRight);
      }
    }

    document.addEventListener("DOMContentLoaded", function() {
      const st = loadStateFromStorage();
      if (st && Array.isArray(st.phases)) window.__PHASE_RESTORE = st.phases;
      initDashboard();
      // Restaurar UI (hist y leyenda) post-render
      applyUiState(st);
    });
    document.addEventListener('DOMContentLoaded', function() {
      // Intentar SSE primero; si falla, usar polling
      const ok = startLiveUpdatesSSE();
      if (!ok) startLiveUpdates(3000);
    });
    // Refrescar cuando la pestaña vuelve a estar visible o la ventana gana foco
    document.addEventListener('visibilitychange', function() {
      if (!document.hidden) refreshFromServer();
    });
    window.addEventListener('focus', function() {
      refreshFromServer();
    });
    document.addEventListener("DOMContentLoaded", function() {
      const toggleBtn = document.getElementById("toggleLegendBtn");
      const toggleIcon = toggleBtn.querySelector("i");
      const toggleText = toggleBtn.querySelector("span");
      let allVisible = true;

      if (toggleBtn) {
        toggleBtn.addEventListener("click", function() {
          const instance = echarts.getInstanceByDom(document.getElementById("trendChart"));
          if (!instance) return;
          const option = instance.getOption();
          if (!option.legend || !option.legend[0]) return;

          const selected = {};
          option.legend[0].data.forEach(name => {
            selected[name] = !allVisible; // alterna visibilidad
          });
          option.legend[0].selected = selected;
          instance.setOption(option);
          allVisible = !allVisible;

          // Actualiza texto, icono y estilo
          if (allVisible) {
            toggleText.textContent = "Ocultar métricas";
            toggleIcon.className = "oi oi-eye";
            toggleBtn.classList.remove("off");
          } else {
            toggleText.textContent = "Mostrar métricas";
            toggleIcon.className = "oi oi-eye-slash";
            toggleBtn.classList.add("off");
          }
        });
      }
    });
  </script>
</body>

</html>