<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();
$cn = BDConexion::getInstancia();

// 🔐 Validación de permisos
if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido.') . '&type=danger');
    exit;
}

// ============================
// 📥 Datos del formulario
// ============================
$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$metricas = isset($_POST['metricas']) ? array_map('intval', $_POST['metricas']) : [];

$metricasNuevasNombres = $_POST['metricas_nuevas']['nombre'] ?? [];
$metricasNuevasDescs = $_POST['metricas_nuevas']['descripcion'] ?? [];

$modeloBaseId = (int)($_POST['modelo_base_id'] ?? 0);
$editarBase = (int)($_POST['editar_base'] ?? 0) === 1;

$proyectoId = (int)($_POST['proyecto'] ?? 0);
// 🚫 VALIDACIÓN CRÍTICA
// Un proyecto con métricas planificadas NO puede cambiar de modelo
$qCheckPlan = "
    SELECT 1
    FROM metrica_iteracion mi
    JOIN iteracion i ON i.id_iteracion = mi.id_iteracion
    WHERE i.id_proyecto = {$proyectoId}
    LIMIT 1
";
$hasPlan = ($cn->query($qCheckPlan)->num_rows > 0);

if ($hasPlan) {
    header('Location: modelos.php?msg=' . urlencode(
        'Este proyecto ya tiene métricas planificadas o ejecutadas. No se puede modificar el modelo.'
    ) . '&type=danger');
    exit;
}

try {
    $cn->begin_transaction();

    // =====================================================
    // 🟢 CASO 1 — Usar modelo base SIN editar
    // =====================================================
    if ($modeloBaseId > 0 && !$editarBase) {

        $cn->query("
            UPDATE proyecto
            SET id_modelo_global = {$modeloBaseId},
                id_modelo_personalizado = NULL,
                estado = 'En Progreso'
            WHERE id_proyecto = {$proyectoId}
        ");

        $cn->commit();
        header('Location: modelos.php?msg=' . urlencode('Modelo base vinculado correctamente al proyecto.') . '&type=success');
        exit;
    }


    // =====================================================
    // 🟢 CASO 2 — Editar modelo base → Crear modelo derivado
    // =====================================================
    $modeloBaseReferencia = null;

    if ($modeloBaseId > 0 && $editarBase) {

        // Cargar datos del modelo base
        $qBase = $cn->query("SELECT nombre, descripcion FROM modelo_calidad WHERE id_modelo = {$modeloBaseId} LIMIT 1");
        if (!$qBase || !$qBase->num_rows) {
            throw new Exception("Modelo base no encontrado.");
        }
        $base = $qBase->fetch_assoc();

        $nombreBase = trim($base['nombre']);
        $descBase = trim($base['descripcion']);

        // Obtener métricas base originales
        $metricasBase = [];
        $resM = $cn->query("SELECT id_metrica FROM metrica_modelo_calidad WHERE id_modelo = {$modeloBaseId}");
        while ($r = $resM->fetch_assoc()) {
            $metricasBase[] = (int)$r['id_metrica'];
        }

        sort($metricasBase);
        sort($metricas);

        $metricasCambiadas = ($metricasBase !== $metricas) || !empty($metricasNuevasNombres);
        $nombreCambiado = strcasecmp($nombreBase, $nombre) !== 0;
        $descCambiado = strcasecmp($descBase, $descripcion) !== 0;

        // ❗ Si NO hubo cambios → solo vincular modelo base
        if (!$nombreCambiado && !$descCambiado && !$metricasCambiadas) {

            $cn->query("
                UPDATE proyecto
                SET id_modelo_global = {$modeloBaseId},
                    id_modelo_personalizado = NULL,
                    estado = 'En Progreso'
                WHERE id_proyecto = {$proyectoId}
            ");

            $cn->commit();
            header('Location: modelos.php?msg=' . urlencode('Sin cambios: se vinculó el modelo base original.') . '&type=info');
            exit;
        }

        // ✔ Hubo cambios → se creará modelo personalizado
        $nombreFinal = $nombreCambiado ? $nombre : "{$nombreBase} (Personalizado)";
        $descripcionFinal = ($descripcion !== '') ? $descripcion : $descBase;

        $nombre = $nombreFinal;
        $descripcion = $descripcionFinal;
        $modeloBaseReferencia = $modeloBaseId;
    }


    // =====================================================
    // 🟢 CASO 3 — Crear modelo desde cero
    // =====================================================
    if ($modeloBaseId === 0) {
        $modeloBaseReferencia = null;
    }


    // =====================================================
    // 🧩 Crear modelo personalizado
    // =====================================================
    $stmt = $cn->prepare("
        INSERT INTO proyecto_modelo_calidad 
            (id_proyecto, id_modelo_base, nombre, descripcion, es_personalizado)
        VALUES (?, ?, ?, ?, 1)
    ");

    $stmt->bind_param('iiss', $proyectoId, $modeloBaseReferencia, $nombre, $descripcion);
    $stmt->execute();

    $idProyectoModelo = $cn->insert_id;


    // =====================================================
    // 📊 Insertar métricas nuevas
    // =====================================================
    if (!empty($metricasNuevasNombres)) {

        $stmtNM = $cn->prepare("
            INSERT INTO metrica (nombre, descripcion, tipo)
            VALUES (?, ?, 'personalizada')
        ");

        foreach ($metricasNuevasNombres as $i => $nom) {
            $desc = $metricasNuevasDescs[$i] ?? '';
            $stmtNM->bind_param('ss', $nom, $desc);
            $stmtNM->execute();
            $metricas[] = $cn->insert_id; // agregar al array
        }
    }


    // =====================================================
    // 🔗 Asociar métricas al modelo personalizado
    // =====================================================
    if (!empty($metricas)) {

        $stmtMM = $cn->prepare("
            INSERT INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo)
            VALUES (?, ?)
        ");

        foreach ($metricas as $idM) {
            $stmtMM->bind_param('ii', $idM, $idProyectoModelo);
            $stmtMM->execute();
        }
    }


    // =====================================================
    // 🔄 Actualizar el proyecto con el nuevo modelo
    // =====================================================
    $cn->query("
        UPDATE proyecto
        SET id_modelo_personalizado = {$idProyectoModelo},
            id_modelo_global = NULL,
            estado = 'En Progreso'
        WHERE id_proyecto = {$proyectoId}
    ");


    // =====================================================
    // ✅ Finalizar
    // =====================================================
    $cn->commit();

    header('Location: modelos.php?msg=' . urlencode('Modelo personalizado creado y vinculado correctamente al proyecto.') . '&type=success');
    exit;


} catch (Throwable $e) {

    $cn->rollback();
    header('Location: modelos.php?msg=' . urlencode('Error al crear modelo: ' . $e->getMessage()) . '&type=danger');
    exit;
}

?>
