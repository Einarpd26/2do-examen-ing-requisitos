// Comodidad en el formulario de citas (CU-09): al elegir especialidad, médico o fecha se avanza
// al siguiente paso sin pulsar el botón. El formulario funciona igual sin JavaScript.
(function () {
    'use strict';

    document.querySelectorAll('form[data-autoenvio]').forEach(function (formulario) {
        formulario.querySelectorAll('[data-autoenvio-campo]').forEach(function (campo) {
            campo.addEventListener('change', function () {
                // Si cambia un paso anterior, los siguientes se descartan: dependen de él.
                var orden = ['especialidad', 'medico', 'fecha'];
                var posicion = orden.indexOf(campo.name);
                orden.slice(posicion + 1).forEach(function (nombre) {
                    var siguiente = formulario.elements[nombre];
                    if (siguiente) { siguiente.value = ''; }
                });

                if (campo.value !== '') { formulario.submit(); }
            });
        });
    });
})();
