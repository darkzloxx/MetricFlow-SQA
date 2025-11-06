<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
// Acceso: Admin/SuperAdmin SIEMPRE. De lo contrario, se requiere permiso de gestión de modelo.
if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: proyectos.php?msg=' . urlencode('Acceso restringido: requiere SUPERADMIN/ADMIN o permiso Gestión de Modelo de Calidad.') . '&type=danger');
    exit;
}

// Proyectos accesibles
$proyectos = [];
if (ControlAcceso::esAdminGlobal()) {
    $sqlP = "SELECT id_proyecto, nombre FROM proyecto ORDER BY nombre";
} else {
    $uid = (int)$_SESSION['usuario']->id;
    $sqlP = "SELECT p.id_proyecto, p.nombre
             FROM usuario_proyecto up
             JOIN proyecto p ON p.id_proyecto = up.id_proyecto
             WHERE up.id_usuario = {$uid}
             ORDER BY p.nombre";
}
$rsP = BDConexion::getInstancia()->query($sqlP);
if ($rsP) { $proyectos = $rsP->fetch_all(MYSQLI_ASSOC); }

// Proyecto seleccionado
$idProyectoSel = isset($_GET['id_proyecto']) ? (int)$_GET['id_proyecto'] : 0;
if ($idProyectoSel <= 0 && !empty($proyectos)) {
    $idProyectoSel = (int)$proyectos[0]['id_proyecto'];
}

// Validar pertenencia si no es admin
if ($idProyectoSel > 0 && !ControlAcceso::esAdminGlobal() && !ControlAcceso::usuarioPerteneceAProyecto($idProyectoSel)) {
    header('Location: modelos.php?msg=' . urlencode('Proyecto no autorizado.') . '&type=danger');
    exit;
}

// Modelo actual del proyecto seleccionado
$modeloActual = 0; $nombreModeloActual = '';
if ($idProyectoSel > 0) {
    $sqlM = "SELECT p.id_modelo, m.nombre AS nombre
             FROM proyecto p
             LEFT JOIN modelo_calidad m ON m.id_modelo = p.id_modelo
             WHERE p.id_proyecto = {$idProyectoSel}";
    $rsM = BDConexion::getInstancia()->query($sqlM);
    if ($rsM && $rsM->num_rows) {
        $r = $rsM->fetch_assoc();
        $modeloActual = (int)($r['id_modelo'] ?? 0);
        $nombreModeloActual = (string)($r['nombre'] ?? '');
    }
}

// Todos los modelos disponibles
$modelos = [];
$rsMods = BDConexion::getInstancia()->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre");
if ($rsMods) { $modelos = $rsMods->fetch_all(MYSQLI_ASSOC); }
?>

<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>        
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modelos</title>
    <style>
      /* Alinear estilos con usuarios.php */
      .btn-outline-secondary {
        border-color: #dee2e6;
        color: #495057;
        background-color: #fff;
      }
      .btn-outline-secondary:hover {
        background-color: #f8f9fa;
        color: #212529;
      }
      .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }
    </style>
    </head>
    <body>

        <?php include_once '../gui/navbar.php'; ?>

    <div class="container">

      <div class="mb-3">
        <a id="btnVolver" href="proyectos.php" class="btn btn-outline-secondary">
          <span class="oi oi-arrow-left mr-1"></span> Volver
        </a>
      </div>

            <div class="card mt-3">
        <div class="card-header">
          <h3>Modelos</h3>
        </div>
                <div class="card-body">
          <!-- Contenedor de alertas dinámicas -->
          <div id="alertContainer">
            <?php if (isset($_GET['msg'])): ?>
              <div class="alert alert-<?= ($_GET['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <script>
                $('html, body').animate({ scrollTop: 0 }, 'fast');
                setTimeout(() => $('.alert').alert('close'), 3500);
              </script>
            <?php endif; ?>
          </div>

          <p>
            <a href="modelo.nuevo.php">
              <button type="button" class="btn btn-success">
                <span class="oi oi-plus"></span> Nuevo Modelo
              </button>
            </a>
          </p>
          <div class="form-group">
                        <label for="id_proyecto">Proyecto</label>
                        <select id="id_proyecto" class="form-control" onchange="location.href='modelos.php?id_proyecto='+this.value;">
                          <?php foreach ($proyectos as $p) { $sel = ((int)$p['id_proyecto'] === (int)$idProyectoSel) ? 'selected' : ''; ?>
                            <option value="<?= (int)$p['id_proyecto'] ?>" <?= $sel ?>><?= htmlspecialchars($p['nombre']) ?></option>
                          <?php } ?>
                        </select>
                        <?php if ($idProyectoSel > 0) { ?>
                          <small class="form-text text-muted mt-1">
                            Modelo actual: <b><?= $nombreModeloActual ? htmlspecialchars($nombreModeloActual) : '— Sin asignar —' ?></b>
                          </small>
                        <?php } ?>
                    </div>

                    <?php if ($idProyectoSel <= 0) { ?>
                        <div class="alert alert-warning mb-0">Seleccione un proyecto para gestionar su modelo de calidad.</div>
                    <?php } else if (empty($modelos)) { ?>
                        <div class="alert alert-info mb-0">No hay modelos de calidad cargados.</div>
                    <?php } else { ?>

          <table class="table table-hover table-sm">
                        <thead class="table-info">
                            <tr>
                                <th>Modelo</th>
                                <th>Descripción</th>
                                <th class="text-center">Estado</th>
                                <th style="width:220px">Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($modelos as $m) { 
                            $isSel = ((int)$m['id_modelo'] === (int)$modeloActual);
                        ?>
                          <tr>
                            <td><?= htmlspecialchars($m['nombre']) ?></td>
                            <td><?= htmlspecialchars($m['descripcion']) ?></td>
                            <td class="text-center">
                                <?php if ($isSel) { ?>
                                  <span class="badge badge-success">Seleccionado</span>
                                <?php } else { ?>
                                  <span class="badge badge-secondary">Disponible</span>
                                <?php } ?>
                            </td>
                            <td>
                              <div class="btn-group" role="group" aria-label="acciones modelo">
                <a title="Ver métricas del proyecto" href="<?= $isSel ? ('modelo.ver.php?id='.(int)$idProyectoSel) : 'javascript:void(0);' ?>" 
                   class="btn btn-outline-info btn-icon <?= $isSel ? '' : 'disabled' ?>" aria-label="Ver métricas">
                  <span class="oi oi-crop" aria-hidden="true"></span>
                                </a>

                      <a title="Configurar métricas (CU07)" href="<?= $isSel ? ('pantalla.alumnos.metrica.php?id='.(int)$idProyectoSel) : 'javascript:void(0);' ?>"
                        class="btn btn-outline-warning btn-icon <?= $isSel ? '' : 'disabled' ?>" aria-label="Configurar modelo">
                                    <span class="oi oi-wrench" aria-hidden="true"></span>
                                </a>

                                <form action="modelo.crear.procesar.php" method="post" class="d-inline" onsubmit="return confirmarCambio(<?= (int)$modeloActual ?>, <?= (int)$m['id_modelo'] ?>);">
                                  <input type="hidden" name="proyecto" value="<?= (int)$idProyectoSel ?>" />
                                  <input type="hidden" name="modelo" value="<?= (int)$m['id_modelo'] ?>" />
                                  <button type="submit" class="btn btn-outline-success btn-icon" title="<?= $modeloActual ? 'Cambiar modelo' : 'Seleccionar modelo' ?>" aria-label="<?= $modeloActual ? 'Cambiar modelo' : 'Seleccionar modelo' ?>" <?= $isSel ? 'disabled' : '' ?>>
                                    <span class="oi oi-check" aria-hidden="true"></span>
                                  </button>
                                </form>
                              </div>
                            </td>
                          </tr>
                        <?php } ?>
                        </tbody>
                    </table>

                    <?php } ?>
                </div>
            </div>
        </div>

        <script>
        function confirmarCambio(actual, nuevo) {
          if (!actual || actual === nuevo) return true;
          return confirm('Está a punto de cambiar el modelo del proyecto. Tenga en cuenta que no podrá modificarse si ya existen métricas planificadas. ¿Desea continuar?');
        }
        </script>
        <script>
          (function() {
            if (!window.history || !window.history.replaceState) return;
            const params = new URLSearchParams(window.location.search);
            if (!params.has('msg')) return;
            params.delete('msg');
            params.delete('type');
            const newSearch = params.toString();
            const newUrl = window.location.pathname + (newSearch ? ('?' + newSearch) : '');
            window.history.replaceState({}, document.title, newUrl);
          })();
        </script>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>

