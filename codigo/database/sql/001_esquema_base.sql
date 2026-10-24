-- Ciclo #1: identidad, acceso, datos maestros y bitácora.
-- Referencia de diseño: documento/Informe-Final.md, sección 6.3.2.
-- Los textos usan TEXT; los formatos se acotan con CHECK donde importa.

CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE TABLE sucursales (
    id         BIGSERIAL   PRIMARY KEY,
    nombre     TEXT        NOT NULL UNIQUE CHECK (btrim(nombre) <> ''),
    direccion  TEXT,
    estado     BOOLEAN     NOT NULL DEFAULT TRUE
);

CREATE TABLE personas (
    id               BIGSERIAL   PRIMARY KEY,
    ci               TEXT        NOT NULL UNIQUE CHECK (ci ~ '^[0-9]{5,10}(-[0-9A-Z]{1,2})?$'),
    primer_nombre    TEXT        NOT NULL CHECK (btrim(primer_nombre) <> ''),
    segundo_nombre   TEXT        CHECK (segundo_nombre IS NULL OR btrim(segundo_nombre) <> ''),
    apellido_paterno TEXT        NOT NULL CHECK (btrim(apellido_paterno) <> ''),
    apellido_materno TEXT        CHECK (apellido_materno IS NULL OR btrim(apellido_materno) <> ''),
    -- Nombre completo derivado: siempre coincide con sus partes y se puede buscar e imprimir.
    nombre_completo  TEXT        GENERATED ALWAYS AS (
        TRIM(BOTH FROM (
            primer_nombre
            || COALESCE(' ' || segundo_nombre, '')
            || ' ' || apellido_paterno
            || COALESCE(' ' || apellido_materno, '')
        ))
    ) STORED,
    fecha_nacimiento DATE        CHECK (fecha_nacimiento <= CURRENT_DATE),
    sexo             TEXT        CHECK (sexo IN ('M', 'F')),
    telefono         TEXT        CHECK (telefono ~ '^\+?[0-9]{7,15}$'),
    correo           TEXT        CHECK (correo ~* '^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$'),
    direccion        TEXT,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
-- El correo es opcional, pero único (sin distinguir mayúsculas) cuando existe.
CREATE UNIQUE INDEX personas_correo_uq ON personas (lower(correo)) WHERE correo IS NOT NULL;

-- Control de acceso (RBAC): los permisos son datos validados por claves foráneas.
-- Cada permiso usa el formato recurso:accion (por ejemplo, pacientes:gestionar).
CREATE TABLE roles (
    id          BIGSERIAL   PRIMARY KEY,
    codigo      TEXT        NOT NULL UNIQUE CHECK (codigo ~ '^[A-Z_]+$'),
    nombre      TEXT        NOT NULL CHECK (btrim(nombre) <> ''),
    descripcion TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE permisos (
    id          BIGSERIAL   PRIMARY KEY,
    codigo      TEXT        NOT NULL UNIQUE CHECK (codigo ~ '^[a-z_]+:[a-z_]+$'),
    descripcion TEXT        NOT NULL CHECK (btrim(descripcion) <> ''),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE rol_permisos (
    rol_id     BIGINT NOT NULL REFERENCES roles (id) ON DELETE CASCADE,
    permiso_id BIGINT NOT NULL REFERENCES permisos (id) ON DELETE CASCADE,
    PRIMARY KEY (rol_id, permiso_id)
);
CREATE INDEX rol_permisos_permiso_idx ON rol_permisos (permiso_id);

CREATE TABLE usuarios (
    id                      BIGSERIAL   PRIMARY KEY,
    persona_id              BIGINT      NOT NULL UNIQUE REFERENCES personas (id) ON DELETE RESTRICT,
    password_hash           TEXT        NOT NULL,
    -- Un solo rol por cuenta. Una persona puede ser médico y paciente, pero entra con rol MEDICO.
    rol_id                  BIGINT      NOT NULL REFERENCES roles (id) ON DELETE RESTRICT,
    estado                  BOOLEAN     NOT NULL DEFAULT TRUE,
    intentos_fallidos       SMALLINT    NOT NULL DEFAULT 0 CHECK (intentos_fallidos >= 0),
    bloqueado_hasta         TIMESTAMPTZ,
    debe_cambiar_password   BOOLEAN     NOT NULL DEFAULT FALSE,
    password_temporal_vence TIMESTAMPTZ,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    -- Las dos columnas van juntas: una contraseña temporal siempre tiene vencimiento.
    CHECK (debe_cambiar_password = (password_temporal_vence IS NOT NULL))
);
CREATE INDEX usuarios_rol_idx ON usuarios (rol_id);

CREATE TABLE pacientes (
    id                    BIGSERIAL   PRIMARY KEY,
    persona_id            BIGINT      NOT NULL UNIQUE REFERENCES personas (id) ON DELETE RESTRICT,
    verificado            BOOLEAN     NOT NULL DEFAULT FALSE,
    estado                BOOLEAN     NOT NULL DEFAULT TRUE,
    tipo_sangre           TEXT        CHECK (tipo_sangre IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')),
    alergias              TEXT,
    enfermedades_cronicas TEXT,
    medicamentos          TEXT,
    antecedentes          TEXT,
    emergencia_nombre     TEXT,
    emergencia_parentesco TEXT,
    emergencia_telefono   TEXT        CHECK (emergencia_telefono ~ '^\+?[0-9]{7,15}$'),
    created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE medicos (
    id                       BIGSERIAL   PRIMARY KEY,
    persona_id               BIGINT      NOT NULL UNIQUE REFERENCES personas (id) ON DELETE RESTRICT,
    nro_registro_profesional TEXT        NOT NULL UNIQUE CHECK (btrim(nro_registro_profesional) <> ''),
    estado                   BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE especialidades (
    id           BIGSERIAL   PRIMARY KEY,
    nombre       TEXT        NOT NULL CHECK (btrim(nombre) <> ''),
    descripcion  TEXT,
    duracion_min SMALLINT    NOT NULL DEFAULT 30 CHECK (duracion_min BETWEEN 5 AND 240),
    estado       BOOLEAN     NOT NULL DEFAULT TRUE
);
-- Nombre único normalizado: sin distinguir mayúsculas ni espacios en los extremos.
CREATE UNIQUE INDEX especialidades_nombre_uq ON especialidades (lower(btrim(nombre)));

CREATE TABLE medico_especialidad (
    medico_id       BIGINT   NOT NULL REFERENCES medicos (id) ON DELETE CASCADE,
    especialidad_id BIGINT   NOT NULL REFERENCES especialidades (id) ON DELETE RESTRICT,
    PRIMARY KEY (medico_id, especialidad_id)
);
CREATE INDEX medico_especialidad_especialidad_idx ON medico_especialidad (especialidad_id);

CREATE TABLE horarios_atencion (
    id          BIGSERIAL PRIMARY KEY,
    medico_id   BIGINT    NOT NULL REFERENCES medicos (id) ON DELETE CASCADE,
    sucursal_id BIGINT    NOT NULL REFERENCES sucursales (id) ON DELETE RESTRICT,
    dia_semana  SMALLINT  NOT NULL CHECK (dia_semana BETWEEN 1 AND 7),
    hora_inicio TIME      NOT NULL,
    hora_fin    TIME      NOT NULL,
    consultorio TEXT,
    CHECK (hora_fin > hora_inicio),
    -- Rango del bloque dentro de un día de referencia, para comparar superposiciones.
    rango TSRANGE GENERATED ALWAYS AS
        (tsrange(DATE '2000-01-01' + hora_inicio, DATE '2000-01-01' + hora_fin)) STORED,
    -- Un médico no puede tener dos bloques superpuestos el mismo día, ni en sucursales distintas.
    EXCLUDE USING gist (medico_id WITH =, dia_semana WITH =, rango WITH &&)
);
CREATE INDEX horarios_atencion_sucursal_idx ON horarios_atencion (sucursal_id);

-- La bitácora es solo de añadir: no se modifica, no se borra y no se vacía.
CREATE TABLE bitacora (
    id         BIGSERIAL   PRIMARY KEY,
    usuario_id BIGINT      REFERENCES usuarios (id) ON DELETE RESTRICT,
    accion     TEXT        NOT NULL CHECK (btrim(accion) <> ''),
    entidad    TEXT,
    entidad_id BIGINT,
    fecha      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX bitacora_usuario_idx ON bitacora (usuario_id);
CREATE INDEX bitacora_fecha_idx ON bitacora (fecha);

CREATE OR REPLACE FUNCTION bitacora_solo_agregar() RETURNS TRIGGER
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'La bitácora es de solo escritura: no se permite % sobre bitacora', TG_OP;
END;
$$;

CREATE TRIGGER bitacora_sin_modificar
    BEFORE UPDATE OR DELETE ON bitacora
    FOR EACH ROW EXECUTE FUNCTION bitacora_solo_agregar();

CREATE TRIGGER bitacora_sin_vaciar
    BEFORE TRUNCATE ON bitacora
    FOR EACH STATEMENT EXECUTE FUNCTION bitacora_solo_agregar();
