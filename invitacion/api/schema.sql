-- Tablas de la invitación de Camila & Juan (MySQL / MariaDB).
CREATE TABLE IF NOT EXISTS confirmaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  asistencia ENUM('si','no') NOT NULL,
  personas TINYINT UNSIGNED NOT NULL DEFAULT 1, -- total: invitado + acompañantes (0 si no va)
  telefono VARCHAR(30) NOT NULL DEFAULT '',
  menu VARCHAR(50) NOT NULL DEFAULT '',
  comentarios TEXT,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acompanantes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  confirmacion_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  tipo ENUM('adulto','nino') NOT NULL,
  restriccion VARCHAR(30) NULL, -- NULL = sin restricción
  FOREIGN KEY (confirmacion_id) REFERENCES confirmaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  cancion VARCHAR(150) NOT NULL,
  artista VARCHAR(150) NOT NULL DEFAULT '',
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  mensaje TEXT NOT NULL,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fotos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  archivo VARCHAR(64) NOT NULL,
  original VARCHAR(200) NOT NULL DEFAULT '',
  bytes INT UNSIGNED NOT NULL,
  drive_id VARCHAR(100) NULL, -- id del archivo en Google Drive (NULL si no se pudo subir)
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitaciones personalizadas: los novios cargan cada grupo y sus personas desde el panel.
CREATE TABLE IF NOT EXISTS grupos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,            -- ej: "Familia Gómez"
  token CHAR(10) NOT NULL UNIQUE,           -- va en el link: camiyjuan.com/?i=TOKEN
  telefono VARCHAR(30) NOT NULL DEFAULT '',
  comentarios TEXT,
  respondido TIMESTAMP NULL,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  tipo ENUM('adulto','nino') NOT NULL DEFAULT 'adulto',
  asiste ENUM('si','no') NULL,              -- NULL = todavía no respondió
  menu VARCHAR(30) NULL,                    -- NULL = sin menú especial
  menu_tipo VARCHAR(10) NULL,               -- adulto / infantil
  FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
