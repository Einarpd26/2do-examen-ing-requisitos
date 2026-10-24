{{--
    Pestañas de filtro de un listado, con subrayado en el color de la clínica y un contador por
    pestaña (por ejemplo Activas / Inactivas / Todas). Se usa en todos los listados con baja lógica.

    pestanas: ['clave' => ['etiqueta' => 'Activas', 'url' => '...', 'cuenta' => 3], ...]
    activa:   clave de la pestaña seleccionada
--}}
@props(['pestanas', 'activa', 'titulo' => 'Filtrar el listado'])

<nav aria-label="{{ $titulo }}" class="mb-3">
    <ul class="nav nav-underline pestanas">
        @foreach ($pestanas as $clave => $pestana)
            <li class="nav-item">
                <a href="{{ $pestana['url'] }}"
                   @class(['nav-link', 'active' => $activa === $clave])
                   @if ($activa === $clave) aria-current="page" @endif>
                    {{ $pestana['etiqueta'] }}
                    <span class="pestanas-cuenta">{{ $pestana['cuenta'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
