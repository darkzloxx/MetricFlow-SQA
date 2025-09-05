<?php

setlocale(LC_TIME, 'es_AR.utf8');

/**
 * 
 * Clase para mantener las directivas de sistema.
 * Deben coincidir con las configuraciones del proyecto.
 * 
 * @author Eder dos Santos <esantos@uarg.unpa.edu.ar>
 * 
 */
class Constantes {

    
    const NOMBRE_SISTEMA = "UARGFlow BS";
    
    const WEBROOT = "/var/www/html/uargflow/";
    const APPDIR = "uargflow";
        
    const SERVER = "http://localhost";
    const APPURL = "http://localhost/uargflow";
    const HOMEURL = "http://localhost/uargflow/app/index.php";
    const HOMEAUTH = "http://localhost/uargflow/app/usuarios.php";
    
    const BD_SCHEMA = "bdUsuarios";
    const BD_USERS = "bdUsuarios";
    
}
