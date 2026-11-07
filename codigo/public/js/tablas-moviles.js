// Tablas en pantallas angostas: cada fila se muestra como tarjeta. El CSS (clinica.css) lee el
// atributo data-label de cada celda, que aquí se copia del encabezado de su columna.
(function () {
    'use strict';

    document.querySelectorAll('.table-responsive > table').forEach(function (tabla) {
        var encabezados = Array.prototype.map.call(
            tabla.querySelectorAll('thead th'),
            function (th) {
                // Una columna sin título visible (por ejemplo las acciones) no lleva etiqueta.
                return th.querySelector('.visually-hidden') ? '' : th.textContent.trim();
            }
        );

        if (encabezados.length === 0) { return; }

        tabla.querySelectorAll('tbody tr').forEach(function (fila) {
            Array.prototype.forEach.call(fila.children, function (celda, i) {
                // Las filas de aviso («No hay resultados») ocupan todas las columnas y no llevan etiqueta.
                if (celda.colSpan > 1 || !encabezados[i]) { return; }
                celda.setAttribute('data-label', encabezados[i]);
            });
        });

        tabla.classList.add('tabla-tarjetas');
    });
})();
