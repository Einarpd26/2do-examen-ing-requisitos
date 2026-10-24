@extends('layouts.base')

@section('titulo', 'Contraseña temporal · Clínica Vida Salud')

@section('contenido')
    <x-comprobante-credenciales
        titulo="Acceso del personal"
        :nombre="$usuario->persona->nombre_completo.' ('.$usuario->rol->nombre.')'"
        etiqueta-usuario="Usuario (correo)"
        :usuario="$credenciales['usuario']"
        :contrasena="$credenciales['contrasena']"
        :vence="\Carbon\CarbonImmutable::parse($credenciales['vence'])"
        :volver-url="route('usuarios.index')"
        volver-texto="Ya la entregué, volver a los usuarios"
        ayuda-renovar="Si se perdió, renueve la contraseña desde la edición del usuario." />
@endsection
