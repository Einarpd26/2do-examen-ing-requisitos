@php
    /**
     * @var \App\Models\HorarioAtencion|null $horario
     * @var bool $compacto  true cuando el formulario va en una columna angosta: los campos se apilan.
     */
    use App\Models\HorarioAtencion;
    $compacto = $compacto ?? false;
    $diaElegido = (int) old('dia_semana', $horario?->dia_semana ?? 0);
@endphp

<div class="row g-3">
    <div class="{{ $compacto ? 'col-12' : 'col-md-3' }}">
        <label for="dia_semana" class="form-label">Día <span class="text-danger" aria-hidden="true">*</span></label>
        <select id="dia_semana" name="dia_semana" required class="form-select @error('dia_semana') is-invalid @enderror">
            <option value="">Elija el día</option>
            @foreach (HorarioAtencion::DIAS as $numero => $nombre)
                <option value="{{ $numero }}" @selected($diaElegido === $numero)>{{ $nombre }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback">@error('dia_semana') {{ $message }} @else Elija el día de la semana. @enderror</div>
    </div>

    <x-campo name="hora_inicio" label="Hora inicial" tipo="time" :valor="$horario?->desde()" requerido
             :columnas="$compacto ? 'col-6' : 'col-md-2'" step="60" />
    <x-campo name="hora_fin" label="Hora final" tipo="time" :valor="$horario?->hasta()" requerido
             :columnas="$compacto ? 'col-6' : 'col-md-2'" step="60" />
    <x-campo name="consultorio" label="Consultorio" :valor="$horario?->consultorio" maximo="50"
             :columnas="$compacto ? 'col-12' : 'col-md-5'" ayuda="Opcional. Por ejemplo, «Consultorio 3»." />
</div>
