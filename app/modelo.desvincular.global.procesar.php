<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';
ControlAcceso::verificaLogin();

$cn = BDConexion::getInstancia();
$usr = ControlAcceso::usuarioActual();

$__isAjax = (!empty($_POST['ajax'])) ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

try {
    $idProyecto = (int)($_POST['id_proyecto'] ?? ($_GET['id_proyecto'] ?? 0));
    if ($idProyecto <= 0) {
        throw new Exception('Proyecto inválido.');
    }

    // Verificar que el usuario pertenezca al proyecto
    if (!ControlAcceso::usuarioPerteneceAProyecto($idProyecto)) {
        throw new Exception('No tiene permisos para modificar este proyecto.');
    }

    // 🚫 Si el usuario es Admin o SuperAdmin, no corresponde que modifique proyectos ajenos
    if (ControlAcceso::esAdminGlobal() || ControlAcceso::esSuperAdminGlobal()) {
        throw new Exception('Los administradores globales no pueden desvincular modelos en proyectos.');
    }

    // ⚙️ Rol del usuario
    $rolProyecto = strtolower(trim(ControlAcceso::rolUsuarioEnProyecto($idProyecto) ?? ''));
    $esGerenteOLider = in_array($rolProyecto, [
        'gerente',
        'gerente de calidad',
        'líder',
        'líder de proyecto',
        'lider de proyecto'
    ], true);

    if (!$esGerenteOLider) {
        throw new Exception('Solo un Gerente o Líder del proyecto puede desvincular el modelo predeterminado.');
    }

    // 🔍 Verificar si el proyecto tiene modelo global asignado
    $row = $cn->query("SELECT id_modelo_global FROM proyecto WHERE id_proyecto = {$idProyecto} LIMIT 1")->fetch_assoc();
    if (empty($row) || (int)$row['id_modelo_global'] === 0) {
        throw new Exception('El proyecto no tiene un modelo global asignado.');
    }
    // =====================================================
    // 🔍 Verificar si el modelo global tiene métricas planificadas o ejecutadas
    // =====================================================
    $sqlUso = "
    SELECT 1
    FROM metrica_iteracion mi
    WHERE mi.id_metrica IN (
        SELECT mmc.id_metrica
        FROM metrica_modelo_calidad mmc
        JOIN proyecto p ON p.id_modelo_global = mmc.id_modelo
        WHERE p.id_proyecto = {$idProyecto}
    )
    LIMIT 1
";

    $resUso = $cn->query($sqlUso);
    if ($resUso && $resUso->num_rows > 0) {
        throw new Exception('No se puede desvincular el modelo global: contiene métricas planificadas o en ejecución.');
    }

    // ✅ Desvincular modelo
    $update = $cn->query("UPDATE proyecto SET id_modelo_global = NULL WHERE id_proyecto = {$idProyecto} LIMIT 1");
    if (!$update) {
        throw new Exception('Error al desvincular el modelo: ' . $cn->error);
    }

    if ($__isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Modelo predeterminado desvinculado correctamente del proyecto.']);
        exit;
    } else {
        header('Location: modelos.php?msg=' . urlencode('Modelo predeterminado desvinculado correctamente.') . '&type=success');
        exit;
    }
} catch (Exception $ex) {
    if ($__isAjax) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
        exit;
    } else {
        header('Location: modelos.php?msg=' . urlencode($ex->getMessage()) . '&type=danger');
        exit;
    }
}
