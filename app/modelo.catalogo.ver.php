<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Solo Admin o SuperAdmin pueden ver el catálogo completo de modelos
if (!ControlAcceso::esAdminGlobal()) {
  header('Location: modelos.php?msg=' . urlencode('Acceso restringido: solo administradores.') . '&type=danger');
  exit;
}

$idModelo = isset($_GET['id_modelo']) ? (int)$_GET['id_modelo'] : 0;
if ($idModelo <= 0) {
  header('Location: modelos.php?msg=' . urlencode('Modelo inválido.') . '&type=danger');
  exit;
}

$cn = BDConexion::getInstancia();
$info = null;
if ($rs = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad WHERE id_modelo = {$idModelo} LIMIT 1")) {
  $info = $rs->fetch_assoc();
}
if (!$info) {
  header('Location: modelos.php?msg=' . urlencode('El modelo no existe.') . '&type=danger');
  exit;
}

$metricas = [];
if ($rsM = $cn->query("SELECT m.id_metrica, m.nombre, m.descripcion
                        FROM metrica_modelo_calidad mmc
                        JOIN metrica m ON m.id_metrica = mmc.id_metrica
                        WHERE mmc.id_modelo = {$idModelo}
                        ORDER BY m.nombre")) {
  $metricas = $rsM->fetch_all(MYSQLI_ASSOC);
}
?>
<html>

<head>
  <meta charset="UTF-8" />
  <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
  <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
  <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
  <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
  <title><?= Constantes::NOMBRE_SISTEMA; ?> - Ver Modelo</title>
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

    <div class="card">
      <div class="card-header">
        <h3><?= htmlspecialchars($info['nombre']); ?></h3>
        <?php if (!empty($info['descripcion'])): ?>
          <div class="text-muted"><?= htmlspecialchars($info['descripcion']); ?></div>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <table class="table table-hover table-sm">
          <tr class="table-info">
            <th>Métrica</th>
            <th>Descripción</th>
          </tr>
          <?php if (empty($metricas)): ?>
            <tr>
              <td colspan="2" class="text-muted">El modelo no tiene métricas asociadas.</td>
            </tr>
            <?php else: foreach ($metricas as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['nombre']); ?></td>
                <td><?= htmlspecialchars($m['descripcion'] ?? ''); ?></td>
              </tr>
          <?php endforeach;
          endif; ?>
        </table>
      </div>
    </div>
  </div>
  <?php include_once '../gui/footer.php'; ?>
</body>

</html>