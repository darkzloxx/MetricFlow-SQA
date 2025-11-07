<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
include_once '../modelo/ColeccionRoles.php';

// Acceso: Admin/SuperAdmin SIEMPRE; caso contrario requiere permiso de Gestión de Métricas
ControlAcceso::verificaLogin();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();
if (!$esAdmin && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

// Cargar modelos según el rol
$cn = BDConexion::getInstancia();
$modelosGlobales = [];
$modelosProyecto = [];
if ($esAdmin) {
    // Admin/SuperAdmin: todos los modelos globales
    if ($rs = $cn->query("SELECT id_modelo, nombre, descripcion FROM modelo_calidad ORDER BY nombre")) {
        $modelosGlobales = $rs->fetch_all(MYSQLI_ASSOC);
    }
} else {
    // No admin: sólo modelos personalizados por proyecto a los que pertenece el usuario
    $usr = ControlAcceso::usuarioActual();
    $sql = "SELECT pmc.id_proyecto_modelo, pmc.nombre, pmc.descripcion, p.nombre AS proyecto
            FROM proyecto_modelo_calidad pmc
            JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
            JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
            WHERE up.id_usuario = ? AND IFNULL(pmc.es_personalizado,1) = 1
            ORDER BY p.nombre, pmc.nombre";
    if ($stmt = $cn->prepare($sql)) {
        $uid = (int)$usr->id;
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $modelosProyecto = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

$Roles = new ColeccionRoles();
?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Metrica</title>
    </head>
    <body>
        <?php include_once '../gui/navbar.php'; ?>
        <div class="container">
            <form action="metrica.crear.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Crear Metrica</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <h4>Propiedades</h4>
                        <div class="form-group">
                            <label for="inputNombre">Nombre</label>
                            <input type="text" name="nombre" class="form-control" pattern="[a-zA-Z\s]+" id="inputNombre" placeholder="Ingrese el nombre de la Metrica" required="">
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Descripcion</label>
                            <br>
                            <input type="text" name="descripcion" class="form-control" pattern="[a-zA-Z\s]+" id="inputDescripcion" placeholder="Ingrese una breve Descripcion" required="">
                        </div>
                        <div class="form-group">
                            <label>Asociar a modelo</label>
                            <br>
                            <?php if ($esAdmin): ?>
                                <?php if (empty($modelosGlobales)): ?>
                                    <div class="text-muted">No hay modelos disponibles.</div>
                                <?php else: foreach ($modelosGlobales as $m): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="<?= (int)$m['id_modelo']; ?>" id="modg<?= (int)$m['id_modelo']; ?>" name="modelos_globales[]" />
                                        <label class="form-check-label" for="modg<?= (int)$m['id_modelo']; ?>">
                                            <?= htmlspecialchars($m['nombre']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; endif; ?>
                            <?php else: ?>
                                <?php if (empty($modelosProyecto)): ?>
                                    <div class="text-muted">No tenés modelos personalizados de tus proyectos.</div>
                                <?php else: foreach ($modelosProyecto as $mp): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="<?= (int)$mp['id_proyecto_modelo']; ?>" id="modp<?= (int)$mp['id_proyecto_modelo']; ?>" name="modelos_proyecto[]" />
                                        <label class="form-check-label" for="modp<?= (int)$mp['id_proyecto_modelo']; ?>">
                                            <?= htmlspecialchars($mp['proyecto'] . ' — ' . $mp['nombre']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; endif; ?>
                                <small class="form-text text-muted">Solo se permiten modelos personalizados (no predeterminados) de proyectos a los que pertenecés.</small>
                            <?php endif; ?>
                        </div>
                        <hr />
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="metricas.php">
                            <button type="button" class="btn btn-outline-danger">
                                <span class="oi oi-x"></span> Cancelar
                            </button>
                        </a>
                    </div>
                </div>
            </form>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>
