-- Ciclo #1: identidad, acceso, datos maestros y bitácora.
-- Referencia de diseño: documento/Informe-Final.md, sección 6.3.2.

CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE TABLE sucursales (
    id         SMALLSERIAL  PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL UNIQUE,
    direccion  VARCHAR(200),
    estado     BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE personas (
    id               BIGSERIAL    PRIMARY KEY,
    ci               VARCHAR(20)  NOT NULL UNIQUE,
    nombres          VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE,
    sexo             CHAR(1)      CHECK (sexo IN ('M', 'F')),
    telefono         VARCHAR(20),
    correo           VARCHAR(150),
    direccion        VARCHAR(200),
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ  NOT NULL DEFAULT now()
);
-- El correo es opcional, pero único (sin distinguir mayúsculas) cuando existe.
CREATE UNIQUE INDEX personas_correo_uq ON personas (lower(correo)) WHERE correo IS NOT NULL;

CREATE TABLE usuarios (
    id                      BIGSERIAL    PRIMARY KEY,
    persona_id              BIGINT       NOT NULL UNIQUE REFERENCES personas (id) ON DELETE RESTRICT,
    password_hash           VARCHAR(255) NOT NULL,
    rol                     VARCHAR(15)  NOT NULL
                            CHECK (rol IN ('ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO', 'PACIENTE')),
    estado                  BOOLEAN      NOT NULL DEFAULT TRUE,
    intentos_fallidos       SMALLINT     NOT NULL DEFAULT 0,
    bloqueado_hasta         TIMESTAMPTZ,
    debe_cambiar_password   BOOLEAN      NOT NULL DEFAULT FALSE,
    password_temporal_vence TIMESTAMPTZ,
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CHECK (debe_cambiar_password OR password_temporal_vence IS NULL)
);

CREATE TABLE pacientes (
    id                    BIGSERIAL    PRIMARY KEY,
    persona_id            BIGINT       NOT NULL UNIQUE REFERENCES personas (id) ON DELETE RESTRICT,
    codigo                VARCHAR(12)  NOT NULL UNIQUE,
    verificado            BOOLEAN      NOT NULL DEFAULT FALSE,
    estado                BOOLEAN      NOT NULL DEFAULT TRUE,
    tipo_sangre           VARCHAR(3),
    alergias              TEXT,
    enfermedades_cronicas TEXT,
    medicamentos          TEXT,
    antecedentes          TEXT,
    emergencia_nombre     VARCHAR(150),
    emergencia_parentesco VARCHAR(50),
    emergencia_telefono   VARCHAR(20),
    created_at            TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at            TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE medicos (
    id                       BIGSERIAL   PRIMARY KEY,
    persona_id               BIGINT      NOT NULL UNIQUE REFERENCES personas (id) ON DELETE RESTRICT,
    codigo                   VARCHAR(12) NOT NULL UNIQUE,
    nro_registro_profesional VARCHAR(30) NOT NULL UNIQUE,
    estado                   BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE especialidades (
    id           SMALLSERIAL  PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL,
    descripcion  VARCHAR(300),
    duracion_min SMALLINT     NOT NULL DEFAULT 30 CHECK (duracion_min BETWEEN 5 AND 240),
    estado       BOOLEAN      NOT NULL DEFAULT TRUE
);
-- Nombre único normalizado: sin distinguir mayúsculas ni espacios en los extremos.
CREATE UNIQUE INDEX especialidades_nombre_uq ON especialidades (lower(btrim(nombre)));

CREATE TABLE medico_especialidad (
    medico_id       BIGINT   NOT NULL REFERENCES medicos (id) ON DELETE CASCADE,
    especialidad_id SMALLINT NOT NULL REFERENCES especialidades (id) ON DELETE RESTRICT,
    PRIMARY KEY (medico_id, especialidad_id)
);

CREATE TABLE horarios_atencion (
    id          BIGSERIAL   PRIMARY KEY,
    medico_id   BIGINT      NOT NULL REFERENCES medicos (id) ON DELETE CASCADE,
    sucursal_id SMALLINT    NOT NULL REFERENCES sucursales (id) ON DELETE RESTRICT,
    dia_semana  SMALLINT    NOT NULL CHECK (dia_semana BETWEEN 1 AND 7),
    hora_inicio TIME        NOT NULL,
    hora_fin    TIME        NOT NULL,
    consultorio VARCHAR(50),
    CHECK (hora_fin > hora_inicio)
);

CREATE TABLE bitacora (
    id         BIGSERIAL   PRIMARY KEY,
    usuario_id BIGINT      REFERENCES usuarios (id) ON DELETE SET NULL,
    accion     VARCHAR(50) NOT NULL,
    entidad    VARCHAR(50),
    entidad_id BIGINT,
    fecha      TIMESTAMPTZ NOT NULL DEFAULT now()
);
