<?php

/*
|--------------------------------------------------------------------------
| Opciones del menú
|--------------------------------------------------------------------------
|
| Cada opción se muestra solo a las cuentas cuyo rol concede el permiso indicado
| (formato recurso:accion, ver informe sección 4.7). "ruta" es el nombre de la ruta
| a la que lleva; mientras no exista, la opción aparece deshabilitada.
|
*/

return [

    'opciones' => [
        ['etiqueta' => 'Usuarios', 'permiso' => 'usuarios:gestionar', 'ruta' => 'usuarios.index'],
        ['etiqueta' => 'Médicos', 'permiso' => 'medicos:gestionar', 'ruta' => 'medicos.index'],
        ['etiqueta' => 'Especialidades', 'permiso' => 'especialidades:gestionar', 'ruta' => 'especialidades.index'],
        ['etiqueta' => 'Horarios de atención', 'permiso' => 'horarios:gestionar', 'ruta' => 'horarios.index'],
        ['etiqueta' => 'Pacientes', 'permiso' => 'pacientes:gestionar', 'ruta' => 'pacientes.index'],
        ['etiqueta' => 'Mi perfil', 'permiso' => 'perfil:gestionar', 'ruta' => 'perfil.mostrar'],
    ],

];
