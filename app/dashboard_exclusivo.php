<?php

/**
 * Dashboard exclusivo de calidad
 * Visualización individual de métricas con semáforo según umbral.
 * 
 * @author    Lorenzo Teppa
 * @project   MetricFlow-SQA
 * @since     2025
 */

require_once __DIR__ . '/../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// ==============================
// Validación de acceso
// ==============================
ControlAcceso::verificaLogin();
$permVisualizacionMetricas = PermisosSistema::VISUALIZACION_METRICAS;
$usuarioActual = ControlAcceso::usuarioActual();

$isSuper = false;
if ($usuarioActual && isset($usuarioActual->roles) && is_array($usuarioActual->roles)) {
  foreach ($usuarioActual->roles as $r) {
    if (strtolower(trim($r->nombre ?? '')) === 'superadmin') {
      $isSuper = true;
      break;
    }
  }
}

if (!$isSuper && !ControlAcceso::verificaPermiso($permVisualizacionMetricas)) {
  header('Location: ' . Constantes::HOMEAUTH);
  exit;
}

// ==============================
// Identificación de proyecto
// ==============================
$idProyecto = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 0;
if ($idProyecto <= 0) {
  $asignados = ControlAcceso::proyectosAsignadosDelUsuario();
  if (!empty($asignados)) {
    header('Location: /metricflowsqa/app/dashboard_exclusivo.php?proyecto=' . (int)$asignados[0]);
    exit;
  } else {
    header('Location: ' . Constantes::HOMEAUTH);
    exit;
  }
}

if (!$isSuper && !ControlAcceso::usuarioPerteneceAProyecto($idProyecto)) {
  header('Location: ' . Constantes::HOMEAUTH);
  exit;
}

// ==============================
// Datos de proyecto
// ==============================
$cn = BDConexion::getConexion();
$sqlProyecto = "SELECT nombre, estado FROM proyecto WHERE id_proyecto = ?";
$stmt = $cn->prepare($sqlProyecto);
$stmt->bind_param('i', $idProyecto);
$stmt->execute();
$resProyecto = $stmt->get_result();
$proyectoExiste = ($resProyecto && $resProyecto->num_rows > 0);

if ($proyectoExiste) {
  $proyecto = $resProyecto->fetch_assoc();
  $nombreProyecto = $proyecto['nombre'];
  $estadoProyecto = $proyecto['estado'];
} else {
  $nombreProyecto = "Proyecto no encontrado";
  $estadoProyecto = "No disponible";
}
$stmt->close();

// ==============================
// Consulta de métricas
// ==============================
$sql = "
SELECT 
  m.id_metrica,
  m.nombre AS nombre_metrica,
  f.nombre AS fase,
  i.numero_iteracion,
  SUM(mi.valor_planificado) AS planificado,
  SUM(mi.valor_ejecutado) AS ejecutado,
  MAX(mi.umbral_desviacion) AS umbral
FROM metrica_iteracion mi
JOIN metrica m ON mi.id_metrica = m.id_metrica
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON i.id_fase = f.id_fase
JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
WHERE pf.id_proyecto = ?
GROUP BY m.id_metrica, m.nombre, f.nombre, i.numero_iteracion
ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;
";
$stmt = $cn->prepare($sql);
$stmt->bind_param('i', $idProyecto);
$stmt->execute();
$res = $stmt->get_result();

$metricas = [];
while ($r = $res->fetch_assoc()) {
  $faseIter = trim($r['fase'] . ' ' . $r['numero_iteracion']);
  $plan = (float)($r['planificado'] ?? 0);
  $ejec = (float)($r['ejecutado'] ?? 0);
  $umbral = (float)($r['umbral'] ?? 10);

  if ($plan == 0 && $ejec == 0) {
    $pct = 100;
  } elseif ($plan == 0 && $ejec > 0) {
    $pct = 100;
  } else {
    $pct = ($plan > 0) ? round(($ejec / $plan) * 100, 1) : 0;
  }

  // Determinar color semáforo
  if ($pct >= 100) {
    $color = "#28a745"; // verde
  } elseif ($pct >= (100 - $umbral)) {
    $color = "#ffc107"; // amarillo
  } else {
    $color = "#dc3545"; // rojo
  }

  $metricas[] = [
    "id" => (int)$r['id_metrica'],
    "nombre" => $r['nombre_metrica'],
    "fase_iteracion" => $faseIter,
    "planificado" => $plan,
    "ejecutado" => $ejec,
    "umbral" => $umbral,
    "porcentaje" => $pct,
    "color" => $color
  ];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?= Constantes::NOMBRE_SISTEMA; ?> - Dashboard de Calidad</title>
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
  <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
  <script src="../lib/JQuery/jquery-3.3.1.js"></script>
  <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
  <?php include __DIR__ . '/../gui/navbar.php'; ?>

  <style>
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", "Inter", sans-serif;
    }

    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
      transition: transform 0.2s ease;
    }

    .card:hover {
      transform: translateY(-2px);
    }

    .chart-container {
      height: 230px;
    }

    .titulo-seccion {
      color: #495057;
      font-weight: 700;
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
  </style>
</head>

<body>

  <div class="container my-4">

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
          // Intentar volver en el historial del navegador cuando sea seguro
          e.preventDefault();
          try {
            var ref = document.referrer;
            if (ref && (new URL(ref)).origin === location.origin && history.length > 1) {
              history.back();
            } else {
              // Fallback: navegar a la lista de proyectos
              location.href = btn.getAttribute('href');
            }
          } catch (err) {
            location.href = btn.getAttribute('href');
          }
        });
      })();
    </script>

    <?php if (empty($metricas)): ?>
      <div class="card text-center">
        <div class="card-body text-muted">
          No se encontraron métricas registradas para este proyecto.
        </div>
      </div>
    <?php else: ?>
      <div class="row">
        <?php foreach ($metricas as $m): ?>
          <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-dark" style="font-weight:600"><?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?></h6>
                <span class="badge" style="background-color: <?= $m['color'] ?>;">&nbsp;</span>
              </div>
              <div class="card-body text-center">
                <div id="chart-<?= $m['id']; ?>" class="chart-container"></div>
                <div class="small text-muted mt-2">
                  Fase: <?= htmlspecialchars($m['fase_iteracion']); ?><br>
                  Planificado: <?= (int)$m['planificado']; ?> — Ejecutado: <?= (int)$m['ejecutado']; ?><br>
                  Cumplimiento: <?= $m['porcentaje']; ?>% — Umbral ±<?= $m['umbral']; ?>%
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <script>
    const metricas = <?= json_encode($metricas, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;

    metricas.forEach(m => {
      const chart = echarts.init(document.getElementById('chart-' + m.id));
      const restante = Math.max(m.planificado - m.ejecutado, 0);
      chart.setOption({
        title: {
          text: `${m.porcentaje}%`,
          subtext: m.fase_iteracion,
          left: 'center',
          top: '40%',
          textStyle: {
            fontSize: 18,
            color: m.color,
            fontWeight: 'bold'
          },
          subtextStyle: {
            fontSize: 12,
            color: '#6c757d'
          }
        },
        tooltip: {
          trigger: 'item',
          formatter: `<b>${m.nombre}</b><br>Planificado: ${m.planificado}<br>Ejecutado: ${m.ejecutado}<br>Umbral: ±${m.umbral}%<br>Cumplimiento: ${m.porcentaje}%`
        },
        series: [{
          type: 'pie',
          radius: ['60%', '85%'],
          avoidLabelOverlap: false,
          label: {
            show: false
          },
          data: [{
              value: m.ejecutado,
              name: 'Ejecutado',
              itemStyle: {
                color: m.color
              }
            },
            {
              value: restante,
              name: 'Restante',
              itemStyle: {
                color: '#dee2e6'
              }
            }
          ]
        }]
      });
    });
  </script>

</body>

</html>