@php
    /** @var \App\Models\Especialidad|null $especialidad */
@endphp

<div class="row g-3 mb-4">
    <x-campo name="nombre" label="Nombre" :valor="$especialidad?->nombre" requerido maximo="100" columnas="col-md-8"
             ayuda="No puede repetirse: «Pediatría» y « pediatría » son el mismo nombre." />
    <x-campo name="duracion_min" label="Duración de la cita (minutos)" tipo="number" :valor="$especialidad?->duracion_min ?? 30"
             requerido min="5" max="240" step="1" columnas="col-md-4"
             ayuda="Entre 5 y 240. Define cuánto dura cada cita de esta especialidad." />
    <x-area name="descripcion" label="Descripción (opcional)" :valor="$especialidad?->descripcion" maximo="300" columnas="col-12" />
</div>
