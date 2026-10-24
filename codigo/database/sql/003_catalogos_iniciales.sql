-- Datos de referencia que el sistema necesita para operar: sucursal, roles, permisos y
-- la asignación de permisos a cada rol. Documentados en informe, sección 4.7.
-- Los permisos no llevan alcance: "propias" o "del paciente" lo resuelve la aplicación.

INSERT INTO sucursales (nombre) VALUES ('Central');

INSERT INTO roles (codigo, nombre, descripcion) VALUES
    ('ADMINISTRADOR', 'Administrador', 'Configura y supervisa el sistema: usuarios, médicos, especialidades, horarios y reportes.'),
    ('RECEPCIONISTA', 'Recepcionista', 'Gestiona pacientes y citas, verifica fichas y renueva accesos.'),
    ('MEDICO', 'Médico', 'Consulta su agenda y a sus pacientes, y registra notas clínicas.'),
    ('PACIENTE', 'Paciente', 'Gestiona su perfil y sus propias citas.');

INSERT INTO permisos (codigo, descripcion) VALUES
    ('usuarios:gestionar', 'Crear, modificar, activar o desactivar usuarios del personal y asignar su rol'),
    ('accesos:renovar', 'Generar una nueva contraseña temporal para una cuenta'),
    ('pacientes:gestionar', 'Registrar y modificar pacientes, su información médica y su contacto de emergencia'),
    ('pacientes:consultar', 'Consultar los datos de un paciente en solo lectura'),
    ('pacientes:verificar', 'Marcar una ficha de paciente como verificada con el documento'),
    ('pacientes:crear_acceso', 'Crear la cuenta de un paciente con contraseña temporal'),
    ('medicos:gestionar', 'Registrar y modificar médicos y sus especialidades'),
    ('especialidades:gestionar', 'Registrar y modificar especialidades y su duración de cita'),
    ('horarios:gestionar', 'Registrar y modificar los horarios de atención'),
    ('perfil:gestionar', 'Ver el propio perfil y cambiar teléfono y contraseña'),
    ('citas:gestionar', 'Registrar, reprogramar, cancelar y confirmar citas de cualquier paciente'),
    ('citas:gestionar_propias', 'Registrar, reprogramar y cancelar las propias citas'),
    ('citas:atender', 'Marcar una cita como atendida'),
    ('agenda:consultar', 'Consultar la agenda de un médico'),
    ('historial:consultar', 'Consultar el historial de citas de un paciente'),
    ('notas:registrar', 'Registrar y editar las propias notas clínicas'),
    ('notas:consultar', 'Consultar las notas clínicas'),
    ('recordatorios:consultar', 'Ver el aviso de próximas citas'),
    ('reportes:consultar', 'Consultar los reportes administrativos');

-- LEFT JOIN a propósito: si un código está mal escrito, la fila queda con NULL y la
-- inserción falla en lugar de omitir el permiso en silencio.
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id
FROM (VALUES
    ('ADMINISTRADOR', 'usuarios:gestionar'),
    ('ADMINISTRADOR', 'accesos:renovar'),
    ('ADMINISTRADOR', 'medicos:gestionar'),
    ('ADMINISTRADOR', 'especialidades:gestionar'),
    ('ADMINISTRADOR', 'horarios:gestionar'),
    ('ADMINISTRADOR', 'reportes:consultar'),
    ('RECEPCIONISTA', 'pacientes:gestionar'),
    ('RECEPCIONISTA', 'pacientes:consultar'),
    ('RECEPCIONISTA', 'pacientes:verificar'),
    ('RECEPCIONISTA', 'pacientes:crear_acceso'),
    ('RECEPCIONISTA', 'accesos:renovar'),
    ('RECEPCIONISTA', 'citas:gestionar'),
    ('RECEPCIONISTA', 'agenda:consultar'),
    ('RECEPCIONISTA', 'historial:consultar'),
    ('RECEPCIONISTA', 'notas:consultar'),
    ('MEDICO', 'pacientes:consultar'),
    ('MEDICO', 'citas:atender'),
    ('MEDICO', 'agenda:consultar'),
    ('MEDICO', 'notas:registrar'),
    ('MEDICO', 'notas:consultar'),
    ('PACIENTE', 'perfil:gestionar'),
    ('PACIENTE', 'citas:gestionar_propias'),
    ('PACIENTE', 'historial:consultar'),
    ('PACIENTE', 'notas:consultar'),
    ('PACIENTE', 'recordatorios:consultar')
) AS m (rol, permiso)
LEFT JOIN roles r ON r.codigo = m.rol
LEFT JOIN permisos p ON p.codigo = m.permiso;
