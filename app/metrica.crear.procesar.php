<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();
$esAdmin = ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal();

$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$modelos = $_POST['modelos'] ?? [];

$regex = '/^[a-zA-Z0-9ÁÉÍÓÚáéíóúüÜñÑ\s_\-()\/.,]+$/u';

if ($nombre === '' || $descripcion === '' || empty($modelos)) {
    header('Location: metrica.nueva.php?msg=' . urlencode('Debe completar todos los campos y seleccionar al menos un modelo.') . '&type=danger');
    exit;
}

if (!preg_match($regex, $nombre) || !preg_match($regex, $descripcion)) {
    header('Location: metrica.nueva.php?msg=' . urlencode('Formato inválido: use solo letras, números, espacios y _ - ( ) / . ,') . '&type=danger');
    exit;
}

// Verificar si ya existe una métrica con el mismo nombre y tipo
$tipo = $esAdmin ? 'base' : 'personalizada';
$stmtCheck = $cn->prepare("SELECT COUNT(*) AS total FROM metrica WHERE nombre = ? AND tipo = ?");
$stmtCheck->bind_param('ss', $nombre, $tipo);
$stmtCheck->execute();
$res = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if ($res['total'] > 0) {
    header('Location: metrica.nueva.php?msg=' . urlencode('Ya existe una métrica con ese nombre y tipo.') . '&type=danger');
    exit;
}

// Crear métrica
$sqlInsert = "INSERT INTO metrica (nombre, descripcion, tipo) VALUES (?, ?, ?)";
$stmt = $cn->prepare($sqlInsert);
$stmt->bind_param('sss', $nombre, $descripcion, $tipo);
$stmt->execute();
$idMetrica = $stmt->insert_id;
$stmt->close();

// Asociar a modelos
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

header('Location: metricas.php?msg=' . urlencode('✅ Métrica creada y asociada correctamente.') . '&type=success');
exit;
?>
