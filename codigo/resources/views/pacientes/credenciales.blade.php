@extends('layouts.base')

@section('titulo', 'Contraseña temporal · Clínica Vida Salud')

@php
    $vence = \Carbon\CarbonImmutable::parse($credenciales['vence']);
@endphp

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-12 col-md-9 col-lg-6">
            <div class="alert alert-warning no-imprimir" role="alert">
                <strong>Esta contraseña se muestra una sola vez.</strong>
                Copiela o imprímala ahora: al salir de esta pantalla no se podrá volver a ver.
                Si se pierde, renueve la contraseña desde la ficha del paciente.
            </div>

            <div class="card shadow-sm" id="comprobante">
                <div class="card-body p-4">
                    <p class="text-secondary text-uppercase small mb-1">Clínica Vida Salud</p>
                    <h1 class="h4 mb-3">Acceso del paciente</h1>

                    <p class="mb-1">Paciente: <strong>{{ $paciente->persona->nombre_completo }}</strong></p>
                    <p class="mb-3">Ingrese en <strong>{{ url('/login') }}</strong> con estos datos:</p>

                    <dl class="row mb-3">
                        <dt class="col-4">Usuario (CI)</dt>
                        <dd class="col-8 font-monospace fs-5 mb-2">{{ $credenciales['usuario'] }}</dd>
                        <dt class="col-4">Contraseña temporal</dt>
                        <dd class="col-8 font-monospace fs-5 mb-2">
                            <span id="contrasena" class="user-select-all">{{ $credenciales['contrasena'] }}</span>
                        </dd>
                        <dt class="col-4">Vence</dt>
                        <dd class="col-8 mb-0">{{ $vence->format('d/m/Y H:i') }}</dd>
                    </dl>

                    <p class="small text-secondary mb-0">
                        Al entrar por primera vez el sistema le pedirá elegir una contraseña propia.
                        ¿Dudas? Comuníquese con recepción: {{ config('clinica.celular') }}.
                    </p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3 no-imprimir">
                <button type="button" class="btn btn-clinica" id="copiar">Copiar contraseña</button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
                <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-link ms-auto">Ya la entregué, volver a la ficha</a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Copia la contraseña al portapapeles; si el navegador no lo permite, queda seleccionable a mano.
        document.getElementById('copiar').addEventListener('click', function (evento) {
            var boton = evento.currentTarget;
            var texto = document.getElementById('contrasena').textContent.trim();

            if (!navigator.clipboard) { return; }
            navigator.clipboard.writeText(texto).then(function () {
                boton.textContent = 'Copiada';
                setTimeout(function () { boton.textContent = 'Copiar contraseña'; }, 1500);
            });
        });
    </script>
@endpush
