@props(['name', 'label', 'valor' => null, 'filas' => 2, 'columnas' => 'col-md-6', 'maximo' => 2000])

<div class="{{ $columnas }}">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $filas }}" maxlength="{{ $maximo }}"
              {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $valor) }}</textarea>
    <div class="invalid-feedback">
        @error($name) {{ $message }} @else Revise este campo. @enderror
    </div>
</div>
