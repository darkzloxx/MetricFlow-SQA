<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
if (!ControlAcceso::esAdminGlobal()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'mensaje' => 'Acceso restringido a administradores.']);
    exit;
}
include_once '../modelo/BDConexion.Class.php';

header('Content-Type: application/json; charset=utf-8');

$DatosFormulario = $_POST;
$bd = BDConexion::getInstancia();
$bd->autocommit(false);
$response = ['success' => false, 'mensaje' => 'Ha ocurrido un error.'];

try {
    $correo = trim($DatosFormulario["mail"] ?? '');
    $nombre = trim($DatosFormulario["nombre"] ?? '');

    if ($nombre === '' || $correo === '') {
        throw new Exception("Debe completar todos los campos obligatorios.");
    }

    if (strpos($correo, "@gmail.com") === false) {
        throw new Exception("El correo ingresado no es válido, debe ser dominio '@gmail.com'.");
    }

    $query = "SELECT * FROM usuario WHERE email = '{$correo}'";
    $consulta = $bd->query($query);
    if ($consulta->num_rows > 0) {
        throw new Exception("Ya existe un usuario con el correo ingresado.");
    }

    // Crear usuario
    $insert = "INSERT INTO usuario (nombre_apellido, email) VALUES ('{$nombre}', '{$correo}')";
    if (!$bd->query($insert)) {
        throw new Exception("Error al crear el usuario: " . $bd->error);
    }

    $idUsuario = $bd->insert_id;

    // Si hay proyectos/roles asociados
    if (!empty($_POST['listaProyectos']) && is_array($_POST['listaProyectos'])) {
        $listaProyectos = $_POST['listaProyectos'];
        $roles = $_POST['rol'];
        for ($i = 0; $i < count($listaProyectos); $i++) {
            $id_proyecto = intval($listaProyectos[$i]);
            $id_rol = intval($roles[$i]);
            if ($id_proyecto > 0 && $id_rol > 0) {
                $bd->query("INSERT INTO usuario_proyecto (id_usuario, id_proyecto, id_rol)
                            VALUES ($idUsuario, $id_proyecto, $id_rol)");
                $bd->query("INSERT IGNORE INTO usuario_rol (id_usuario, id_rol)
                            VALUES ($idUsuario, $id_rol)");
            }
        }
    }

    $bd->commit();
    $response['success'] = true;
    $response['mensaje'] = "Usuario creado correctamente.";
} catch (Exception $e) {
    $bd->rollback();
    $response['mensaje'] = $e->getMessage();
} finally {
    $bd->autocommit(true);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;

