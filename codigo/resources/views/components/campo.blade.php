@props([
    'name',
    'label',
    'valor' => null,
    'tipo' => 'text',
    'requerido' => false,
    'ayuda' => null,
    'patron' => null,
    'maximo' => null,
    'columnas' => 'col-md-6',
])

<div class="{{ $columnas }}">
    <label for="{{ $name }}" class="form-label">
        {{ $label }}@if ($requerido) <span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    {{-- Una contraseña escrita nunca se vuelve a imprimir en el formulario. --}}
    <input type="{{ $tipo }}" id="{{ $name }}" name="{{ $name }}"
           @if ($tipo !== 'password') value="{{ old($name, $valor) }}" @endif
           @if ($requerido) required @endif
           @if ($patron) pattern="{{ $patron }}" @endif
           @if ($maximo) maxlength="{{ $maximo }}" @endif
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @if ($ayuda)
        <div class="form-text">{{ $ayuda }}</div>
    @endif
    <div class="invalid-feedback">
        @error($name) {{ $message }} @else Revise este campo. @enderror
    </div>
</div>
