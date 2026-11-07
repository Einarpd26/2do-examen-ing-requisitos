@extends('layouts.base')

@section('titulo', 'Ficha de '.$paciente->codigo.' · Clínica Vida Salud')

@php
    $persona = $paciente->persona;
    $dato = fn ($valor) => filled($valor) ? $valor : '—';
    $estados = ['PROGRAMADA' => 'Programada', 'CONFIRMADA' => 'Confirmada', 'ATENDIDA' => 'Atendida', 'CANCELADA' => 'Cancelada', 'INASISTENCIA' => 'Inasistencia'];
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            {{ $persona->nombre_completo }}
            <small class="text-secondary">{{ $paciente->codigo }}</small>
        </h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary btn-sm">Volver a mi agenda</a>
            <a href="{{ route('pacientes.notas', $paciente) }}" class="btn btn-outline-primary btn-sm">Notas clínicas</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">Datos básicos</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">CI</dt><dd class="col-7">{{ $persona->ci }}</dd>
                        <dt class="col-5">Fecha de nacimiento</dt>
                        <dd class="col-7">
                            {{ $dato($persona->fecha_nacimiento?->format('d/m/Y')) }}
                            @if ($persona->fecha_nacimiento)
                                <span class="text-secondary">({{ $persona->fecha_nacimiento->age }} años)</span>
                            @endif
                        </dd>
                        <dt class="col-5">Sexo</dt><dd class="col-7">{{ ['M' => 'Masculino', 'F' => 'Femenino'][$persona->sexo] ?? '—' }}</dd>
                        <dt class="col-5">Teléfono</dt><dd class="col-7">{{ $dato($persona->telefono) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">Información médica</h2>
                    @if ($paciente->verificado)
                        <dl class="row mb-3">
                            <dt class="col-5">Tipo de sangre</dt><dd class="col-7">{{ $dato($paciente->tipo_sangre) }}</dd>
                            <dt class="col-5">Alergias</dt><dd class="col-7">{{ $dato($paciente->alergias) }}</dd>
                            <dt class="col-5">Enfermedades crónicas</dt><dd class="col-7">{{ $dato($paciente->enfermedades_cronicas) }}</dd>
                            <dt class="col-5">Medicamentos</dt><dd class="col-7">{{ $dato($paciente->medicamentos) }}</dd>
                            <dt class="col-5">Antecedentes</dt><dd class="col-7">{{ $dato($paciente->antecedentes) }}</dd>
                        </dl>
                        <h2 class="h6 text-uppercase text-secondary">Contacto de emergencia</h2>
                        <dl class="row mb-0">
                            <dt class="col-5">Nombre</dt><dd class="col-7">{{ $dato($paciente->emergencia_nombre) }}</dd>
                            <dt class="col-5">Parentesco</dt><dd class="col-7">{{ $dato($paciente->emergencia_parentesco) }}</dd>
                            <dt class="col-5">Teléfono</dt><dd class="col-7">{{ $dato($paciente->emergencia_telefono) }}</dd>
                        </dl>
                    @else
                        <div class="alert alert-warning mb-0">
                            La ficha de este paciente todavía no fue verificada por la recepción, así que su información
                            médica y su contacto de emergencia no se muestran.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <h2 class="h6 text-uppercase text-secondary mt-4">Citas con usted</h2>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Código</th><th>Fecha y hora</th><th>Especialidad</th><th>Estado</th></tr></thead>
            <tbody>
                @foreach ($citas as $cita)
                    <tr>
                        <td>{{ $cita->codigo }}</td>
                        <td>{{ $cita->fecha->format('d/m/Y') }} {{ $cita->desde() }}–{{ $cita->hasta() }}</td>
                        <td>{{ $cita->especialidad->nombre }}</td>
                        <td>{{ $estados[$cita->estado] ?? $cita->estado }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
