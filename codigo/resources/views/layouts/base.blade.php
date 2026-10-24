<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Clínica Vida Salud')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/clinica.css') }}">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">

@auth
    <nav class="navbar navbar-expand-lg navbar-dark bg-clinica">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('inicio') }}">Clínica Vida Salud</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                    aria-controls="menu" aria-expanded="false" aria-label="Mostrar el menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @foreach (\App\Support\Menu::para(auth()->user()) as $opcion)
                        <li class="nav-item">
                            @if ($opcion['url'])
                                <a class="nav-link" href="{{ $opcion['url'] }}">{{ $opcion['etiqueta'] }}</a>
                            @else
                                <span class="nav-link disabled" aria-disabled="true">{{ $opcion['etiqueta'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="d-flex align-items-center gap-3 text-white">
                    <span class="small">
                        {{ auth()->user()->persona->nombre_completo }}
                        <span class="badge text-bg-light ms-1">{{ auth()->user()->rol->nombre }}</span>
                    </span>
                    <a class="btn btn-outline-light btn-sm" href="{{ route('password.cambiar') }}">Cambiar contraseña</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-light btn-sm">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
@endauth

<main class="container py-4 flex-grow-1">
    @if (session('exito'))
        <div class="alert alert-success" role="alert">{{ session('exito') }}</div>
    @endif

    @yield('contenido')
</main>

<footer class="py-3 text-center text-secondary small">
    Clínica Vida Salud &middot; Sistema de gestión de citas médicas
</footer>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/validaciones.js') }}"></script>
</body>
</html>
