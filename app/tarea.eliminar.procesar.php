<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$cn = BDConexion::getInstancia();

$idTarea = intval($_POST["id"] ?? $_GET["id"] ?? 0); // 🔹 Soportar GET o POST

if ($idTarea <= 0) {
    $_SESSION['flash'] = ['danger', 'Tarea inválida'];
    header("Location: tarea.php");
    exit;
}

$cn->autocommit(false);
$cn->begin_transaction();

// ----------------------
// Eliminar referencias
// ----------------------
if (!$cn->query("DELETE FROM metrica_tarea WHERE id_tarea = $idTarea")) goto FAIL;
if (!$cn->query("DELETE FROM iteracion_tarea WHERE id_tarea = $idTarea")) goto FAIL;

// ----------------------
// Eliminar tarea
// ----------------------
if (!$cn->query("DELETE FROM tarea WHERE id_tarea = $idTarea")) goto FAIL;

$cn->commit();
$cn->autocommit(true);

$_SESSION['flash'] = ['success', 'Tarea eliminada correctamente'];
header("Location: tarea.php");
exit;

// ----------------------
FAIL:
$cn->rollback();
$cn->autocommit(true);
$_SESSION['flash'] = ['danger', 'Error al eliminar la tarea'];
header("Location: tarea.php");
exit;
