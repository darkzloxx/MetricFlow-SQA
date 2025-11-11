<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../lib/ControlAcceso.Class.php';
include_once '../../modelo/BDConexion.Class.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $idProyecto = isset($_GET['idProyecto']) ? (int)$_GET['idProyecto'] : 0;
    $idFase = isset($_GET['idFase']) ? (int)$_GET['idFase'] : 0;

    if ($idProyecto <= 0 || $idFase <= 0) {
        echo json_encode(['siguiente' => 1, 'error' => 'Parámetros inválidos']);
        exit;
    }

    $cn = BDConexion::getInstancia();
    if (!$cn) {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    // Numeración por proyecto y fase
    $sql = "
        SELECT COALESCE(MAX(numero_iteracion), 0) + 1 AS siguiente
        FROM iteracion
        WHERE id_proyecto = $idProyecto
          AND id_fase = $idFase
    ";

    $res = $cn->query($sql);
    if (!$res) {
        throw new Exception('Error SQL: ' . $cn->error);
    }

    $row = $res->fetch_assoc();
    $siguiente = max(1, (int)($row['siguiente'] ?? 1));

    echo json_encode(['siguiente' => $siguiente]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'siguiente' => 1]);
    exit;
}
