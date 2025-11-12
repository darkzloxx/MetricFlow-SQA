-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 06-11-2025 a las 01:33:10
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12
DROP DATABASE IF EXISTS bd_codevit;
CREATE DATABASE bd_codevit CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE bd_codevit;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;



CREATE TABLE `fase` (
  `id_fase` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `fase`
--

INSERT INTO `fase` (`id_fase`, `nombre`) VALUES
(1, 'Inicio'),
(2, 'Elaboración'),
(3, 'Construcción'),
(4, 'Transición');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `iteracion`
--

CREATE TABLE `iteracion` (
  `id_iteracion` int(11) NOT NULL,
  `id_proyecto` int(11) NOT NULL,
  `numero_iteracion` int(11) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `objetivo` text DEFAULT NULL,
  `id_fase` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `iteracion`
--

INSERT INTO `iteracion` (`id_iteracion`, `id_proyecto`, `numero_iteracion`, `fecha_inicio`, `fecha_fin`, `objetivo`, `id_fase`) VALUES
(1, 1, 1, '2025-08-19', '2025-09-09', 'Definición del alcance y plan de calidad.', 1),
(2, 1, 1, '2025-09-10', '2025-09-23', 'Primera iteración de elaboración: definición de métricas.', 2),
(3, 1, 2, '2025-09-24', '2025-10-10', 'Segunda iteración de elaboración: revisión del modelo híbrido.', 2),
(4, 1, 1, '2025-10-11', '2025-10-28', 'Implementación del módulo de métricas.', 3),
(5, 1, 2, '2025-10-29', '2025-11-07', 'Validación del modelo híbrido.', 3),
(6, 1, 3, '2025-11-08', '2025-11-14', 'Integración del dashboard de calidad.', 3),
(7, 1, 1, '2025-11-15', '2025-11-28', 'Despliegue y cierre del proyecto.', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `iteracion_tarea`
--

CREATE TABLE `iteracion_tarea` (
  `id_iteracion` int(11) NOT NULL,
  `id_tarea` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metrica`
--

CREATE TABLE `metrica` (
  `id_metrica` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('base','personalizada') NOT NULL DEFAULT 'base'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metrica`
--

INSERT INTO `metrica` (`id_metrica`, `nombre`, `descripcion`, `tipo`) VALUES
(1, 'Revisiones de documentos', 'Cantidad de documentos revisados frente a los planificados.','base'),
(2, 'Revisiones técnicas formales (RTF)', 'Número de revisiones técnicas realizadas.','base'),
(3, 'Reuniones de equipo', 'Número total de reuniones efectuadas en la iteración.','base'),
(4, 'Tasa de corrección de defectos', 'Defectos corregidos frente a defectos reportados.','base'),
(5, 'Horas trabajadas por iteración', 'Cantidad total de horas registradas en la iteración.','base'),
(6, 'Cumplimiento de actividades previstas', 'Número de actividades completadas frente a las planificadas.','base'),
(7, 'Cobertura de pruebas', 'Cantidad de pruebas ejecutadas frente a las planificadas.','base'),
(8, 'Defectos detectados', 'Número total de defectos encontrados durante pruebas o revisión.','base'),
(9, 'Defectos corregidos', 'Cantidad de defectos corregidos durante la iteración.','base'),
(10, 'Requisitos implementados', 'Número de requisitos implementados durante la iteración.','base'),
(11, 'Retrabajos realizados', 'Cantidad de tareas repetidas por errores o ajustes.','base'),
(12, 'Incidencias reportadas', 'Número total de incidencias registradas.','base'),
(13, 'Iteraciones completadas', 'Cantidad de iteraciones finalizadas dentro del proyecto.','base'),
(14, 'Revisiones realizadas', 'Número total de revisiones completadas en el ciclo.','base'),
(15, 'Casos de prueba ejecutados', 'Cantidad total de casos de prueba efectivamente ejecutados.','base'),
(16, 'Casos de prueba exitosos', 'Número de casos de prueba que pasaron exitosamente.','base'),
(17, 'Defectos postentrega', 'Cantidad de defectos reportados después de la entrega.','base');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metrica_iteracion`
--

CREATE TABLE `metrica_iteracion` (
  `id_metrica` int(11) NOT NULL,
  `id_iteracion` int(11) NOT NULL,
  `valor_planificado` int(11) DEFAULT NULL,
  `valor_ejecutado` int(11) DEFAULT NULL,
  `umbral_desviacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metrica_iteracion`
--

INSERT INTO `metrica_iteracion` (`id_metrica`, `id_iteracion`, `valor_planificado`, `valor_ejecutado`, `umbral_desviacion`) VALUES
(1, 3, 8, 8, 10),
(1, 4, 9, 9, 10),
(1, 5, 6, 1, 10),
(2, 3, 1, 2, 10),
(2, 4, 1, 1, 10),
(2, 5, 0, 0, 10),
(3, 3, 2, 2, 15),
(3, 4, 3, 3, 15),
(3, 5, 2, 0, 15),
(5, 3, 130, 143, 15),
(5, 4, 120, 137, 15),
(5, 5, 70, 6, 15),
(6, 3, 22, 22, 10),
(6, 4, 18, 20, 10),
(6, 5, 16, 2, 10),
(7, 3, 0, 3, 10),
(7, 4, 3, 75, 10),
(7, 5, 30, 0, 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metrica_modelo_calidad`
--

CREATE TABLE `metrica_modelo_calidad` (
  `id_metrica` int(11) NOT NULL,
  `id_modelo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metrica_modelo_calidad`
--

INSERT INTO `metrica_modelo_calidad` (`id_metrica`, `id_modelo`) VALUES
(1, 1),
(1, 2),
(1, 6),
(1, 7),
(2, 2),
(2, 6),
(2, 7),
(3, 1),
(3, 4),
(3, 6),
(3, 7),
(4, 3),
(4, 6),
(5, 1),
(5, 4),
(5, 6),
(5, 7),
(6, 1),
(6, 6),
(6, 7),
(7, 6),
(7, 7),
(8, 5),
(8, 6),
(9, 5),
(9, 6),
(10, 1),
(10, 6),
(11, 3),
(11, 6),
(12, 3),
(12, 6),
(13, 4),
(13, 6),
(14, 2),
(14, 6),
(15, 5),
(15, 6),
(16, 5),
(16, 6),
(17, 3),
(17, 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metrica_proyecto_modelo`
--

CREATE TABLE `metrica_proyecto_modelo` (
  `id_metrica` int(11) NOT NULL,
  `id_proyecto_modelo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metrica_tarea`
--

CREATE TABLE `metrica_tarea` (
  `id_metrica` int(11) NOT NULL,
  `id_tarea` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metrica_tarea`
--

INSERT INTO `metrica_tarea` (`id_metrica`, `id_tarea`) VALUES
(1, 4),
(1, 7),
(2, 4),
(2, 7),
(3, 5),
(3, 14),
(4, 7),
(4, 13),
(4, 15),
(5, 8),
(5, 13),
(5, 14),
(6, 6),
(6, 7),
(6, 14),
(7, 6),
(7, 7),
(7, 9),
(8, 4),
(8, 7),
(8, 9),
(9, 7),
(9, 9),
(9, 13),
(10, 12),
(10, 13),
(10, 14),
(11, 6),
(11, 7),
(12, 5),
(12, 6),
(12, 15),
(13, 8),
(13, 14),
(14, 4),
(14, 7),
(15, 8),
(15, 9),
(15, 14),
(16, 4),
(16, 7),
(16, 9),
(17, 6),
(17, 7),
(17, 15);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modelo_calidad`
--

CREATE TABLE `modelo_calidad` (
  `id_modelo` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `modelo_calidad`
--

INSERT INTO `modelo_calidad` (`id_modelo`, `nombre`, `descripcion`) VALUES
(1, 'ISO 9001:2015', 'Norma de gestión de calidad orientada a procesos.'),
(2, 'IEEE 1028', 'Estándar para revisiones y auditorías de software.'),
(3, 'CMMI-DEV', 'Modelo de madurez para mejora de procesos de desarrollo.'),
(4, 'ISO 21500', 'Guía internacional para la gestión de proyectos.'),
(5, 'ISO/IEC 25000 (SQuaRE)', 'Norma que evalúa la calidad del producto software.'),
(6, 'Híbrido', 'Modelo de calidad integrado basado en ISO, IEEE y CMMI.'),
(7, 'Modelo MF-SQA', 'Modelo personalizado utilizado en MetricFlow-SQA.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permiso`
--

CREATE TABLE `permiso` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permiso`
--

INSERT INTO `permiso` (`id`, `nombre`) VALUES
(1, 'ABM Usuarios'),
(2, 'ABM Proyectos'),
(3, 'Gestión de Modelo de Calidad'),
(4, 'Gestión de Iteraciones'),
(5, 'Gestión de Tareas'),
(6, 'Gestión de Métricas de Calidad'),
(7, 'Registro de Métricas Ejecutadas'),
(8, 'Visualización de Métricas'),
(9, 'Exportación de Informes y Gráficos'),
(10, 'Filtrado Avanzado de Reportes'),
(11, 'Visualización de Dashboard Inicial'),
(12, 'ABM Roles');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto`
--

CREATE TABLE `proyecto` (
  `id_proyecto` int(11) NOT NULL,
  `objetivo` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('Registrado','En Progreso','Finalizado','Cancelado') NOT NULL DEFAULT 'Registrado',
  `nombre` varchar(100) NOT NULL,
  `id_modelo` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `anio` int(4) GENERATED ALWAYS AS (year(`fecha_creacion`)) STORED,
  `id_modelo_global` int(11) DEFAULT NULL,
  `id_modelo_personalizado` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proyecto`
--

INSERT INTO `proyecto` (`id_proyecto`, `objetivo`, `descripcion`, `estado`, `nombre`, `id_modelo`, `id_modelo_global`, `id_modelo_personalizado`) VALUES
(1, 'MetricFlow SQA es un software web para registrar, seguir y analizar métricas de calidad durante las iteraciones del proyecto.', 'Permite el seguimiento de estándares de calidad mediante métricas e indicadores definidos en el plan de SQA.', 'En Progreso', 'MetricFlow-SQA', 7, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto_fase`
--

CREATE TABLE `proyecto_fase` (
  `id_proyecto` int(11) NOT NULL,
  `id_fase` int(11) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proyecto_fase`
--

INSERT INTO `proyecto_fase` (`id_proyecto`, `id_fase`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 1, '2025-08-19', '2025-09-09'),
(1, 2, '2025-09-10', '2025-10-10'),
(1, 3, '2025-10-11', '2025-11-14'),
(1, 4, '2025-11-15', '2025-11-28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto_modelo_calidad`
--

CREATE TABLE `proyecto_modelo_calidad` (
  `id_proyecto_modelo` int(11) NOT NULL,
  `id_proyecto` int(11) NOT NULL,
  `id_modelo_base` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `es_personalizado` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Gerente de Calidad'),
(3, 'Líder de Proyecto'),
(4, 'Espectador'),
(5, 'Sin Rol'),
(6, 'SuperAdmin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permiso`
--

CREATE TABLE `rol_permiso` (
  `id_rol` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol_permiso`
--

INSERT INTO `rol_permiso` (`id_rol`, `id_permiso`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 6),
(1, 11),
(2, 3),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 9),
(3, 10),
(3, 11),
(4, 11),
(6, 1),
(6, 2),
(6, 3),
(6, 4),
(6, 5),
(6, 6),
(6, 7),
(6, 8),
(6, 9),
(6, 10),
(6, 11),
(6, 12);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarea`
--

CREATE TABLE `tarea` (
  `id_tarea` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tarea`
--

INSERT INTO `tarea` (`id_tarea`, `nombre`) VALUES
(1, 'Entrevista'),
(2, 'Documentación'),
(3, 'Diseño'),
(4, 'Validación y Verificación'),
(5, 'Reunión'),
(6, 'Riesgos'),
(7, 'Calidad'),
(8, 'Estimación'),
(9, 'Pruebas'),
(10, 'Administración de Configuraciones'),
(11, 'Análisis'),
(12, 'Requerimientos'),
(13, 'Programación'),
(14, 'Cronograma'),
(15, 'Mantenimiento');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre_apellido` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre_apellido`, `email`) VALUES
(1, 'Malcom Salazar', 'malcom38794@gmail.com'),
(2, 'Fabricio Nuñez', 'fabrydamian@gmail.com'),
(3, 'Lorenzo Teppa', 'lorenzoas12@gmail.com'),
(4, 'Ezequiel Mansilla', 'ezequielmansi87@gmail.com'),
(5, 'Santiago Pacheco', 'pacheco.santi990@gmail.com'),
(6, 'Osiris Sofia', 'osofia@uarg.unpa.edu.ar'),
(7, 'Karim Hallar', 'khallar@uarg.unpa.edu.ar'),
(8, 'Esteban Gesto', 'estebangesto@gmail.com'),
(12, 'Lorenzo Teppa', 'sistemasprexa@gmail.com'),
(13, 'Silvia ailen Gariglio', 'silviagarigliosivi@gmail.com'),
(14, 'Aylen Gariglio', 'gariglioaylen@gmail.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_proyecto`
--

CREATE TABLE `usuario_proyecto` (
  `id_usuario` int(11) NOT NULL,
  `id_proyecto` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_proyecto`
--

INSERT INTO `usuario_proyecto` (`id_usuario`, `id_proyecto`, `id_rol`) VALUES
(1, 1, 2),
(13, 1, 2),
(4, 1, 3),
(2, 1, 4),
(5, 1, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_rol`
--

CREATE TABLE `usuario_rol` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_rol`
--

INSERT INTO `usuario_rol` (`id_usuario`, `id_rol`) VALUES
(1, 2),
(2, 4),
(3, 6),
(4, 3),
(5, 4),
(6, 1),
(7, 1),
(8, 1),
(12, 2),
(13, 2),
(14, 2);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `fase`
--
ALTER TABLE `fase`
  ADD PRIMARY KEY (`id_fase`);

--
-- Indices de la tabla `iteracion`
--
ALTER TABLE `iteracion`
  ADD PRIMARY KEY (`id_iteracion`),
  ADD KEY `id_fase` (`id_fase`),
  ADD KEY `fk_iteracion_proyecto` (`id_proyecto`);

--
-- Indices de la tabla `iteracion_tarea`
--
ALTER TABLE `iteracion_tarea`
  ADD PRIMARY KEY (`id_iteracion`,`id_tarea`),
  ADD KEY `id_tarea` (`id_tarea`);

--
-- Indices de la tabla `metrica`
--
ALTER TABLE `metrica`
  ADD PRIMARY KEY (`id_metrica`);

--
-- Indices de la tabla `metrica_iteracion`
--
ALTER TABLE `metrica_iteracion`
  ADD PRIMARY KEY (`id_metrica`,`id_iteracion`),
  ADD KEY `id_iteracion` (`id_iteracion`);

--
-- Indices de la tabla `metrica_modelo_calidad`
--
ALTER TABLE `metrica_modelo_calidad`
  ADD PRIMARY KEY (`id_metrica`,`id_modelo`),
  ADD KEY `id_modelo` (`id_modelo`);

--
-- Indices de la tabla `metrica_proyecto_modelo`
--
ALTER TABLE `metrica_proyecto_modelo`
  ADD PRIMARY KEY (`id_metrica`,`id_proyecto_modelo`),
  ADD KEY `fk_mpm_proy_modelo` (`id_proyecto_modelo`);

--
-- Indices de la tabla `metrica_tarea`
--
ALTER TABLE `metrica_tarea`
  ADD PRIMARY KEY (`id_metrica`,`id_tarea`),
  ADD KEY `id_tarea` (`id_tarea`);

--
-- Indices de la tabla `modelo_calidad`
--
ALTER TABLE `modelo_calidad`
  ADD PRIMARY KEY (`id_modelo`);

--
-- Indices de la tabla `permiso`
--
ALTER TABLE `permiso`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `proyecto`
--
ALTER TABLE `proyecto`
  ADD PRIMARY KEY (`id_proyecto`),
  ADD KEY `id_modelo` (`id_modelo`);

--
-- Indices de la tabla `proyecto_fase`
--
ALTER TABLE `proyecto_fase`
  ADD PRIMARY KEY (`id_proyecto`,`id_fase`),
  ADD KEY `id_fase` (`id_fase`);

--
-- Indices de la tabla `proyecto_modelo_calidad`
--
ALTER TABLE `proyecto_modelo_calidad`
  ADD PRIMARY KEY (`id_proyecto_modelo`),
  ADD KEY `fk_proy_modelo_proyecto` (`id_proyecto`),
  ADD KEY `fk_proy_modelo_modelo_base` (`id_modelo_base`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD PRIMARY KEY (`id_rol`,`id_permiso`),
  ADD KEY `id_permiso` (`id_permiso`);

--
-- Indices de la tabla `tarea`
--
ALTER TABLE `tarea`
  ADD PRIMARY KEY (`id_tarea`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `usuario_proyecto`
--
ALTER TABLE `usuario_proyecto`
  ADD PRIMARY KEY (`id_usuario`,`id_proyecto`),
  ADD KEY `usuario_proyecto_ibfk_2` (`id_proyecto`),
  ADD KEY `usuario_proyecto_ibfk_3` (`id_rol`);

--
-- Indices de la tabla `usuario_rol`
--
ALTER TABLE `usuario_rol`
  ADD PRIMARY KEY (`id_usuario`,`id_rol`),
  ADD KEY `id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `fase`
--
ALTER TABLE `fase`
  MODIFY `id_fase` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `iteracion`
--
ALTER TABLE `iteracion`
  MODIFY `id_iteracion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `metrica`
--
ALTER TABLE `metrica`
  MODIFY `id_metrica` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de la tabla `modelo_calidad`
--
ALTER TABLE `modelo_calidad`
  MODIFY `id_modelo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `permiso`
--
ALTER TABLE `permiso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `proyecto`
--
ALTER TABLE `proyecto`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `proyecto_modelo_calidad`
--
ALTER TABLE `proyecto_modelo_calidad`
  MODIFY `id_proyecto_modelo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tarea`
--
ALTER TABLE `tarea`
  MODIFY `id_tarea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `iteracion`
--
ALTER TABLE `iteracion`
  ADD CONSTRAINT `fk_iteracion_proyecto` FOREIGN KEY (`id_proyecto`) REFERENCES `proyecto` (`id_proyecto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `iteracion_tarea`
--
ALTER TABLE `iteracion_tarea`
  ADD CONSTRAINT `iteracion_tarea_ibfk_1` FOREIGN KEY (`id_iteracion`) REFERENCES `iteracion` (`id_iteracion`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `iteracion_tarea_ibfk_2` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `metrica_iteracion`
--
ALTER TABLE `metrica_iteracion`
  ADD CONSTRAINT `metrica_iteracion_ibfk_1` FOREIGN KEY (`id_metrica`) REFERENCES `metrica` (`id_metrica`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `metrica_iteracion_ibfk_2` FOREIGN KEY (`id_iteracion`) REFERENCES `iteracion` (`id_iteracion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `metrica_modelo_calidad`
--
ALTER TABLE `metrica_modelo_calidad`
  ADD CONSTRAINT `metrica_modelo_calidad_ibfk_1` FOREIGN KEY (`id_metrica`) REFERENCES `metrica` (`id_metrica`),
  ADD CONSTRAINT `metrica_modelo_calidad_ibfk_2` FOREIGN KEY (`id_modelo`) REFERENCES `modelo_calidad` (`id_modelo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `metrica_proyecto_modelo`
--
ALTER TABLE `metrica_proyecto_modelo`
  ADD CONSTRAINT `fk_mpm_metrica` FOREIGN KEY (`id_metrica`) REFERENCES `metrica` (`id_metrica`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mpm_proy_modelo` FOREIGN KEY (`id_proyecto_modelo`) REFERENCES `proyecto_modelo_calidad` (`id_proyecto_modelo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `metrica_tarea`
--
ALTER TABLE `metrica_tarea`
  ADD CONSTRAINT `metrica_tarea_ibfk_1` FOREIGN KEY (`id_metrica`) REFERENCES `metrica` (`id_metrica`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `metrica_tarea_ibfk_2` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `proyecto`
--
ALTER TABLE `proyecto`
  ADD CONSTRAINT `proyecto_ibfk_1` FOREIGN KEY (`id_modelo`) REFERENCES `modelo_calidad` (`id_modelo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `proyecto_fase`
--
ALTER TABLE `proyecto_fase`
  ADD CONSTRAINT `proyecto_fase_ibfk_1` FOREIGN KEY (`id_proyecto`) REFERENCES `proyecto` (`id_proyecto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `proyecto_fase_ibfk_2` FOREIGN KEY (`id_fase`) REFERENCES `fase` (`id_fase`);

--
-- Filtros para la tabla `proyecto_modelo_calidad`
--
ALTER TABLE `proyecto_modelo_calidad`
  ADD CONSTRAINT `fk_proy_modelo_modelo_base` FOREIGN KEY (`id_modelo_base`) REFERENCES `modelo_calidad` (`id_modelo`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_proy_modelo_proyecto` FOREIGN KEY (`id_proyecto`) REFERENCES `proyecto` (`id_proyecto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD CONSTRAINT `rol_permiso_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `rol_permiso_ibfk_2` FOREIGN KEY (`id_permiso`) REFERENCES `permiso` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_proyecto`
--
ALTER TABLE `usuario_proyecto`
  ADD CONSTRAINT `usuario_proyecto_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `usuario_proyecto_ibfk_2` FOREIGN KEY (`id_proyecto`) REFERENCES `proyecto` (`id_proyecto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `usuario_proyecto_ibfk_3` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_rol`
--
ALTER TABLE `usuario_rol`
  ADD CONSTRAINT `usuario_rol_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `usuario_rol_ibfk_2` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
