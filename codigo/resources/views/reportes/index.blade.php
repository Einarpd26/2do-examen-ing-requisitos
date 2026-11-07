@extends('layouts.base')

@section('titulo', 'Reportes · Clínica Vida Salud')

@php
    $insignia = ['PROGRAMADA' => 'text-bg-primary', 'CONFIRMADA' => 'text-bg-info', 'ATENDIDA' => 'text-bg-success', 'CANCELADA' => 'text-bg-secondary', 'INASISTENCIA' => 'text-bg-warning'];
@endphp

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 fw-semibold mb-1">Reportes</h1>
        <p class="text-secondary mb-0">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}.</p>
    </div>

    @if ($aviso)
        <div class="alert alert-warning" role="alert">{{ $aviso }}</div>
    @endif

    <ul class="nav nav-tabs mb-3">
        @foreach (['citas' => 'Citas', 'pacientes' => 'Pacientes registrados'] as $clave => $etiqueta)
            <li class="nav-item">
                <a class="nav-link {{ $tipo === $clave ? 'active' : '' }}"
                   href="{{ route('reportes.index', ['tipo' => $clave, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]) }}">{{ $etiqueta }}</a>
            </li>
        @endforeach
    </ul>

    <form method="GET" action="{{ route('reportes.index') }}" class="row g-2 align-items-end mb-4 needs-validation" novalidate>
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <div class="col-6 col-md-2">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $desde->toDateString() }}" class="form-control" required data-fecha-desde>
            <div class="invalid-feedback">Elija la fecha inicial.</div>
        </div>
        <div class="col-6 col-md-2">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $hasta->toDateString() }}" class="form-control" required data-fecha-hasta>
            <div class="invalid-feedback">Revise las fechas: la final es obligatoria y no puede ser anterior a la inicial.</div>
        </div>
        @if ($tipo === 'citas')
            <div class="col-md-3">
                <label for="medico" class="form-label">Médico</label>
                <select id="medico" name="medico" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($medicos as $opcion)
                        <option value="{{ $opcion->id }}" @selected($medico === $opcion->id)>{{ $opcion->persona->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="especialidad" class="form-label">Especialidad</label>
                <select id="especialidad" name="especialidad" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($especialidades as $opcion)
                        <option value="{{ $opcion->id }}" @selected($especialidad === $opcion->id)>{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($estados as $codigo => $etiqueta)
                        <option value="{{ $codigo }}" @selected($estado === $codigo)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-auto">
            <button type="submit" class="btn btn-clinica">Generar</button>
        </div>
    </form>

    @if ($tipo === 'pacientes')
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge text-bg-primary">Registrados: {{ $totales['total'] }}</span>
            <span class="badge text-bg-success">Verificados: {{ $totales['verificados'] }}</span>
            <span class="badge text-bg-info">Activos: {{ $totales['activos'] }}</span>
        </div>

        @if ($pacientes->isEmpty())
            <div class="alert alert-info">No se registraron pacientes en ese período.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Código</th><th>Paciente</th><th>CI</th><th>Registrado</th><th>Ficha</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($pacientes as $paciente)
                            <tr>
                                <td>{{ $paciente->codigo }}</td>
                                <td>{{ $paciente->persona->nombre_completo }}</td>
                                <td>{{ $paciente->persona->ci }}</td>
                                <td>{{ $paciente->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                <td>{{ $paciente->verificado ? 'Verificada' : 'Pendiente' }}</td>
                                <td>{{ $paciente->estado ? 'Activo' : 'Inactivo' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $pacientes->links() }}</div>
        @endif
    @else
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge text-bg-dark">Total de citas: {{ $totalCitas }}</span>
            @foreach ($estados as $codigo => $etiqueta)
                <span class="badge {{ $insignia[$codigo] }}">{{ $etiqueta }}: {{ $porEstado[$codigo] }}</span>
            @endforeach
        </div>

        @if ($totalCitas === 0)
            <div class="alert alert-info">No hay citas con esos filtros en el período.</div>
        @else
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <h2 class="h6 text-uppercase text-secondary">Por médico</h2>
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach ($porMedico as $fila)
                                <tr><td>{{ $fila->nombre }}</td><td class="text-end">{{ $fila->total }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h2 class="h6 text-uppercase text-secondary">Por especialidad</h2>
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach ($porEspecialidad as $fila)
                                <tr><td>{{ $fila->nombre }}</td><td class="text-end">{{ $fila->total }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <h2 class="h6 text-uppercase text-secondary">Detalle de citas</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Fecha</th><th>Hora</th><th>Código</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($citas as $cita)
                            <tr>
                                <td>{{ $cita->fecha->format('d/m/Y') }}</td>
                                <td>{{ $cita->desde() }}–{{ $cita->hasta() }}</td>
                                <td>{{ $cita->codigo }}</td>
                                <td>{{ $cita->paciente->persona->nombre_completo }}</td>
                                <td>{{ $cita->medico->persona->nombre_completo }}</td>
                                <td>{{ $cita->especialidad->nombre }}</td>
                                <td><span class="badge {{ $insignia[$cita->estado] ?? 'text-bg-light' }}">{{ $estados[$cita->estado] ?? $cita->estado }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $citas->links() }}</div>
        @endif
    @endif
@endsection
