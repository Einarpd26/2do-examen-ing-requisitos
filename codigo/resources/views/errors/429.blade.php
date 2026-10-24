@extends('layouts.base')

@section('titulo', 'Demasiadas solicitudes · Clínica Vida Salud')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <h1 class="h3 fw-semibold">Demasiadas solicitudes</h1>
            <p class="text-secondary">Espere un minuto e intente de nuevo.</p>
            <a href="{{ url()->previous() }}" class="btn btn-clinica">Volver</a>
        </div>
    </div>
@endsection
