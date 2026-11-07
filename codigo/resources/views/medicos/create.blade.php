@extends('layouts.base')

@section('titulo', 'Registrar médico · Clínica Vida Salud')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">Registrar médico</h1>
        <a href="{{ route('medicos.index') }}" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
    </div>

    {{-- Sin especialidades activas no se puede registrar un médico: se avisa antes de pedir los datos. --}}
    @if ($opciones->isEmpty())
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span>
                No hay especialidades activas. Registre una especialidad antes de registrar médicos.
            </span>
            <a href="{{ route('especialidades.create') }}" class="btn btn-clinica btn-sm">Registrar especialidad</a>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('medicos.store') }}" class="needs-validation" novalidate>
                    @csrf
                    @include('medicos._formulario', ['medico' => null])

                    <p class="text-secondary small">
                        Si el CI ya pertenece a otra persona (por ejemplo, un paciente), se reutiliza esa persona y sus datos
                        personales no se modifican.
                    </p>
                    <button type="submit" class="btn btn-clinica">Registrar médico</button>
                </form>
            </div>
        </div>
    @endif
@endsection
