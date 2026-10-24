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

        formulario.addEventListener('submit', function (evento) {
            validarNueva();
            validarConfirmacion();

            if (!formulario.checkValidity()) {
                evento.preventDefault();
                evento.stopPropagation();
            }

            formulario.classList.add('was-validated');
        });
    });
})();
