@extends('layouts.base')

@section('titulo', 'Consultorios · Clínica Vida Salud')

@php
    $etiquetas = ['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'];
    $pestanas = collect($etiquetas)->map(fn ($etiqueta, $clave) => [
        'etiqueta' => $etiqueta,
        'url' => route('consultorios.index', $clave === 'activos' ? [] : ['estado' => $clave]),
        'cuenta' => $cuentas[$clave],
    ])->all();
    $vacio = [
        'activos' => 'No hay consultorios activos.',
        'inactivos' => 'No hay consultorios inactivos.',
        'todos' => 'Todavía no hay consultorios registrados.',
    ];
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 fw-semibold mb-1">Consultorios</h1>
            <p class="text-secondary mb-0">Se asignan a los horarios de atención. Dos médicos no pueden usar el mismo consultorio a la misma hora.</p>
        </div>
        <a href="{{ route('consultorios.create') }}" class="btn btn-clinica">Registrar consultorio</a>
    </div>

    <x-pestanas :pestanas="$pestanas" :activa="$vista" titulo="Estado de los consultorios" />

    @if ($consultorios->isEmpty())
        <div class="alert alert-info">{{ $vacio[$vista] }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th class="text-end">Bloques de atención</th>
                            <th>Estado</th>
                            <th><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($consultorios as $consultorio)
                            <tr @class(['fila-inactiva' => ! $consultorio->estado])>
                                <td class="fw-semibold">{{ $consultorio->nombre }}</td>
                                <td class="text-end">{{ $consultorio->horarios_count }}</td>
                                <td>
                                    @if ($consultorio->estado)
                                        <span class="badge text-bg-primary">Activo</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('consultorios.edit', $consultorio) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                                    <form method="POST" action="{{ route('consultorios.estado', $consultorio) }}" class="d-inline"
                                          onsubmit="return confirm('{{ $consultorio->estado ? 'Se desactivará el consultorio: no se ofrecerá en los horarios.' : 'Se reactivará el consultorio.' }}')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="activo" value="{{ $consultorio->estado ? 0 : 1 }}">
                                        <input type="hidden" name="vista" value="{{ $vista }}">
                                        @if ($consultorio->estado)
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Desactivar</button>
                                        @else
                                            <button type="submit" class="btn btn-success btn-sm">Reactivar</button>
                                        @endif
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
