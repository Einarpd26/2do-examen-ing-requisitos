// Validación en el cliente (RNF08). El servidor valida siempre; esto solo adelanta el aviso.
(function () {
    'use strict';

    var REQUISITOS_PASSWORD = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

    document.querySelectorAll('form.needs-validation').forEach(function (formulario) {
        var nueva = formulario.querySelector('#password');
        var confirmacion = formulario.querySelector('#password_confirmation');

        // Contraseña nueva: mínimo 8 caracteres con mayúscula, minúscula y número.
        function validarNueva() {
            if (!nueva) { return; }
            nueva.setCustomValidity(
                nueva.value === '' || REQUISITOS_PASSWORD.test(nueva.value)
                    ? ''
                    : 'La nueva contraseña no cumple los requisitos.'
            );
        }

        // La confirmación debe coincidir con la nueva contraseña.
        function validarConfirmacion() {
            if (!nueva || !confirmacion) { return; }
            confirmacion.setCustomValidity(
                confirmacion.value === nueva.value ? '' : 'Las contraseñas no coinciden.'
            );
        }

        if (nueva) { nueva.addEventListener('input', function () { validarNueva(); validarConfirmacion(); }); }
        if (confirmacion) { confirmacion.addEventListener('input', validarConfirmacion); }

        // Grupos de casillas donde hay que marcar al menos una (por ejemplo, las especialidades de un médico).
        function validarGrupos() {
            formulario.querySelectorAll('fieldset[data-al-menos-uno]').forEach(function (grupo) {
                var casillas = grupo.querySelectorAll('input[type="checkbox"]');
                var mensaje = grupo.getAttribute('data-al-menos-uno');
                var aviso = grupo.querySelector('[data-mensaje-grupo]');
                var hayMarcada = Array.prototype.some.call(casillas, function (casilla) { return casilla.checked; });

                // Todas las casillas del grupo quedan inválidas a la vez, para que se vean iguales.
                casillas.forEach(function (casilla) { casilla.setCustomValidity(hayMarcada ? '' : mensaje); });
                if (aviso) {
                    aviso.textContent = hayMarcada ? '' : mensaje;
                    aviso.classList.toggle('d-none', hayMarcada);
                    aviso.classList.toggle('d-block', !hayMarcada);
                }
            });
        }

        formulario.querySelectorAll('fieldset[data-al-menos-uno] input[type="checkbox"]').forEach(function (casilla) {
            casilla.addEventListener('change', validarGrupos);
        });

        formulario.addEventListener('submit', function (evento) {
            validarNueva();
            validarConfirmacion();
            validarGrupos();

            if (!formulario.checkValidity()) {
                evento.preventDefault();
                evento.stopPropagation();
            }

            formulario.classList.add('was-validated');
        });
    });
})();
