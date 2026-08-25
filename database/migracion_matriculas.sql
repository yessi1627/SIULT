SET @usuarios_tiene_pk = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND CONSTRAINT_TYPE = 'PRIMARY KEY'
);
SET @sql_usuarios_pk = IF(
  @usuarios_tiene_pk = 0,
  'ALTER TABLE usuarios ADD PRIMARY KEY (id_usuario)',
  'SELECT 1'
);
PREPARE sentencia_usuarios_pk FROM @sql_usuarios_pk;
EXECUTE sentencia_usuarios_pk;
DEALLOCATE PREPARE sentencia_usuarios_pk;

SET @materias_tiene_auto_increment = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'materias'
    AND COLUMN_NAME = 'id_materia'
    AND EXTRA LIKE '%auto_increment%'
);
SET @sql_materias_auto_increment = IF(
  @materias_tiene_auto_increment = 0,
  'ALTER TABLE materias MODIFY id_materia INT NOT NULL AUTO_INCREMENT',
  'SELECT 1'
);
PREPARE sentencia_materias_auto_increment FROM @sql_materias_auto_increment;
EXECUTE sentencia_materias_auto_increment;
DEALLOCATE PREPARE sentencia_materias_auto_increment;

CREATE TABLE IF NOT EXISTS matriculas (
  id_matricula INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  id_materia INT NOT NULL,
  fecha_matricula DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_matricula),
  UNIQUE KEY usuario_materia (id_usuario, id_materia),
  KEY id_materia (id_materia),
  CONSTRAINT matriculas_ibfk_1 FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT matriculas_ibfk_2 FOREIGN KEY (id_materia) REFERENCES materias (id_materia)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
