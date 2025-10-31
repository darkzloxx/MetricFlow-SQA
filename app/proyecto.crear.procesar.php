<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_PROYECTOS);
include_once '../modelo/BDConexion.Class.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'mensaje' => 'Ha ocurrido un error.'];

try {
    $bd = BDConexion::getInstancia();
    $bd->autocommit(false);

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        throw new Exception("El nombre es obligatorio.");
    }

    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/u', $nombre)) {
        throw new Exception("El nombre solo puede contener letras y espacios.");
    }

    $query = "SELECT 1 FROM proyecto WHERE nombre = ?";
    $stmt = $bd->prepare($query);
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        throw new Exception("Ya existe un proyecto con el nombre ingresado.");
    }

    $sql = "INSERT INTO proyecto (objetivo, descripcion, estado, nombre, id_modelo)
        VALUES (NULL, ?, 'Registrado', ?, NULL)";
    $stmt = $bd->prepare($sql);
    $stmt->bind_param('ss', $descripcion, $nombre);
    if (!$stmt->execute()) {
        throw new Exception("Error al crear el proyecto: " . $stmt->error);
    }

    $bd->commit();
    $response['success'] = true;
    $response['mensaje'] = "Proyecto creado correctamente.";

} catch (Exception $e) {
    $bd->rollback();
    $response['mensaje'] = $e->getMessage();
} finally {
    $bd->autocommit(true);
}

echo json_encode($response);
exit;
