CREATE TABLE IF NOT EXISTS calificaciones (
  id_calificacion INT NOT NULL AUTO_INCREMENT,
  id_tarea INT NOT NULL,
  id_usuario INT NOT NULL,
  nota DECIMAL(3,2) NOT NULL,
  observacion TEXT DEFAULT NULL,
  fecha_calificacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_calificacion),
  UNIQUE KEY tarea_usuario (id_tarea, id_usuario),
  KEY id_usuario (id_usuario),
  CONSTRAINT calificaciones_chk_nota CHECK (nota BETWEEN 0 AND 5),
  CONSTRAINT calificaciones_ibfk_1 FOREIGN KEY (id_tarea) REFERENCES tareas (id_tarea)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT calificaciones_ibfk_2 FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
