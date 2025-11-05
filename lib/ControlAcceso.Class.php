<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../modelo/BDConexion.Class.php';
require_once __DIR__ . '/Constantes.Class.php';

class PermisosSistema
{
    // Mapeo de permisos a los nombres de la BD bd_codevit
    public const DASHBOARD = 'Visualización de Dashboard Inicial';
    public const ABM_USUARIOS = 'ABM Usuarios';
    public const ABM_PROYECTOS = 'ABM Proyectos';
    // Permiso para ver las métricas individuales / dashboard exclusivo
    public const VISUALIZACION_METRICAS = 'Visualización de Métricas';
    public const GESTION_MODELO_CALIDAD = 'Gestión de Modelo de Calidad';
    public const GESTION_METRICAS = 'Gestión de Métricas';
    // Alias de compatibilidad con código antiguo
    public const PERMISO_USUARIOS = self::ABM_USUARIOS;
    public const PERMISO_PERMISOS = 'ABM Permisos';
    public const PERMISO_ROLES = 'ABM Roles';

    // Rol por defecto para auto-registro
    public const ROL_ESTANDAR = 'Espectador';
}

class PermisoSesion
{
    public $id;
    public $nombre;
}

class RolSesion
{
    public $id;
    public $nombre;
    /** @var PermisoSesion[] */
    public $permisos = [];

    public function cargarPermisos(): void
    {
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

class UsuarioSesion
{
    public $id; // id_usuario
    public $email;
    public $nombre; // nombre_apellido
    /** @var RolSesion[] */
    public $roles = [];

    /** @var object[] lista de proyectos con sus roles asociados */
    public $proyectos = [];

    public function __construct(string $email_, ?string $nombre_ = null)
    {
        $this->email = $email_;
        $this->nombre = $nombre_ ?? '';

        $existente = $this->buscarUsuarioBd();
        if (!$existente) {
            // auto-registro básico asignando rol 'Espectador'
            $this->registrarUsuario();
        }
        $this->cargarRoles();
    }

    private function buscarUsuarioBd(): bool
    {
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

    private function registrarUsuario(): void
    {
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

    private function cargarRoles(): void
    {
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

class ControlAcceso
{
    /**
     * Verifica si el usuario autenticado posee un rol global Administrador o SuperAdmin.
     */
    public static function esAdminGlobal(): bool
    {
        $usr = self::usuarioActual();
        if (!$usr || !isset($usr->roles) || !is_array($usr->roles)) {
            return false;
        }
        foreach ($usr->roles as $r) {
            $rolName = strtoupper(trim((string)($r->nombre ?? '')));
            if ($rolName === 'ADMINISTRADOR' || $rolName === 'SUPERADMIN') {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica si el usuario autenticado posee un rol global SuperAdmin.
     */
    public static function esSuperAdminGlobal(): bool
    {
        $usr = self::usuarioActual();
        if (!$usr || !isset($usr->roles) || !is_array($usr->roles)) {
            return false;
        }
        foreach ($usr->roles as $r) {
            $rolName = strtoupper(trim((string)($r->nombre ?? '')));
            if ($rolName === 'SUPERADMIN') {
                return true;
            }
        }
        return false;
    }
    public static function requierePermiso(string $permisoNombre): void
    {
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

    public static function verificaPermiso(string $permisoNombre): bool
    {
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

    public static function verificaLogin(): void
    {
        if (!isset($_SESSION['usuario']) || !($_SESSION['usuario'] instanceof UsuarioSesion)) {
            header('Location: ' . Constantes::HOMEURL);
            exit;
        }
    }

    /**
     * Retorna el usuario de sesión o null si no hay login válido
     */
    public static function usuarioActual(): ?UsuarioSesion
    {
        return (isset($_SESSION['usuario']) && ($_SESSION['usuario'] instanceof UsuarioSesion))
            ? $_SESSION['usuario']
            : null;
    }
   public static function rolUsuarioEnProyecto($idProyecto)
{
    if (!isset($_SESSION['usuario']) || !is_object($_SESSION['usuario'])) {
        return null;
    }
    $usuario = $_SESSION['usuario'];
    if (!isset($usuario->proyectos) || !is_array($usuario->proyectos)) {
        return null;
    }
    foreach ($usuario->proyectos as $p) {
        // ⚙️ Cambiado id_proyecto → id
        if ((int)$p->id === (int)$idProyecto) {
            if (isset($p->roles) && is_array($p->roles) && count($p->roles)) {
                return $p->roles[0]->nombre; // devuelve el nombre del primer rol asignado
            }
        }
    }
    return null;
}


    /**
     * Lista los IDs de proyectos asignados al usuario actual (usuario_proyecto)
     * @return int[]
     */
    public static function proyectosAsignadosDelUsuario(): array
    {
        $usr = self::usuarioActual();
        if (!$usr) {
            return [];
        }
        $cn = BDConexion::getConexion();
        $sql = 'SELECT id_proyecto FROM usuario_proyecto WHERE id_usuario = ?';
        $stmt = $cn->prepare($sql);
        $stmt->bind_param('i', $usr->id);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id_proyecto'];
        }
        $stmt->close();
        return $ids;
    }

    /**
     * Verifica si el usuario actual tiene un rol en el proyecto indicado
     */
    public static function usuarioPerteneceAProyecto(int $idProyecto): bool
    {
        $usr = self::usuarioActual();
        if (!$usr || $idProyecto <= 0) {
            return false;
        }
        $cn = BDConexion::getConexion();
        $sql = 'SELECT 1 FROM usuario_proyecto WHERE id_usuario = ? AND id_proyecto = ? LIMIT 1';
        $stmt = $cn->prepare($sql);
        $stmt->bind_param('ii', $usr->id, $idProyecto);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok = (bool)$res->fetch_row();
        $stmt->close();
        return $ok;
    }

    /**
     * Requiere que el usuario esté autenticado y pertenezca al proyecto
     * Si no cumple, redirige a HOMEAUTH o devuelve 403 según $emitir403.
     */
    public static function requiereProyecto(int $idProyecto, bool $emitir403 = false): void
    {
        self::verificaLogin();
        // Permitir a Administrador / SuperAdmin o a quien tenga ABM_PROYECTOS
        $usr = self::usuarioActual();
        if ($usr) {
            // roles globales (RolSesion->nombre)
            if (isset($usr->roles) && is_array($usr->roles)) {
                foreach ($usr->roles as $r) {
                    $rolName = mb_strtolower(trim($r->nombre ?? ''), 'UTF-8');
                    if (in_array($rolName, ['administrador', 'superadmin'], true)) {
                        return; // acceso permitido
                    }
                }
            }
            // permiso global para ver/administrar proyectos
            if (self::verificaPermiso(PermisosSistema::ABM_PROYECTOS)) {
                return;
            }
        }

        if (!self::usuarioPerteneceAProyecto($idProyecto)) {
            if ($emitir403) {
                http_response_code(403);
                echo 'Acceso denegado al proyecto';
                exit;
            }
            header('Location: ' . Constantes::HOMEAUTH);
            exit;
        }
    }

    public static function creaSesion(string $email, ?string $nombre = null): void
    {
        $_SESSION['usuario'] = new UsuarioSesion($email, $nombre);
        $usuario = $_SESSION['usuario'];

        // ============================================
        // 🔹 Cargar proyectos y roles asociados
        // ============================================
        $cn = BDConexion::getConexion();

        // Ahora la tabla `usuario_proyecto` tiene `id_rol` como FK numérica.
        $sql = "
    SELECT 
        p.id_proyecto,
        p.nombre AS proyecto,
        r.id AS id_rol,
        r.nombre AS rol_name
    FROM usuario_proyecto up
    JOIN proyecto p ON p.id_proyecto = up.id_proyecto
    LEFT JOIN rol r ON r.id = up.id_rol
    WHERE up.id_usuario = ?
";
        $stmt = $cn->prepare($sql);
        $stmt->bind_param('i', $usuario->id);
        $stmt->execute();
        $res = $stmt->get_result();

        $usuario->proyectos = [];
        while ($row = $res->fetch_assoc()) {
            $idProyecto = (int)$row['id_proyecto'];
            if (!isset($usuario->proyectos[$idProyecto])) {
                $usuario->proyectos[$idProyecto] = (object)[
                    'id' => $idProyecto,
                    'nombre' => $row['proyecto'],
                    'roles' => []
                ];
            }

            // Agregamos el rol (si existe) a la lista del proyecto
            if (!empty($row['id_rol']) || !empty($row['rol_name'])) {
                $usuario->proyectos[$idProyecto]->roles[] = (object)[
                    'id' => (int)$row['id_rol'],
                    'nombre' => $row['rol_name']
                ];
            }
        }
        $stmt->close();

        // Convertir a array indexado
        $usuario->proyectos = array_values($usuario->proyectos);
    }
}
