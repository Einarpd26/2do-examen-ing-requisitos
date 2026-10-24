@extends('layouts.base')

@section('titulo', 'Mi perfil · Clínica Vida Salud')

@php
    $persona = $paciente->persona;
    $dato = fn ($valor) => filled($valor) ? $valor : '—';
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            Mi perfil <small class="text-secondary">{{ $paciente->codigo }}</small>
        </h1>
        <a href="{{ route('password.cambiar') }}" class="btn btn-outline-secondary btn-sm">Cambiar contraseña</a>
    </div>

    <div class="mb-3">
        @if ($paciente->verificado)
            <span class="badge text-bg-success">Ficha verificada</span>
        @else
            <span class="badge text-bg-warning">Ficha pendiente de verificación</span>
        @endif
    </div>

    @unless ($paciente->verificado)
        <div class="alert alert-warning">
            La recepción todavía debe verificar sus datos con su documento de identidad. Cuando lo haga,
            verá aquí su información médica y su contacto de emergencia.
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">Datos personales</h2>
                    <dl class="row mb-3">
                        <dt class="col-5">Nombre</dt><dd class="col-7">{{ $persona->nombre_completo }}</dd>
                        <dt class="col-5">CI</dt><dd class="col-7">{{ $persona->ci }}</dd>
                        <dt class="col-5">Fecha de nacimiento</dt><dd class="col-7">{{ $dato($persona->fecha_nacimiento?->format('d/m/Y')) }}</dd>
                        <dt class="col-5">Sexo</dt><dd class="col-7">{{ ['M' => 'Masculino', 'F' => 'Femenino'][$persona->sexo] ?? '—' }}</dd>
                        <dt class="col-5">Correo</dt><dd class="col-7">{{ $dato($persona->correo) }}</dd>
                        <dt class="col-5">Dirección</dt><dd class="col-7">{{ $dato($persona->direccion) }}</dd>
                    </dl>

                    <form method="POST" action="{{ route('perfil.actualizar') }}" class="needs-validation" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="row g-2 align-items-start">
                            <x-campo name="telefono" label="Teléfono" tipo="tel" :valor="$persona->telefono" requerido
                                     patron="\+?[0-9 \-\(\)]{7,20}" maximo="20" columnas="col-sm-8"
                                     ayuda="Entre 7 y 15 dígitos. Es el número al que la recepción le envía una contraseña temporal." />
                            <div class="col-sm-4 pt-sm-4">
                                <button type="submit" class="btn btn-clinica mt-sm-2">Guardar teléfono</button>
                            </div>
                        </div>
                    </form>
                    <p class="text-secondary small mt-3 mb-0">
                        El resto de sus datos los modifica la recepción: preséntese con su documento de identidad.
                    </p>
                </div>
            </div>
        </div>

        @if ($paciente->verificado)
            <div class="col-lg-6">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-secondary">Información médica</h2>
                        <dl class="row mb-0">
                            <dt class="col-5">Tipo de sangre</dt><dd class="col-7">{{ $dato($paciente->tipo_sangre) }}</dd>
                            <dt class="col-5">Alergias</dt><dd class="col-7">{{ $dato($paciente->alergias) }}</dd>
                            <dt class="col-5">Enfermedades crónicas</dt><dd class="col-7">{{ $dato($paciente->enfermedades_cronicas) }}</dd>
                            <dt class="col-5">Medicamentos</dt><dd class="col-7">{{ $dato($paciente->medicamentos) }}</dd>
                            <dt class="col-5">Antecedentes</dt><dd class="col-7">{{ $dato($paciente->antecedentes) }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-secondary">Contacto de emergencia</h2>
                        <dl class="row mb-0">
                            <dt class="col-5">Nombre</dt><dd class="col-7">{{ $dato($paciente->emergencia_nombre) }}</dd>
                            <dt class="col-5">Parentesco</dt><dd class="col-7">{{ $dato($paciente->emergencia_parentesco) }}</dd>
                            <dt class="col-5">Teléfono</dt><dd class="col-7">{{ $dato($paciente->emergencia_telefono) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
