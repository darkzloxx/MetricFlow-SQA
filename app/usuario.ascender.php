<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);

if (!ControlAcceso::esSuperAdminGlobal()) {
    header('Location: usuarios.php?msg=' . urlencode('Acceso restringido a SUPERADMIN.') . '&type=danger');
    exit;
}

include_once '../modelo/Usuario.php';
include_once '../modelo/BDConexion.Class.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: usuarios.php?msg=' . urlencode('ID de usuario inválido.') . '&type=danger');
    exit;
}

$cn = BDConexion::getConexion();
$tieneProyectos = (int)$cn->query("SELECT COUNT(*) AS c FROM usuario_proyecto WHERE id_usuario = $id")->fetch_assoc()['c'];
$rolActual = strtolower(trim($cn->query("SELECT r.nombre FROM usuario_rol ur JOIN rol r ON ur.id_rol = r.id WHERE ur.id_usuario = $id")->fetch_assoc()['nombre'] ?? ''));

if ($tieneProyectos > 0 || in_array($rolActual, ['administrador', 'superadmin'])) {
    header('Location: usuarios.php?msg=' . urlencode('No se puede ascender: usuario con proyectos o rol elevado.') . '&type=danger');
    exit;
}

// ID del rol Administrador
$idRolAdmin = (int)$cn->query("SELECT id FROM rol WHERE LOWER(nombre)='administrador' LIMIT 1")->fetch_assoc()['id'];


// Actualizar rol en usuario_rol (no existe id_rol en usuario)
$ok = $cn->query("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES ($id, $idRolAdmin) ON DUPLICATE KEY UPDATE id_rol = $idRolAdmin");

if ($ok) {
    header('Location: usuarios.php?msg=' . urlencode('Usuario ascendido a Administrador correctamente.') . '&type=success');
} else {
    header('Location: usuarios.php?msg=' . urlencode('Error al ascender el usuario.') . '&type=danger');
}
exit;
?>
