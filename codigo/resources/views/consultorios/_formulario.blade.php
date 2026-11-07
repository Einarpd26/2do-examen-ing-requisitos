@php
    /** @var \App\Models\Consultorio|null $consultorio */
@endphp

<div class="row g-3 mb-4">
    <x-campo name="nombre" label="Nombre" :valor="$consultorio?->nombre" requerido maximo="50" columnas="col-md-6"
             ayuda="Por ejemplo, «Consultorio 3». No puede repetirse: «Consultorio 3» y « consultorio 3 » son el mismo." />
</div>
