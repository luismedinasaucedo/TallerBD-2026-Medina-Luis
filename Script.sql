-- ============================================================================
-- PROYECTO: Sistema de Registro y Control de Préstamos de Equipo Tecnológico
-- MATERIA: Taller de Base de Datos
-- AVANCE 1: Estructura de Base de Datos (DDL), Claves y Relaciones
-- ============================================================================

USE `db_inventario-21`;

-- ----------------------------------------------------------------------------
-- 1. TABLA: departamentos
-- ----------------------------------------------------------------------------
CREATE TABLE departamentos (
    id_departamento INT UNSIGNED AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    
    CONSTRAINT pk_departamentos PRIMARY KEY (id_departamento),
    CONSTRAINT uq_departamentos_nombre UNIQUE (nombre)
    
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 2. TABLA: categorias
-- ----------------------------------------------------------------------------
CREATE TABLE categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    
    CONSTRAINT pk_categorias PRIMARY KEY (id_categoria),
    CONSTRAINT uq_categorias_nombre UNIQUE (nombre)
    
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 3. TABLA: usuarios
-- ----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT,
    num_empleado VARCHAR(30) NOT NULL,
    nombre_completo VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('administrador', 'consulta') NOT NULL DEFAULT 'consulta',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    
    CONSTRAINT pk_usuarios PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuarios_empleado UNIQUE (num_empleado),
    CONSTRAINT uq_usuarios_username UNIQUE (username)
    
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 4. TABLA: solicitantes
-- ----------------------------------------------------------------------------
CREATE TABLE solicitantes (
    id_solicitante INT UNSIGNED AUTO_INCREMENT,
    numero_control VARCHAR(30) NOT NULL,
    nombre_completo VARCHAR(120) NOT NULL,
    id_departamento INT UNSIGNED NOT NULL,
    
    CONSTRAINT pk_solicitantes PRIMARY KEY (id_solicitante),
    CONSTRAINT uq_solicitantes_num UNIQUE (numero_control),
    
    CONSTRAINT fk_solicitantes_departamento
        FOREIGN KEY (id_departamento) REFERENCES departamentos (id_departamento)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
        
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 5. TABLA: equipos
-- ----------------------------------------------------------------------------
CREATE TABLE equipos (
    id_equipo INT UNSIGNED AUTO_INCREMENT,
    codigo_inventario VARCHAR(50) NOT NULL,
    numero_serie VARCHAR(80) NULL,
    descripcion VARCHAR(150) NOT NULL,
    marca VARCHAR(60) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    id_categoria INT UNSIGNED NOT NULL,
    estado ENUM('disponible', 'prestado', 'mantenimiento', 'baja') NOT NULL DEFAULT 'disponible',
    condicion_fisica VARCHAR(150) NOT NULL DEFAULT 'Óptima',
    
    CONSTRAINT pk_equipos PRIMARY KEY (id_equipo),
    CONSTRAINT uq_equipos_inventario UNIQUE (codigo_inventario),
    CONSTRAINT uq_equipos_serie UNIQUE (numero_serie),
    
    CONSTRAINT fk_equipos_categoria
        FOREIGN KEY (id_categoria) REFERENCES categorias (id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
        
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 6. TABLA: prestamos
-- ----------------------------------------------------------------------------
CREATE TABLE prestamos (
    id_prestamo INT UNSIGNED AUTO_INCREMENT,
    id_equipo INT UNSIGNED NOT NULL,
    id_solicitante INT UNSIGNED NOT NULL,
    id_usuario_entrega INT UNSIGNED NOT NULL,
    id_usuario_recibe INT UNSIGNED NULL,
    fecha_prestamo DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_prevista_devolucion DATETIME NOT NULL,
    fecha_devolucion_real DATETIME NULL,
    estado_prestamo ENUM('activo', 'devuelto', 'vencido') NOT NULL DEFAULT 'activo',
    condicion_devolucion VARCHAR(150) NULL,
    requiere_mantenimiento TINYINT(1) NOT NULL DEFAULT 0,
    
    CONSTRAINT pk_prestamos PRIMARY KEY (id_prestamo),
    CONSTRAINT chk_fechas_prestamo CHECK (fecha_prevista_devolucion >= fecha_prestamo),
    CONSTRAINT chk_fecha_devolucion CHECK (fecha_devolucion_real IS NULL OR fecha_devolucion_real >= fecha_prestamo),
    
    CONSTRAINT fk_prestamos_equipo
        FOREIGN KEY (id_equipo) REFERENCES equipos (id_equipo)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_solicitante
        FOREIGN KEY (id_solicitante) REFERENCES solicitantes (id_solicitante)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
        
    CONSTRAINT fk_prestamos_usuario_entrega
        FOREIGN KEY (id_usuario_entrega) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
        
    CONSTRAINT fk_prestamos_usuario_recibe
        FOREIGN KEY (id_usuario_recibe) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
        
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- ÍNDICES ADICIONALES PARA OPTIMIZAR CONSULTAS FRECUENTES
-- ----------------------------------------------------------------------------
CREATE INDEX idx_equipos_estado ON equipos (estado);
CREATE INDEX idx_prestamos_estado ON prestamos (estado_prestamo);
CREATE INDEX idx_prestamos_fecha_prevista ON prestamos (fecha_prevista_devolucion);
CREATE INDEX idx_solicitantes_nombre ON solicitantes (nombre_completo);

-- ============================================================================
-- DATOS INICIALES (SEED)
-- ============================================================================
-- 1. DEPARTAMENTOS
INSERT IGNORE INTO departamentos (nombre) VALUES
('Ingeniería en Sistemas Computacionales'),
('Soporte y Servicios Tecnológicos'),
('Ciencias Básicas'),
('Electrónica y Mecatrónica'),
('Servicios Escolares');

-- 2. CATEGORÍAS
INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
('Computadoras Portátiles', 'Laptops para préstamo académico, talleres y eventos'),
('Proyectores', 'Proyectores multimedia HDMI y VGA para aulas'),
('Kits de Electrónica', 'Placas Arduino, protoboards, sensores y multímetros'),
('Equipos de Red', 'Switches, routers, puntos de acceso y ponchadoras'),
('Adaptadores y Accesorios', 'Convertidores HDMI-VGA, extensiones y cables de red');

-- 3. USUARIOS DEL SISTEMA
-- Contraseña para ambos: admin123 (password) y consulta123 (secret)
INSERT IGNORE INTO usuarios (num_empleado, nombre_completo, username, password_hash, rol, activo) VALUES
('SOP-001', 'Administrador TI', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador', 1),
('SOP-002', 'Laura Méndez (Consulta)', 'invitado', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'consulta', 1);

-- 4. SOLICITANTES
INSERT IGNORE INTO solicitantes (numero_control, nombre_completo, id_departamento) VALUES
('EMP-1042', 'Dr. Carlos Ramírez Vega', 1),
('EMP-1088', 'Mtra. Sofía Hernández Cruz', 3),
('22460145', 'Luis Carlo Medina Saucedo', 1),
('22460210', 'Ana Paola Torres Gómez', 4),
('EMP-1105', 'Ing. Roberto Salazar Núñez', 2);

-- 5. EQUIPOS
INSERT IGNORE INTO equipos (codigo_inventario, numero_serie, descripcion, marca, modelo, id_categoria, estado, condicion_fisica) VALUES
('INV-LAP-001', 'SN-DELL-88410', 'Laptop para desarrollo y laboratorio', 'Dell', 'Latitude 5430', 1, 'disponible', 'Óptima'),
('INV-LAP-002', 'SN-DELL-88411', 'Laptop para desarrollo y laboratorio', 'Dell', 'Latitude 5430', 1, 'prestado', 'Óptima, incluye cargador'),
('INV-LAP-003', 'SN-LEN-55201', 'Laptop portátil para conferencias', 'Lenovo', 'ThinkPad T14', 1, 'mantenimiento', 'Falla en puerto de carga'),
('INV-PRY-001', 'SN-EPS-33019', 'Proyector portátil alta luminosidad', 'Epson', 'PowerLite X49', 2, 'disponible', 'Óptima, con control remoto'),
('INV-PRY-002', 'SN-BEN-77412', 'Proyector para sala audiovisual', 'BenQ', 'MS560', 2, 'prestado', 'Buen estado, lente limpio'),
('INV-KIT-001', 'SN-ARD-10091', 'Kit básico Arduino Uno + sensores', 'Arduino', 'Starter Kit R3', 3, 'disponible', 'Completo en caja plástica'),
('INV-RED-001', 'SN-CIS-90214', 'Switch administrable 24 puertos', 'Cisco', 'Catalyst 2960', 4, 'disponible', 'Óptima'),
('INV-ADP-001', NULL, 'Adaptador multipuerto USB-C a HDMI/LAN', 'UGREEN', 'CM498', 5, 'baja', 'Cable roto sin reparación');

-- 6. PRÉSTAMOS (Solo inserta si la tabla prestamos está vacía)
INSERT INTO prestamos (
    id_equipo, id_solicitante, id_usuario_entrega, id_usuario_recibe,
    fecha_prestamo, fecha_prevista_devolucion, fecha_devolucion_real,
    estado_prestamo, condicion_devolucion, requiere_mantenimiento
)
SELECT * FROM (
    SELECT 
        (SELECT id_equipo FROM equipos WHERE codigo_inventario = 'INV-LAP-002') AS id_equipo,
        (SELECT id_solicitante FROM solicitantes WHERE numero_control = 'EMP-1042') AS id_solicitante,
        (SELECT id_usuario FROM usuarios WHERE username = 'admin') AS id_usuario_entrega,
        NULL AS id_usuario_recibe,
        NOW() - INTERVAL 1 DAY AS fecha_prestamo,
        NOW() + INTERVAL 3 DAY AS fecha_prevista_devolucion,
        NULL AS fecha_devolucion_real,
        'activo' AS estado_prestamo,
        NULL AS condicion_devolucion,
        0 AS requiere_mantenimiento
    UNION ALL
    SELECT 
        (SELECT id_equipo FROM equipos WHERE codigo_inventario = 'INV-PRY-002'),
        (SELECT id_solicitante FROM solicitantes WHERE numero_control = '22460145'),
        (SELECT id_usuario FROM usuarios WHERE username = 'admin'),
        NULL,
        NOW() - INTERVAL 5 DAY,
        NOW() - INTERVAL 1 DAY,
        NULL,
        'vencido',
        NULL,
        0
    UNION ALL
    SELECT 
        (SELECT id_equipo FROM equipos WHERE codigo_inventario = 'INV-PRY-001'),
        (SELECT id_solicitante FROM solicitantes WHERE numero_control = 'EMP-1088'),
        (SELECT id_usuario FROM usuarios WHERE username = 'admin'),
        (SELECT id_usuario FROM usuarios WHERE username = 'admin'),
        NOW() - INTERVAL 10 DAY,
        NOW() - INTERVAL 8 DAY,
        NOW() - INTERVAL 8 DAY,
        'devuelto',
        'Devuelto sin daños, incluye maletín y cable HDMI',
        0
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM prestamos);