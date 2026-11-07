-- Ciclo #2 (CU-09): un paciente no puede tener dos citas superpuestas, ni siquiera con médicos
-- distintos; las canceladas no ocupan horario (RN17, RN09).
-- Referencia de diseño: documento/Informe-Final.md, sección 6.3.2.

ALTER TABLE citas
    ADD CONSTRAINT citas_paciente_sin_superposicion
    EXCLUDE USING gist (paciente_id WITH =, periodo WITH &&) WHERE (estado <> 'CANCELADA');
