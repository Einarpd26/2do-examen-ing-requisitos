<?php

// Mensajes de validación en español. Solo incluye las reglas que usa el sistema.
return [

    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser un texto.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'confirmed' => 'La confirmación del campo :attribute no coincide.',
    'different' => 'El campo :attribute debe ser distinto de :other.',
    'in' => 'El valor elegido para :attribute no es válido.',
    'exists' => 'El valor elegido para :attribute no existe.',
    'unique' => 'El valor del campo :attribute ya está registrado.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',

    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],

    'password' => [
        'letters' => 'El campo :attribute debe incluir al menos una letra.',
        'mixed' => 'El campo :attribute debe combinar mayúsculas y minúsculas.',
        'numbers' => 'El campo :attribute debe incluir al menos un número.',
        'symbols' => 'El campo :attribute debe incluir al menos un símbolo.',
        'uncompromised' => 'El valor de :attribute apareció en una filtración de datos. Elija otro.',
    ],

    'custom' => [],

    // Nombres de los campos tal como se muestran al usuario en los mensajes.
    'attributes' => [
        'identificador' => 'CI o correo',
        'contrasena' => 'contraseña',
        'password' => 'nueva contraseña',
        'password_actual' => 'contraseña actual',
        'password_confirmation' => 'confirmación de la contraseña',

        'ci' => 'CI',
        'primer_nombre' => 'primer nombre',
        'segundo_nombre' => 'segundo nombre',
        'apellido_paterno' => 'apellido paterno',
        'apellido_materno' => 'apellido materno',
        'fecha_nacimiento' => 'fecha de nacimiento',
        'sexo' => 'sexo',
        'telefono' => 'teléfono',
        'correo' => 'correo',
        'direccion' => 'dirección',
        'tipo_sangre' => 'tipo de sangre',
        'alergias' => 'alergias',
        'enfermedades_cronicas' => 'enfermedades crónicas',
        'medicamentos' => 'medicamentos',
        'antecedentes' => 'antecedentes',
        'emergencia_nombre' => 'nombre del contacto de emergencia',
        'emergencia_parentesco' => 'parentesco del contacto de emergencia',
        'emergencia_telefono' => 'teléfono del contacto de emergencia',
    ],

];
