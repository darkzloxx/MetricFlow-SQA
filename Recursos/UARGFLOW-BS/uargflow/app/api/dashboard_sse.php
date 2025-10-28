<?php
// Server-Sent Events endpoint to push dashboard updates without full page reload
// URL: /app/api/dashboard_sse.php?proyecto=ID
// Sends event: update with same payload as dashboard_data.php plus version, serverTime

ignore_user_abort(true);
set_time_limit(0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
// Disable buffering if possible
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', '0');

$proyectoId = isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 1;

function build_payload_and_version($proyectoId)
{
    // Prefer the DB used by tests first, then fallback
    $conexion = @new mysqli('localhost', 'root', '', 'bd_CU12', 3306);
    if ($conexion->connect_error) {
        $conexion = @new mysqli('localhost', 'root', '', 'bd_CU12_1', 3308);
        if ($conexion->connect_error) {
            return [null, null];
        }
    }

    // Proyecto existe?
    $sqlProyecto = "SELECT nombre, estado FROM proyecto WHERE id_proyecto = $proyectoId";
    $resProyecto = $conexion->query($sqlProyecto);
    $proyectoExiste = ($resProyecto && $resProyecto->num_rows > 0);
    $proyectoInfo = null;
    if ($proyectoExiste) {
        $rowProyecto = $resProyecto->fetch_assoc();
        $proyectoInfo = [
            'nombre' => $rowProyecto['nombre'],
            'estado' => $rowProyecto['estado']
        ];
    }

    // Totales
    $sqlMetricas = "SELECT COUNT(DISTINCT mi.id_metrica) AS total
                  FROM metrica_iteracion mi
                  JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
                  JOIN fase f ON f.id_fase = i.id_fase
                  JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
                  WHERE pf.id_proyecto = $proyectoId";
    $resMetricas = $conexion->query($sqlMetricas);
    $totalMetricas = ($resMetricas && $resMetricas->num_rows > 0) ? (int)$resMetricas->fetch_assoc()['total'] : 0;

    $sqlIter = "SELECT COUNT(*) AS total
              FROM iteracion i
              JOIN fase f ON f.id_fase = i.id_fase
              JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
              WHERE pf.id_proyecto = $proyectoId";
    $resIter = $conexion->query($sqlIter);
    $totalIteraciones = ($resIter && $resIter->num_rows > 0) ? (int)$resIter->fetch_assoc()['total'] : 0;

    $sqlHayIter = "SELECT COUNT(*) AS total
                 FROM iteracion i
                 JOIN fase f ON f.id_fase = i.id_fase
                 JOIN proyecto_fase pf ON pf.id_fase = f.id_fase
                 WHERE pf.id_proyecto = $proyectoId";
    $resHayIter = $conexion->query($sqlHayIter);
    $hayIteraciones = ($resHayIter && $resHayIter->num_rows > 0 && (int)$resHayIter->fetch_assoc()['total'] > 0);

    // Core data (igual a dashboard.php)
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
  WHERE pf.id_proyecto = $proyectoId
  ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;
  ";
    $result = $conexion->query($query);

    $iterMap = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $key = trim($r['fase'] . ' ' . $r['numero_iteracion']);
            if (!isset($iterMap[$key])) {
                $iterMap[$key] = [
                    'iteracion' => $key,
                    'fase'      => $r['fase'],
                    'numero'    => $r['numero_iteracion'],
                    'inicio'    => $r['inicio'],
                    'fin'       => $r['fin'],
                    'metricas'   => []
                ];
            }

            $plan = (float)($r['planificado'] ?? 0);
            $ejec = (float)($r['ejecutado'] ?? 0);
            if ($plan == 0 && $ejec == 0) {
                $pct = 100;
                $nota = 'Se cumplió';
            } elseif ($plan == 0 && $ejec > 0) {
                $pct = 100;
                $nota = 'Se planificó 0 (' . $ejec . ')';
            } elseif ($ejec > $plan) {
                $pct = round(($ejec / $plan) * 100);
                $nota = 'Supera planificado (+' . ($ejec - $plan) . ')';
            } else {
                $pct = round(($ejec / $plan) * 100);
                $nota = '';
            }

            $iterMap[$key]['metricas'][] = [
                'id'           => (int)$r['id_metrica'],
                'nombre'       => $r['metrica'],
                'executed'     => $pct,
                'planned'      => $plan,
                'executedReal' => $ejec,
                'unit'         => 'u',
                'min'          => max(0, 100 - (float)$r['umbral']),
                'max'          => 100 + (float)$r['umbral'],
                'nota'         => $nota,
                'extra'        => ($plan > 0 ? max(0, $ejec - $plan) : $ejec)
            ];
        }
    }
    $DATA = array_values($iterMap);

    // Actual / anterior
    $hoy = date('Y-m-d');
    $actualIter = null;
    $anteriorIter = null;
    if (count($DATA) > 0) {
        $actualIndex = null;
        foreach ($DATA as $idx => $it) {
            if ($it['inicio'] <= $hoy && $it['fin'] >= $hoy) {
                $actualIndex = $idx;
                break;
            }
        }
        if ($actualIndex !== null) {
            $actualIter = $DATA[$actualIndex];
        }
        $fechaReferencia = $actualIter ? $actualIter['inicio'] : $hoy;
        $anteriores = array_filter($DATA, fn($it) => $it['fin'] < $fechaReferencia);
        if (count($anteriores) > 0) {
            $anteriorIter = end($anteriores);
        }
    }

    $conexion->close();

    $payload = [
        'data' => $DATA,
        'actual' => $actualIter,
        'anterior' => $anteriorIter,
        'hayIteraciones' => $hayIteraciones,
        'totalMetricas' => $totalMetricas,
        'totalIteraciones' => $totalIteraciones,
        'proyecto' => $proyectoInfo,
    ];
    $version = md5(json_encode($payload));
    $payload['version'] = $version;
    $payload['serverTime'] = date('c');
    return [$payload, $version];
}

function send_event($event, $data)
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) . "\n\n";
    @ob_flush();
    @flush();
}

list($initialPayload, $currentVersion) = build_payload_and_version($proyectoId);
if ($initialPayload === null) {
    send_event('error', ['error' => 'DB connection failed']);
    exit;
}

// Enviar estado inicial
echo "retry: 4000\n\n"; // hint de reconexión del lado del cliente
send_event('update', $initialPayload);

$lastVersion = $currentVersion;
$start = time();
$maxSeconds = 300; // mantener la conexión ~5 minutos; el cliente se reconectará

while (!connection_aborted()) {
    // Chequeo periódico
    usleep(1500000); // 1.5s
    list($payload, $ver) = build_payload_and_version($proyectoId);
    if ($payload === null) {
        send_event('error', ['error' => 'DB connection lost']);
        break;
    }
    if ($ver !== $lastVersion) {
        $lastVersion = $ver;
        send_event('update', $payload);
    } else {
        // heartbeat para mantener viva la conexión en algunos proxies
        echo ": ping\n\n";
        @ob_flush();
        @flush();
    }

    if ((time() - $start) > $maxSeconds) {
        // cerrar para que el cliente reconecte periódicamente y evitar procesos eternos
        break;
    }
}

exit;
