<?php
include_once '../lib/ControlAcceso.Class.php';
// Acceso: Admin/SuperAdmin SIEMPRE. De lo contrario, requiere permiso de gestión de modelo
if (!ControlAcceso::esAdminGlobal() && !ControlAcceso::verificaPermiso(PermisosSistema::GESTION_MODELO_CALIDAD)) {
    header('Location: modelos.php?msg=' . urlencode('Acceso restringido: requiere SUPERADMIN/ADMIN o permiso Gestión de Modelo de Calidad.') . '&type=danger');
    exit;
}
include_once '../modelo/ColeccionUsuarios.php';
$ColeccionUsuarios = new ColeccionUsuarios();


?>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../lib/bootstrap-4.1.1-dist/css/bootstrap.css" />
        <link rel="stylesheet" href="../lib/open-iconic-master/font/css/open-iconic-bootstrap.css" />
        <script type="text/javascript" src="../lib/JQuery/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="../lib/bootstrap-4.1.1-dist/js/bootstrap.min.js"></script>
        <title><?= Constantes::NOMBRE_SISTEMA; ?> - Cargar Modelo</title>
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
            <a id="btnVolver" href="modelos.php" class="btn btn-outline-secondary">
                <span class="oi oi-arrow-left mr-1"></span> Volver
            </a>
        </div>
            <form action="modelo.crear.procesar.php" method="post">
                <div class="card">
                    <div class="card-header">
                        <h3>Cargar Modelo</h3>
                        <p>
                            Complete los campos a continuaci&oacute;n. 
                            Luego, presione el bot&oacute;n <b>Confirmar</b>.<br />
                            Si desea cancelar, presione el bot&oacute;n <b>Cancelar</b>.
                        </p>
                    </div>
                    <div class="card-body">
                        <h4>Propiedades</h4>
                        <div class="form-group">
                            <label for="proyecto">Proyecto</label>
                            <br>
                            <select id="proyecto" name="proyecto" class="form-control">
                            <?php 
                            // Si es admin global, listar todos los proyectos. Si no, solo los asignados al usuario.
                            if (ControlAcceso::esAdminGlobal()) {
                                $sqlProy = "SELECT id_proyecto, nombre AS proyecto FROM proyecto ORDER BY nombre";
                            } else {
                                $uid = (int)$_SESSION['usuario']->id;
                                $sqlProy = "SELECT p.id_proyecto, p.nombre AS proyecto
                                            FROM usuario_proyecto up
                                            JOIN usuario u ON up.id_usuario = u.id
                                            JOIN proyecto p ON up.id_proyecto = p.id_proyecto
                                            WHERE u.id = {$uid}
                                            ORDER BY p.nombre";
                            }
                            $rsProy = BDConexion::getInstancia()->query($sqlProy);
                            $proyectos = $rsProy ? $rsProy->fetch_all(MYSQLI_ASSOC) : [];
                            foreach ($proyectos as $Proyec) { ?>
                              <option value="<?= $Proyec['id_proyecto']; ?>"><?= $Proyec['proyecto']; ?></option>
                            <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inputMail">Modelo asociado</label>
                            <br>
                            <select id="modelo" name="modelo" class="form-control">
                                                        <?php 
                                                        $qModelos = "SELECT * FROM modelo_calidad ORDER BY nombre"; 
                                                        $rsModelos = BDConexion::getInstancia()->query($qModelos);
                                                        $modelos = $rsModelos ? $rsModelos->fetch_all(MYSQLI_ASSOC) : []; 
                                                        foreach ($modelos as $m) { ?>
                                                            <option value="<?= $m['id_modelo']; ?>" title="<?= htmlspecialchars($m['descripcion']); ?>"><?= $m['nombre']; ?></option>
                                                        <?php } ?>
                            </select>
                        </div>
                        <hr />
                <br>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-success">
                            <span class="oi oi-check"></span> Confirmar
                        </button>
                        <a href="modelos.php">
                            <button type="button" class="btn btn-outline-danger">
                                <span class="oi oi-x"></span> Cancelar
                            </button>
                        </a>
                    </div>
                </div>
                </div>
            </form>
        </div>
        <?php include_once '../gui/footer.php'; ?>
    </body>
</html>