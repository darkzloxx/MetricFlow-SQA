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

    // Nombre del sistema y rutas básicas usadas por la app
    public const NOMBRE_SISTEMA = 'MetricFlow-SQA';
    // Prefijo del servidor (para redirecciones simples)
    public const SERVER = '';
    // Ruta del login
    public const HOMEURL = '/metricflow/app/index.php';
    // Ruta por defecto luego del login
    public const HOMEAUTH = '/metricflow/app/proyectos.php';

    // Base de datos unificada para toda la app
    public const DB_HOST = 'localhost';
    public const DB_PORT = 3306;
    public const DB_USER = 'root';
    public const DB_PASS = 'root';
    public const DB_NAME = 'bd_codevit2';

}
