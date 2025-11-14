<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::ABM_ITERACIONES);

include_once '../modelo/BDConexion.Class.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cn = BDConexion::getInstancia();
$cn->autocommit(false);
$cn->begin_transaction();

$Datos = $_POST;

// ==============================
// 📥 Datos del formulario
// ==============================
$idProyecto   = (int)$Datos["id_proyecto"];
$numero       = (int)$Datos["numero"];
$fechaInicio  = $Datos["fecha_inicio"] ?? "";
$fechaFin     = $Datos["fecha_fin"] ?? "";
$objetivo     = trim($Datos["objetivo"] ?? "");
$idFase       = (int)$Datos["fase"];

// Guardar temporal para repoblar
$_SESSION['form_data'] = $Datos;

$hoy = date('Y-m-d');

$ok = false;
$mensaje = "Ha ocurrido un error.";

// ==============================
// 🚨 Validaciones básicas
// ==============================
if (!$idProyecto || !$idFase || $fechaInicio === "" || $fechaFin === "") {
    $mensaje = "Debe completar todos los campos obligatorios.";
    goto fin;
}

if ($fechaInicio < $hoy) {
    $mensaje = "La fecha de inicio no puede ser anterior a la fecha actual.";
    goto fin;
}

if ($fechaFin <= $fechaInicio) {
    $mensaje = "La fecha de fin debe ser posterior a la fecha de inicio.";
    goto fin;
}

// ==============================
// 🚨 Validar solapamiento con otras iteraciones del mismo proyecto
// ==============================
//
// Regla universal de solapamiento:
// (inicioNuevo <= finExistente) AND (finNuevo >= inicioExistente)
//
$sqlSolap = "
    SELECT COUNT(*) AS c
    FROM iteracion
    WHERE id_proyecto = ?
      AND (DATE(?) <= fecha_fin AND DATE(?) >= fecha_inicio)
";

$stmtSolap = $cn->prepare($sqlSolap);
$stmtSolap->bind_param("iss", $idProyecto, $fechaInicio, $fechaFin);
$stmtSolap->execute();
$rSolap = $stmtSolap->get_result()->fetch_assoc();
$stmtSolap->close();

if ($rSolap["c"] > 0) {
    $mensaje = "Las fechas ingresadas se superponen con otra iteración existente. No se permiten solapamientos.";
    goto fin;
}


// ==============================
// 🚨 Validar número duplicado en la misma fase
// ==============================
$sqlCheck = "
    SELECT COUNT(*) AS c
    FROM iteracion
    WHERE id_proyecto = ?
      AND id_fase = ?
      AND numero_iteracion = ?
";

$stmtChk = $cn->prepare($sqlCheck);
$stmtChk->bind_param("iii", $idProyecto, $idFase, $numero);
$stmtChk->execute();
$rChk = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if ($rChk["c"] > 0) {
    $mensaje = "Ya existe una iteración con el mismo número en la fase seleccionada.";
    goto fin;
}

// ==============================
// 🧮 Si número = 0 → calcular automáticamente
// ==============================
if ($numero === 0) {
    $sqlNext = "
        SELECT COALESCE(MAX(numero_iteracion), 0) + 1 AS siguiente
        FROM iteracion
        WHERE id_proyecto = ?
    ";
    $stmtNext = $cn->prepare($sqlNext);
    $stmtNext->bind_param("i", $idProyecto);
    $stmtNext->execute();
    $numero = (int)$stmtNext->get_result()->fetch_assoc()['siguiente'];
    $stmtNext->close();
}

// ==============================
// 🟢 Insertar iteración
// ==============================
$sqlInsert = "
    INSERT INTO iteracion (id_proyecto, numero_iteracion, fecha_inicio, fecha_fin, objetivo, id_fase)
    VALUES (?, ?, ?, ?, ?, ?)
";

$stmtIns = $cn->prepare($sqlInsert);
$stmtIns->bind_param("iisssi", $idProyecto, $numero, $fechaInicio, $fechaFin, $objetivo, $idFase);

if ($stmtIns->execute()) {
    $cn->commit();
    $ok = true;
    $mensaje = "Iteración creada exitosamente.";
} else {
    $cn->rollback();
    $mensaje = "Error al crear la iteración: " . $cn->error;
}

$stmtIns->close();
$cn->autocommit(true);

unset($_SESSION['form_data']);

fin:
?>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Iteración</title>
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css"/>
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css"/>
</head>

<body>
<?php include_once '../gui/navbar.php'; ?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header"><h3>Crear Iteración</h3></div>

        <div class="card-body">
            <div class="alert alert-<?= $ok ? 'success' : 'danger'; ?>">
                <?= htmlspecialchars($mensaje); ?>
            </div>

            <a href="iteraciones.php" class="btn btn-outline-primary">
                <span class="oi oi-arrow-left"></span> Volver
            </a>
        </div>
    </div>
</div>

<?php include_once '../gui/footer.php'; ?>
</body>
</html>
