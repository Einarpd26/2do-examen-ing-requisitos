// Botón con ícono de ojo para mostrar u ocultar la contraseña mientras se escribe.
(function () {
    'use strict';

    document.querySelectorAll('[data-mostrar-contrasena]').forEach(function (boton) {
        var campo = document.getElementById(boton.getAttribute('data-mostrar-contrasena'));
        if (!campo) { return; }

        var iconoMostrar = boton.querySelector('[data-icono-mostrar]');
        var iconoOcultar = boton.querySelector('[data-icono-ocultar]');

        function mostrar(visible) {
            var accion = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';

            campo.type = visible ? 'text' : 'password';
            // El nombre accesible cambia con el estado: el ícono solo no lo dice.
            boton.setAttribute('aria-label', accion);
            boton.setAttribute('title', accion);
            if (iconoMostrar) { iconoMostrar.classList.toggle('d-none', visible); }
            if (iconoOcultar) { iconoOcultar.classList.toggle('d-none', !visible); }
        }

        boton.addEventListener('click', function () {
            mostrar(campo.type === 'password');
            campo.focus();
        });

        // Se vuelve a ocultar al enviar, para que el navegador no la guarde como texto común.
        if (campo.form) {
            campo.form.addEventListener('submit', function (evento) {
                if (!evento.defaultPrevented) { mostrar(false); }
            });
        }
    });
})();
