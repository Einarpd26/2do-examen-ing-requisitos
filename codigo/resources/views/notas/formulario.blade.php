@extends('layouts.base')

@section('titulo', 'Nota clínica '.$cita->codigo.' · Clínica Vida Salud')

@php
    $campos = [
        'motivo_consulta' => 'Motivo de consulta',
        'diagnostico' => 'Diagnóstico',
        'tratamiento' => 'Tratamiento o indicaciones',
        'observaciones' => 'Observaciones',
    ];
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            Nota clínica <small class="text-secondary">{{ $cita->codigo }}</small>
        </h1>
        <a href="{{ route('agenda.index', ['fecha' => $cita->fecha->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">Volver a mi agenda</a>
    </div>

    <p class="text-secondary">
        {{ $cita->paciente->persona->nombre_completo }} · {{ $cita->especialidad->nombre }} ·
        {{ $cita->fecha->format('d/m/Y') }} {{ $cita->desde() }}–{{ $cita->hasta() }}
    </p>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('notas.guardar', $cita) }}" class="needs-validation" novalidate
                  data-mensaje-textos="Complete al menos un campo de la nota clínica.">
                @csrf
                @method('PUT')

                @foreach ($campos as $campo => $etiqueta)
                    <div class="mb-3">
                        <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                        <textarea id="{{ $campo }}" name="{{ $campo }}" rows="3" maxlength="2000" data-grupo-texto data-contador
                                  class="form-control @error($campo) is-invalid @enderror">{{ old($campo, $nota?->{$campo}) }}</textarea>
                        <div class="d-flex justify-content-end"><span class="form-text" data-contador-de="{{ $campo }}"></span></div>
                        @error($campo)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach

                <div class="text-danger small mb-3 d-none" role="alert" data-aviso-textos></div>

                <button type="submit" class="btn btn-clinica">{{ $nota ? 'Guardar cambios' : 'Guardar nota' }}</button>
            </form>
            @if ($nota)
                <p class="small text-secondary mt-3 mb-0">
                    Al guardar se sobrescribe la nota anterior; se registra quién y cuándo la modificó por última vez.
                </p>
            @endif
        </div>
    </div>
@endsection
