@extends('layouts.base')

@section('titulo', 'Reprogramar cita '.$cita->codigo.' · Clínica Vida Salud')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            Reprogramar cita <small class="text-secondary">{{ $cita->codigo }}</small>
        </h1>
        <a href="{{ route($propia ? 'mis-citas.comprobante' : 'citas.comprobante', $cita) }}" class="btn btn-outline-secondary btn-sm">Volver al comprobante</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-secondary">Cita actual</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Paciente</dt><dd class="col-sm-8">{{ $cita->paciente->persona->nombre_completo }}</dd>
                <dt class="col-sm-4">Médico</dt><dd class="col-sm-8">{{ $cita->medico->persona->nombre_completo }} · {{ $cita->especialidad->nombre }}</dd>
                <dt class="col-sm-4">Fecha y horario</dt>
                <dd class="col-sm-8">{{ $cita->fecha->format('d/m/Y') }}, {{ $cita->desde() }} – {{ $cita->hasta() }}</dd>
            </dl>
            @if ($propia)
                <p class="small text-secondary mt-2 mb-0">Puede reprogramar con al menos {{ config('clinica.citas.horas_min_cancelacion_paciente') }} horas de anticipación.</p>
            @endif
        </div>
    </div>

    {{-- Paso 1: la nueva fecha (GET, funciona sin JavaScript). --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-secondary">1. Nueva fecha</h2>
            <form method="GET" action="{{ route($propia ? 'mis-citas.reprogramar' : 'citas.reprogramar', $cita) }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-4">
                    <label for="fecha" class="form-label">Fecha</label>
                    <input type="date" id="fecha" name="fecha" value="{{ $fecha }}" class="form-control"
                           min="{{ $primeraFecha }}" max="{{ $ultimaFecha }}" required>
                    <div class="form-text">Hasta el {{ \Carbon\Carbon::parse($ultimaFecha)->format('d/m/Y') }}.</div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-clinica">Ver horarios disponibles</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Paso 2: el nuevo horario; se guarda con PUT. --}}
    @if ($fecha !== '')
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-secondary">2. Nuevo horario</h2>

                @if ($aviso)
                    <div class="alert alert-warning mb-0">{{ $aviso }}</div>
                @elseif (count($horarios) === 0)
                    <div class="alert alert-info mb-0">
                        No hay horarios disponibles el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}. Pruebe con otra fecha.
                    </div>
                @else
                    <form method="POST" action="{{ route($propia ? 'mis-citas.actualizar' : 'citas.actualizar', $cita) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="fecha" value="{{ $fecha }}">

                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">
                                Horarios disponibles el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                                <span class="text-danger" aria-hidden="true">*</span>
                            </legend>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($horarios as $horario)
                                    <div>
                                        <input type="radio" class="btn-check" name="hora_inicio" id="hora_{{ $loop->index }}"
                                               value="{{ $horario['hora_inicio'] }}" required>
                                        <label class="btn btn-outline-primary" for="hora_{{ $loop->index }}">
                                            {{ $horario['hora_inicio'] }} – {{ $horario['hora_fin'] }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <button type="submit" class="btn btn-clinica">Reprogramar cita</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
