<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$usr = ControlAcceso::usuarioActual();
$cn = BDConexion::getInstancia();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: metricas.php?msg=' . urlencode('Métrica inválida.') . '&type=danger');
    exit;
}

// ==============================
// 🔍 Validar métrica y permisos
// ==============================
$sql = "SELECT id_metrica, nombre, descripcion, tipo 
        FROM metrica 
        WHERE id_metrica = {$id} LIMIT 1";
$rs = $cn->query($sql);
if (!$rs || !$rs->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
    exit;
}

$metrica = $rs->fetch_assoc();
if (strtolower($metrica['tipo'] ?? '') !== 'personalizada') {
    header('Location: metricas.php?msg=' . urlencode('Solo se pueden editar métricas personalizadas.') . '&type=danger');
    exit;
}

// Verificar acceso del usuario
$sqlAcceso = "
    SELECT 1
    FROM metrica_proyecto_modelo mpm
    JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
    JOIN usuario_proyecto up ON up.id_proyecto = pmc.id_proyecto
    WHERE up.id_usuario = {$usr->id} AND mpm.id_metrica = {$id}
    LIMIT 1";
$rsAcceso = $cn->query($sqlAcceso);
if (!$rsAcceso || !$rsAcceso->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('No tiene permisos para editar esta métrica.') . '&type=danger');
    exit;
}
?>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css">
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css">
    <script src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Editar Métrica Personalizada</title>
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

    <script type="text/javascript">
        function validarFormulario() {
            const nombre = document.getElementById('nombre').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();

            // 🔤 mismo regex que backend
            const regexGeneral = /^[A-Za-z0-9ÁÉÍÓÚáéíóúÜüÑñ _\-\(\)\.,:%]+$/u;

            // Campos vacíos
            if (nombre === "" || descripcion === "") {
                alert("⚠️ Debe completar todos los campos.");
                return false;
            }

            // Validar caracteres
            if (!regexGeneral.test(nombre)) {
                alert("⚠️ El nombre contiene caracteres no permitidos.\nSolo se admiten letras, números, espacios y los símbolos _ - ( ) . , : %");
                return false;
            }
            if (!regexGeneral.test(descripcion)) {
                alert("⚠️ La descripción contiene caracteres no permitidos.\nSolo se admiten letras, números, espacios y los símbolos _ - ( ) . , : %");
                return false;
            }

            // Validar longitudes
            if (nombre.length < 3 || nombre.length > 100) {
                alert("⚠️ El nombre debe tener entre 3 y 100 caracteres.");
                return false;
            }
            if (descripcion.length < 5 || descripcion.length > 255) {
                alert("⚠️ La descripción debe tener entre 5 y 255 caracteres.");
                return false;
            }

            return true;
        }
    </script>
</head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a href="metricas.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>

        <form action="metrica.modificar.personalizado.procesar.php" method="post" onsubmit="return validarFormulario();">
            <input type="hidden" name="id" value="<?= (int)$metrica['id_metrica']; ?>">

            <div class="card shadow-sm">
                <div class="card-header">
                    <h3>Editar Métrica <span class="badge badge-info">Personalizada</span></h3>
                    <p>
                        Complete los campos a continuación.
                        Luego, presione el botón <b>Confirmar</b>.<br />
                        Si desea cancelar, presione el botón <b>Cancelar</b>.
                    </p>
                </div>

                <div class="card-body">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" maxlength="100"
                            required value="<?= htmlspecialchars($metrica['nombre']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3" maxlength="255"
                            required><?= htmlspecialchars($metrica['descripcion'] ?? ''); ?></textarea>
                    </div>
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