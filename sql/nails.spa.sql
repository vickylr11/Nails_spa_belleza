-- ==========================================================
-- BASE DE DATOS NAILS SPA BELLEZA — SCRIPT FINAL
-- ----------------------------------------------------------
-- Crea la base completa desde cero: tablas, servicios, horarios,
-- manicuristas y usuarios de ejemplo.
--
-- ⚠ ATENCIÓN: la primera línea BORRA la base nails_spa si ya existe,
--   con TODOS sus datos (citas, clientas, pagos). Úsenlo para instalar
--   el proyecto en un computador nuevo o para volver a empezar.
--   Si la base ya tiene datos que hay que conservar, NO lo ejecuten:
--   primero exporten la base desde phpMyAdmin.
--
-- En phpMyAdmin: pestaña Importar > Seleccionar este archivo > Importar.
-- ==========================================================

DROP DATABASE IF EXISTS nails_spa;

CREATE DATABASE nails_spa
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE nails_spa;

-- Para que las tildes y la ñ se guarden bien.
SET NAMES utf8mb4;


-- ==========================================
-- TABLA: CLIENTES
-- ==========================================

CREATE TABLE clientes (

    id_cliente INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    -- Celular de 10 dígitos, siempre guardado igual (sin espacios, guiones ni +57).
    -- Es ÚNICO: con él se reconoce a la clienta y se le cuentan las citas.
    telefono VARCHAR(20) NOT NULL UNIQUE,

    correo VARCHAR(100),

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- ==========================================
-- TABLA: SERVICIOS
-- ==========================================

CREATE TABLE servicios (

    id_servicio INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    descripcion TEXT,

    precio DECIMAL(10,2) NOT NULL,

    duracion INT NOT NULL,

    -- Ruta de la foto, desde la raíz del proyecto (ej. assets/img/servicios/servicio_ab12.jpg).
    imagen VARCHAR(255) NULL,

    activo TINYINT(1) DEFAULT 1

);


-- ==========================================
-- TABLA: MANICURISTAS
-- Cuántas activas hay = cuántas clientas se atienden a la misma hora.
-- Para que una deje de recibir citas: activa = 0 (nunca se borra).
-- ==========================================

CREATE TABLE manicuristas (

    id_manicurista INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    -- Porcentaje del precio de cada servicio que se le paga a la manicurista.
    porcentaje TINYINT UNSIGNED NOT NULL DEFAULT 50,

    activa TINYINT(1) DEFAULT 1

);


-- ==========================================
-- TABLA: RESERVAS
-- ==========================================

CREATE TABLE reservas (

    id_reserva INT AUTO_INCREMENT PRIMARY KEY,

    id_cliente INT NOT NULL,

    id_servicio INT NOT NULL,

    id_manicurista INT NOT NULL,

    fecha DATE NOT NULL,

    hora TIME NOT NULL,

    -- Precio y duración COPIADOS del servicio el día que se reservó.
    -- Si después el salón cambia el precio o la duración del servicio,
    -- las citas ya agendadas no cambian (la historia no se altera y
    -- ninguna cita vieja se "estira" encima de otra).
    precio DECIMAL(10,2) NOT NULL,

    duracion INT NOT NULL,

    -- web = la reservó la clienta en la página; salon = la registró el administrador
    -- (clienta que llegó sin cita, o por teléfono).
    origen ENUM('web', 'salon') NOT NULL DEFAULT 'web',

    -- 1 = cita gratis de la tarjeta de fidelidad (la número 10).
    -- La clienta no paga; a la manicurista se le paga igual (sobre el precio).
    gratis TINYINT(1) NOT NULL DEFAULT 0,

    -- Pago a la manicurista: en qué pago quedó incluida esta cita y cuánto se le pagó.
    -- NULL = todavía no se le ha pagado.
    id_pago INT NULL,

    valor_manicurista DECIMAL(10,2) NULL,

    estado ENUM(
        'Pendiente',
        'Confirmada',
        'Completada',
        'No asistió',
        'Cancelada'
    ) DEFAULT 'Pendiente',

    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reserva_cliente

        FOREIGN KEY (id_cliente)

        REFERENCES clientes(id_cliente)

        ON DELETE RESTRICT,


    CONSTRAINT fk_reserva_servicio

        FOREIGN KEY (id_servicio)

        REFERENCES servicios(id_servicio)

        ON DELETE RESTRICT,


    CONSTRAINT fk_reserva_manicurista

        FOREIGN KEY (id_manicurista)

        REFERENCES manicuristas(id_manicurista)

        ON DELETE RESTRICT

);


-- ==========================================
-- TABLA: USUARIOS (personal del salón)
-- La clave se guarda como SHA-256 de (sal + clave).
-- ==========================================

CREATE TABLE usuarios (

    id_usuario INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    correo VARCHAR(100) NOT NULL UNIQUE,

    sal VARCHAR(32) NOT NULL,

    clave CHAR(64) NOT NULL,

    -- administrador: todo. manicurista: solo su agenda.
    rol ENUM('administrador', 'manicurista') NOT NULL,

    -- Solo para rol manicurista: a cuál manicurista corresponde.
    -- UNIQUE: una manicurista tiene como máximo UNA cuenta.
    id_manicurista INT NULL UNIQUE,

    -- Para quitarle el acceso a alguien sin borrarla: activo = 0.
    activo TINYINT(1) DEFAULT 1,

    CONSTRAINT fk_usuario_manicurista
        FOREIGN KEY (id_manicurista)
        REFERENCES manicuristas(id_manicurista)
        ON DELETE RESTRICT,

    -- Una manicurista SIEMPRE debe estar ligada a su registro.
    CONSTRAINT chk_usuario_manicurista
        CHECK (rol = 'administrador' OR id_manicurista IS NOT NULL)

);


-- ==========================================
-- TABLA: PAGOS (a las manicuristas)
-- Cada pago junta las citas Completadas de una manicurista en un periodo.
-- ==========================================

CREATE TABLE pagos (

    id_pago INT AUTO_INCREMENT PRIMARY KEY,

    id_manicurista INT NOT NULL,

    desde DATE NOT NULL,

    hasta DATE NOT NULL,

    servicios INT NOT NULL,

    total DECIMAL(10,2) NOT NULL,

    -- Quién registró el pago y cuándo.
    id_usuario INT NOT NULL,

    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pago_manicurista
        FOREIGN KEY (id_manicurista) REFERENCES manicuristas(id_manicurista) ON DELETE RESTRICT,

    CONSTRAINT fk_pago_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT

);

-- La llave de reservas hacia pagos se agrega aquí, porque pagos se crea después de reservas.
ALTER TABLE reservas
    ADD CONSTRAINT fk_reserva_pago
        FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE RESTRICT;


-- ==========================================
-- TABLA: HORARIOS
-- ==========================================

CREATE TABLE horarios (

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
    duracion,
    imagen
)
VALUES

(
    'Manicure clásico',
    'Limpieza, cuidado y esmaltado tradicional.',
    25000,
    60,
    'assets/img/foto4.png'
),

(
    'Pedicure clásico',
    'Cuidado completo de pies y uñas.',
    35000,
    60,
    'assets/img/foto3.png'
),

(
    'Manicure semipermanente',
    'Esmaltado semipermanente de larga duración.',
    50000,
    90,
    'assets/img/foto5.png'
),

(
    'Spa de uñas',
    'Tratamiento de relajación y cuidado especial.',
    55000,
    90,
    'assets/img/foto6.png'
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


-- ==========================================
-- MANICURISTAS
-- ==========================================

INSERT INTO manicuristas (nombre) VALUES
('Marangeles Perez'),
('Victoria Lemos'),
('Jennifer Atencia'),
('Valeria Albaran');


-- ==========================================
-- USUARIOS DE LA ZONA DEL PERSONAL (/super/)
-- admin@nailsspa.com / admin123  (administrador)
-- marangeles@ / victoria@ / jennifer@ / valeria@nailsspa.com
--   con clave <nombre>123           (manicuristas)
-- ¡CAMBIARLAS antes de entregar! (Mi contraseña, dentro de /super/)
-- ==========================================

INSERT INTO usuarios (nombre, correo, sal, clave, rol, id_manicurista) VALUES
('Administradora',   'admin@nailsspa.com',      'n41ls5pa', SHA2(CONCAT('n41ls5pa', 'admin123'),      256), 'administrador', NULL),
('Marangeles Perez', 'marangeles@nailsspa.com', 'm4r4ng3l', SHA2(CONCAT('m4r4ng3l', 'marangeles123'), 256), 'manicurista', 1),
('Victoria Lemos',   'victoria@nailsspa.com',   'v1ct0r1a', SHA2(CONCAT('v1ct0r1a', 'victoria123'),   256), 'manicurista', 2),
('Jennifer Atencia', 'jennifer@nailsspa.com',   'j3nn1f3r', SHA2(CONCAT('j3nn1f3r', 'jennifer123'),   256), 'manicurista', 3),
('Valeria Albaran',  'valeria@nailsspa.com',    'v4l3r14a', SHA2(CONCAT('v4l3r14a', 'valeria123'),    256), 'manicurista', 4);
