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
                            <input type="password" id="contrasena" name="contrasena" required
                                   autocomplete="current-password"
                                   class="form-control @error('contrasena') is-invalid @enderror">
                            <div class="invalid-feedback">
                                @error('contrasena') {{ $message }} @else Ingrese su contraseña. @enderror
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
