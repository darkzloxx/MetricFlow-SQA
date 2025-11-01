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
    $objetivo = trim($_POST['objetivo'] ?? '');

    if ($nombre === '') {
        throw new Exception("El nombre es obligatorio.");
    }

    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/u', $nombre)) {
        throw new Exception("El nombre solo puede contener letras y espacios.");
    }

    // Verificar duplicado
    $query = "SELECT 1 FROM proyecto WHERE nombre = ?";
    $stmt = $bd->prepare($query);
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        throw new Exception("Ya existe un proyecto con el nombre ingresado.");
    }
    $stmt->close();

    // Insertar nuevo proyecto (con objetivo opcional)
    $sql = "INSERT INTO proyecto (objetivo, descripcion, estado, nombre, id_modelo)
            VALUES (?, ?, 'Registrado', ?, NULL)";
    $stmt = $bd->prepare($sql);
    $stmt->bind_param('sss', $objetivo, $descripcion, $nombre); // 👈 tipos y orden correctos

    if (!$stmt->execute()) {
        throw new Exception("Error al crear el proyecto: " . $stmt->error);
    }

    // Obtener id del proyecto insertado
    $idProyecto = $bd->insert_id;

    // Asociar el nuevo proyecto a todas las fases existentes (fechas NULL por defecto)
    $sqlFases = "SELECT id_fase FROM fase ORDER BY id_fase";
    $resFases = $bd->query($sqlFases);
    if ($resFases === false) {
        throw new Exception("Error al obtener fases: " . $bd->error);
    }

    $fases = $resFases->fetch_all(MYSQLI_ASSOC);
    $resFases->free();

    if (!empty($fases)) {
        $stmtIns = $bd->prepare("INSERT INTO proyecto_fase (id_proyecto, id_fase, fecha_inicio, fecha_fin) VALUES (?, ?, NULL, NULL)");
        if ($stmtIns === false) {
            throw new Exception("Error al preparar inserción en proyecto_fase: " . $bd->error);
        }

        foreach ($fases as $f) {
            $idFase = (int)$f['id_fase'];
            $stmtIns->bind_param('ii', $idProyecto, $idFase);
            if (!$stmtIns->execute()) {
                $stmtIns->close();
                throw new Exception("Error al asignar fase (id_fase={$idFase}) al proyecto: " . $stmtIns->error);
            }
        }

        $stmtIns->close();
    }

    $bd->commit();
    $response['success'] = true;
    $response['mensaje'] = "Proyecto creado correctamente.";

} catch (Exception $e) {
    if (isset($bd)) $bd->rollback();
    $response['mensaje'] = $e->getMessage();
} finally {
    if (isset($bd)) $bd->autocommit(true);
}

echo json_encode($response);
exit;
