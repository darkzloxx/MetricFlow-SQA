<?php

/**
 * Description of BDConexion
 * 
 * Esta clase implementa la conexión a una base de datos mediante el patrón Singleton.
 *
 * @author Eder dos Santos <esantos@uarg.unpa.edu.ar>
 * 
 * @uses mysqli Libería estándar de PHP para acceder a bases de datos MySQL
 * @see https://es.wikipedia.org/wiki/Singleton
 * 
 */
require_once __DIR__ . '/../lib/Constantes.Class.php';

class BDConexion {
    private static ?mysqli $cn = null;

    public static function getConexion(): mysqli {
        if (self::$cn instanceof mysqli) {
            return self::$cn;
        }
        $cn = @new mysqli(
            Constantes::DB_HOST,
            Constantes::DB_USER,
            Constantes::DB_PASS,
            Constantes::DB_NAME,
            Constantes::DB_PORT
        );
        if ($cn->connect_errno) {
            throw new RuntimeException('Error de Conexion a la Base de Datos: ' . $cn->connect_errno . ' - ' . $cn->connect_error);
        }
        $cn->set_charset('utf8mb4');
        self::$cn = $cn;
        return self::$cn;
    }

    // Compatibilidad con código existente
    public static function getInstancia(): mysqli {
        return self::getConexion();
    }
}