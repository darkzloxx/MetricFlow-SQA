<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/BDConexion.Class.php';

$DatosFormulario = $_POST;
$idUsuario = (int)$DatosFormulario["id"];
$todoOk = true;

$bd = BDConexion::getInstancia();
$bd->autocommit(false);
$bd->begin_transaction();

// === Actualizar datos básicos del usuario ===
$query = "UPDATE usuario 
          SET nombre_apellido = ?, email = ?
          WHERE id_usuario = ?";
$stmt = $bd->prepare($query);
$stmt->bind_param('ssi', $DatosFormulario["nombre"], $DatosFormulario["email"], $idUsuario);
if (!$stmt->execute()) {
    $bd->rollback();
    die("Error al actualizar usuario: " . $bd->error);
}
$stmt->close();

// === Eliminar proyectos previos ===
$query = "DELETE FROM usuario_proyecto WHERE id_usuario = ?";
$stmt = $bd->prepare($query);
$stmt->bind_param('i', $idUsuario);
if (!$stmt->execute()) {
    $bd->rollback();
    die("Error al limpiar proyectos: " . $bd->error);
}
$stmt->close();

// === Reinsertar relaciones usuario-proyecto-rol ===
if (isset($_POST['listaProyectos'])) {
    $listaProyectos = $_POST['listaProyectos'];
    $roles = $_POST['rol'];
    $total = count($listaProyectos);
    // Evitar insertar duplicados: map de proyectos ya procesados
    $seenProjects = [];
    for ($i = 0; $i < $total; $i++) {
        $nombreProyecto = trim($listaProyectos[$i] ?? '');
        $nombreRol = trim($roles[$i] ?? '');

        if ($nombreProyecto === '' || $nombreRol === '') continue;

        // Obtener ID del proyecto
        $stmtProyecto = $bd->prepare("SELECT id_proyecto FROM proyecto WHERE nombre = ? LIMIT 1");
        $stmtProyecto->bind_param('s', $nombreProyecto);
        $stmtProyecto->execute();
        $resProyecto = $stmtProyecto->get_result();
        $rowProyecto = $resProyecto->fetch_assoc();
        $stmtProyecto->close();
        if (!$rowProyecto) continue;
        $id_proyecto = (int)$rowProyecto['id_proyecto'];

        // Si ya procesamos este proyecto, saltarlo
        if (isset($seenProjects[$id_proyecto])) continue;

        // Obtener ID del rol
        $stmtRol = $bd->prepare("SELECT id FROM rol WHERE nombre = ? LIMIT 1");
        $stmtRol->bind_param('s', $nombreRol);
        $stmtRol->execute();
        $resRol = $stmtRol->get_result();
        $rowRol = $resRol->fetch_assoc();
        $stmtRol->close();
        if (!$rowRol) continue;
        $id_rol = (int)$rowRol['id'];

        // Insertar relación usuario-proyecto-rol
        $stmtInsert = $bd->prepare(
            "INSERT INTO usuario_proyecto (id_usuario, id_proyecto, id_rol) VALUES (?, ?, ?)"
        );
        $stmtInsert->bind_param('iii', $idUsuario, $id_proyecto, $id_rol);
        if (!$stmtInsert->execute()) {
            $bd->rollback();
            die("Error al insertar usuario_proyecto: " . $bd->error);
        }
        $stmtInsert->close();

        $seenProjects[$id_proyecto] = true;
    }
}

$bd->commit();
$bd->autocommit(true);

// === Refrescar sesión si es el mismo usuario ===
$current = ControlAcceso::usuarioActual();
if ($current && isset($idUsuario) && $current->id === (int)$idUsuario) {
    $emailNuevo = trim($_POST['email'] ?? $current->email);
    $nombreNuevo = trim($_POST['nombre'] ?? $current->nombre);
    ControlAcceso::creaSesion($emailNuevo, $nombreNuevo);
}
?>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
    <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
    <title><?= Constantes::NOMBRE_SISTEMA; ?> - Actualizar Usuario</title>
<style>
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background-color: #fff;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #212529;
        }
    </style>
    </head>

<body>
    <?php include_once '../gui/navbar.php'; ?>
    <div class="container">
        <div class="mb-3">
            <a id="btnVolver" href="usuarios.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
    <div class="card">
        <div class="card-header">
            <h3>Actualizar Usuario</h3>
        </div>
        <div class="card-body">
            <?php if ($todoOk) { ?>
                <div class="alert alert-success" role="alert">
                    Operaci&oacute;n realizada con &eacute;xito.
                </div>
            <?php } else { ?>
                <div class="alert alert-danger" role="alert">
                    Ha ocurrido un error durante la actualización.
                </div>
            <?php } ?>
           
        </div>
    </div>
</div>
<?php include_once '../gui/footer.php'; ?>
</body>
</html>
