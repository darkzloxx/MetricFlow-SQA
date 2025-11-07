<?php
include_once '../../lib/ControlAcceso.Class.php';
include_once '../../modelo/BDConexion.Class.php';

header('Content-Type: application/json; charset=utf-8');

try {
    ControlAcceso::verificaLogin();
    // Permisos: Admin/SuperAdmin o permiso de gestión de modelo
    if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
        http_response_code(403);
        echo json_encode([ 'ok' => false, 'error' => 'Acceso restringido' ]);
        exit;
    }

    $idModelo = isset($_GET['id_modelo']) ? (int)$_GET['id_modelo'] : 0;
    if ($idModelo <= 0) {
        http_response_code(400);
        echo json_encode([ 'ok' => false, 'error' => 'id_modelo inválido' ]);
        exit;
    }

    $cn = BDConexion::getInstancia();
    $sql = "SELECT m.id_metrica, m.nombre, m.descripcion
            FROM metrica_modelo_calidad mmc
            JOIN metrica m ON m.id_metrica = mmc.id_metrica
            WHERE mmc.id_modelo = {$idModelo}
            ORDER BY m.nombre";
    $rs = $cn->query($sql);
    $data = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];

    echo json_encode([ 'ok' => true, 'metricas' => $data ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'ok' => false, 'error' => 'Error del servidor' ]);
}
?>