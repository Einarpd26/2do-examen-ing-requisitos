@extends('layouts.base')

@section('titulo', 'Cita '.$cita->codigo.' · Clínica Vida Salud')

@php
    $paciente = $cita->paciente;
    $dato = fn ($valor) => filled($valor) ? $valor : '—';
    $estados = ['PROGRAMADA' => 'Programada', 'CONFIRMADA' => 'Confirmada', 'ATENDIDA' => 'Atendida', 'CANCELADA' => 'Cancelada', 'INASISTENCIA' => 'Inasistencia'];
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            Comprobante de cita <small class="text-secondary">{{ $cita->codigo }}</small>
        </h1>
        <a href="{{ route($propia ? 'mis-citas.nueva' : 'citas.nueva') }}" class="btn btn-clinica btn-sm">
            {{ $propia ? 'Reservar otra cita' : 'Registrar otra cita' }}
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="mb-3">
                <span class="badge text-bg-primary">{{ $estados[$cita->estado] ?? $cita->estado }}</span>
            </p>
            <dl class="row mb-0">
                <dt class="col-sm-4">Código</dt><dd class="col-sm-8">{{ $cita->codigo }}</dd>
                <dt class="col-sm-4">Paciente</dt>
                <dd class="col-sm-8">{{ $paciente->persona->nombre_completo }} <span class="text-secondary small">{{ $paciente->codigo }}</span></dd>
                <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8">{{ $cita->especialidad->nombre }}</dd>
                <dt class="col-sm-4">Médico</dt><dd class="col-sm-8">{{ $cita->medico->persona->nombre_completo }}</dd>
                <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">{{ $cita->fecha->format('d/m/Y') }}</dd>
                <dt class="col-sm-4">Horario</dt><dd class="col-sm-8">{{ $cita->desde() }} – {{ $cita->hasta() }}</dd>
                <dt class="col-sm-4">Modalidad</dt><dd class="col-sm-8">{{ $cita->modalidad === 'VIRTUAL' ? 'Virtual' : 'Presencial' }}</dd>
                <dt class="col-sm-4">Sucursal</dt>
                <dd class="col-sm-8">{{ $cita->sucursal->nombre }} <span class="text-secondary small">{{ $cita->sucursal->direccion }}</span></dd>
                <dt class="col-sm-4">Motivo</dt><dd class="col-sm-8">{{ $dato($cita->motivo) }}</dd>
            </dl>
        </div>
    </div>
@endsection
