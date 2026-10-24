@extends('layouts.base')

@section('titulo', 'Horarios de atención · Clínica Vida Salud')

@php
    use App\Models\HorarioAtencion;
@endphp

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 fw-semibold mb-1">Horarios de atención</h1>
        <p class="text-secondary mb-0">Elija un médico para administrar los bloques de su semana.</p>
    </div>

    @if ($medicos->isEmpty())
        <div class="alert alert-info">
            No hay médicos activos. Registre uno en <a href="{{ route('medicos.index') }}">Médicos</a>.
        </div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Médico</th>
                            <th>Especialidades</th>
                            <th>Semana</th>
                            <th><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($medicos as $medico)
                            @php
                                $porDia = $medico->horarios->sortBy('hora_inicio')->groupBy('dia_semana')->sortKeys();
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $medico->persona->nombre_completo }}</span>
                                    <div class="small text-secondary">{{ $medico->codigo }}</div>
                                </td>
                                <td>
                                    @foreach ($medico->especialidades->sortBy('nombre') as $especialidad)
                                        <span class="badge text-bg-primary">{{ $especialidad->nombre }}</span>
                                    @endforeach
                                </td>
                                <td class="small">
                                    @forelse ($porDia as $dia => $bloques)
                                        <div>
                                            <strong>{{ mb_substr(HorarioAtencion::DIAS[$dia], 0, 3) }}</strong>
                                            {{ $bloques->map(fn ($b) => $b->desde().'–'.$b->hasta())->implode(', ') }}
                                        </div>
                                    @empty
                                        <span class="badge text-bg-warning">Sin horarios</span>
                                    @endforelse
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('horarios.medico', $medico) }}" class="btn btn-clinica btn-sm">Administrar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
