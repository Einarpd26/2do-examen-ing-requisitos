@php
    /** @var \App\Models\Persona|null $persona */
    $telefono = '\+?[0-9 \-\(\)]{7,20}';
@endphp

<div class="row g-3">
    <x-campo name="ci" label="CI" :valor="$persona?->ci" requerido maximo="13" patron="[0-9]{5,10}(-[0-9A-Za-z]{1,2})?"
             ayuda="De 5 a 10 dígitos, con un complemento opcional (1234567-1A)." columnas="col-md-4" />
    <x-campo name="primer_nombre" label="Primer nombre" :valor="$persona?->primer_nombre" requerido maximo="100" columnas="col-md-4" />
    <x-campo name="segundo_nombre" label="Segundo nombre" :valor="$persona?->segundo_nombre" maximo="100" columnas="col-md-4" />
    <x-campo name="apellido_paterno" label="Apellido paterno" :valor="$persona?->apellido_paterno" requerido maximo="100" columnas="col-md-4" />
    <x-campo name="apellido_materno" label="Apellido materno" :valor="$persona?->apellido_materno" maximo="100" columnas="col-md-4" />
    <div class="col-md-4"></div>
    <x-campo name="telefono" label="Teléfono" tipo="tel" :valor="$persona?->telefono" :patron="$telefono" maximo="20"
             ayuda="Opcional. Entre 7 y 15 dígitos." columnas="col-md-4" />
    <x-campo name="correo" label="Correo" tipo="email" :valor="$persona?->correo" requerido maximo="150" columnas="col-md-8"
             ayuda="Es el usuario con el que la persona entra al sistema." />
</div>
