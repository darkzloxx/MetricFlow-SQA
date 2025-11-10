<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$modelos = $_POST['modelos'] ?? [];

// Guardar datos del formulario en sesión (para repoblar si hay error)
$_SESSION['form_data'] = [
    'nombre' => $nombre,
    'descripcion' => $descripcion,
    'modelos' => $modelos
];

// ===============================
// 🔹 Validaciones básicas
// ===============================
if ($nombre === '' || $descripcion === '' || empty($modelos)) {
    header('Location: metrica.crear.php?msg=' . urlencode('Debe completar todos los campos y seleccionar al menos un modelo.') . '&type=danger');
    exit;
}

// ===============================
// 🔹 Validar formato de nombre y descripción
// ===============================
$pattern = '/^[a-zA-Z0-9ÁÉÍÓÚáéíóúüÜñÑ_\-\(\)\.\/ ]{3,255}$/u';

if (!preg_match($pattern, $nombre) || !preg_match($pattern, $descripcion)) {
    header('Location: metrica.crear.php?msg=' . urlencode('El nombre o la descripción contienen caracteres no permitidos. Solo se admiten letras, números y los símbolos . - _ / ( )') . '&type=danger');
    exit;
}

// ===============================
// 🔹 Verificar nombre duplicado (en base o personalizada)
// ===============================
$sqlDup = "SELECT COUNT(*) AS c FROM metrica WHERE LOWER(nombre) = LOWER(?)";
$stmt = $cn->prepare($sqlDup);
$stmt->bind_param('s', $nombre);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($res['c'] > 0) {
    header('Location: metrica.crear.php?msg=' . urlencode('⚠️ Ya existe una métrica con ese nombre en el sistema. Si desea usarla, puede vincularla desde la opción “Vincular existente”.') . '&type=danger');
    exit;
}

// ===============================
// 🔹 Insertar nueva métrica
// ===============================
$tipo = $esAdmin ? 'base' : 'personalizada';
$sqlInsert = "INSERT INTO metrica (nombre, descripcion, tipo) VALUES (?, ?, ?)";
$stmt = $cn->prepare($sqlInsert);
$stmt->bind_param('sss', $nombre, $descripcion, $tipo);
$stmt->execute();
$idMetrica = $stmt->insert_id;
$stmt->close();

// ===============================
// 🔹 Asociar la métrica a modelos
// ===============================
if ($esAdmin) {
    foreach ($modelos as $idModelo) {
        $idModelo = (int)$idModelo;
        $cn->query("INSERT INTO metrica_modelo_calidad (id_metrica, id_modelo) VALUES ($idMetrica, $idModelo)");
    }
} else {
    foreach ($modelos as $idPM) {
        $idPM = (int)$idPM;
        $cn->query("INSERT INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo) VALUES ($idMetrica, $idPM)");
    }
}

// ✅ Limpiar la sesión temporal
unset($_SESSION['form_data']);

// Redirigir con éxito
header('Location: metricas.php?msg=' . urlencode('✅ Métrica creada y asociada correctamente.') . '&type=success');
exit;
?>
