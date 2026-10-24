@extends('layouts.base')

@section('titulo', 'Contraseña temporal · Clínica Vida Salud')

@section('contenido')
    <x-comprobante-credenciales
        titulo="Acceso del paciente"
        :nombre="$paciente->persona->nombre_completo"
        etiqueta-usuario="Usuario (CI)"
        :usuario="$credenciales['usuario']"
        :contrasena="$credenciales['contrasena']"
        :vence="\Carbon\CarbonImmutable::parse($credenciales['vence'])"
        :volver-url="route('pacientes.show', $paciente)"
        volver-texto="Ya la entregué, volver a la ficha"
        ayuda-renovar="Si se perdió, renueve la contraseña desde la ficha del paciente." />
@endsection
