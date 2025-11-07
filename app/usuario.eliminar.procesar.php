<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
if (!ControlAcceso::esAdminGlobal()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores.']);
    exit;
}
include_once '../modelo/BDConexion.Class.php';

header('Content-Type: application/json; charset=utf-8');

$idUsuario = (int)($_POST['id'] ?? 0);
$response = ['success' => false, 'error' => ''];

if ($idUsuario <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de usuario inválido.']);
    exit;
}

$bd = BDConexion::getInstancia();
$bd->autocommit(false);

try {
    // Eliminar relaciones dependientes
    $tablas = ['usuario_rol', 'usuario_proyecto'];
    foreach ($tablas as $tabla) {
        $q = "DELETE FROM $tabla WHERE id_usuario = {$idUsuario}";
        if (!$bd->query($q)) {
            throw new Exception("Error eliminando en $tabla: " . $bd->error);
        }
    }

    // Eliminar el usuario
    $qUser = "DELETE FROM usuario WHERE id_usuario = {$idUsuario}";
    if (!$bd->query($qUser)) {
        throw new Exception("Error eliminando usuario: " . $bd->error);
    }

    $bd->commit();
    $response['success'] = true;
} catch (Exception $e) {
    $bd->rollback();
    $response['error'] = $e->getMessage();
}

$bd->autocommit(true);
echo json_encode($response);
exit;
?>