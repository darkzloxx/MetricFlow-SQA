<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/BDConexion.Class.php';

$DatosFormulario = $_POST;
$bd = BDConexion::getInstancia();
$bd->autocommit(false);
$bd->begin_transaction();

$idUsuario = (int)$DatosFormulario["id"];
$nombre = trim($DatosFormulario["nombre"] ?? '');
$email = trim($DatosFormulario["email"] ?? '');
$skippedRows = [];
$resultado = false;
$mensaje = "Ha ocurrido un error durante la actualización.";

// ============================
// VALIDACIONES BÁSICAS
// ============================

// Validar nombre
if ($nombre === '') {
    $mensaje = "El nombre no puede estar vacío.";
} elseif (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/u', $nombre)) {
    $mensaje = "El nombre contiene caracteres inválidos.";
} 
// Validar email
elseif (strpos($email, '@gmail.com') === false) {
    $mensaje = "El correo ingresado no es válido, debe tener dominio '@gmail.com'.";
} else {
    // Verificar si ya existe otro usuario con ese email
    $checkEmail = $bd->prepare("SELECT id_usuario FROM usuario WHERE email = ? AND id_usuario <> ?");
    $checkEmail->bind_param('si', $email, $idUsuario);
    $checkEmail->execute();
    $resEmail = $checkEmail->get_result();
    if ($resEmail->num_rows > 0) {
        $mensaje = "Ya existe otro usuario con el correo ingresado.";
        $checkEmail->close();
    } else {
        $checkEmail->close();

        // ============================
        // ACTUALIZAR DATOS DEL USUARIO
        // ============================
        $query = "UPDATE usuario SET nombre_apellido = ?, email = ? WHERE id_usuario = ?";
        $stmt = $bd->prepare($query);
        $stmt->bind_param('ssi', $nombre, $email, $idUsuario);
        if (!$stmt->execute()) {
            $bd->rollback();
            die("Error al actualizar usuario: " . $bd->error);
        }
        $stmt->close();

        // ============================================================
        // Antes de reinicializar relaciones: comprobar si es admin/superadmin
        // ============================================================
        include_once '../modelo/Usuario.Class.php';
        $UsuarioObj = new Usuario($idUsuario);
        $rolesUsuario = $UsuarioObj->getRoles() ?? [];
        $esAdmin = false;
        foreach ($rolesUsuario as $r) {
            $nombreRol = mb_strtolower(trim($r->getNombre() ?? ''), 'UTF-8');
            if (in_array($nombreRol, ['administrador', 'superadmin'], true)) {
                $esAdmin = true;
                break;
            }
        }

        // ============================
        // REINICIALIZAR RELACIONES
        // ============================
        // Si es admin/superadmin preservamos sus relaciones globales (no las borramos).
        if (!$esAdmin) {
            $bd->query("DELETE FROM usuario_proyecto WHERE id_usuario = {$idUsuario}");
            $bd->query("DELETE FROM usuario_rol WHERE id_usuario = {$idUsuario}");
        } else {
            // Mantener roles/proyectos tal cual para administradores.
        }

        // ============================
        // REINSERTAR PROYECTOS Y ROLES
        // ============================
        if (isset($_POST['listaProyectos'])) {
            $listaProyectos = $_POST['listaProyectos'];
            $roles = $_POST['rol'];
            $total = count($listaProyectos);

            for ($i = 0; $i < $total; $i++) {
                $id_proyecto = intval($listaProyectos[$i] ?? 0);
                $id_rol = intval($roles[$i] ?? 0);

                if ($id_proyecto <= 0) {
                    $skippedRows[] = ['index' => $i, 'reason' => 'proyecto inválido o vacío'];
                    continue;
                }
                if ($id_rol <= 0) {
                    $skippedRows[] = ['index' => $i, 'reason' => 'rol inválido o vacío'];
                    continue;
                }

                // Insertar relación usuario-proyecto
                $stmt = $bd->prepare("INSERT INTO usuario_proyecto (id_usuario, id_proyecto, id_rol) VALUES (?, ?, ?)");
                $stmt->bind_param('iii', $idUsuario, $id_proyecto, $id_rol);
                if (!$stmt->execute()) {
                    $bd->rollback();
                    die("Error al insertar usuario_proyecto: " . $bd->error);
                }
                $stmt->close();

                // Insertar relación usuario-rol si no existe
                $stmtCheck = $bd->prepare("SELECT 1 FROM usuario_rol WHERE id_usuario = ? AND id_rol = ? LIMIT 1");
                $stmtCheck->bind_param('ii', $idUsuario, $id_rol);
                $stmtCheck->execute();
                $exists = $stmtCheck->get_result();
                $stmtCheck->close();

                if ($exists->num_rows === 0) {
                    $stmtUR = $bd->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)");
                    $stmtUR->bind_param('ii', $idUsuario, $id_rol);
                    if (!$stmtUR->execute()) {
                        $bd->rollback();
                        die("Error al insertar usuario_rol: " . $bd->error);
                    }
                    $stmtUR->close();
                }
            }
        }

        // ============================
        // COMMIT FINAL
        // ============================
        $bd->commit();
        $bd->autocommit(true);
        $resultado = true;
        $mensaje = "Operación realizada con éxito.";

        if (!empty($skippedRows)) {
            $mensaje .= ' - Algunas filas fueron omitidas: ';
            $detalle = [];
            foreach ($skippedRows as $r) {
                $detalle[] = sprintf('fila %d: %s', $r['index'] + 1, $r['reason']);
            }
            $mensaje .= implode('; ', $detalle);
        }
    }
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
        <a href="usuarios.php" class="btn btn-outline-secondary">
            <span class="oi oi-arrow-left mr-1"></span> Volver
        </a>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>Actualizar Usuario</h3>
        </div>
        <div class="card-body">
            <?php if ($resultado): ?>
                <div class="alert alert-success" role="alert">
                    <?= htmlspecialchars($mensaje); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include_once '../gui/footer.php'; ?>
</body>
</html>
