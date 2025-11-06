<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdminGlobal = ControlAcceso::esAdminGlobal();

// =====================================================
// OBTENER PROYECTOS ASIGNADOS (o todos si es admin/superadmin)
// =====================================================
if ($esAdminGlobal || $esSuperAdmin) {
    $sql = "SELECT id_proyecto, nombre, id_modelo FROM proyecto ORDER BY nombre";
    $stmt = $cn->query($sql);
} else {
    $sql = "SELECT p.id_proyecto, p.nombre, p.id_modelo 
            FROM usuario_proyecto up
            JOIN proyecto p ON p.id_proyecto = up.id_proyecto
            WHERE up.id_usuario = ?
            ORDER BY p.nombre";
    $stmt = $cn->prepare($sql);
    $stmt->bind_param('i', $usr->id);
    $stmt->execute();
    $stmt = $stmt->get_result();
}
$proyectos = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];

// =====================================================
// OBTENER MODELOS DISPONIBLES
// =====================================================
$modelos = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
?>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css">
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css">
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Modelos</title>
    <style>
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
<?php include_once '../gui/navbar.php'; ?>

<div class="container mt-4">
    <!-- 🔔 ALERTAS -->
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
                setTimeout(() => $('.alert').alert('close'), 3000);
            </script>
        <?php endif; ?>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3>Modelos de Calidad</h3>
        </div>
        <div class="card-body">
                        <!-- Botón Nuevo Modelo -->
                        <p>
                                <?php if ($esAdminGlobal || $esSuperAdmin): ?>
                                    <form action="modelo.nuevo.predeterminado.procesar.php" method="post" class="d-inline">
                                        <button type="submit" class="btn btn-success" title="Crear modelo predeterminado (global)">
                                            <span class="oi oi-plus"></span> Nuevo Modelo (predeterminado)
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="modelo.nuevo.php" class="btn btn-success" title="Crear modelo desde cero">
                                        <span class="oi oi-plus"></span> Nuevo Modelo
                                    </a>
                                <?php endif; ?>
                        </p>

            <!-- Tabla de proyectos -->
            <?php if (empty($proyectos)): ?>
                <div class="card my-4 text-center" style="border:1px dashed rgba(23,162,184,0.15); background:rgba(23,162,184,0.03);">
                    <div class="card-body p-4">
                        <i class="oi oi-info mb-2" style="font-size:2rem; color:#17a2b8;"></i>
                        <h5 class="text-info font-weight-bold mb-2">No tenés proyectos asignados</h5>
                        <p class="text-muted mb-3">Aún no fuiste asignado a ningún proyecto. Si creés que esto es un error, contactá a un administrador.</p>
                    </div>
                </div>
            <?php else: ?>
                <table class="table table-hover table-sm">
                    <thead class="table-info">
                        <tr>
                            <th>Proyecto</th>
                            <th>Modelo Actual</th>
                            <th>Tipo</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($proyectos as $p): 
                        $modeloSel = (int)($p['id_modelo'] ?? 0);
                        $modeloNombre = '—';
                        $tipo = '—';
                        if ($modeloSel) {
                            $r = $cn->query("SELECT nombre, descripcion FROM modelo_calidad WHERE id_modelo=" . $modeloSel . " LIMIT 1")->fetch_assoc();
                            $modeloNombre = $r ? $r['nombre'] : '—';
                            // Si en el futuro hay una columna/flag para predeterminados, puede usarse aquí.
                            $tipo = 'Predeterminado';
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nombre']); ?></td>
                            <td>
                              <?php if ($esAdminGlobal || $esSuperAdmin): ?>
                                <form action="modelo.crear.procesar.php" method="post" class="m-0 p-0">
                                  <input type="hidden" name="proyecto" value="<?= (int)$p['id_proyecto']; ?>" />
                                  <select name="modelo" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="">— Seleccionar —</option>
                                    <?php foreach ($modelos as $m): $sel = ((int)$m['id_modelo'] === $modeloSel) ? 'selected' : ''; ?>
                                      <option value="<?= (int)$m['id_modelo']; ?>" <?= $sel ?>><?= htmlspecialchars($m['nombre']); ?></option>
                                    <?php endforeach; ?>
                                  </select>
                                </form>
                              <?php else: ?>
                                <?= htmlspecialchars($modeloNombre); ?>
                              <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $tipo === 'Predeterminado' ? 'secondary' : 'info'; ?>"><?= $tipo; ?></span></td>
                            <td>
                                <!-- Ver métricas -->
                                <a title="Ver métricas del modelo" href="modelo.ver.php?id=<?= (int)$p['id_proyecto']; ?>" class="btn btn-outline-info btn-icon">
                                    <span class="oi oi-graph"></span>
                                </a>

                                <!-- Configurar -->
                                <a title="Configurar métricas" href="pantalla.alumnos.metrica.php?id=<?= (int)$p['id_proyecto']; ?>" class="btn btn-outline-warning btn-icon">
                                    <span class="oi oi-wrench"></span>
                                </a>

                                <!-- Acciones extra opcionales podrían agregarse aquí si hay endpoints disponibles -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../gui/footer.php'; ?>
</body>
</html>
