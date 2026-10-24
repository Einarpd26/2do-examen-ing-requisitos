@extends('layouts.base')

@section('titulo', 'Inicio · Clínica Vida Salud')

@section('contenido')
    <div class="mb-4">
        <h1 class="h3 fw-semibold">Hola, {{ auth()->user()->persona->primer_nombre }}</h1>
        <p class="text-secondary mb-0">Ingresó como {{ auth()->user()->rol->nombre }}.</p>
    </div>

    @php($opciones = \App\Support\Menu::para(auth()->user()))

    @if (count($opciones) === 0)
        <div class="alert alert-info">Su rol no tiene opciones disponibles todavía.</div>
    @else
        <div class="row g-3">
            @foreach ($opciones as $opcion)
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h2 class="h5">{{ $opcion['etiqueta'] }}</h2>
                            @if ($opcion['url'])
                                <a href="{{ $opcion['url'] }}" class="btn btn-clinica mt-auto">Abrir</a>
                            @else
                                <span class="badge text-bg-secondary mt-auto align-self-start">Próximamente</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
