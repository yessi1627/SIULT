-- Migracion: control de concurrencia y respaldo de la cola de mensajes.
-- Se puede ejecutar varias veces sin dañar los datos.

-- 1. Bloqueo optimista en calificaciones.
--    Cada vez que se modifica una nota, version aumenta en 1. Quien guarda envia la version que leyo;
--    si otro profesor la cambio antes, la version ya no coincide y el UPDATE no afecta filas (HTTP 409).
SET @tiene_version = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'version'
);
SET @sql_version = IF(
  @tiene_version = 0,
  'ALTER TABLE calificaciones ADD COLUMN version INT NOT NULL DEFAULT 1 AFTER observacion',
  'SELECT 1'
);
PREPARE sentencia_version FROM @sql_version;
EXECUTE sentencia_version;
DEALLOCATE PREPARE sentencia_version;

-- 2. Respaldo de la cola de notificaciones (patron "outbox").
--    Si Redis no responde al crear una tarea, el mensaje se guarda aqui y se reenvia a la cola
--    cuando Redis vuelve a estar disponible. Asi ningun aviso se pierde.
CREATE TABLE IF NOT EXISTS cola_respaldo (
  id_mensaje INT NOT NULL AUTO_INCREMENT,
  cola VARCHAR(100) NOT NULL,
  contenido JSON NOT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  intentos INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id_mensaje),
  KEY cola (cola)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
