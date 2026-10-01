-- Migracion: estados de tarea unificados y tabla de entregas por estudiante.
-- Se puede ejecutar varias veces sin dañar los datos.

-- 1. Unifico los valores de estado de las tareas.
--    Antes existian 'Pendiente', 'pendiente', 'completado', 'completada' y 'No entrego'.
UPDATE tareas SET estado = 'Pendiente' WHERE LOWER(estado) = 'pendiente';
UPDATE tareas SET estado = 'Completada' WHERE LOWER(estado) IN ('completado', 'completada');
UPDATE tareas SET estado = 'Vencida' WHERE LOWER(estado) IN ('no entrego', 'vencida');

-- 2. Cada estudiante registra su propia entrega. Antes, cuando un estudiante subia un archivo,
--    la tarea quedaba marcada como completada para todos.
--    UNIQUE (id_tarea, id_usuario): un estudiante tiene una sola entrega por tarea; si vuelve a subir, se reemplaza.
CREATE TABLE IF NOT EXISTS entregas (
  id_entrega INT NOT NULL AUTO_INCREMENT,
  id_tarea INT NOT NULL,
  id_usuario INT NOT NULL,
  ruta_archivo VARCHAR(255) NOT NULL,
  nombre_original VARCHAR(255) DEFAULT NULL,
  fecha_entrega DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_entrega),
  UNIQUE KEY tarea_usuario (id_tarea, id_usuario),
  KEY id_usuario (id_usuario),
  CONSTRAINT entregas_ibfk_1 FOREIGN KEY (id_tarea) REFERENCES tareas (id_tarea)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT entregas_ibfk_2 FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- 3. La tabla archivos se conserva para el material que adjunta el profesor o el administrador.
--    Los registros antiguos de archivos no guardaban que usuario subio el archivo, por eso no se
--    pueden convertir en entregas de un estudiante; quedan como material de la tarea.
