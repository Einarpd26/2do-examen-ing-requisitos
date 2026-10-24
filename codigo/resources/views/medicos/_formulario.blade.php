@php
    /** @var \App\Models\Medico|null $medico */
    $persona = $medico?->persona;
    $telefono = '\+?[0-9 \-\(\)]{7,20}';
    $asignadas = old('especialidades', $medico?->especialidades->pluck('id')->all() ?? []);
@endphp

<h2 class="h6 text-uppercase text-secondary mb-3">Datos personales</h2>
<div class="row g-3 mb-4">
    <x-campo name="ci" label="CI" :valor="$persona?->ci" requerido maximo="13" patron="[0-9]{5,10}(-[0-9A-Za-z]{1,2})?"
             ayuda="De 5 a 10 dígitos, con un complemento opcional (1234567-1A)." columnas="col-md-4" />
    <x-campo name="primer_nombre" label="Primer nombre" :valor="$persona?->primer_nombre" requerido maximo="100" columnas="col-md-4" />
    <x-campo name="segundo_nombre" label="Segundo nombre" :valor="$persona?->segundo_nombre" maximo="100" columnas="col-md-4" />
    <x-campo name="apellido_paterno" label="Apellido paterno" :valor="$persona?->apellido_paterno" requerido maximo="100" columnas="col-md-4" />
    <x-campo name="apellido_materno" label="Apellido materno" :valor="$persona?->apellido_materno" maximo="100" columnas="col-md-4" />
    <div class="col-md-4"></div>
    <x-campo name="telefono" label="Teléfono" tipo="tel" :valor="$persona?->telefono" requerido :patron="$telefono" maximo="20"
             ayuda="Entre 7 y 15 dígitos." columnas="col-md-4" />
    <x-campo name="correo" label="Correo" tipo="email" :valor="$persona?->correo" requerido maximo="150"
             ayuda="Con este correo el médico entrará al sistema." columnas="col-md-8" />
</div>

<h2 class="h6 text-uppercase text-secondary mb-3">Datos profesionales</h2>
<div class="row g-3 mb-4">
    <x-campo name="nro_registro_profesional" label="Número de registro profesional" :valor="$medico?->nro_registro_profesional"
             requerido maximo="30" columnas="col-md-6"
             ayuda="Lo emite el colegio médico. Es único: no puede repetirse." />

    <div class="col-12">
        <fieldset data-al-menos-uno="Elija al menos una especialidad.">
            <legend class="form-label fs-6">Especialidades <span class="text-danger" aria-hidden="true">*</span></legend>

            @forelse ($opciones as $especialidad)
                <div class="form-check form-check-inline">
                    <input class="form-check-input @error('especialidades') is-invalid @enderror" type="checkbox"
                           name="especialidades[]" id="especialidad_{{ $especialidad->id }}" value="{{ $especialidad->id }}"
                           @checked(in_array($especialidad->id, array_map('intval', (array) $asignadas), true))>
                    <label class="form-check-label" for="especialidad_{{ $especialidad->id }}">
                        {{ $especialidad->nombre }}
                        @unless ($especialidad->estado) <span class="badge text-bg-secondary">Inactiva</span> @endunless
                    </label>
                </div>
            @empty
                <div class="alert alert-warning mb-0">
                    No hay especialidades activas. Registre una en <a href="{{ route('especialidades.index') }}">Especialidades</a>
                    antes de registrar médicos.
                </div>
            @endforelse

            @error('especialidades') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @error('especialidades.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            <div class="invalid-feedback d-none" data-mensaje-grupo></div>
        </fieldset>
    </div>
</div>
