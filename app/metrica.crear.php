<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

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
// 🔹 Cargar modelos disponibles según el rol
// ===========================
$modelos = [];
if ($esAdmin || $esSuperAdmin) {
    $sql = "SELECT id_modelo AS id, nombre, descripcion, 'global' AS tipo
            FROM modelo_calidad
            ORDER BY nombre";
} else {
    $sql = "SELECT pmc.id_proyecto_modelo AS id, pmc.nombre, p.nombre AS proyecto
            FROM proyecto_modelo_calidad pmc
            JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
            JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
            WHERE up.id_usuario = {$usr->id}
              AND p.id_modelo_personalizado = pmc.id_proyecto_modelo
            ORDER BY p.nombre";
}
$rs = $cn->query($sql);
$modelos = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
?>

<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Métrica</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <style>
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background: #fff;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            color: #212529;
        }
    </style>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>

    <div class="container mt-4">
        <div class="mb-3">
            <a href="metricas.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-<?= ($_GET['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?= $_GET['msg']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <script>
                $('html, body').animate({
                    scrollTop: 0
                }, 'fast');
                setTimeout(() => $('.alert').alert('close'), 4000);
            </script>
        <?php endif; ?>

        <?php if (empty($modelos)): ?>
            <!-- 🔹 Mostrar mensaje si no hay modelos personalizados -->
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <p class="mb-2 text-secondary">
                        <span class="oi oi-info mr-2 text-muted"></span>
                        No tenés modelos personalizados asignados a tus proyectos.
                    </p>
                    <p class="text-muted mb-0">
                        Para crear y asignar nuevas métricas, necesitás contar con un <strong>modelo de calidad personalizado</strong>, ya sea creado a partir de uno existente o definido desde cero.
                        Si deseás modificar uno existente, accedé a la sección <a href="modelos.php">Modelos de Calidad</a>.
                    </p>

                </div>
            </div>
        <?php else: ?>

            <!-- 🔹 Formulario normal si hay modelos -->
            <form action="metrica.crear.procesar.php" method="post">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="mb-0">Crear Métrica <?= ($esAdmin || $esSuperAdmin) ? 'Base' : 'Personalizada'; ?></h3>
                        <p class="text-muted mb-1 mt-1">
                            <?= ($esAdmin || $esSuperAdmin)
                                ? 'Las métricas base estarán disponibles para todos los modelos globales.'
                                : 'Las métricas personalizadas se asociarán únicamente a tus proyectos.'; ?>
                        </p>
                        <hr class="my-2">
                        <p class="mb-0">
                            Complete los campos a continuación. Luego, presione el botón <b>Confirmar</b>.<br>
                            Si desea cancelar, presione el botón <b>Cancelar</b>.
                        </p>
                    </div>

                    <div class="card-body">
                        <h5 class="mb-3">Propiedades de la Métrica</h5>

                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                placeholder="Ejemplo: Revisiones de código"
                                value="<?= htmlspecialchars($formData['nombre'] ?? '') ?>">
                            <small class="form-text text-muted">
                                Puede usar letras, números y los símbolos <b>. - _ / \ ( ) :</b>. No puede quedar vacío.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea name="descripcion" id="descripcion" rows="3" class="form-control"
                                placeholder="Describa brevemente la métrica"><?= htmlspecialchars($formData['descripcion'] ?? '') ?></textarea>
                            <small class="form-text text-muted">
                                Puede usar letras, números y los símbolos <b>. - _ / \ ( ) :</b>. No puede quedar vacío.
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Asociar a modelo<?= ($esAdmin || $esSuperAdmin) ? ' global' : ' de proyecto'; ?></label>
                            <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                                <?php foreach ($modelos as $m): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            id="modelo<?= (int)$m['id']; ?>"
                                            name="modelos[]" value="<?= (int)$m['id']; ?>"
                                            <?= in_array($m['id'], $formData['modelos'] ?? []) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="modelo<?= (int)$m['id']; ?>">
                                            <strong><?= htmlspecialchars($m['nombre']); ?></strong>
                                            <?php if (!$esAdmin): ?>
                                                <small class="text-muted">(Proyecto: <?= htmlspecialchars($m['proyecto']); ?>)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="form-text text-muted mt-2">
                                Seleccione uno o más modelos para asociar la métrica.
                            </small>
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
        <?php endif; ?>
    </div>

    <?php include_once '../gui/footer.php'; ?>
</body>

</html>