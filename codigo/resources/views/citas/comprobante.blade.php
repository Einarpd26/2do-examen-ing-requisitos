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
                <span class="badge {{ $cita->estado === 'CANCELADA' ? 'text-bg-secondary' : ($cita->estado === 'ATENDIDA' ? 'text-bg-success' : 'text-bg-primary') }}">{{ $estados[$cita->estado] ?? $cita->estado }}</span>
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
                @if ($cita->estado === 'CANCELADA')
                    <dt class="col-sm-4">Motivo de la cancelación</dt><dd class="col-sm-8">{{ $dato($cita->motivo_cancelacion) }}</dd>
                @endif
            </dl>
        </div>
    </div>

    {{-- CU-12: solo se ofrecen las acciones de asistencia que corresponden hoy a la cita. --}}
    @php($acciones = app(\App\Services\AsistenciaService::class)->posibles($cita))
    @if ($acciones !== [])
        <div class="card shadow-sm mt-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <h2 class="h6 text-uppercase text-secondary mb-0 me-2">Asistencia</h2>
                @if ($propia)
                    @if (in_array('confirmar', $acciones, true))
                        <form method="POST" action="{{ route('mis-citas.confirmar', $cita) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-clinica btn-sm">Confirmar mi asistencia</button>
                        </form>
                    @endif
                @else
                    @foreach (['confirmar' => ['Confirmar asistencia', 'btn-clinica'], 'atendida' => ['Marcar como atendida', 'btn-success'], 'inasistencia' => ['Registrar inasistencia', 'btn-outline-warning']] as $accion => [$etiqueta, $clase])
                        @if (in_array($accion, $acciones, true))
                            <form method="POST" action="{{ route('citas.asistencia', $cita) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="accion" value="{{ $accion }}">
                                <button type="submit" class="btn {{ $clase }} btn-sm">{{ $etiqueta }}</button>
                            </form>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
    @endif

    {{-- CU-10: solo se reprograman las citas programadas o confirmadas. --}}
    @if (in_array($cita->estado, ['PROGRAMADA', 'CONFIRMADA'], true))
        <div class="mt-3">
            <a href="{{ route($propia ? 'mis-citas.reprogramar' : 'citas.reprogramar', $cita) }}" class="btn btn-outline-primary btn-sm">Reprogramar cita</a>
        </div>
    @endif

    {{-- CU-11: solo se cancelan las citas programadas o confirmadas. --}}
    @if (in_array($cita->estado, ['PROGRAMADA', 'CONFIRMADA'], true))
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-secondary">Cancelar esta cita</h2>
                @if ($propia)
                    <p class="small text-secondary">Puede cancelar con al menos {{ config('clinica.citas.horas_min_cancelacion_paciente') }} horas de anticipación.</p>
                @endif
                <form method="POST" action="{{ route($propia ? 'mis-citas.cancelar' : 'citas.cancelar', $cita) }}"
                      class="needs-validation" novalidate
                      data-confirmar="¿Cancelar la cita {{ $cita->codigo }}? Esta acción no se puede deshacer.">
                    @csrf
                    @method('PATCH')
                    <label for="motivo_cancelacion" class="form-label">Motivo de la cancelación</label>
                    <textarea id="motivo_cancelacion" name="motivo_cancelacion" rows="2" maxlength="500" required
                              data-no-vacio="Indique el motivo de la cancelación."
                              class="form-control @error('motivo_cancelacion') is-invalid @enderror">{{ old('motivo_cancelacion') }}</textarea>
                    <div class="invalid-feedback">@error('motivo_cancelacion') {{ $message }} @else Indique el motivo de la cancelación. @enderror</div>
                    <button type="submit" class="btn btn-outline-danger btn-sm mt-3">Cancelar cita</button>
                </form>
            </div>
        </div>
    @endif
@endsection
