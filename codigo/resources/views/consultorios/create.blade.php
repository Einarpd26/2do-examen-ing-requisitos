@extends('layouts.base')

@section('titulo', 'Registrar consultorio · Clínica Vida Salud')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">Registrar consultorio</h1>
        <a href="{{ route('consultorios.index') }}" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('consultorios.store') }}" class="needs-validation" novalidate>
                @csrf
                @include('consultorios._formulario', ['consultorio' => null])

                <button type="submit" class="btn btn-clinica">Registrar consultorio</button>
            </form>
        </div>
    </div>
@endsection
