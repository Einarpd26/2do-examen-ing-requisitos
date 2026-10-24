@extends('layouts.base')

@section('titulo', 'Cambiar contraseña · Clínica Vida Salud')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
            @if (auth()->user()->debe_cambiar_password)
                <div class="alert alert-warning" role="alert">
                    Ingresó con una contraseña temporal. Debe elegir una contraseña propia antes de continuar.
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h5 mb-3">Cambiar contraseña</h1>

                    <form method="POST" action="{{ route('password.actualizar') }}" class="needs-validation" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="password_actual" class="form-label">Contraseña actual</label>
                            <input type="password" id="password_actual" name="password_actual" required
                                   autocomplete="current-password"
                                   class="form-control @error('password_actual') is-invalid @enderror">
                            <div class="invalid-feedback">
                                @error('password_actual') {{ $message }} @else Ingrese su contraseña actual. @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <input type="password" id="password" name="password" required minlength="8"
                                   autocomplete="new-password" aria-describedby="ayuda-password"
                                   class="form-control @error('password') is-invalid @enderror">
                            <div id="ayuda-password" class="form-text">
                                Al menos 8 caracteres, con mayúsculas, minúsculas y un número.
                            </div>
                            <div class="invalid-feedback" data-mensaje-por-defecto="La nueva contraseña no cumple los requisitos.">
                                @error('password') {{ $message }} @else La nueva contraseña no cumple los requisitos. @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required
                                   autocomplete="new-password" class="form-control">
                            <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                        </div>

                        <button type="submit" class="btn btn-clinica w-100">Guardar contraseña</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
