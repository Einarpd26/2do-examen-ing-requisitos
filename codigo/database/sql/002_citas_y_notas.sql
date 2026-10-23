-- Ciclo #2: citas médicas y notas clínicas.
-- Referencia de diseño: documento/Informe-Final.md, sección 6.3.2.

CREATE TABLE citas (
    id                 BIGSERIAL   PRIMARY KEY,
    codigo             VARCHAR(12) NOT NULL UNIQUE,
    paciente_id        BIGINT      NOT NULL REFERENCES pacientes (id) ON DELETE RESTRICT,
    medico_id          BIGINT      NOT NULL REFERENCES medicos (id) ON DELETE RESTRICT,
    especialidad_id    SMALLINT    NOT NULL REFERENCES especialidades (id) ON DELETE RESTRICT,
    sucursal_id        SMALLINT    NOT NULL REFERENCES sucursales (id) ON DELETE RESTRICT,
    fecha              DATE        NOT NULL,
    hora_inicio        TIME        NOT NULL,
    hora_fin           TIME        NOT NULL,
    modalidad          VARCHAR(10) NOT NULL DEFAULT 'PRESENCIAL'
                       CHECK (modalidad IN ('PRESENCIAL', 'VIRTUAL')),
    estado             VARCHAR(12) NOT NULL DEFAULT 'PROGRAMADA'
                       CHECK (estado IN ('PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA')),
    motivo             VARCHAR(300),
    motivo_cancelacion VARCHAR(300),
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (hora_fin > hora_inicio),
    periodo TSRANGE GENERATED ALWAYS AS (tsrange(fecha + hora_inicio, fecha + hora_fin)) STORED,
    -- Un médico no puede tener dos citas superpuestas; las canceladas no ocupan horario (RN07, RN09).
    EXCLUDE USING gist (medico_id WITH =, periodo WITH &&) WHERE (estado <> 'CANCELADA')
);

CREATE TABLE notas_clinicas (
    id               BIGSERIAL   PRIMARY KEY,
    cita_id          BIGINT      NOT NULL UNIQUE REFERENCES citas (id) ON DELETE RESTRICT,
    medico_id        BIGINT      NOT NULL REFERENCES medicos (id) ON DELETE RESTRICT,
    motivo_consulta  TEXT,
    diagnostico      TEXT,
    tratamiento      TEXT,
    observaciones    TEXT,
    modificado_por   BIGINT      REFERENCES usuarios (id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
