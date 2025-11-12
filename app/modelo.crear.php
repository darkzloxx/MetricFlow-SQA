<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

// ==================================================
// 🔹 Cargar modelos disponibles según el rol
// ==================================================
if ($esAdmin) {
    // Admin → siempre puede crear métricas base
    $sqlModelos = "SELECT id_modelo AS id, nombre, 'base' AS tipo FROM modelo_calidad ORDER BY nombre";
} else {
    // Gerente/Líder → solo proyectos con modelo personalizado asignado
    $sqlModelos = "
        SELECT pmc.id_proyecto_modelo AS id, pmc.nombre, p.nombre AS proyecto
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
        WHERE up.id_usuario = {$usr->id}
          AND p.id_modelo_personalizado IS NOT NULL
          AND p.id_modelo_personalizado = pmc.id_proyecto_modelo
        ORDER BY p.nombre
    ";
}

$rsModelos = $cn->query($sqlModelos);
$modelos = $rsModelos ? $rsModelos->fetch_all(MYSQLI_ASSOC) : [];
?>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Métrica</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css">
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css">
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
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

<div class="container mt-4">
    <div class="mb-3">
        <a href="metricas.php" class="btn btn-outline-secondary">
            <span class="oi oi-arrow-left mr-1"></span> Volver
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0">Crear Nueva Métrica</h3>
            <p class="mb-0 mt-1 text-muted">
                <?= $esAdmin
                    ? 'Solo puede crear métricas base para modelos globales.'
                    : 'Puede crear métricas personalizadas únicamente en proyectos con modelo personalizado.'; ?>
            </p>
        </div>

        <div class="card-body">
            <?php if (!$esAdmin && empty($modelos)): ?>
                <div class="alert alert-info mb-0">
                    <span class="oi oi-info mr-2"></span>
                    No tiene proyectos con un modelo personalizado asignado.<br>
                    Primero debe crear o asignar un modelo personalizado antes de poder registrar nuevas métricas.
                    <br><br>
                    <a href="modelos.php" class="btn btn-outline-primary btn-sm">
                        <span class="oi oi-cog"></span> Ir a gestión de modelos
                    </a>
                </div>
            <?php else: ?>
                <form action="metrica.crear.procesar.php" method="post" id="formMetrica">
                    <!-- 🔹 Selección de modelo -->
                    <div class="form-group">
                        <label for="modelo">Modelo</label>
                        <?php if (count($modelos) === 1): ?>
                            <?php $m = $modelos[0]; ?>
                            <p class="form-control-plaintext font-weight-bold">
                                <?= htmlspecialchars($m['nombre']); ?>
                                <?php if (!$esAdmin): ?>
                                    <small class="text-muted">(Proyecto: <?= htmlspecialchars($m['proyecto']); ?>)</small>
                                <?php endif; ?>
                            </p>
                            <input type="hidden" name="modelo" value="<?= (int)$m['id']; ?>">
                        <?php else: ?>
                            <select name="modelo" id="modelo" class="form-control" required>
                                <option value="">Seleccione un modelo...</option>
                                <?php foreach ($modelos as $m): ?>
                                    <option value="<?= (int)$m['id']; ?>">
                                        <?= $esAdmin
                                            ? htmlspecialchars($m['nombre'])
                                            : htmlspecialchars($m['proyecto'] . ' — ' . $m['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- 🔹 Campos de la métrica -->
                    <div class="form-group">
                        <label for="nombre">Nombre de la métrica</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required>
                        <div class="invalid-feedback d-block text-danger mt-1" id="errorNombre"></div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea name="descripcion" id="descripcion" rows="3" class="form-control" required></textarea>
                        <div class="invalid-feedback d-block text-danger mt-1" id="errorDesc"></div>
                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="metricas.php" class="btn btn-outline-secondary">
                            <span class="oi oi-x"></span> Cancelar
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ================================
// 🔹 Validación front-end estándar
// ================================
$(function() {
    const regex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/\\():]+$/;

    $('#nombre').on('input', function() {
        const v = $(this).val().trim(), $e = $('#errorNombre');
        if (v === '') {
            $e.text('El nombre de la métrica es obligatorio.');
            $(this).addClass('is-invalid');
        } else if (!regex.test(v)) {
            $e.text('Solo se permiten letras, números y los símbolos . - _ / \\ ( ) :');
            $(this).addClass('is-invalid');
        } else {
            $e.text('');
            $(this).removeClass('is-invalid');
        }
    });

    $('#descripcion').on('input', function() {
        const v = $(this).val().trim(), $e = $('#errorDesc');
        if (v === '') {
            $e.text('La descripción es obligatoria.');
            $(this).addClass('is-invalid');
        } else if (!regex.test(v)) {
            $e.text('Solo se permiten letras, números y los símbolos . - _ / \\ ( ) :');
            $(this).addClass('is-invalid');
        } else {
            $e.text('');
            $(this).removeClass('is-invalid');
        }
    });

    $('#formMetrica').on('submit', function(e) {
        const nombre = $('#nombre').val().trim();
        const desc = $('#descripcion').val().trim();
        let valido = true;

        if (nombre === '' || !regex.test(nombre)) {
            $('#nombre').addClass('is-invalid');
            $('#errorNombre').text('El nombre es obligatorio o contiene caracteres inválidos.');
            valido = false;
        }
        if (desc === '' || !regex.test(desc)) {
            $('#descripcion').addClass('is-invalid');
            $('#errorDesc').text('La descripción es obligatoria o contiene caracteres inválidos.');
            valido = false;
        }

        if (!valido) {
            e.preventDefault();
            $('html, body').animate({ scrollTop: 0 }, 'fast');
        }
    });
});
</script>

<?php include_once '../gui/footer.php'; ?>
</body>
</html>
