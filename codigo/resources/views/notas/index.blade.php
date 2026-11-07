@extends('layouts.base')

@section('titulo', 'Notas clínicas · Clínica Vida Salud')

@php
    $campos = [
        'motivo_consulta' => 'Motivo de consulta',
        'diagnostico' => 'Diagnóstico',
        'tratamiento' => 'Tratamiento o indicaciones',
        'observaciones' => 'Observaciones',
    ];
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            {{ $propio ? 'Mis notas clínicas' : 'Notas clínicas' }}
            @unless ($propio)
                <small class="text-secondary">{{ $paciente->persona->nombre_completo }} · {{ $paciente->codigo }}</small>
            @endunless
        </h1>
    </div>

    @if ($pendiente)
        <div class="alert alert-warning">
            La recepción todavía debe verificar sus datos con su documento de identidad. Cuando lo haga,
            verá aquí las notas clínicas de sus consultas.
        </div>
    @elseif ($notas->isEmpty())
        <div class="alert alert-info">
            {{ $propio ? 'Todavía no tiene notas clínicas.' : 'Este paciente todavía no tiene notas clínicas.' }}
        </div>
    @else
        @foreach ($notas as $nota)
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <h2 class="h6 mb-0">
                                {{ $nota->cita->fecha->format('d/m/Y') }} {{ $nota->cita->desde() }} · {{ $nota->cita->especialidad->nombre }}
                            </h2>
                            <div class="small text-secondary">
                                {{ $nota->medico->persona->nombre_completo }} · cita {{ $nota->cita->codigo }}
                            </div>
                        </div>
                        @if ($puedeEditar !== null && $puedeEditar === $nota->medico_id)
                            <a href="{{ route('notas.formulario', $nota->cita) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                        @endif
                    </div>
                    <dl class="row mb-2">
                        @foreach ($campos as $campo => $etiqueta)
                            @if (filled($nota->{$campo}))
                                <dt class="col-sm-3">{{ $etiqueta }}</dt>
                                <dd class="col-sm-9" style="white-space: pre-line">{{ $nota->{$campo} }}</dd>
                            @endif
                        @endforeach
                    </dl>
                    <div class="small text-secondary">
                        Última modificación: {{ $nota->modificador?->persona->nombre_completo ?? '—' }},
                        {{ $nota->updated_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
