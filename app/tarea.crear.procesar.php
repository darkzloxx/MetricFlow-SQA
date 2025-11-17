<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$DatosFormulario = $_POST;
$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$nombre = trim($DatosFormulario["nombre"] ?? '');
$iteracion = intval($DatosFormulario["iteracion"] ?? 0);

$tipo = "danger";
$mensaje = "Ha ocurrido un error.";

// ==========================
// VALIDACIONES
// ==========================
if ($nombre === "") {
    $mensaje = "El nombre de la tarea es obligatorio.";
} elseif (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/():]+$/u', $nombre)) {
    $mensaje = "El nombre solo puede contener letras, números y . - _ / ( ) :";
} elseif (!isset($DatosFormulario["metricas"]) || count($DatosFormulario["metricas"]) == 0) {
    $mensaje = "Debe seleccionar al menos una métrica.";
} elseif ($iteracion <= 0) {
    $mensaje = "Debe seleccionar una iteración.";
} else {

    // Nombre duplicado
    $sql = "SELECT 1 FROM tarea WHERE nombre = '" . $cn->real_escape_string($nombre) . "'";
    $dup = $cn->query($sql);

    if ($dup->num_rows > 0) {
        $mensaje = "Ya existe una tarea con ese nombre.";
    } else {

        // Crear tarea
        $sql = "INSERT INTO tarea (nombre) VALUES ('" . $cn->real_escape_string($nombre) . "')";
        if (!$cn->query($sql)) goto ERROR_SQL;

        $idTarea = $cn->insert_id;

        // Asociar métricas
        foreach ($DatosFormulario["metricas"] as $idMetrica) {
            $sql = "INSERT INTO metrica_tarea (id_metrica, id_tarea)
                    VALUES (" . intval($idMetrica) . ", $idTarea)";
            if (!$cn->query($sql)) goto ERROR_SQL;
        }

        // Asociar iteración
        $sql = "INSERT INTO iteracion_tarea (id_iteracion, id_tarea)
                VALUES ($iteracion, $idTarea)";
        if (!$cn->query($sql)) goto ERROR_SQL;

        $cn->commit();
        $cn->autocommit(true);

        unset($_SESSION['old']);
        $_SESSION['flash'] = ["success", "Tarea creada exitosamente. 🚀"];
        header("Location: tarea.php");
        exit;
    }
}

// ========= ERROR ==========
ERROR_SQL:
$cn->rollback();
$cn->autocommit(true);

$_SESSION['old'] = $_POST;
$_SESSION['flash'] = [$tipo, $mensaje];
header("Location: tarea.crear.php");
exit;
