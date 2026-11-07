@php
    /**
     * @var \App\Models\HorarioAtencion|null $horario
     * @var \Illuminate\Support\Collection<int, \App\Models\Consultorio> $consultorios  consultorios para elegir
     * @var bool $compacto  true cuando el formulario va en una columna angosta: los campos se apilan.
     */
    use App\Models\HorarioAtencion;
    $compacto = $compacto ?? false;
    $diaElegido = (int) old('dia_semana', $horario?->dia_semana ?? 0);
    $consultorioElegido = (int) old('consultorio_id', $horario?->consultorio_id ?? 0);
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
    <div class="{{ $compacto ? 'col-12' : 'col-md-5' }}">
        <label for="consultorio_id" class="form-label">Consultorio</label>
        <select id="consultorio_id" name="consultorio_id" aria-describedby="ayuda-consultorio"
                class="form-select @error('consultorio_id') is-invalid @enderror">
            <option value="">Sin consultorio</option>
            @foreach ($consultorios as $consultorio)
                <option value="{{ $consultorio->id }}" @selected($consultorioElegido === $consultorio->id)>
                    {{ $consultorio->nombre }}@unless ($consultorio->estado) (inactivo)@endunless
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback">@error('consultorio_id') {{ $message }} @enderror</div>
        <div id="ayuda-consultorio" class="form-text">
            @if ($consultorios->isEmpty())
                Opcional. Todavía no hay consultorios:
                @can('consultorios:gestionar')
                    regístrelos en <a href="{{ route('consultorios.index') }}">Consultorios</a>.
                @else
                    pida al Administrador que los registre.
                @endcan
            @else
                Opcional. Dos médicos no pueden usar el mismo consultorio a la misma hora.
            @endif
        </div>
    </div>
</div>
