<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

ControlAcceso::verificaLogin();
$cn = BDConexion::getInstancia();

if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido.') . '&type=danger');
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$metricas = isset($_POST['metricas']) ? array_map('intval', $_POST['metricas']) : [];
$metricasNuevasNombres = $_POST['metricas_nuevas']['nombre'] ?? [];
$metricasNuevasDescs = $_POST['metricas_nuevas']['descripcion'] ?? [];
$modeloBaseId = (int)($_POST['modelo_base_id'] ?? 0);
$editarBase = (int)($_POST['editar_base'] ?? 0) === 1;
$proyectoId = (int)($_POST['proyecto'] ?? 0);

try {
    $cn->begin_transaction();

    // 🟢 Caso 1: usar modelo base sin editar → solo vincular modelo global
    if ($modeloBaseId > 0 && !$editarBase) {
        $cn->query("UPDATE proyecto 
                    SET id_modelo_global = {$modeloBaseId}, 
                        id_modelo_personalizado = NULL,
                         estado = 'En Progreso'
        WHERE id_proyecto = {$proyectoId}
          AND (estado = 'Registrado' OR estado IS NULL)");
        $cn->commit();
        header('Location: modelos.php?msg=' . urlencode('Modelo base vinculado correctamente al proyecto.') . '&type=success');
        exit;
    }

    // 🟢 Caso 2: editar modelo base → crear modelo personalizado
    $modeloBaseReferencia = null;
    if ($modeloBaseId > 0 && $editarBase) {
        $qBase = $cn->query("SELECT nombre, descripcion FROM modelo_calidad WHERE id_modelo = {$modeloBaseId} LIMIT 1");
        $base = $qBase && $qBase->num_rows ? $qBase->fetch_assoc() : null;
        if (!$base) {
            throw new Exception("Modelo base no encontrado.");
        }

        $nombreBase = trim($base['nombre']);
        $descBase = trim($base['descripcion']);

        // Obtener métricas base
        $metricasBase = [];
        $resM = $cn->query("SELECT id_metrica FROM metrica_modelo_calidad WHERE id_modelo = {$modeloBaseId}");
        while ($r = $resM->fetch_assoc()) {
            $metricasBase[] = (int)$r['id_metrica'];
        }

        sort($metricasBase);
        sort($metricas);
        $metricasCambiadas = $metricasBase !== $metricas || !empty($metricasNuevasNombres);
        $nombreCambiado = strcasecmp($nombreBase, $nombre) !== 0;
        $descCambiado = strcasecmp($descBase, $descripcion) !== 0;

        if (!$nombreCambiado && !$descCambiado && !$metricasCambiadas) {
            // No hubo cambios → solo vincular modelo global
            $cn->query("UPDATE proyecto 
                        SET id_modelo_global = {$modeloBaseId}, 
                            id_modelo_personalizado = NULL,
                             estado = 'En Progreso'
        WHERE id_proyecto = {$proyectoId}
          AND (estado = 'Registrado' OR estado IS NULL)");
            $cn->commit();
            header('Location: modelos.php?msg=' . urlencode('Sin cambios: se vinculó el modelo base original.') . '&type=info');
            exit;
        }

        // Hubo cambios → crear modelo personalizado
        // Hubo cambios → crear modelo personalizado
        if ($nombreCambiado) {
            // El usuario cambió el nombre → usar el nuevo tal cual
            $nombreFinal = $nombre;
        } else {
            // No lo cambió → agregar sufijo (Personalizado)
            $nombreFinal = "{$nombreBase} (Personalizado)";
        }

        // Si el usuario no escribió descripción, mantener la base
        $descripcionFinal = $descripcion !== '' ? $descripcion : $descBase;

        $modeloBaseReferencia = $modeloBaseId;
        $nombre = $nombreFinal;
        $descripcion = $descripcionFinal;
    }

    // 🟢 Caso 3: modelo nuevo desde cero → también se crea como personalizado
    if ($modeloBaseId === 0) {
        $modeloBaseReferencia = null;
    }

    // ===============================
    // 🧩 Crear modelo personalizado
    // ===============================
    $stmt = $cn->prepare("INSERT INTO proyecto_modelo_calidad (id_proyecto, id_modelo_base, nombre, descripcion, es_personalizado) 
                          VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param('iiss', $proyectoId, $modeloBaseReferencia, $nombre, $descripcion);
    $stmt->execute();
    $idProyectoModelo = $cn->insert_id;

    // ===============================
    // 📊 Insertar métricas nuevas
    // ===============================
    if (!empty($metricasNuevasNombres)) {
        $stmtNM = $cn->prepare("INSERT INTO metrica (nombre, descripcion, tipo) VALUES (?, ?, 'personalizada')");
        foreach ($metricasNuevasNombres as $i => $nom) {
            $desc = $metricasNuevasDescs[$i] ?? '';
            $stmtNM->bind_param('ss', $nom, $desc);
            $stmtNM->execute();
            $metricas[] = $cn->insert_id;
        }
    }

    // ===============================
    // 🔗 Asociar métricas al modelo personalizado
    // ===============================
    if (!empty($metricas)) {
        $stmtMM = $cn->prepare("INSERT INTO metrica_proyecto_modelo (id_metrica, id_proyecto_modelo) VALUES (?, ?)");
        foreach ($metricas as $idM) {
            $stmtMM->bind_param('ii', $idM, $idProyectoModelo);
            $stmtMM->execute();
        }
    }

    // ===============================
    // 🔄 Actualizar el proyecto
    // ===============================
    if ($proyectoId > 0 && $idProyectoModelo > 0) {
        $cn->query("UPDATE proyecto 
                    SET id_modelo_personalizado = {$idProyectoModelo}, 
                        id_modelo_global = NULL,
 estado = 'En Progreso'
        WHERE id_proyecto = {$proyectoId}
          AND (estado = 'Registrado' OR estado IS NULL)");
    }

    $cn->commit();

    header('Location: modelos.php?msg=' . urlencode('Modelo personalizado creado y vinculado correctamente al proyecto.') . '&type=success');
    exit;
} catch (Throwable $e) {
    $cn->rollback();
    header('Location: modelos.php?msg=' . urlencode('Error al crear modelo: ' . $e->getMessage()) . '&type=danger');
    exit;
}
