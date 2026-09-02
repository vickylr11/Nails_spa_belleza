-- ==========================================
-- BASE DE DATOS NAILS SPA BELLEZA
-- ==========================================

CREATE DATABASE IF NOT EXISTS nails_spa
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE nails_spa;


-- ==========================================
-- TABLA: CLIENTES
-- ==========================================

CREATE TABLE IF NOT EXISTS clientes (

    id_cliente INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    telefono VARCHAR(20) NOT NULL,

    correo VARCHAR(100),

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- ==========================================
-- TABLA: SERVICIOS
-- ==========================================

CREATE TABLE IF NOT EXISTS servicios (

    id_servicio INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    descripcion TEXT,

    precio DECIMAL(10,2) NOT NULL,

    duracion INT NOT NULL,

    activo TINYINT(1) DEFAULT 1

);


-- ==========================================
-- TABLA: RESERVAS
-- ==========================================

CREATE TABLE IF NOT EXISTS reservas (

    id_reserva INT AUTO_INCREMENT PRIMARY KEY,

    id_cliente INT NOT NULL,

    id_servicio INT NOT NULL,

    fecha DATE NOT NULL,

    hora TIME NOT NULL,

    estado ENUM(
        'Pendiente',
        'Confirmada',
        'Cancelada'
    ) DEFAULT 'Pendiente',

    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reserva_cliente

        FOREIGN KEY (id_cliente)

        REFERENCES clientes(id_cliente)

        ON DELETE CASCADE,


    CONSTRAINT fk_reserva_servicio

        FOREIGN KEY (id_servicio)

        REFERENCES servicios(id_servicio)

        ON DELETE CASCADE,


    UNIQUE KEY reserva_horario (fecha, hora)

);


-- ==========================================
-- TABLA: HORARIOS
-- ==========================================

CREATE TABLE IF NOT EXISTS horarios (

    id_horario INT AUTO_INCREMENT PRIMARY KEY,

    dia VARCHAR(20) NOT NULL,

    hora_apertura TIME NULL,

    hora_cierre TIME NULL,

    disponible TINYINT(1) DEFAULT 1

);


-- ==========================================
-- SERVICIOS INICIALES
-- ==========================================

INSERT INTO servicios
(
    nombre,
    descripcion,
    precio,
    duracion
)
VALUES

(
    'Manicure clásico',
    'Limpieza, cuidado y esmaltado tradicional.',
    25000,
    60
),

(
    'Pedicure clásico',
    'Cuidado completo de pies y uñas.',
    35000,
    60
),

(
    'Manicure semipermanente',
    'Esmaltado semipermanente de larga duración.',
    50000,
    90
),

(
    'Spa de uñas',
    'Tratamiento de relajación y cuidado especial.',
    55000,
    90
);


-- ==========================================
-- HORARIOS INICIALES
-- ==========================================

INSERT INTO horarios
(
    dia,
    hora_apertura,
    hora_cierre,
    disponible
)
VALUES

(
    'Lunes',
    '09:00:00',
    '18:00:00',
    1
),

(
    'Martes',
    '09:00:00',
    '18:00:00',
    1
),

(
    'Miércoles',
    '09:00:00',
    '18:00:00',
    1
),

(
    'Jueves',
    '09:00:00',
    '18:00:00',
    1
),

(
    'Viernes',
    '09:00:00',
    '18:00:00',
    1
),

(
    'Sábado',
    '09:00:00',
    '16:00:00',
    1
),

(
    'Domingo',
    NULL,
    NULL,
    0
);