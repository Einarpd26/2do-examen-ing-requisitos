@extends('layouts.base')

@section('titulo', 'Especialidades · Clínica Vida Salud')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 fw-semibold mb-0">Especialidades</h1>
        <a href="{{ route('especialidades.create') }}" class="btn btn-clinica">Registrar especialidad</a>
    </div>

    @if ($especialidades->isEmpty())
        <div class="alert alert-info">Todavía no hay especialidades registradas.</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th class="text-end">Duración</th>
                            <th class="text-end">Médicos</th>
                            <th>Estado</th>
                            <th><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($especialidades as $especialidad)
                            <tr>
                                <td class="fw-semibold">{{ $especialidad->nombre }}</td>
                                <td class="small text-secondary">{{ $especialidad->descripcion ?? '—' }}</td>
                                <td class="text-end text-nowrap">{{ $especialidad->duracion_min }} min</td>
                                <td class="text-end">{{ $especialidad->medicos_count }}</td>
                                <td>
                                    @if ($especialidad->estado)
                                        <span class="badge text-bg-primary">Activa</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactiva</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('especialidades.edit', $especialidad) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                                    <form method="POST" action="{{ route('especialidades.estado', $especialidad) }}" class="d-inline"
                                          onsubmit="return confirm('{{ $especialidad->estado ? 'Se desactivará la especialidad: no se ofrecerá para nuevos médicos ni nuevas citas. Lo ya registrado se conserva.' : 'Se reactivará la especialidad.' }}')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="activa" value="{{ $especialidad->estado ? 0 : 1 }}">
                                        <button type="submit"
                                                class="btn btn-sm {{ $especialidad->estado ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                            {{ $especialidad->estado ? 'Desactivar' : 'Reactivar' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
