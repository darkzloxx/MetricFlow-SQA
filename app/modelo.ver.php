<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Acceso: Admin/SuperAdmin SIEMPRE. Caso contrario: requiere permiso Gestión de Modelo y pertenecer al proyecto
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: modelos.php?msg=' . urlencode('Proyecto inválido.') . '&type=danger');
    exit;
}

if (!ControlAcceso::esAdminGlobal()) {
    if (!ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD) || !ControlAcceso::usuarioPerteneceAProyecto($id)) {
        header('Location: modelos.php?msg=' . urlencode('Acceso restringido: requiere permiso y pertenecer al proyecto.') . '&type=danger');
        exit;
    }
}

$cn = BDConexion::getInstancia();

// ============================
// 🔍 Obtener modelo del proyecto
// ============================
$qModelo = "
    SELECT 
        COALESCE(mg.nombre, mp.nombre) AS nombre_modelo,
        CASE WHEN mg.id_modelo IS NOT NULL THEN 'global' ELSE 'personalizado' END AS tipo_modelo,
        COALESCE(mg.id_modelo, mp.id_proyecto_modelo) AS id_modelo
    FROM proyecto p
    LEFT JOIN modelo_calidad mg ON mg.id_modelo = p.id_modelo_global
    LEFT JOIN proyecto_modelo_calidad mp ON mp.id_proyecto_modelo = p.id_modelo_personalizado
    WHERE p.id_proyecto = {$id}
    LIMIT 1
";

$rsModelo = $cn->query($qModelo);
$modelo = $rsModelo ? $rsModelo->fetch_assoc() : null;
$nombreModelo = $modelo['nombre_modelo'] ?? null;
$tipoModelo = $modelo['tipo_modelo'] ?? null;
$idModelo = (int)($modelo['id_modelo'] ?? 0);
?>

<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Propiedades del Modelo</title>
    <style>
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
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a id="btnVolver" href="modelos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <?php if (!$modelo): ?>
            <div class="alert alert-warning">
                <span class="oi oi-warning"></span> No se encontró modelo asociado al proyecto.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3>
                        <?= 'Modelo asignado: ' . htmlspecialchars($nombreModelo) . ' (' . ucfirst($tipoModelo) . ')'; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php
                    // =============================
                    // 🔒 Bloqueo: si hay métricas planificadas
                    // =============================
                    $sqlPlan = "
                        SELECT 1
                        FROM metrica_iteracion mi
                        JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
                        WHERE i.id_proyecto = {$id}
                        AND mi.valor_planificado IS NOT NULL
                        LIMIT 1
                    ";
                    $rsPlan = $cn->query($sqlPlan);
                    $bloqueado = ($rsPlan && $rsPlan->num_rows > 0);

                    // =============================
                    // 📋 Cargar métricas del modelo
                    // =============================
                    if ($tipoModelo === 'global') {
                        $sqlDet = "
                            SELECT m.nombre AS metrica, m.descripcion AS descripcion
                            FROM metrica_modelo_calidad mmc
                            JOIN metrica m ON mmc.id_metrica = m.id_metrica
                            WHERE mmc.id_modelo = {$idModelo}
                            ORDER BY m.nombre
                        ";
                    } else {
                        $sqlDet = "
                            SELECT m.nombre AS metrica, m.descripcion AS descripcion
                            FROM metrica_proyecto_modelo mpm
                            JOIN metrica m ON mpm.id_metrica = m.id_metrica
                            WHERE mpm.id_proyecto_modelo = {$idModelo}
                            ORDER BY m.nombre
                        ";
                    }

                    $rsDet = $cn->query($sqlDet);
                    ?>
                    <table class="table table-hover table-sm">
                        <thead class="table-info">
                            <tr>
                                <th>Métrica</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tiene = false;
                            if ($rsDet && $rsDet->num_rows > 0):
                                while ($row = $rsDet->fetch_assoc()):
                                    $tiene = true; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['metrica']); ?></td>
                                        <td><?= htmlspecialchars($row['descripcion']); ?></td>
                                    </tr>
                                <?php endwhile;
                            endif;

                            if (!$tiene): ?>
                                <tr><td colspan="2" class="text-muted">El modelo no tiene métricas asociadas para el proyecto.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include_once '../gui/footer.php'; ?>
</body>

</html>
