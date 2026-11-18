-- Migracion: recuperacion de contraseña.
-- Se puede ejecutar varias veces sin dañar los datos.

CREATE TABLE IF NOT EXISTS recuperaciones_clave (
  id_recuperacion INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_recuperacion),
  UNIQUE KEY token_hash (token_hash),
  KEY id_usuario (id_usuario),
  CONSTRAINT recuperaciones_clave_ibfk_1 FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
