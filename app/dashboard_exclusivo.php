<?php
include_once '../lib/ControlAcceso.Class.php';
// Requiere login
ControlAcceso::verificaLogin();
// Permiso requerido para ver este dashboard
$permVisualizacionMetricas = PermisosSistema::VISUALIZACION_METRICAS;

// Usuario en sesión
$usuarioActual = ControlAcceso::usuarioActual();
$isSuper = false;
if ($usuarioActual && isset($usuarioActual->roles) && is_array($usuarioActual->roles)) {
    foreach ($usuarioActual->roles as $r) {
        $rolName = mb_strtolower(trim($r->nombre ?? ''), 'UTF-8');
        if ($rolName === 'superadmin') {
            $isSuper = true;
            break;
        }
    }
}

// Debe ser superadmin o tener el permiso de visualización de métricas
if (!$isSuper && !ControlAcceso::verificaPermiso($permVisualizacionMetricas)) {
    // No autorizado
    header('Location: ' . Constantes::HOMEAUTH);
    exit;
}

include_once '../modelo/BDConexion.Class.php';

// Proyecto requerido (igual que en dashboard.php): si no viene, redirigir al primer proyecto asignado
$idProyecto = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 1;
if ($idProyecto <= 0) {
    $asignados = ControlAcceso::proyectosAsignadosDelUsuario();
    if (!empty($asignados)) {
        header('Location: ' . '/metricflowsqa/app/dashboard_exclusivo.php?proyecto=' . (int)$asignados[0]);
        exit;
    } else {
        // Si no tiene proyectos asignados y no es superadmin, volver al HOMEAUTH
        if (!$isSuper) {
            header('Location: ' . Constantes::HOMEAUTH);
            exit;
        }
        // Si es superadmin y no hay proyecto en GET, pedir que se indique uno (redirigir a proyectos)
        header('Location: ' . Constantes::HOMEAUTH);
        exit;
    }
}

// Si no es superadmin, debe pertenecer al proyecto
if (!$isSuper && !ControlAcceso::usuarioPerteneceAProyecto($idProyecto)) {
    header('Location: ' . Constantes::HOMEAUTH);
    exit;
}

// Conexión y carga dinámica de métricas para el proyecto (agregadas por todas las iteraciones)
$cn = BDConexion::getConexion();
$sql = "SELECT m.id_metrica AS id, m.nombre AS nombre, COALESCE(SUM(mi.valor_planificado),0) AS planificado, COALESCE(SUM(mi.valor_ejecutado),0) AS ejecutado
        FROM metrica m
        JOIN metrica_iteracion mi ON m.id_metrica = mi.id_metrica
        JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
        JOIN fase f ON i.id_fase = f.id_fase
        JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
        WHERE pf.id_proyecto = ?
        GROUP BY m.id_metrica, m.nombre
        ORDER BY m.nombre;";
$stmt = $cn->prepare($sql);
$stmt->bind_param('i', $idProyecto);
$stmt->execute();
$res = $stmt->get_result();
$metricas = [];
while ($row = $res->fetch_assoc()) {
    $metricas[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'planificado' => (float)$row['planificado'],
        'ejecutado' => (float)$row['ejecutado']
    ];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?php echo Constantes::NOMBRE_SISTEMA; ?> - Dashboard Exclusivo</title>

  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
  <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
  <script src="../lib/JQuery/jquery-3.3.1.js"></script>
  <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
  <script src="../lib/echarts/echarts.min.js"></script>

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

    h5, h6 {
      font-weight: 600;
    }

    .chart-container {
      height: 230px;
    }

    .titulo-seccion {
      color: #495057;
      font-weight: 700;
    }
  </style>
</head>

<body>
  <?php include_once '../gui/navbar.php'; ?>

  <div class="container-fluid mt-4">
    <h4 class="mb-4 titulo-seccion">Visualización individual de métricas</h4>

    <div class="row">
      <?php if (empty($metricas)): ?>
        <div class="col-12">
          <div class="card my-4">
            <div class="card-body text-center text-muted">No se encontraron métricas para este proyecto.</div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($metricas as $m): ?>
          <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
              <div class="card-body text-center">
                <h6 class="text-muted mb-3"><?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?></h6>
                <div id="chart-<?= $m['id']; ?>" class="chart-container"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const palette = [
      "#007bff", "#1b9437", "#dc3545", "#b98b00", "#0d8092",
      "#6f42c1", "#fd7e14", "#147b5c", "#6610f2", "#e83e8c",
      "#343a40", "#582349", "#00c2ff", "#b07ef2", "#ff9f40"
    ];

    // Métricas cargadas desde el backend (PHP)
    const metricas = <?= json_encode($metricas, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>.map((m, i) => ({
      id: 'chart-' + m.id,
      nombre: m.nombre,
      planificado: Number(m.planificado) || 0,
      ejecutado: Number(m.ejecutado) || 0,
      color: palette[i % palette.length]
    }));

    function generarDonut({ id, planificado, ejecutado, nombre, color }) {
      const chart = echarts.init(document.getElementById(id));
  const porcentaje = planificado > 0 ? Math.round((ejecutado / planificado) * 100) : (ejecutado > 0 ? 100 : 0);

      chart.setOption({
        title: {
          text: `${porcentaje}%`,
          subtext: 'Cumplimiento',
          left: 'center',
          top: '38%',
          textStyle: {
            fontSize: 20,
            fontWeight: 'bold',
            color: color
          },
          subtextStyle: {
            fontSize: 12,
            color: '#6c757d'
          }
        },
        tooltip: {
          trigger: 'item',
          formatter: `<b>${nombre}</b><br>Planificado: ${planificado}<br>Ejecutado: ${ejecutado}<br>Cumplimiento: ${porcentaje}%`
        },
        series: [
          {
            type: 'pie',
            radius: ['70%', '90%'],
            avoidLabelOverlap: false,
            label: { show: false },
            data: [
              {
                value: ejecutado,
                name: 'Ejecutado',
                itemStyle: { color: color }
              },
              {
                value: Math.max(planificado - ejecutado, 0),
                name: 'Restante',
                itemStyle: { color: '#dee2e6' }
              }
            ]
          }
        ]
      });
    }

    // Renderizar todos los gráficos
    metricas.forEach(m => generarDonut(m));
  </script>
</body>

</html>
