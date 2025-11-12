<?php
ob_start(); // Evita cualquier salida previa que rompa el JSON

include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// 🔍 Detección de solicitud AJAX
// ============================================================
$__isAjax = !empty($_POST['ajax']) ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$usr = ControlAcceso::usuarioActual();
$cn = BDConexion::getInstancia();

try {
    // ============================================================
    // 🔹 Validaciones de entrada
    // ============================================================
    $regexGeneral = "/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _\.\-\/\\():]+$/u";

    $idModelo = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($idModelo <= 0) {
        throw new Exception("ID de modelo inválido.");
    }

    if ($nombre === '' || $descripcion === '') {
        throw new Exception('Debe completar todos los campos obligatorios.');
    }

    if (!preg_match($regexGeneral, $nombre)) {
        throw new Exception('El nombre contiene caracteres no permitidos.');
    }

    if (!preg_match($regexGeneral, $descripcion)) {
        throw new Exception('La descripción contiene caracteres no permitidos.');
    }

    // ============================================================
    // 🔒 Validar que el modelo pertenece al usuario
    // ============================================================
    $sqlPerm = "
        SELECT pmc.id_proyecto_modelo, pmc.nombre AS nombre_actual, pmc.descripcion AS desc_actual, pmc.id_proyecto
        FROM proyecto_modelo_calidad pmc
        JOIN proyecto p ON p.id_proyecto = pmc.id_proyecto
        JOIN usuario_proyecto up ON up.id_proyecto = p.id_proyecto
        WHERE pmc.id_proyecto_modelo = {$idModelo}
          AND up.id_usuario = {$usr->id}
    ";
    $resPerm = $cn->query($sqlPerm);
    if (!$resPerm || $resPerm->num_rows === 0) {
        throw new Exception("No tiene permisos para modificar este modelo personalizado.");
    }

    $modelo = $resPerm->fetch_assoc();
    $idProyecto = (int)$modelo['id_proyecto'];

    // ============================================================
    // 🧠 Verificar si hay cambios reales
    // ============================================================
    if ($modelo['nombre_actual'] === $nombre && $modelo['desc_actual'] === $descripcion) {
        ob_clean();
        if ($__isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'info' => 'Sin cambios detectados.']);
            exit;
        }
        $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'No se detectaron cambios para guardar.'];
        header("Location: modelo.editar.personalizado.php?id=" . $idModelo);
        exit;
    }

    // ============================================================
    // 🔍 Validar unicidad del nombre dentro del mismo proyecto
    // ============================================================
    $stmt = $cn->prepare("
        SELECT COUNT(*) AS c 
        FROM proyecto_modelo_calidad 
        WHERE nombre = ? 
          AND id_proyecto = ? 
          AND id_proyecto_modelo <> ?
    ");
    $stmt->bind_param("sii", $nombre, $idProyecto, $idModelo);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$row['c'] > 0) {
        throw new Exception("Ya existe otro modelo personalizado con el nombre \"$nombre\" en este proyecto.");
    }

    // ============================================================
    // 💾 Actualizar modelo
    // ============================================================
    $stmt = $cn->prepare("
        UPDATE proyecto_modelo_calidad 
        SET nombre = ?, descripcion = ? 
        WHERE id_proyecto_modelo = ?
    ");
    $stmt->bind_param("ssi", $nombre, $descripcion, $idModelo);
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar el modelo: " . $stmt->error);
    }
    $stmt->close();

    // ============================================================
    // ✅ Respuesta exitosa
    // ============================================================
    ob_clean();
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => "Modelo \"{$nombre}\" actualizado correctamente."
    ];

    if ($__isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'redirect' => 'modelos.php'
        ]);
        exit;
    }

    // Si no es AJAX, redirige normalmente
    header("Location: modelos.php");
    exit;
} catch (Exception $ex) {
    ob_clean();
    $_SESSION['form_data'] = $_POST;
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $ex->getMessage()];

    if ($__isAjax) {
        header('Content-Type: application/json; charset=utf-8', true, 400);
        echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
        exit;
    }

    header("Location: modelo.editar.personalizado.php?id=" . ($idModelo ?? 0));
    exit;
}
