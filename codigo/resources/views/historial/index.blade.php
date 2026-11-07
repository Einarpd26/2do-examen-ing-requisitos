@extends('layouts.base')

@section('titulo', 'Historial de citas · Clínica Vida Salud')

@php
    $insignia = ['PROGRAMADA' => 'text-bg-primary', 'CONFIRMADA' => 'text-bg-info', 'ATENDIDA' => 'text-bg-success', 'CANCELADA' => 'text-bg-secondary', 'INASISTENCIA' => 'text-bg-warning'];
    $ruta = $propio ? route('historial.propio') : route('pacientes.historial', $paciente);
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            Historial de citas
            @unless ($propio)
                <small class="text-secondary">{{ $paciente->persona->nombre_completo }} · {{ $paciente->codigo }}</small>
            @endunless
        </h1>
        @unless ($propio)
            @can('pacientes:gestionar')
                <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm">Volver a la ficha</a>
            @endcan
        @endunless
    </div>

    @unless ($propio || $paciente->estado)
        <div class="alert alert-secondary">Este paciente está desactivado; su historial se conserva.</div>
    @endunless

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($estados as $codigo => $etiqueta)
            <span class="badge {{ $insignia[$codigo] }}">{{ $etiqueta }}: {{ $resumen[$codigo] }}</span>
        @endforeach
    </div>

    <form method="GET" action="{{ $ruta }}" class="row g-2 align-items-end mb-3">
        <div class="col-sm-4 col-md-3">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                @foreach ($estados as $codigo => $etiqueta)
                    <option value="{{ $codigo }}" @selected($estado === $codigo)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-clinica">Filtrar</button>
            @if ($estado)
                <a href="{{ $ruta }}" class="btn btn-outline-secondary">Quitar filtro</a>
            @endif
        </div>
    </form>

    @if ($citas->isEmpty())
        <div class="alert alert-info">
            {{ $estado ? 'No hay citas con ese estado.' : ($propio ? 'Todavía no tiene citas registradas.' : 'Este paciente todavía no tiene citas registradas.') }}
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha y hora</th>
                        <th>Médico</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th>Registrada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($citas as $cita)
                        <tr>
                            <td>{{ $cita->codigo }}</td>
                            <td>{{ $cita->fecha->format('d/m/Y') }} {{ $cita->desde() }}–{{ $cita->hasta() }}</td>
                            <td>{{ $cita->medico->persona->nombre_completo }}</td>
                            <td>{{ $cita->especialidad->nombre }}</td>
                            <td>
                                <span class="badge {{ $insignia[$cita->estado] ?? 'text-bg-light' }}">{{ $estados[$cita->estado] ?? $cita->estado }}</span>
                                @if ($cita->estado === 'CANCELADA' && filled($cita->motivo_cancelacion))
                                    <div class="small text-secondary">{{ $cita->motivo_cancelacion }}</div>
                                @endif
                            </td>
                            <td>{{ $cita->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route($propio ? 'mis-citas.comprobante' : 'citas.comprobante', $cita) }}" class="btn btn-outline-secondary btn-sm">Ver</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $citas->links() }}</div>
    @endif
@endsection
