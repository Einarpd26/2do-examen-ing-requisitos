// Botón para mostrar u ocultar la contraseña mientras se escribe.
(function () {
    'use strict';

    document.querySelectorAll('[data-mostrar-contrasena]').forEach(function (boton) {
        var campo = document.getElementById(boton.getAttribute('data-mostrar-contrasena'));
        if (!campo) { return; }

        function mostrar(visible) {
            campo.type = visible ? 'text' : 'password';
            boton.textContent = visible ? 'Ocultar' : 'Mostrar';
            boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
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
