-- Migracion: indices para las consultas mas frecuentes (ver docs/optimizacion_consultas.md).
-- Se puede ejecutar varias veces: cada indice solo se crea si no existe.
--
-- Indices que YA existian y no hace falta duplicar:
--   matriculas  UNIQUE (id_usuario, id_materia) -> sirve para buscar por id_usuario (prefijo izquierdo)
--   calificaciones KEY id_usuario y UNIQUE (id_tarea, id_usuario)
--   entregas    UNIQUE (id_tarea, id_usuario)
--   usuarios    UNIQUE email

DROP PROCEDURE IF EXISTS crear_indice_si_falta;
DELIMITER //
CREATE PROCEDURE crear_indice_si_falta(IN tabla VARCHAR(64), IN indice VARCHAR(64), IN columnas VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tabla AND INDEX_NAME = indice
  ) THEN
    SET @sql_indice = CONCAT('CREATE INDEX ', indice, ' ON ', tabla, ' (', columnas, ')');
    PREPARE sentencia_indice FROM @sql_indice;
    EXECUTE sentencia_indice;
    DEALLOCATE PREPARE sentencia_indice;
  END IF;
END //
DELIMITER ;

-- Tareas de las materias de un estudiante ordenadas por fecha de entrega (listado y panel)
CALL crear_indice_si_falta('tareas', 'idx_tareas_materia_fecha', 'id_materia, fecha_entrega');

-- actualizarTareasVencidas(): UPDATE ... WHERE estado = 'Pendiente' AND fecha_entrega < hoy
CALL crear_indice_si_falta('tareas', 'idx_tareas_estado_fecha', 'estado, fecha_entrega');

-- Notificaciones mas recientes (ORDER BY fecha_creacion DESC LIMIT n)
CALL crear_indice_si_falta('notificaciones', 'idx_notificaciones_fecha', 'fecha_creacion');

DROP PROCEDURE IF EXISTS crear_indice_si_falta;
