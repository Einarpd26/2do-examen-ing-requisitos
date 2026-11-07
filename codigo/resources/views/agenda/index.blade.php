@extends('layouts.base')

@section('titulo', 'Agenda médica · Clínica Vida Salud')

@php
    $insignia = ['PROGRAMADA' => 'text-bg-primary', 'CONFIRMADA' => 'text-bg-info', 'ATENDIDA' => 'text-bg-success', 'CANCELADA' => 'text-bg-secondary', 'INASISTENCIA' => 'text-bg-warning'];
@endphp

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 fw-semibold mb-1">{{ $propia ? 'Mi agenda' : 'Agenda médica' }}</h1>
        <p class="text-secondary mb-0">
            {{ $propia ? 'Sus citas del día.' : 'Elija al médico y la fecha para ver sus citas.' }}
        </p>
    </div>

    @if ($aviso)
        <div class="alert alert-warning" role="alert">{{ $aviso }}</div>
    @endif

    <form method="GET" action="{{ route('agenda.index') }}" class="row g-2 align-items-end mb-3">
        @unless ($propia)
            <div class="col-md-4">
                <label for="medico" class="form-label">Médico</label>
                <select id="medico" name="medico" class="form-select">
                    <option value="">Elija un médico</option>
                    @foreach ($medicos as $opcion)
                        <option value="{{ $opcion->id }}" @selected($medico?->id === $opcion->id)>
                            {{ $opcion->persona->nombre_completo }}{{ $opcion->estado ? '' : ' (inactivo)' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endunless
        <div class="col-sm-6 col-md-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" id="fecha" name="fecha" value="{{ $fecha->toDateString() }}" class="form-control">
        </div>
        <div class="col-sm-6 col-md-3">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                @foreach ($estados as $codigo => $etiqueta)
                    <option value="{{ $codigo }}" @selected($estado === $codigo)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-clinica">Consultar</button>
        </div>
    </form>

    @if ($medico === null)
        <div class="alert alert-info">Elija un médico para ver su agenda.</div>
    @else
        <h2 class="h5">
            {{ $medico->persona->nombre_completo }}
            <small class="text-secondary">{{ $fecha->format('d/m/Y') }}</small>
        </h2>

        @if ($citas->isEmpty())
            <div class="alert alert-info">
                {{ $estado ? 'No hay citas con ese estado en esa fecha.' : 'No hay citas en esa fecha.' }}
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Código</th>
                            <th>Paciente</th>
                            <th>Especialidad</th>
                            <th>Estado</th>
                            <th>Motivo</th>
                            @if ($propia)
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($citas as $cita)
                            <tr>
                                <td>{{ $cita->desde() }}–{{ $cita->hasta() }}</td>
                                <td>{{ $cita->codigo }}</td>
                                <td>{{ $cita->paciente->persona->nombre_completo }}</td>
                                <td>{{ $cita->especialidad->nombre }}</td>
                                <td><span class="badge {{ $insignia[$cita->estado] ?? 'text-bg-light' }}">{{ $estados[$cita->estado] ?? $cita->estado }}</span></td>
                                <td>{{ filled($cita->motivo) ? $cita->motivo : '—' }}</td>
                                @if ($propia)
                                    <td class="text-end">
                                        {{-- CU-12: el Médico marca como atendida una cita vigente que ya comenzó. --}}
                                        @can('citas:atender')
                                            @if (in_array('atendida', $acciones[$cita->id] ?? [], true))
                                                <form method="POST" action="{{ route('agenda.atender', $cita) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-success btn-sm">Marcar atendida</button>
                                                </form>
                                            @endif
                                        @endcan
                                        {{-- CU-16 y CU-17: nota clínica de una cita atendida y notas del paciente. --}}
                                        @if ($cita->estado === 'ATENDIDA')
                                            <a href="{{ route('notas.formulario', $cita) }}" class="btn btn-outline-primary btn-sm">Nota clínica</a>
                                        @endif
                                        {{-- Las citas canceladas no hacen «suyo» al paciente: no se ofrece su ficha ni sus notas. --}}
                                        @if ($cita->estado !== 'CANCELADA')
                                            <a href="{{ route('pacientes.ficha', $cita->paciente_id) }}" class="btn btn-outline-secondary btn-sm">Ficha del paciente</a>
                                            <a href="{{ route('pacientes.notas', $cita->paciente_id) }}" class="btn btn-outline-secondary btn-sm">Notas del paciente</a>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
