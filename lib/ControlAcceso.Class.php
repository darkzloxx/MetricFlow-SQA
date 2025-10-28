<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../modelo/BDConexion.Class.php';
require_once __DIR__ . '/Constantes.Class.php';

class PermisosSistema {
    // Mapeo de permisos a los nombres de la BD bd_codevit
    public const DASHBOARD = 'Visualización de Dashboard Inicial';
    public const ABM_USUARIOS = 'ABM Usuarios';
    public const ABM_PROYECTOS = 'ABM Proyectos';
    // Alias de compatibilidad con código antiguo
    public const PERMISO_USUARIOS = self::ABM_USUARIOS;
    public const PERMISO_PERMISOS = 'ABM Permisos';
    public const PERMISO_ROLES = 'ABM Roles';

    // Rol por defecto para auto-registro
    public const ROL_ESTANDAR = 'Espectador';
}

class PermisoSesion {
    public $id;
    public $nombre;
}

class RolSesion {
    public $id;
    public $nombre;
    /** @var PermisoSesion[] */
    public $permisos = [];

    public function cargarPermisos(): void {
        $cn = BDConexion::getConexion();
        $sql = "SELECT p.id, p.nombre FROM permiso p JOIN rol_permiso rp ON p.id = rp.id_permiso WHERE rp.id_rol = ?";
        $stmt = $cn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $res = $stmt->get_result();
        $this->permisos = [];
        while ($row = $res->fetch_assoc()) {
            $perm = new PermisoSesion();
            $perm->id = (int)$row['id'];
            $perm->nombre = $row['nombre'];
            $this->permisos[] = $perm;
        }
        $stmt->close();
    }
}

class UsuarioSesion {
    public $id; // id_usuario
    public $email;
    public $nombre; // nombre_apellido
    /** @var RolSesion[] */
    public $roles = [];

    public function __construct(string $email_, ?string $nombre_ = null) {
        $this->email = $email_;
        $this->nombre = $nombre_ ?? '';

        $existente = $this->buscarUsuarioBd();
        if (!$existente) {
            // auto-registro básico asignando rol 'Espectador'
            $this->registrarUsuario();
        }
        $this->cargarRoles();
    }

    private function buscarUsuarioBd(): bool {
        $cn = BDConexion::getConexion();
        $sql = "SELECT id_usuario, nombre_apellido, email FROM usuario WHERE email = ? LIMIT 1";
        $stmt = $cn->prepare($sql);
        $stmt->bind_param('s', $this->email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row) {
            $this->id = (int)$row['id_usuario'];
            $this->nombre = $row['nombre_apellido'];
            return true;
        }
        return false;
    }

    private function registrarUsuario(): void {
        $cn = BDConexion::getConexion();
        $cn->begin_transaction();
        try {
            // Inserta usuario
            $sqlIns = "INSERT INTO usuario (nombre_apellido, email) VALUES (?, ?)";
            $stmt = $cn->prepare($sqlIns);
            $stmt->bind_param('ss', $this->nombre, $this->email);
            $stmt->execute();
            $stmt->close();
            $this->id = (int)$cn->insert_id;

            // Busca rol estándar
            $sqlRol = "SELECT id FROM rol WHERE nombre = ? LIMIT 1";
            $stmt2 = $cn->prepare($sqlRol);
            $rolNombre = PermisosSistema::ROL_ESTANDAR;
            $stmt2->bind_param('s', $rolNombre);
            $stmt2->execute();
            $resRol = $stmt2->get_result();
            $rowRol = $resRol->fetch_assoc();
            $stmt2->close();

            if ($rowRol) {
                $idRol = (int)$rowRol['id'];
                $sqlUR = "INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)";
                $stmt3 = $cn->prepare($sqlUR);
                $stmt3->bind_param('ii', $this->id, $idRol);
                $stmt3->execute();
                $stmt3->close();
            }

            $cn->commit();
        } catch (Throwable $e) {
            $cn->rollback();
            throw $e;
        }
    }

    private function cargarRoles(): void {
        $cn = BDConexion::getConexion();
        $sql = "SELECT r.id, r.nombre FROM usuario_rol ur JOIN rol r ON r.id = ur.id_rol WHERE ur.id_usuario = ?";
        $stmt = $cn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $res = $stmt->get_result();
        $this->roles = [];
        while ($row = $res->fetch_assoc()) {
            $rol = new RolSesion();
            $rol->id = (int)$row['id'];
            $rol->nombre = $row['nombre'];
            $rol->cargarPermisos();
            $this->roles[] = $rol;
        }
        $stmt->close();
    }
}

class ControlAcceso {
    public static function requierePermiso(string $permisoNombre): void {
        if (!isset($_SESSION['usuario']) || !($_SESSION['usuario'] instanceof UsuarioSesion)) {
            header('Location: ' . Constantes::HOMEURL);
            exit;
        }
        if (!self::verificaPermiso($permisoNombre)) {
            http_response_code(403);
            echo 'Acceso denegado';
            exit;
        }
    }

    public static function verificaPermiso(string $permisoNombre): bool {
        if (!isset($_SESSION['usuario']) || !($_SESSION['usuario'] instanceof UsuarioSesion)) {
            return false;
        }
        $Usuario = $_SESSION['usuario'];
        foreach ($Usuario->roles as $Rol) {
            foreach ($Rol->permisos as $Permiso) {
                if ($Permiso->nombre === $permisoNombre) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function verificaLogin(): void {
        if (!isset($_SESSION['usuario']) || !($_SESSION['usuario'] instanceof UsuarioSesion)) {
            header('Location: ' . Constantes::HOMEURL);
            exit;
        }
    }

    public static function creaSesion(string $email, ?string $nombre = null): void {
        $_SESSION['usuario'] = new UsuarioSesion($email, $nombre);
    }
}
