-- ============================================================================
-- PROYECTO: Sistema de Registro y Control de Préstamos de Equipo Tecnológico
-- MATERIA: Taller de Base de Datos
-- AVANCE 1: Estructura de Base de Datos (DDL), Claves y Relaciones
-- ============================================================================

USE db_inventario_21;

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