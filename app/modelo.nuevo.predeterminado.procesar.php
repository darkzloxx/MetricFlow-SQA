<?php
include_once '../lib/ControlAcceso.Class.php';
include_once '../modelo/BDConexion.Class.php';

// Solo Admin o SuperAdmin pueden crear modelos predeterminados (globales)
if (!ControlAcceso::esAdminGlobal()) {
    header('Location: modelos.php?msg=' . urlencode('Solo administradores pueden crear modelos predeterminados.') . '&type=danger');
    exit;
}

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

try {
    // ============================
    // 1. Validar nombre del modelo
    // ============================
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _\.\-\/\\():]+$/u", $nombre)) {
        throw new Exception("El nombre contiene caracteres no permitidos. Solo letras (con o sin tilde), puntos y guiones.");
    }

    if (!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _\.\-\/\\():]+$/u", $descripcion)) {
        throw new Exception("La descripción contiene caracteres no permitidos. Solo letras, números, puntos y guiones.");
    }

    // Verificar duplicado
    $stmt = $cn->prepare("SELECT COUNT(*) AS total FROM modelo_calidad WHERE nombre = ?");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if ($row['total'] > 0) {
        throw new Exception("Ya existe un modelo con el nombre '$nombre'.");
    }

    // ==================================
    // 2. Insertar modelo predeterminado
    // ==================================
    $stmt = $cn->prepare("INSERT INTO modelo_calidad (nombre, descripcion) VALUES (?, ?)");
    $stmt->bind_param('ss', $nombre, $descripcion);
    if (!$stmt->execute()) {
        throw new Exception("Error al crear el modelo: " . $stmt->error);
    }
    $idModelo = $cn->insert_id;

    // ==================================
    // 3. Validar e insertar métricas nuevas
    // ==================================
    $metricasInsertadas = [];
    if (!empty($_POST['metricas_nuevas']['nombre'])) {
        $nombres = array_map('trim', $_POST['metricas_nuevas']['nombre']);
        $desc = $_POST['metricas_nuevas']['descripcion'];

        // Validar duplicadas en la BD
        $placeholders = implode(',', array_fill(0, count($nombres), '?'));
        $sqlCheck = "SELECT nombre FROM metrica WHERE nombre IN ($placeholders)";
        $stmt = $cn->prepare($sqlCheck);
        $stmt->bind_param(str_repeat('s', count($nombres)), ...$nombres);
        $stmt->execute();
        $existentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $duplicadas = array_column($existentes, 'nombre');

        if (!empty($duplicadas)) {
            throw new Exception("Ya existen métricas con esos nombres: " . implode(', ', $duplicadas));
        }

        // Insertar nuevas métricas (tipo base)
        $stmt = $cn->prepare("INSERT INTO metrica (nombre, descripcion, tipo) VALUES (?, ?, 'base')");
        foreach ($nombres as $i => $nombreMet) {
            $descripcionMet = trim($desc[$i] ?? '');
            if ($nombreMet !== '' && $descripcionMet !== '') {
                $stmt->bind_param('ss', $nombreMet, $descripcionMet);
                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar métrica '$nombreMet': " . $stmt->error);
                }
                $metricasInsertadas[] = $cn->insert_id;
            }
        }
    }

    // ==================================
    // 4. Asociar métricas al modelo
    // ==================================
    $metricasSeleccionadas = array_map('intval', $_POST['metricas'] ?? []);
    $todas = array_merge($metricasSeleccionadas, $metricasInsertadas);

    if (!empty($todas)) {
        $stmt = $cn->prepare("INSERT INTO metrica_modelo_calidad (id_modelo, id_metrica) VALUES (?, ?)");
        foreach ($todas as $idm) {
            $stmt->bind_param('ii', $idModelo, $idm);
            if (!$stmt->execute()) {
                throw new Exception("Error al asociar métrica ID $idm al modelo: " . $stmt->error);
            }
        }
    }

    // ==================================
    // 5. Confirmar transacción
    // ==================================
    $cn->commit();
    $cn->autocommit(true);

    header('Location: modelos.php?msg=' . urlencode('Modelo predeterminado creado correctamente.') . '&type=success');
    exit;
}  catch (Exception $e) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Guardar los datos del formulario
    $_SESSION['form_data'] = [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'metricas' => $_POST['metricas'] ?? []
    ];

    // Guardar mensaje temporal (flash)
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Error: ' . $e->getMessage()
    ];

    $cn->rollback();
    $cn->autocommit(true);

    // Redirigir sin parámetros visibles
    header('Location: modelo.nuevo.predeterminado.php');
    exit;
}

