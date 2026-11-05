@extends('layouts.base')

@section('titulo', 'Iniciar sesión · Clínica Vida Salud')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
            <div class="text-center mb-4">
                <h1 class="h3 fw-semibold text-clinica">Clínica Vida Salud</h1>
                <p class="text-secondary mb-0">Sistema de gestión de citas médicas</p>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Iniciar sesión</h2>

                    <form method="POST" action="{{ route('login.autenticar') }}" class="needs-validation" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="identificador" class="form-label">CI (pacientes) o correo (personal)</label>
                            <input type="text" id="identificador" name="identificador"
                                   value="{{ old('identificador') }}" maxlength="150" required autofocus
                                   autocomplete="username"
                                   class="form-control @error('identificador') is-invalid @enderror">
                            <div class="invalid-feedback">
                                @error('identificador') {{ $message }} @else Ingrese su CI o su correo. @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="contrasena" class="form-label">Contraseña</label>
                            <div class="input-group has-validation">
                                <input type="password" id="contrasena" name="contrasena" required
                                       autocomplete="current-password"
                                       class="form-control @error('contrasena') is-invalid @enderror">
                                {{-- Íconos de Bootstrap Icons (MIT): ojo abierto = mostrar, ojo tachado = ocultar. --}}
                                <button type="button" class="btn btn-outline-secondary btn-mostrar-contrasena"
                                        data-mostrar-contrasena="contrasena" aria-controls="contrasena"
                                        aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                    <svg data-icono-mostrar xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                         fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                    </svg>
                                    <svg data-icono-ocultar class="d-none" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                         fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                        <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/>
                                        <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/>
                                        <path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>
                                    </svg>
                                </button>
                                <div class="invalid-feedback">
                                    @error('contrasena') {{ $message }} @else Ingrese su contraseña. @enderror
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-clinica w-100">Entrar</button>
                    </form>
                </div>
            </div>

            <p class="text-center text-secondary small mt-3 mb-1">
                ¿Olvidó su contraseña? Pida una nueva en recepción:
                <strong>{{ config('clinica.celular') }}</strong>
            </p>
            @if (Route::has('registro.formulario'))
                <p class="text-center small">
                    ¿Es paciente y no tiene cuenta?
                    <a href="{{ route('registro.formulario') }}">Regístrese aquí</a>
                </p>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/mostrar-contrasena.js') }}"></script>
@endpush
