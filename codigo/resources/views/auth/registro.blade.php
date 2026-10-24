@extends('layouts.base')

@section('titulo', 'Crear mi cuenta · Clínica Vida Salud')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7">
            <div class="text-center mb-4">
                <h1 class="h3 fw-semibold text-clinica">Crear mi cuenta</h1>
                <p class="text-secondary mb-0">Solo necesitamos unos pocos datos. El resto lo completa recepción.</p>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('registro.guardar') }}" class="needs-validation" novalidate>
                        @csrf

                        <div class="row g-3 mb-3">
                            <x-campo name="primer_nombre" label="Primer nombre" requerido maximo="100" columnas="col-md-6" />
                            <x-campo name="segundo_nombre" label="Segundo nombre" maximo="100" columnas="col-md-6" />
                            <x-campo name="apellido_paterno" label="Apellido paterno" requerido maximo="100" columnas="col-md-6" />
                            <x-campo name="apellido_materno" label="Apellido materno" maximo="100" columnas="col-md-6" />
                            <x-campo name="ci" label="CI" requerido maximo="13" patron="[0-9]{5,10}(-[0-9A-Za-z]{1,2})?"
                                     ayuda="Con este CI entrará al sistema. Ejemplo: 1234567 o 1234567-1A." columnas="col-md-6" />
                            <x-campo name="telefono" label="Celular" tipo="tel" requerido maximo="20" patron="\+?[0-9 \-\(\)]{7,20}"
                                     ayuda="Entre 7 y 15 dígitos." columnas="col-md-6" />
                            <x-campo name="correo" label="Correo (opcional)" tipo="email" maximo="150" columnas="col-12" />
                        </div>

                        <div class="row g-3 mb-3">
                            <x-campo name="password" label="Contraseña" tipo="password" requerido
                                     ayuda="Al menos 8 caracteres, con mayúsculas, minúsculas y un número." columnas="col-md-6" />
                            <x-campo name="password_confirmation" label="Confirmar contraseña" tipo="password" requerido columnas="col-md-6" />
                        </div>

                        <p class="small text-secondary">
                            Su ficha quedará <strong>pendiente de verificación</strong> hasta que presente su documento
                            de identidad en recepción. Mientras tanto puede entrar al sistema.
                        </p>

                        <button type="submit" class="btn btn-clinica w-100">Crear mi cuenta</button>
                    </form>
                </div>
            </div>

            <p class="text-center small mt-3">
                ¿Ya tiene cuenta? <a href="{{ route('login') }}">Inicie sesión</a>
            </p>
        </div>
    </div>
@endsection
