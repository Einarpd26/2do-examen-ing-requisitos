@extends('layouts.base')

@section('titulo', 'Editar consultorio · Clínica Vida Salud')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">Editar consultorio</h1>
        <a href="{{ route('consultorios.index') }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('consultorios.update', $consultorio) }}" class="needs-validation" novalidate>
                @csrf
                @method('PUT')
                @include('consultorios._formulario', ['consultorio' => $consultorio])

                <p class="small text-secondary">
                    El nombre nuevo se verá en todos los horarios que usan este consultorio.
                </p>
                <button type="submit" class="btn btn-clinica">Guardar cambios</button>
            </form>
        </div>
    </div>
@endsection
