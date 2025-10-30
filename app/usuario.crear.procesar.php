<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::PERMISO_USUARIOS);
include_once '../modelo/BDConexion.Class.php';
$DatosFormulario = $_POST;
BDConexion::getInstancia()->autocommit(false);
BDConexion::getInstancia()->begin_transaction();

// init helper for skipped rows
$skippedRows = [];

// Debug: if no proyectos/roles are posted, log the POST payload to a temp file for inspection
if (empty($_POST['listaProyectos'])) {
	$logPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'usuario_crear_post.log';
	$entry = "---- " . date('Y-m-d H:i:s') . " ----\n" . print_r($_POST, true) . "\n";
	@file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
}

$correo = $DatosFormulario["mail"];

$resultado = "";
$mensaje = "Ha ocurrido un error.";

$query = "select * from usuario where email = '{$correo}'";
$consulta = BDConexion::getInstancia()->query($query);

if ($consulta->num_rows > 0){
	$resultado = false;
	$mensaje = "Ya existe un usuario con el correo ingresado";
} else {
	
	if(strpos($correo, "@gmail.com") === false){
		$resultado = false;
		$mensaje = "El correo ingresado no es valido, debe ser dominio ''@gmail.com''";
	} else {

		$query = "INSERT INTO usuario "
				. "VALUES (null,'{$DatosFormulario["nombre"]}','{$DatosFormulario["mail"]}')";
		$consulta = BDConexion::getInstancia()->query($query);
		if (!$consulta) {
			BDConexion::getInstancia()->rollback();
			//arrojar una excepcion
			die(BDConexion::getInstancia()->errno);
		}
		
		$idUsuario = BDConexion::getInstancia()->insert_id;
		
		if (isset($_POST['listaProyectos'])) {
			$listaProyectos = $_POST['listaProyectos'];
			$rol = $_POST['rol'];
			$cont = count($listaProyectos);
				for ($i = 0; $i < $cont; ++$i) {
					$projVal = trim($listaProyectos[$i] ?? '');
					$roleVal = trim($rol[$i] ?? '');
					if ($projVal === '') {
						// nothing to add for this row
						$skippedRows[] = [ 'index' => $i, 'reason' => 'proyecto vacío' ];
						continue;
					}

					// Asumimos que ahora recibimos IDs en los arrays: validar existencia
					$id_proyecto = intval($projVal);
					$projCheck = BDConexion::getInstancia()->query("SELECT 1 FROM proyecto WHERE id_proyecto = " . intval($id_proyecto) . " LIMIT 1");
					if (!($projCheck && $projCheck->num_rows > 0)) {
						$skippedRows[] = [ 'index' => $i, 'reason' => 'proyecto inválido' ];
						continue;
					}

					// Validar rol (también esperamos ID)
					$id_rol = null;
					if ($roleVal !== '') {
						$id_rol = intval($roleVal);
						$roleCheck = BDConexion::getInstancia()->query("SELECT 1 FROM rol WHERE id = " . intval($id_rol) . " LIMIT 1");
						if (!($roleCheck && $roleCheck->num_rows > 0)) {
							$skippedRows[] = [ 'index' => $i, 'reason' => 'rol inválido o vacío' ];
							continue;
						}
					} else {
						$skippedRows[] = [ 'index' => $i, 'reason' => 'rol inválido o vacío' ];
						continue;
					}

					// Sólo insertar si tenemos ids válidos
					if (!empty($idUsuario) && !empty($id_proyecto) && !empty($id_rol)) {
						// Evitar duplicados (mismo usuario-proyecto)
						$checkSql = "SELECT 1 FROM usuario_proyecto WHERE id_usuario = " . intval($idUsuario) . " AND id_proyecto = " . intval($id_proyecto) . " LIMIT 1";
						$exists = BDConexion::getInstancia()->query($checkSql);
						if (!($exists && $exists->num_rows > 0)) {
							$sql = "INSERT INTO usuario_proyecto (id_usuario, id_proyecto, id_rol) VALUES (" .
								intval($idUsuario) . "," . intval($id_proyecto) . "," . intval($id_rol) . ")";
							$consultaGestion = BDConexion::getInstancia()->query($sql);
							if (!$consultaGestion) {
								BDConexion::getInstancia()->rollback();
								die('DB error: ' . BDConexion::getInstancia()->error);
							}
							// Además, asegurar que la tabla usuario_rol tenga el rol asignado al usuario
							$checkUR = BDConexion::getInstancia()->query("SELECT 1 FROM usuario_rol WHERE id_usuario = " . intval($idUsuario) . " AND id_rol = " . intval($id_rol) . " LIMIT 1");
							if (!($checkUR && $checkUR->num_rows > 0)) {
								$insUR = BDConexion::getInstancia()->query("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (" . intval($idUsuario) . "," . intval($id_rol) . ")");
								if (!$insUR) {
									BDConexion::getInstancia()->rollback();
									die('DB error (usuario_rol): ' . BDConexion::getInstancia()->error);
								}
							}
						}
					}
				}
		
		} 
		
		BDConexion::getInstancia()->commit();
		BDConexion::getInstancia()->autocommit(true);
		$resultado = true;
		$mensaje = "Operacion Realizada con Exito";
		// Si hubo filas omitidas, agregar detalle al mensaje
		if (!empty($skippedRows)) {
			$mensaje .= ' - Algunas filas fueron omitidas: ';
			$parts = [];
			foreach ($skippedRows as $r) {
				$parts[] = sprintf('fila %d: %s', $r['index']+1, $r['reason']);
			}
			$mensaje .= implode('; ', $parts);
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
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Crear Usuario</title>
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
            <p></p>
            <div class="card">
                <div class="card-header">
                    <h3>Crear Usuario</h3>
                </div>
                <div class="card-body">
                    <?php if ($resultado) { ?>
                        <div class="alert alert-success" role="alert">
                            <?= $mensaje; ?>
                        </div>
                    <?php } ?>   
                    <?php if (!$resultado) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $mensaje; ?>
                        </div>
                    <?php } ?>
                  
                </div>
            </div>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>