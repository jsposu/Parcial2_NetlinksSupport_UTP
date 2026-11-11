-- Book&Go Lite / Netlinks. Importar en MySQL 8+ o MariaDB 10.4+.
-- Zona horaria de la aplicación: America/Panama. No contiene credenciales.
CREATE DATABASE IF NOT EXISTS bookgo_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookgo_lite;

CREATE TABLE IF NOT EXISTS roles (
 id TINYINT UNSIGNED PRIMARY KEY,
 nombre VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS usuarios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rol_id TINYINT UNSIGNED NOT NULL,
 nombre VARCHAR(80) NOT NULL,
 apellido VARCHAR(80) NOT NULL,
 correo VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 debe_cambiar_password TINYINT UNSIGNED NOT NULL DEFAULT 0,
 password_version INT UNSIGNED NOT NULL DEFAULT 0,
 estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS categorias (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL UNIQUE,
 descripcion VARCHAR(500) NOT NULL,
 estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS servicios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 categoria_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(120) NOT NULL,
 descripcion TEXT NOT NULL,
 precio DECIMAL(10,2) NOT NULL,
 duracion_min SMALLINT UNSIGNED NOT NULL,
 estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
 CONSTRAINT chk_precio CHECK (precio >= 0),
 CONSTRAINT chk_duracion CHECK (duracion_min > 0),
 FOREIGN KEY (categoria_id) REFERENCES categorias(id),
 INDEX (categoria_id, estado)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS horarios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 servicio_id BIGINT UNSIGNED NOT NULL,
 fecha DATE NOT NULL,
 hora_inicio TIME NOT NULL,
 hora_fin TIME NOT NULL,
 cupos_total SMALLINT UNSIGNED NOT NULL,
 cupos_disponibles SMALLINT UNSIGNED NOT NULL,
 estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
 CONSTRAINT chk_horas CHECK (hora_fin > hora_inicio),
 CONSTRAINT chk_cupos CHECK (cupos_total > 0 AND cupos_disponibles <= cupos_total),
 UNIQUE KEY uq_horario_servicio (id, servicio_id),
 INDEX (servicio_id, fecha, hora_inicio),
 FOREIGN KEY (servicio_id) REFERENCES servicios(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS reservas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cliente_id BIGINT UNSIGNED NOT NULL,
 servicio_id BIGINT UNSIGNED NOT NULL,
 horario_id BIGINT UNSIGNED NOT NULL,
 estado ENUM('PENDIENTE','CONFIRMADA','REALIZADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
 creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 -- NULL admite varias canceladas; los estados activos solo permiten una por cliente y horario.
 cliente_activo BIGINT UNSIGNED GENERATED ALWAYS AS
  (CASE WHEN estado <> 'CANCELADA' THEN cliente_id ELSE NULL END) STORED,
 UNIQUE KEY uq_reserva_activa (horario_id, cliente_activo),
 UNIQUE KEY uq_reserva_cliente (id, cliente_id),
 INDEX (cliente_id, estado),
 FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
 FOREIGN KEY (horario_id, servicio_id) REFERENCES horarios(id, servicio_id),
 FOREIGN KEY (servicio_id) REFERENCES servicios(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS resenas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reserva_id BIGINT UNSIGNED NOT NULL UNIQUE,
 cliente_id BIGINT UNSIGNED NOT NULL,
 calificacion TINYINT UNSIGNED NOT NULL,
 comentario TEXT NOT NULL,
 creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 5),
 FOREIGN KEY (reserva_id, cliente_id) REFERENCES reservas(id, cliente_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS intentos_login (
 clave CHAR(64) PRIMARY KEY,
 fallos TINYINT UNSIGNED NOT NULL DEFAULT 0,
 bloqueado_hasta DATETIME NULL,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO roles (id, nombre) VALUES (1,'ADMIN'),(2,'CLIENTE')
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);
INSERT INTO categorias (id,nombre,descripcion) VALUES
 (1,'Computadoras','Diagnóstico y mantenimiento de equipos de escritorio y portátiles.'),
 (2,'Redes y conectividad','Soluciones para conexiones y redes domésticas.'),
 (3,'Software y seguridad','Optimización, protección y configuración de sistemas.')
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion);
INSERT INTO servicios (id,categoria_id,nombre,descripcion,precio,duracion_min) VALUES
 (1,1,'Diagnóstico de computadora','Revisión de hardware, rendimiento y reporte de fallas.',20.00,60),
 (2,1,'Mantenimiento preventivo','Limpieza interna y verificación de componentes.',35.00,90),
 (3,2,'Configuración de Wi-Fi','Optimización de red inalámbrica y cobertura.',25.00,60),
 (4,2,'Diagnóstico de red','Identificación de problemas de conexión y dispositivos.',30.00,75),
 (5,3,'Instalación de software','Instalación y configuración de aplicaciones autorizadas.',22.00,60),
 (6,3,'Revisión de seguridad','Análisis básico de malware y recomendaciones de protección.',40.00,90)
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),descripcion=VALUES(descripcion),precio=VALUES(precio),duracion_min=VALUES(duracion_min);
-- Horarios relativos al día de importación: reimportar o crear nuevos si vencen.
INSERT INTO horarios (servicio_id,fecha,hora_inicio,hora_fin,cupos_total,cupos_disponibles)
SELECT s.id, DATE_ADD(CURDATE(), INTERVAL (s.id + 2) DAY), '09:00:00', '11:00:00', 3, 3
FROM servicios s WHERE s.id BETWEEN 1 AND 6
 AND NOT EXISTS (SELECT 1 FROM horarios h WHERE h.servicio_id=s.id AND h.fecha=DATE_ADD(CURDATE(), INTERVAL (s.id + 2) DAY) AND h.hora_inicio='09:00:00');
