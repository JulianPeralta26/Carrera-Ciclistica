-- =====================================================================
-- BASE DE DATOS: uniespinal_ciclismo
-- PROYECTO: uniespinal2026 ciclistico
-- =====================================================================

DROP DATABASE IF EXISTS uniespinal_ciclismo;
CREATE DATABASE uniespinal_ciclismo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uniespinal_ciclismo;

--Tabla1: eventos
CREATE TABLE eventos (
    id_evento INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha_evento DATE NOT NULL,
    hora_inicio TIME NOT NULL DEFAULT '08:00:00',
    hora_fin TIME NOT NULL DEFAULT '16:00:00',
    lugar VARCHAR(150) NOT NULL DEFAULT 'El Espinal, Tolima'
) ENGINE=InnoDB;

--Tabla2: categorias
CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255)
) ENGINE=InnoDB;

--Tabla3: recorridos
CREATE TABLE recorridos (
    id_recorrido INT AUTO_INCREMENT PRIMARY KEY,
    distancia_km DECIMAL(5,2) NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255)
) ENGINE=InnoDB;

--Tabla4: modalidades
CREATE TABLE modalidades (
    id_modalidad INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    id_categoria INT NOT NULL,
    id_recorrido INT NOT NULL,
    desc_modalidad VARCHAR(255),
    FOREIGN KEY (id_evento) REFERENCES eventos(id_evento) ON DELETE RESTRICT,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria) ON DELETE RESTRICT,
    FOREIGN KEY (id_recorrido) REFERENCES recorridos(id_recorrido) ON DELETE RESTRICT,
    UNIQUE KEY uk_evento_cat_rec (id_evento, id_categoria, id_recorrido)
) ENGINE=InnoDB;

--Tabla5: grupos_salida
-- Permite definir libremente el color, modalidad, rango de edad y hora de salida.
-- El color no queda atado a la edad: cada grupo define su color y horario.
CREATE TABLE grupos_salida (
    id_grupo INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL DEFAULT 1,
    id_modalidad INT NOT NULL,
    color VARCHAR(30) NOT NULL,
    edad_minima INT NOT NULL,
    edad_maxima INT NOT NULL,
    hora_salida TIME NOT NULL,
    FOREIGN KEY (id_evento) REFERENCES eventos(id_evento) ON DELETE RESTRICT,
    FOREIGN KEY (id_modalidad) REFERENCES modalidades(id_modalidad) ON DELETE RESTRICT,
    CONSTRAINT chk_edades CHECK (edad_minima <= edad_maxima)
) ENGINE=InnoDB;

--Tabla6: participantes
CREATE TABLE participantes (
    id_participante INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    primer_nombre VARCHAR(60) NOT NULL,
    segundo_nombre VARCHAR(60),
    primer_apellido VARCHAR(60) NOT NULL,
    segundo_apellido VARCHAR(60),
    direccion VARCHAR(200) NOT NULL,
    fecha_nacimiento DATE NOT NULL
) ENGINE=InnoDB;

--Tabla7: contactos
CREATE TABLE contactos (
    id_contacto INT AUTO_INCREMENT PRIMARY KEY,
    id_participante INT NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    telefono_emergencia VARCHAR(20) NOT NULL,
    nombre_contacto_emergencia VARCHAR(100),
    FOREIGN KEY (id_participante) REFERENCES participantes(id_participante) ON DELETE RESTRICT
) ENGINE=InnoDB;

--Tabla8: estados
CREATE TABLE estados (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    desc_estado VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

--Tabla9: inscripciones
CREATE TABLE inscripciones (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_participante INT NOT NULL,
    id_modalidad INT NOT NULL,
    id_grupo_salida INT NOT NULL,
    id_estado INT NOT NULL DEFAULT 1,
    fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_participante) REFERENCES participantes(id_participante) ON DELETE RESTRICT,
    FOREIGN KEY (id_modalidad) REFERENCES modalidades(id_modalidad) ON DELETE RESTRICT,
    FOREIGN KEY (id_grupo_salida) REFERENCES grupos_salida(id_grupo) ON DELETE RESTRICT,
    FOREIGN KEY (id_estado) REFERENCES estados(id_estado) ON DELETE RESTRICT,
    UNIQUE KEY uk_participante_modalidad (id_participante, id_modalidad)
) ENGINE=InnoDB;

--Tabla10: resultados
CREATE TABLE resultados (
    id_resultado INT AUTO_INCREMENT PRIMARY KEY,
    id_inscripcion INT NOT NULL UNIQUE,
    hora_llegada TIME,
    tiempo_empleado TIME,
    puesto_modalidad INT,
    puesto_general INT,
    id_estado INT NOT NULL DEFAULT 3,
    observaciones VARCHAR(255),
    FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion) ON DELETE RESTRICT,
    FOREIGN KEY (id_estado) REFERENCES estados(id_estado) ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =====================================================================
-- DATOS INICIALES / SEMILLA (Seed Data)
-- =====================================================================

-- 1. Insertar el evento principal
INSERT INTO eventos (nombre, descripcion, fecha_evento, hora_inicio, hora_fin, lugar)
VALUES ('uniespinal2026 ciclistico', 'Gran carrera ciclistica uniespinal 2026', '2026-11-15', '08:00:00', '16:00:00', 'El Espinal');

-- 2. Insertar Estados de competencia e inscripcion
INSERT INTO estados (desc_estado) VALUES
('Inscrito'),
('Cancelado'),
('Finalizado'),
('Retirado'),
('Descalificado'),
('No inicio');

-- 3. Insertar Categorias
INSERT INTO categorias (nombre, descripcion) VALUES
('Velocidad', 'Bicicletas de ruta y alto rendimiento'),
('Panaderas', 'Bicicletas tradicionales de trabajo/panaderas'),
('Recreativas', 'Participantes aficionados y bicicletas de paseo/montaña');

-- 4. Insertar Recorridos
INSERT INTO recorridos (distancia_km, nombre, descripcion) VALUES
(5.00, 'Circuito Corto 5K', 'Recorrido urbano de 5 kilometros'),
(10.00, 'Circuito Largo 10K', 'Recorrido extendido de 10 kilometros');

-- 5. Generar Modalidades del evento (combinando categorias y recorridos)
INSERT INTO modalidades (id_evento, id_categoria, id_recorrido, desc_modalidad) VALUES
(1, 1, 1, 'Velocidad - 5 Kilómetros'),
(1, 1, 2, 'Velocidad - 10 Kilómetros'),
(1, 2, 1, 'Panaderas - 5 Kilómetros'),
(1, 2, 2, 'Panaderas - 10 Kilómetros'),
(1, 3, 1, 'Recreativas - 5 Kilómetros'),
(1, 3, 2, 'Recreativas - 10 Kilómetros');

-- 6. Insertar Grupos de salida configurados por modalidad, color, rango de edad y hora de salida
INSERT INTO grupos_salida (id_evento, id_modalidad, color, edad_minima, edad_maxima, hora_salida) VALUES
-- Grupos para Velocidad 5K (Modalidad 1)
(1, 1, 'Amarillo', 14, 29, '08:00:00'),
(1, 1, 'Azul',     30, 99, '08:15:00'),

-- Grupos para Velocidad 10K (Modalidad 2)
(1, 2, 'Rojo',     18, 35, '08:30:00'),
(1, 2, 'Negro',    36, 99, '08:45:00'),

-- Grupos para Panaderas 5K (Modalidad 3)
(1, 3, 'Verde',    14, 40, '09:00:00'),
(1, 3, 'Naranja',  41, 99, '09:15:00'),

-- Grupos para Panaderas 10K (Modalidad 4)
(1, 4, 'Blanco',   18, 99, '09:30:00'),

-- Grupos para Recreativas 5K (Modalidad 5)
(1, 5, 'Morado',   10, 99, '10:00:00'),

-- Grupos para Recreativas 10K (Modalidad 6)
(1, 6, 'Dorado',   16, 99, '10:30:00');


-- ---------------------------------------------------------------------
-- VISTA DE CONSULTA: Vista general de resultados
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_clasificacion_modalidad AS
SELECT 
    e.nombre AS evento,
    c.nombre AS categoria,
    CONCAT(r.distancia_km, ' Km') AS recorrido,
    p.cedula,
    CONCAT(
        p.primer_nombre, ' ', 
        IFNULL(CONCAT(p.segundo_nombre, ' '), ''), 
        p.primer_apellido, ' ', 
        IFNULL(p.segundo_apellido, '')
    ) AS participante,
    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, e.fecha_evento) AS edad,
    gs.color AS color_salida,
    gs.hora_salida,
    res.hora_llegada,
    COALESCE(
        res.tiempo_empleado, 
        TIMEDIFF(res.hora_llegada, gs.hora_salida)
    ) AS tiempo_calculado,
    res.puesto_modalidad,
    res.puesto_general,
    est.desc_estado AS estado
FROM resultados res
JOIN inscripciones i ON res.id_inscripcion = i.id_inscripcion
JOIN participantes p ON i.id_participante = p.id_participante
JOIN modalidades m ON i.id_modalidad = m.id_modalidad
JOIN eventos e ON m.id_evento = e.id_evento
JOIN categorias c ON m.id_categoria = c.id_categoria
JOIN recorridos r ON m.id_recorrido = r.id_recorrido
JOIN grupos_salida gs ON i.id_grupo_salida = gs.id_grupo
JOIN estados est ON res.id_estado = est.id_estado
ORDER BY m.id_modalidad, res.puesto_modalidad ASC;
