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
                var casillas = grupo.querySelectorAll('input[type="checkbox"], input[type="radio"]');
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

        formulario.querySelectorAll('fieldset[data-al-menos-uno] input[type="checkbox"], fieldset[data-al-menos-uno] input[type="radio"]').forEach(function (casilla) {
            casilla.addEventListener('change', validarGrupos);
        });

        // Textos que no pueden quedar en blanco, ni siquiera con espacios (por ejemplo, un motivo).
        function validarNoVacios() {
            formulario.querySelectorAll('[data-no-vacio]').forEach(function (campo) {
                campo.setCustomValidity(campo.value.trim() === '' ? campo.getAttribute('data-no-vacio') : '');
            });
        }
        formulario.querySelectorAll('[data-no-vacio]').forEach(function (campo) {
            campo.addEventListener('input', validarNoVacios);
        });

        // Grupo de textos donde al menos uno debe tener contenido (la nota clínica).
        function validarTextos() {
            var grupo = formulario.querySelectorAll('[data-grupo-texto]');
            if (grupo.length === 0) { return; }
            var mensaje = formulario.getAttribute('data-mensaje-textos') || 'Complete al menos un campo.';
            var hayTexto = Array.prototype.some.call(grupo, function (campo) { return campo.value.trim() !== ''; });
            grupo.forEach(function (campo) { campo.setCustomValidity(hayTexto ? '' : mensaje); });
            var aviso = formulario.querySelector('[data-aviso-textos]');
            if (aviso) {
                aviso.textContent = hayTexto ? '' : mensaje;
                aviso.classList.toggle('d-none', hayTexto);
            }
        }
        formulario.querySelectorAll('[data-grupo-texto]').forEach(function (campo) {
            campo.addEventListener('input', validarTextos);
        });

        // Contador de caracteres de los textos largos.
        formulario.querySelectorAll('textarea[data-contador]').forEach(function (campo) {
            var salida = formulario.querySelector('[data-contador-de="' + campo.id + '"]');
            function contar() {
                if (salida) { salida.textContent = campo.value.length + ' / ' + campo.getAttribute('maxlength'); }
            }
            campo.addEventListener('input', contar);
            contar();
        });

        // Rango de fechas: «desde» no puede ser posterior a «hasta».
        function validarRango() {
            var desde = formulario.querySelector('[data-fecha-desde]');
            var hasta = formulario.querySelector('[data-fecha-hasta]');
            if (!desde || !hasta) { return; }
            var al_reves = desde.value !== '' && hasta.value !== '' && desde.value > hasta.value;
            hasta.setCustomValidity(al_reves ? 'La fecha final no puede ser anterior a la inicial.' : '');
        }
        formulario.querySelectorAll('[data-fecha-desde], [data-fecha-hasta]').forEach(function (campo) {
            campo.addEventListener('input', validarRango);
        });

        formulario.addEventListener('submit', function (evento) {
            validarNueva();
            validarConfirmacion();
            validarGrupos();
            validarNoVacios();
            validarTextos();
            validarRango();

            if (!formulario.checkValidity()) {
                evento.preventDefault();
                evento.stopPropagation();
            } else if (formulario.hasAttribute('data-confirmar') && !window.confirm(formulario.getAttribute('data-confirmar'))) {
                // Confirmación opcional antes de una acción que no se deshace (por ejemplo, cancelar una cita).
                evento.preventDefault();
            }

            formulario.classList.add('was-validated');
        });
    });
})();
