<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();

$esSuperAdmin = ControlAcceso::esSuperAdminGlobal();
$esAdmin = ControlAcceso::esAdminGlobal();
$tienePermGestionMetricas = ControlAcceso::verificaPermiso(PermisosSistema::GESTION_METRICAS);

if (!($esAdmin || $esSuperAdmin || $tienePermGestionMetricas)) {
    header('Location: metricas.php?msg=' . urlencode('Acceso denegado.') . '&type=danger');
    exit;
}

// ===========================
// 🔹 Cargar modelos disponibles (solo los activos por proyecto)
// ===========================
$modelos = [];
if ($esAdmin || $esSuperAdmin) {
    $sql = "SELECT id_modelo AS id, nombre, descripcion, 'global' AS tipo
            FROM modelo_calidad
            ORDER BY nombre;";
} else {
    $sql = "
        SELECT 
            pmc.id_proyecto_modelo AS id,
            pmc.nombre,
            pmc.descripcion,
            CONCAT('Proyecto: ', p.nombre) AS tipo
        FROM proyecto p
        JOIN proyecto_modelo_calidad pmc 
            ON p.id_modelo_personalizado = pmc.id_proyecto_modelo
        JOIN usuario_proyecto up 
            ON up.id_proyecto = p.id_proyecto
        WHERE up.id_usuario = {$usr->id}
        ORDER BY p.nombre;";
}
$rs = $cn->query($sql);
$modelos = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
?>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Nueva Métrica</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
</head>

<body>
<?php include_once '../gui/navbar.php'; ?>
<div class="container mt-4">
    <form action="metrica.crear.procesar.php" method="post" id="formNuevaMetrica">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="mb-0">Crear Métrica <?= ($esAdmin || $esSuperAdmin) ? 'Base' : 'Personalizada'; ?></h3>
                <p class="mb-0 mt-1 text-muted">
                    <?= ($esAdmin || $esSuperAdmin)
                        ? 'Como administrador, la métrica creada será de tipo <b>base</b> y podrá ser usada por todos los modelos globales del sistema.'
                        : 'Como líder o gerente de proyecto, la métrica creada será de tipo <b>personalizada</b> y se asociará al modelo activo de tu proyecto.'; ?>
                </p>
            </div>

            <div class="card-body">
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" name="nombre" id="nombre" class="form-control"
                        placeholder="Ejemplo: Revisiones de código"
                        pattern="[a-zA-Z0-9ÁÉÍÓÚáéíóúüÜñÑ\s_\-()\/.,]+" required maxlength="120">
                    <small class="text-muted">
                        Se permiten letras, números, espacios y los caracteres: _ - ( ) / . ,
                    </small>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea name="descripcion" id="descripcion" rows="3" class="form-control"
                        placeholder="Describa brevemente el propósito de la métrica"
                        pattern="[a-zA-Z0-9ÁÉÍÓÚáéíóúüÜñÑ\s_\-()\/.,]+" required></textarea>
                </div>

                <div class="form-group">
                    <label>Asociar a modelo<?= ($esAdmin || $esSuperAdmin) ? ' global' : ' de proyecto'; ?></label>
                    <?php if (empty($modelos)): ?>
                        <div class="text-muted mt-1">
                            <?= ($esAdmin || $esSuperAdmin)
                                ? 'No hay modelos globales disponibles.'
                                : 'No tenés modelos personalizados asignados a tus proyectos.'; ?>
                        </div>
                    <?php else: ?>
                        <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                            <?php foreach ($modelos as $m): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="modelo<?= (int)$m['id']; ?>"
                                           name="modelos[]" value="<?= (int)$m['id']; ?>">
                                    <label class="form-check-label" for="modelo<?= (int)$m['id']; ?>">
                                        <strong><?= htmlspecialchars($m['nombre']); ?></strong>
                                        <small class="text-muted">(<?= htmlspecialchars($m['tipo']); ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-text text-muted mt-2">
                            Debe seleccionar al menos un modelo para asociar la métrica.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-footer text-right">
                <button type="submit" class="btn btn-success">
                    <span class="oi oi-check"></span> Confirmar
                </button>
                <a href="metricas.php" class="btn btn-outline-secondary">
                    <span class="oi oi-x"></span> Cancelar
                </a>
            </div>
        </div>
    </form>
</div>

<script>
$('#formNuevaMetrica').on('submit', function(e) {
    const nombre = $('#nombre').val().trim();
    const descripcion = $('#descripcion').val().trim();
    const seleccionados = $('input[name="modelos[]"]:checked').length;

    const regex = /^[a-zA-Z0-9ÁÉÍÓÚáéíóúüÜñÑ\s_\-()\/.,]+$/;

    if (!regex.test(nombre) || !regex.test(descripcion)) {
        alert('❌ Solo se permiten letras, números, espacios y los caracteres _ - ( ) / . ,');
        e.preventDefault();
        return false;
    }

    if (nombre.length === 0 || descripcion.length === 0) {
        alert('❌ Complete todos los campos.');
        e.preventDefault();
        return false;
    }

    if (seleccionados === 0) {
        alert('⚠️ Debe seleccionar al menos un modelo.');
        e.preventDefault();
        return false;
    }
});
</script>
<?php include_once '../gui/footer.php'; ?>
</body>
</html>
