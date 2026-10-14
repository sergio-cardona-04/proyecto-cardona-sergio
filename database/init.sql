CREATE TABLE IF NOT EXISTS incidencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    titulo VARCHAR(120) NOT NULL,

    descripcion TEXT NOT NULL,

    categoria ENUM(
        'hardware',
        'software',
        'red',
        'otro'
    ) NOT NULL,

    estado ENUM(
        'pendiente',
        'en_proceso',
        'resuelta'
    ) NOT NULL DEFAULT 'pendiente',

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_titulo
        CHECK (CHAR_LENGTH(TRIM(titulo)) >= 3)
);

INSERT INTO incidencias
    (titulo, descripcion, categoria, estado)
VALUES
    (
        'Equipo no enciende',
        'Computadora del laboratorio no inicia.',
        'hardware',
        'pendiente'
    ),
    (
        'Error al iniciar sesión',
        'El usuario no puede acceder al sistema.',
        'software',
        'en_proceso'
    ),
    (
        'Sin conexión a Internet',
        'El aula presenta problemas de red.',
        'red',
        'resuelta'
    );
