-- Consultorios como entidad propia (ADR-002, CU-20, RN18).
-- Un consultorio pertenece a una sucursal y se asigna, de forma opcional, a los bloques de atención.
-- Dos médicos no pueden tener bloques superpuestos en el mismo consultorio el mismo día de la semana:
-- el consultorio se reserva durante todo el bloque, tenga citas o no (ADR-002, decisión 7).
-- Las citas no se tocan: heredan el consultorio del bloque en el que caen.

CREATE TABLE consultorios (
    id          BIGSERIAL PRIMARY KEY,
    sucursal_id BIGINT    NOT NULL REFERENCES sucursales (id) ON DELETE RESTRICT,
    nombre      TEXT      NOT NULL CHECK (btrim(nombre) <> ''),
    estado      BOOLEAN   NOT NULL DEFAULT TRUE,
    -- Destino de la clave foránea compuesta de horarios_atencion (mismo consultorio y misma sucursal).
    UNIQUE (id, sucursal_id)
);
-- Nombre único por sucursal, sin distinguir mayúsculas ni espacios en los extremos.
CREATE UNIQUE INDEX consultorios_nombre_uq ON consultorios (sucursal_id, lower(btrim(nombre)));

-- El sistema está en desarrollo y solo tiene datos de prueba: el texto libre se descarta y los
-- bloques quedan sin consultorio, que es opcional (ADR-002, decisión 6).
ALTER TABLE horarios_atencion DROP COLUMN consultorio;
ALTER TABLE horarios_atencion ADD COLUMN consultorio_id BIGINT;

-- Clave foránea compuesta: un bloque no puede usar un consultorio de otra sucursal.
-- Con consultorio_id nulo no se comprueba (MATCH SIMPLE), porque el consultorio es opcional.
ALTER TABLE horarios_atencion
    ADD CONSTRAINT horarios_atencion_consultorio_fk
    FOREIGN KEY (consultorio_id, sucursal_id) REFERENCES consultorios (id, sucursal_id) ON DELETE RESTRICT;

-- RN18: un consultorio no puede tener dos bloques superpuestos el mismo día. Un bloque sin
-- consultorio no choca con nadie, porque NULL nunca es igual a NULL. Los bloques pegados
-- (uno termina cuando empieza el otro) no se superponen: el rango es semiabierto.
ALTER TABLE horarios_atencion
    ADD CONSTRAINT horarios_consultorio_sin_superposicion
    EXCLUDE USING gist (consultorio_id WITH =, dia_semana WITH =, rango WITH &&);

INSERT INTO permisos (codigo, descripcion) VALUES
    ('consultorios:gestionar', 'Registrar, modificar, activar o desactivar consultorios');

-- LEFT JOIN a propósito, como en 003: un código mal escrito hace fallar la inserción.
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id
FROM (VALUES
    ('ADMINISTRADOR', 'consultorios:gestionar')
) AS m (rol, permiso)
LEFT JOIN roles r ON r.codigo = m.rol
LEFT JOIN permisos p ON p.codigo = m.permiso;
