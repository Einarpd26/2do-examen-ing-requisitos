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
    {{-- Una contraseña lleva el botón para mostrarla; el mensaje de error va dentro del grupo para que Bootstrap lo muestre. --}}
    @if ($tipo === 'password')
        <div class="input-group has-validation">
    @endif
    {{-- Una contraseña escrita nunca se vuelve a imprimir en el formulario. --}}
    <input type="{{ $tipo }}" id="{{ $name }}" name="{{ $name }}"
           @if ($tipo !== 'password') value="{{ old($name, $valor) }}" @endif
           @if ($requerido) required @endif
           @if ($patron) pattern="{{ $patron }}" @endif
           @if ($maximo) maxlength="{{ $maximo }}" @endif
           @if ($ayuda) aria-describedby="ayuda-{{ $name }}" @endif
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @if ($tipo === 'password')
            <x-boton-ojo :para="$name" />
    @endif
    <div class="invalid-feedback">
        @error($name) {{ $message }} @else Revise este campo. @enderror
    </div>
    @if ($tipo === 'password')
        </div>
    @endif
    @if ($ayuda)
        <div id="ayuda-{{ $name }}" class="form-text">{{ $ayuda }}</div>
    @endif
</div>

@if ($tipo === 'password')
    @once
        @push('scripts')
            <script src="{{ asset('js/mostrar-contrasena.js') }}"></script>
        @endpush
    @endonce
@endif
