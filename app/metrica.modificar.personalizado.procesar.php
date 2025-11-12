<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$usr = ControlAcceso::usuarioActual();
$cn = BDConexion::getInstancia();

// ===============================
// 📥 Obtener y sanitizar datos
// ===============================
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';

if ($id <= 0 || $nombre === '' || $descripcion === '') {
    header('Location: metricas.php?msg=' . urlencode('Datos inválidos o incompletos.') . '&type=danger');
    exit;
}

// ===============================
// 🔍 Validar existencia y tipo
// ===============================
$sql = "SELECT tipo FROM metrica WHERE id_metrica = {$id} LIMIT 1";
$rs = $cn->query($sql);
if (!$rs || !$rs->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('La métrica no existe.') . '&type=danger');
    exit;
}

$row = $rs->fetch_assoc();
if (strtolower($row['tipo'] ?? '') !== 'personalizada') {
    header('Location: metricas.php?msg=' . urlencode('Solo se pueden modificar métricas personalizadas.') . '&type=danger');
    exit;
}

// ===============================
// 🔐 Validar acceso del usuario
// ===============================
$sqlAcceso = "
    SELECT mpm.id_proyecto_modelo
    FROM metrica_proyecto_modelo mpm
    JOIN proyecto_modelo_calidad pmc ON pmc.id_proyecto_modelo = mpm.id_proyecto_modelo
    JOIN usuario_proyecto up ON up.id_proyecto = pmc.id_proyecto
    WHERE up.id_usuario = {$usr->id} 
      AND mpm.id_metrica = {$id}
    LIMIT 1";
$rsAcceso = $cn->query($sqlAcceso);

if (!$rsAcceso || !$rsAcceso->num_rows) {
    header('Location: metricas.php?msg=' . urlencode('No tiene permisos para modificar esta métrica.') . '&type=danger');
    exit;
}
$idModelo = (int)$rsAcceso->fetch_assoc()['id_proyecto_modelo'];

// ===============================
// 🧩 Validaciones de formato y longitud
// ===============================
// Mismo regex que en otros formularios (nombre y descripción)
$regexGeneral = '/^[A-Za-z0-9ÁÉÍÓÚáéíóúÜüÑñ _\-\(\)\.,:%]+$/u';

// Validar caracteres permitidos
if (!preg_match($regexGeneral, $nombre)) {
    header('Location: metricas.php?msg=' . urlencode('El nombre contiene caracteres no permitidos.') . '&type=danger');
    exit;
}
if (!preg_match($regexGeneral, $descripcion)) {
    header('Location: metricas.php?msg=' . urlencode('La descripción contiene caracteres no permitidos.') . '&type=danger');
    exit;
}

// Validar longitud
if (strlen($nombre) < 3 || strlen($nombre) > 100) {
    header('Location: metricas.php?msg=' . urlencode('El nombre debe tener entre 3 y 100 caracteres.') . '&type=danger');
    exit;
}
if (strlen($descripcion) < 5 || strlen($descripcion) > 255) {
    header('Location: metricas.php?msg=' . urlencode('La descripción debe tener entre 5 y 255 caracteres.') . '&type=danger');
    exit;
}

// ===============================
// 🚫 Verificar duplicado de nombre
// ===============================
$nomEsc = $cn->real_escape_string($nombre);
$sqlDup = "
    SELECT 1
    FROM metrica m
    JOIN metrica_proyecto_modelo mpm ON m.id_metrica = mpm.id_metrica
    WHERE mpm.id_proyecto_modelo = {$idModelo}
      AND m.nombre = '{$nomEsc}'
      AND m.id_metrica <> {$id}
    LIMIT 1";
$rsDup = $cn->query($sqlDup);
if ($rsDup && $rsDup->num_rows > 0) {
    header('Location: metricas.php?msg=' . urlencode('Ya existe una métrica personalizada con ese nombre en este modelo.') . '&type=danger');
    exit;
}

// ===============================
// 💾 Actualizar métrica
// ===============================
$descEsc = $cn->real_escape_string($descripcion);
$qUpd = "
    UPDATE metrica 
    SET nombre = '{$nomEsc}', descripcion = '{$descEsc}' 
    WHERE id_metrica = {$id}
";
if (!$cn->query($qUpd)) {
    header('Location: metricas.php?msg=' . urlencode('Error al actualizar la métrica personalizada.') . '&type=danger');
    exit;
}

// ===============================
// ✅ Éxito
// ===============================
header('Location: metricas.php?msg=' . urlencode('✅ Métrica personalizada actualizada correctamente.') . '&type=success');
exit;
