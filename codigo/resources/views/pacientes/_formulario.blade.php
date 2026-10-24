@php
    /** @var \App\Models\Paciente|null $paciente */
    $persona = $paciente?->persona;
    // Con el modo "v" de los navegadores, dentro de una clase los paréntesis y el guion se escapan.
    $telefono = '\+?[0-9 \-\(\)]{7,20}';
@endphp

<h2 class="h6 text-uppercase text-secondary mb-3">Datos personales</h2>
<div class="row g-3 mb-4">
    <x-campo name="ci" label="CI" :valor="$persona?->ci" requerido maximo="13" patron="[0-9]{5,10}(-[0-9A-Za-z]{1,2})?"
             ayuda="De 5 a 10 dígitos, con un complemento opcional (1234567-1A)." columnas="col-md-4" />
    <x-campo name="primer_nombre" label="Primer nombre" :valor="$persona?->primer_nombre" requerido maximo="100" columnas="col-md-4" />
    <x-campo name="segundo_nombre" label="Segundo nombre" :valor="$persona?->segundo_nombre" maximo="100" columnas="col-md-4" />
    <x-campo name="apellido_paterno" label="Apellido paterno" :valor="$persona?->apellido_paterno" requerido maximo="100" columnas="col-md-4" />
    <x-campo name="apellido_materno" label="Apellido materno" :valor="$persona?->apellido_materno" maximo="100" columnas="col-md-4" />
    <x-campo name="fecha_nacimiento" label="Fecha de nacimiento" tipo="date" :valor="$persona?->fecha_nacimiento?->format('Y-m-d')"
             :max="now()->format('Y-m-d')" columnas="col-md-4" />

    <div class="col-md-4">
        <label for="sexo" class="form-label">Sexo</label>
        <select id="sexo" name="sexo" class="form-select @error('sexo') is-invalid @enderror">
            <option value="">Sin indicar</option>
            @foreach (['M' => 'Masculino', 'F' => 'Femenino'] as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(old('sexo', $persona?->sexo) === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback">@error('sexo') {{ $message }} @enderror</div>
    </div>

    <x-campo name="telefono" label="Teléfono" tipo="tel" :valor="$persona?->telefono" :patron="$telefono" maximo="20" columnas="col-md-4"
             ayuda="Opcional. Entre 7 y 15 dígitos." />
    <x-campo name="correo" label="Correo" tipo="email" :valor="$persona?->correo" maximo="150" columnas="col-md-4"
             ayuda="Opcional. No se usa para entrar: el paciente entra con su CI." />
    <x-campo name="direccion" label="Dirección" :valor="$persona?->direccion" maximo="200" columnas="col-12" />
</div>

<h2 class="h6 text-uppercase text-secondary mb-3">Información médica</h2>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <label for="tipo_sangre" class="form-label">Tipo de sangre</label>
        <select id="tipo_sangre" name="tipo_sangre" class="form-select @error('tipo_sangre') is-invalid @enderror">
            <option value="">Sin indicar</option>
            @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $tipo)
                <option value="{{ $tipo }}" @selected(old('tipo_sangre', $paciente?->tipo_sangre) === $tipo)>{{ $tipo }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback">@error('tipo_sangre') {{ $message }} @enderror</div>
    </div>
    <div class="col-md-8"></div>
    <x-area name="alergias" label="Alergias" :valor="$paciente?->alergias" />
    <x-area name="enfermedades_cronicas" label="Enfermedades crónicas" :valor="$paciente?->enfermedades_cronicas" />
    <x-area name="medicamentos" label="Medicamentos" :valor="$paciente?->medicamentos" />
    <x-area name="antecedentes" label="Antecedentes relevantes" :valor="$paciente?->antecedentes" />
</div>

<h2 class="h6 text-uppercase text-secondary mb-3">Contacto de emergencia</h2>
<div class="row g-3 mb-4">
    <x-campo name="emergencia_nombre" label="Nombre" :valor="$paciente?->emergencia_nombre" maximo="150" columnas="col-md-5" />
    <x-campo name="emergencia_parentesco" label="Parentesco" :valor="$paciente?->emergencia_parentesco" maximo="50" columnas="col-md-3" />
    <x-campo name="emergencia_telefono" label="Teléfono" tipo="tel" :valor="$paciente?->emergencia_telefono" :patron="$telefono" maximo="20" columnas="col-md-4" />
</div>
