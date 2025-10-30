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
    public const DB_PORT = 3308;
    public const DB_USER = 'root';
    public const DB_PASS = '';
    public const DB_NAME = 'bd_codevit';

    // Permiso del dashboard (según tu dump, todos los roles lo tienen)
    public const PERMISO_DASHBOARD = 'Visualización de Dashboard Inicial';
    // Otros permisos frecuentes en navegación
    public const PERMISO_ABM_USUARIOS = 'ABM Usuarios';
    public const PERMISO_ABM_PROYECTOS = 'ABM Proyectos';
}
