-- Ciclo #2: citas médicas y notas clínicas.
-- Referencia de diseño: documento/Informe-Final.md, sección 6.3.2.

CREATE SEQUENCE seq_codigo_cita;

CREATE TABLE citas (
    id                 BIGSERIAL   PRIMARY KEY,
    codigo             TEXT        NOT NULL UNIQUE
                       DEFAULT codigo_con_prefijo('CM-', nextval('seq_codigo_cita'))
                       CHECK (codigo ~ '^CM-[0-9]{4,}$'),
    paciente_id        BIGINT      NOT NULL REFERENCES pacientes (id) ON DELETE RESTRICT,
    medico_id          BIGINT      NOT NULL,
    especialidad_id    BIGINT      NOT NULL,
    sucursal_id        BIGINT      NOT NULL REFERENCES sucursales (id) ON DELETE RESTRICT,
    fecha              DATE        NOT NULL,
    hora_inicio        TIME        NOT NULL,
    hora_fin           TIME        NOT NULL,
    modalidad          TEXT        NOT NULL DEFAULT 'PRESENCIAL'
                       CHECK (modalidad IN ('PRESENCIAL', 'VIRTUAL')),
    estado             TEXT        NOT NULL DEFAULT 'PROGRAMADA'
                       CHECK (estado IN ('PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA')),
    motivo             TEXT,
    motivo_cancelacion TEXT,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (hora_fin > hora_inicio),
    periodo TSRANGE GENERATED ALWAYS AS (tsrange(fecha + hora_inicio, fecha + hora_fin)) STORED,
    -- El médico solo puede atender una especialidad que ejerce.
    FOREIGN KEY (medico_id, especialidad_id)
        REFERENCES medico_especialidad (medico_id, especialidad_id) ON DELETE RESTRICT,
    -- Un médico no puede tener dos citas superpuestas; las canceladas no ocupan horario (RN07, RN09).
    EXCLUDE USING gist (medico_id WITH =, periodo WITH &&) WHERE (estado <> 'CANCELADA'),
    -- Permite que la nota clínica se ate al médico de la cita.
    UNIQUE (id, medico_id)
);
ALTER SEQUENCE seq_codigo_cita OWNED BY citas.codigo;
CREATE INDEX citas_paciente_idx ON citas (paciente_id);
CREATE INDEX citas_especialidad_idx ON citas (especialidad_id);
CREATE INDEX citas_sucursal_idx ON citas (sucursal_id);
CREATE INDEX citas_fecha_idx ON citas (fecha);

CREATE TABLE notas_clinicas (
    id               BIGSERIAL   PRIMARY KEY,
    cita_id          BIGINT      NOT NULL UNIQUE,
    medico_id        BIGINT      NOT NULL,
    motivo_consulta  TEXT,
    diagnostico      TEXT,
    tratamiento      TEXT,
    observaciones    TEXT,
    modificado_por   BIGINT      REFERENCES usuarios (id) ON DELETE RESTRICT,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    -- La nota la escribe el médico de la cita.
    FOREIGN KEY (cita_id, medico_id) REFERENCES citas (id, medico_id) ON DELETE RESTRICT
);
CREATE INDEX notas_clinicas_medico_idx ON notas_clinicas (medico_id);
