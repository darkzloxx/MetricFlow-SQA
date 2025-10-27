-- Proyectos con su modelo de calidad asociado
SELECT 
  p.id_proyecto,
  p.nombre AS proyecto,
  m.nombre AS modelo_calidad
FROM proyecto p
JOIN modelo_calidad m ON p.id_modelo = m.id_modelo;

-- Iteraciones agrupadas por fase
SELECT 
  f.nombre AS fase,
  i.numero_iteracion,
  i.fecha_inicio,
  i.fecha_fin
FROM iteracion i
JOIN fase f ON i.id_fase = f.id_fase
ORDER BY f.id_fase, i.numero_iteracion;

-- Usuarios y sus roles
SELECT 
  u.nombre_apellido,
  r.nombre AS rol
FROM usuario u
JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario
JOIN rol r ON ur.id_rol = r.id;

-- Roles y los permisos asignados
SELECT 
  r.nombre AS rol,
  p.nombre AS permiso
FROM rol_permiso rp
JOIN rol r ON rp.id_rol = r.id
JOIN permiso p ON rp.id_permiso = p.id
ORDER BY r.nombre;

-- Usuarios asignados a un proyecto y su rol
SELECT 
  u.nombre_apellido,
  p.nombre AS proyecto,
  up.rol AS rol_en_proyecto
FROM usuario_proyecto up
JOIN usuario u ON up.id_usuario = u.id_usuario
JOIN proyecto p ON up.id_proyecto = p.id_proyecto
ORDER BY p.id_proyecto;

-- Metricas y tarea/s que miden
SELECT 
  m.nombre AS metrica,
  t.nombre AS tarea
FROM metrica_tarea mt
JOIN metrica m ON mt.id_metrica = m.id_metrica
JOIN tarea t ON mt.id_tarea = t.id_tarea
ORDER BY m.id_metrica;

-- Métricas  vinculadas a cada modelo de calidad
SELECT 
  mo.nombre AS modelo_calidad,
  m.nombre AS metrica
FROM metrica_modelo_calidad mmc
JOIN modelo_calidad mo ON mmc.id_modelo = mo.id_modelo
JOIN metrica m ON mmc.id_metrica = m.id_metrica
ORDER BY mo.nombre;

-- Promedio de desviación 
SELECT 
  ROUND(AVG(ABS(((mi.valor_ejecutado - mi.valor_planificado)/NULLIF(mi.valor_planificado,0))*100)), 2) AS desviacion_promedio
FROM metrica_iteracion mi;

-- Métricas con peores cumplimiento
SELECT 
  m.nombre AS metrica,
  f.nombre AS fase,
  i.numero_iteracion,
  ROUND((mi.valor_ejecutado / mi.valor_planificado) * 100, 2) AS cumplimiento
FROM metrica_iteracion mi
JOIN metrica m ON mi.id_metrica = m.id_metrica
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON i.id_fase = f.id_fase
WHERE mi.valor_planificado > 0
ORDER BY cumplimiento ASC
LIMIT 5;

-- Métricas que superan el 100% de cumplimiento
SELECT 
  m.nombre AS metrica,
  f.nombre AS fase,
  i.numero_iteracion,
  ROUND((mi.valor_ejecutado / NULLIF(mi.valor_planificado,0)) * 100, 2) AS cumplimiento
FROM metrica_iteracion mi
JOIN metrica m ON mi.id_metrica = m.id_metrica
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON i.id_fase = f.id_fase
WHERE mi.valor_planificado > 0 
  AND (mi.valor_ejecutado / mi.valor_planificado) * 100 > 100
ORDER BY cumplimiento DESC
LIMIT 5;

-- Consultas Dashboard
SELECT 
  f.nombre AS fase,
  i.numero_iteracion AS iteracion,
  m.nombre AS metrica,
  mi.valor_planificado AS planificado,
  mi.valor_ejecutado AS ejecutado,
  CONCAT(ROUND((mi.valor_ejecutado / mi.valor_planificado) * 100, 2), '%') AS cumplimiento,
  mi.umbral_desviacion AS umbral
FROM metrica_iteracion mi
JOIN metrica m ON mi.id_metrica = m.id_metrica
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON i.id_fase = f.id_fase
ORDER BY f.id_fase, i.numero_iteracion, m.id_metrica;

-- Métricas dentro del umbral de desviación (aceptables)
SELECT 
  m.nombre AS metrica,
  f.nombre AS fase,
  i.numero_iteracion,
  mi.valor_planificado,
  mi.valor_ejecutado,
  mi.umbral_desviacion AS umbral,
  ROUND((mi.valor_ejecutado / NULLIF(mi.valor_planificado, 0)) * 100, 2) AS cumplimiento
FROM metrica_iteracion mi
JOIN metrica m ON mi.id_metrica = m.id_metrica
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON i.id_fase = f.id_fase
WHERE mi.valor_planificado > 0
  AND ROUND((mi.valor_ejecutado / mi.valor_planificado) * 100, 2)
      BETWEEN (100 - mi.umbral_desviacion) AND (100 + mi.umbral_desviacion)
ORDER BY cumplimiento ASC;

-- Métricas fuera del umbral de desviación (no aceptables)
SELECT 
  m.nombre AS metrica,
  f.nombre AS fase,
  i.numero_iteracion,
  mi.valor_planificado,
  mi.valor_ejecutado,
  mi.umbral_desviacion AS umbral,
  ROUND((mi.valor_ejecutado / NULLIF(mi.valor_planificado, 0)) * 100, 2) AS cumplimiento
FROM metrica_iteracion mi
JOIN metrica m ON mi.id_metrica = m.id_metrica
JOIN iteracion i ON mi.id_iteracion = i.id_iteracion
JOIN fase f ON i.id_fase = f.id_fase
WHERE mi.valor_planificado > 0
  AND (
    ROUND((mi.valor_ejecutado / mi.valor_planificado) * 100, 2) < (100 - mi.umbral_desviacion)
    OR ROUND((mi.valor_ejecutado / mi.valor_planificado) * 100, 2) > (100 + mi.umbral_desviacion)
  )
ORDER BY cumplimiento ASC;



