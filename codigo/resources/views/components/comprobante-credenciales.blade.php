{{--
    Comprobante de una contraseña temporal: se muestra una sola vez, con botones para copiarla e
    imprimirla. Lo usan pacientes (CU-04) y personal (CU-03).
--}}
@props([
    'titulo',
    'nombre',
    'etiquetaUsuario',
    'usuario',
    'contrasena',
    'vence',
    'volverUrl',
    'volverTexto',
    'ayudaRenovar',
])

<div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-6">
        <div class="alert alert-warning no-imprimir" role="alert">
            <strong>Esta contraseña se muestra una sola vez.</strong>
            Cópiela o imprímala ahora: al salir de esta pantalla no se podrá volver a ver.
            {{ $ayudaRenovar }}
        </div>

        <div class="card shadow-sm" id="comprobante">
            <div class="card-body p-4">
                <p class="text-secondary text-uppercase small mb-1">Clínica Vida Salud</p>
                <h1 class="h4 mb-3">{{ $titulo }}</h1>

                <p class="mb-1">Nombre: <strong>{{ $nombre }}</strong></p>
                <p class="mb-3">Ingrese en <strong>{{ url('/login') }}</strong> con estos datos:</p>

                <dl class="row mb-3">
                    <dt class="col-4">{{ $etiquetaUsuario }}</dt>
                    <dd class="col-8 font-monospace fs-5 mb-2">{{ $usuario }}</dd>
                    <dt class="col-4">Contraseña temporal</dt>
                    <dd class="col-8 font-monospace fs-5 mb-2">
                        <span id="contrasena" class="user-select-all">{{ $contrasena }}</span>
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
            <a href="{{ $volverUrl }}" class="btn btn-link ms-auto">{{ $volverTexto }}</a>
        </div>
    </div>
</div>

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
