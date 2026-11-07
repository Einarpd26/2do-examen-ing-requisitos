<?php

/*
|--------------------------------------------------------------------------
| Opciones del menú
|--------------------------------------------------------------------------
|
| Cada opción se muestra solo a las cuentas cuyo rol concede el permiso indicado
| (formato recurso:accion, ver informe sección 4.7). "ruta" es el nombre de la ruta
| a la que lleva; mientras no exista, la opción aparece deshabilitada. "rol" (opcional) limita la opción a ese rol.
|
*/

return [

    'opciones' => [
        ['etiqueta' => 'Usuarios', 'permiso' => 'usuarios:gestionar', 'ruta' => 'usuarios.index'],
        ['etiqueta' => 'Médicos', 'permiso' => 'medicos:gestionar', 'ruta' => 'medicos.index'],
        ['etiqueta' => 'Especialidades', 'permiso' => 'especialidades:gestionar', 'ruta' => 'especialidades.index'],
        ['etiqueta' => 'Consultorios', 'permiso' => 'consultorios:gestionar', 'ruta' => 'consultorios.index'],
        ['etiqueta' => 'Horarios de atención', 'permiso' => 'horarios:gestionar', 'ruta' => 'horarios.index'],
        ['etiqueta' => 'Registrar cita', 'permiso' => 'citas:gestionar', 'ruta' => 'citas.nueva'],
        ['etiqueta' => 'Reservar cita', 'permiso' => 'citas:gestionar_propias', 'ruta' => 'mis-citas.nueva'],
        ['etiqueta' => 'Reportes', 'permiso' => 'reportes:consultar', 'ruta' => 'reportes.index'],
        ['etiqueta' => 'Agenda', 'permiso' => 'agenda:consultar', 'ruta' => 'agenda.index'],
        ['etiqueta' => 'Mi historial', 'permiso' => 'historial:consultar', 'ruta' => 'historial.propio', 'rol' => 'PACIENTE'],
        ['etiqueta' => 'Mis notas', 'permiso' => 'notas:consultar', 'ruta' => 'notas.propias', 'rol' => 'PACIENTE'],
        ['etiqueta' => 'Pacientes', 'permiso' => 'pacientes:gestionar', 'ruta' => 'pacientes.index'],
        ['etiqueta' => 'Mi perfil', 'permiso' => 'perfil:gestionar', 'ruta' => 'perfil.mostrar'],
    ],

];
