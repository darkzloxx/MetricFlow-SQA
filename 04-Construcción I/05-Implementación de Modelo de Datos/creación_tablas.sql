-- =====================================================
-- MÓDULO DE AUTENTICACIÓN / ROLES / PERMISOS
-- =====================================================

CREATE TABLE PERMISO (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ROL (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ROL_PERMISO (
  id_rol INT NOT NULL,
  id_permiso INT NOT NULL,
  PRIMARY KEY (id_rol, id_permiso),
  FOREIGN KEY (id_rol) REFERENCES ROL(id)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  FOREIGN KEY (id_permiso) REFERENCES PERMISO(id)
      ON DELETE CASCADE -- Eliminar permisos al eliminar rol
      ON UPDATE CASCADE -- Actualizar permisos al actualizar rol
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE USUARIO (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  nombre_apellido VARCHAR(150) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE USUARIO_ROL (
  id_usuario INT NOT NULL,
  id_rol INT NOT NULL,
  PRIMARY KEY (id_usuario, id_rol),
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
      ON DELETE CASCADE -- Eliminar roles al eliminar usuario
      ON UPDATE CASCADE, -- Actualizar roles al actualizar usuario
  FOREIGN KEY (id_rol) REFERENCES rol(id)
      ON DELETE CASCADE -- Eliminar usuarios al eliminar rol
      ON UPDATE CASCADE -- Actualizar usuarios al actualizar rol
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MÓDULO DE GESTIÓN DE CALIDAD (SQA)
-- =====================================================

CREATE TABLE MODELO_CALIDAD (
  id_modelo INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT
);

CREATE TABLE PROYECTO (
  id_proyecto INT AUTO_INCREMENT PRIMARY KEY,
  objetivo TEXT,
  descripcion TEXT,
  estado ENUM('Registrado', 'En_Progreso', 'Finalizado', 'Cancelado')
      NOT NULL DEFAULT 'Registrado',
  nombre VARCHAR(100) NOT NULL,
  id_modelo INT,
  FOREIGN KEY (id_modelo)
      REFERENCES MODELO_CALIDAD (id_modelo)
      ON DELETE RESTRICT -- Evitar eliminación si hay proyectos asociados
      ON UPDATE CASCADE -- Actualizar id_modelo en cascada
);

CREATE TABLE USUARIO_PROYECTO (
  id_usuario INT,
  id_proyecto INT,
  rol VARCHAR(50),
  PRIMARY KEY (id_usuario, id_proyecto),
  FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario),
  FOREIGN KEY (id_proyecto) REFERENCES PROYECTO (id_proyecto)
);

CREATE TABLE FASE (
  id_fase INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
);

CREATE TABLE PROYECTO_FASE (
  id_proyecto INT,
  id_fase INT,
  fecha_inicio DATE,
  fecha_fin DATE,
  PRIMARY KEY (id_proyecto, id_fase),
  FOREIGN KEY (id_proyecto) REFERENCES PROYECTO (id_proyecto),
  FOREIGN KEY (id_fase) REFERENCES FASE (id_fase)
);

CREATE TABLE METRICA (
  id_metrica INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT
);

CREATE TABLE TAREA (
  id_tarea INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
);

CREATE TABLE ITERACION (
  id_iteracion INT AUTO_INCREMENT PRIMARY KEY,
  numero_iteracion INT NOT NULL,
  fecha_inicio DATE,
  fecha_fin DATE,
  objetivo TEXT,
  id_fase INT,
  FOREIGN KEY (id_fase) REFERENCES FASE (id_fase)
);

CREATE TABLE ITERACION_TAREA (
  id_iteracion INT,
  id_tarea INT,
  PRIMARY KEY (id_iteracion, id_tarea),
  FOREIGN KEY (id_iteracion) REFERENCES ITERACION (id_iteracion)
      ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_tarea) REFERENCES TAREA (id_tarea)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE METRICA_MODELO_CALIDAD (
  id_metrica INT,
  id_modelo INT,
  PRIMARY KEY (id_metrica, id_modelo),
  FOREIGN KEY (id_metrica) REFERENCES METRICA (id_metrica),
  FOREIGN KEY (id_modelo) REFERENCES MODELO_CALIDAD (id_modelo)
      ON DELETE RESTRICT -- Evitar eliminación si hay métricas asociadas
      ON UPDATE CASCADE -- Actualizar id_modelo en cascada
);

CREATE TABLE METRICA_ITERACION (
  id_metrica INT NOT NULL,
  id_iteracion INT NOT NULL,
  valor_planificado INT,
  valor_ejecutado INT,
  umbral_desviacion INT,
  PRIMARY KEY (id_metrica, id_iteracion),
  FOREIGN KEY (id_metrica) REFERENCES METRICA (id_metrica)
      ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_iteracion) REFERENCES ITERACION (id_iteracion)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE METRICA_TAREA (
  id_metrica INT NOT NULL,
  id_tarea INT NOT NULL,
  PRIMARY KEY (id_metrica, id_tarea),
  FOREIGN KEY (id_metrica) REFERENCES METRICA (id_metrica)
      ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_tarea) REFERENCES TAREA (id_tarea)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




