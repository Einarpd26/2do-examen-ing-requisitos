# ADR-002: Consultorios como entidad propia y sin superposición en la base de datos

- **Estado:** aceptada
- **Fecha:** 07/11/2026
- **Decide:** Einar Padilla
- **Relacionada con:** CU-07 (gestionar horarios de atención), CU-09 (registrar cita), RN05, RN07, RN11, RN17, ADR-001

## Contexto

Hoy el consultorio es un **texto libre y opcional** dentro del bloque de atención del médico (`horarios_atencion.consultorio TEXT`). El Informe lo fija así de forma expresa: «El `consultorio` se registra en el horario de atención» y «El consultorio es opcional» (CU-07).

**Esto no estaba en los requisitos.** El enunciado del examen solo menciona la «ubicación» como criterio de búsqueda de médicos y nunca habla de consultorios. El Informe tampoco tiene una regla que impida que dos médicos usen el mismo consultorio a la misma hora: las reglas de negocio cubren solo los choques de un mismo médico (RN07) y de un mismo paciente (RN17).

Al probar el flujo de horarios (`/horarios/medico/{id}`) se detectó el hueco: dos médicos pueden tener bloques superpuestos en el «Consultorio 3», y el sistema permite agendar dos citas a la misma hora en la misma sala. En la práctica es una doble reserva de un recurso físico.

Además, como es texto libre, «Consultorio 3», «consultorio 3» y «Cons. 3» se tratan como tres lugares distintos.

## Hallazgo que acota el cambio

La cita **no guarda** el consultorio: este vive en el bloque de atención del médico. Dos citas solo pueden coincidir en un consultorio si dos bloques de médicos distintos se superponen en ese consultorio el mismo día de la semana, porque:

- un mismo médico ya no puede tener dos citas simultáneas (restricción de exclusión sobre `citas`);
- toda cita cae dentro de un bloque de su médico (RN05).

Por lo tanto la regla se garantiza en `horarios_atencion` y **no hace falta tocar la tabla `citas`**. «El mismo día» significa el mismo día de la semana, porque los bloques se repiten cada semana.

## Decisión

1. Crear la tabla `consultorios` (`id`, `sucursal_id`, `nombre`, `estado`), con nombre único por sucursal sin distinguir mayúsculas ni espacios: un índice único sobre `(sucursal_id, lower(btrim(nombre)))`, igual que `especialidades_nombre_uq`.
2. Reemplazar `horarios_atencion.consultorio` (texto) por `horarios_atencion.consultorio_id` (clave foránea opcional hacia `consultorios`). La clave foránea es compuesta, `(consultorio_id, sucursal_id)`, para que la base impida asignar a un bloque un consultorio de otra sucursal.
3. Agregar a `horarios_atencion` una restricción de exclusión (`EXCLUDE USING gist`) con el mismo patrón que ya protege a los médicos:

   ```sql
   EXCLUDE USING gist (consultorio_id WITH =, dia_semana WITH =, rango WITH &&)
   ```

   Un bloque sin consultorio no entra en conflicto con nadie, porque `NULL` nunca es igual a `NULL`.
4. Agregar una pantalla de **gestión de consultorios** para el Administrador, con el mismo comportamiento que especialidades: alta, edición, desactivar y reactivar, nunca eliminar (RN11).
5. El formulario de horarios pasa de un campo de texto a un selector de consultorios activos.
6. Aplicar la defensa en profundidad: la aplicación valida antes para dar un mensaje claro, y la base de datos rechaza cualquier caso que se le escape. Si dos solicitudes compiten, el rechazo de la base se traduce a la misma regla de negocio con su mensaje.

## Por qué en la base de datos y no solo en la aplicación

Validar «¿está libre?» y después guardar (check-then-act) falla bajo concurrencia: dos solicitudes simultáneas ven «libre» y ambas guardan. Una restricción de exclusión sobre rangos es la forma estándar de garantizarlo: ninguna ruta del código puede saltársela. Es el mismo criterio que el proyecto ya aplicó a los médicos (RN07) y a los pacientes (RN17).

## Por qué una tabla y no una restricción sobre el texto

| Alternativa | Por qué se descartó |
|---|---|
| **A. Mantener el texto y agregar la exclusión sobre el texto normalizado** | Resuelve la regla con una sola migración, pero los errores de escritura («Consultorio3», «Cons. 3») escapan de la restricción. Los consultorios no se pueden desactivar ni listar. |
| **B. Tabla `consultorios` (elegida)** | La regla queda garantizada por la base y no depende de cómo escriba el usuario. Los consultorios se administran y se pueden desactivar. |
| Validar solo en la aplicación | Falla bajo concurrencia; no es una garantía. |
| Guardar el consultorio también en `citas` | Duplica un dato que ya se deduce del bloque y exige mantenerlo sincronizado. No aporta a la regla. |
| Reservar la sala solo durante cada cita, prestando a otro médico las horas libres del dueño | Rompe la prioridad del dueño: si otro médico ocupa primero una hora libre, el dueño ya no puede recibir a un paciente en su propio turno, y en la práctica gana quien agenda primero. Además obliga a guardar el consultorio en cada cita y a mover la restricción a `citas`. La versión correcta de esta idea es la **liberación explícita del bloque** (*block release*, habitual en quirófanos): el tiempo libre se presta solo cuando el dueño lo libera, a mano o con una regla del tipo «48 horas antes, si no hay citas». Queda como mejora futura. |

## Por qué ahora

Hacerlo después, con el sistema en uso, es mucho más costoso:

- **Los textos libres hay que limpiarlos a mano.** Cada día de uso suma variantes («Cons. 3», «sala 3») que alguien tendría que reconocer como el mismo consultorio antes de poder enlazarlas.
- **Pueden existir conflictos reales.** Si hoy hay dos médicos superpuestos en la misma sala, la restricción nueva rechazaría la migración, y habría que decidir quién cede sin poder deshacerlo con tranquilidad.
- **Se toca información en uso.** Cambiar el tipo de una columna de datos reales exige copia de seguridad, ventana de mantenimiento y un plan de reversa.

El sistema **todavía no está en producción**. Está desplegado en la nube (Railway) solo con datos de prueba, y en desarrollo hay 4 bloques con 2 consultorios distintos. Ningún dato es real, así que migrar ahora es barato: si algo sale mal, los datos de prueba se pueden regenerar.

## Decisiones de negocio

Confirmadas el 07/11/2026.

| # | Tema | Propuesta |
|---|---|---|
| 1 | ¿El consultorio es obligatorio en un bloque? | **Sigue siendo opcional**, como dice el Informe. Un bloque sin consultorio no choca con nadie. |
| 2 | ¿Quién gestiona los consultorios? | Solo el **Administrador**, con un permiso nuevo `consultorios:gestionar`, igual que especialidades. |
| 3 | ¿Los consultorios pertenecen a una sucursal? | **Sí** (`sucursal_id`), con nombre único por sucursal. Hoy hay una sola y se asigna sola. |
| 4 | ¿Se puede desactivar un consultorio que tiene bloques? | **No.** Se rechaza hasta reasignar esos bloques, igual que con las especialidades que tienen citas. |
| 5 | ¿Se puede cambiar el consultorio de un bloque con citas agendadas? | **Sí**, como ahora, siempre que el consultorio nuevo esté libre a esa hora. |
| 6 | ¿Qué hace la migración con los datos existentes? | **Nada con ellos.** Como el sistema está en desarrollo y solo hay datos de prueba, la migración elimina la columna de texto y los bloques existentes quedan sin consultorio, algo válido porque es opcional (decisión 1). Las citas no se tocan. Los consultorios se vuelven a cargar con los seeders de demostración (`DemoSeeder` y `DemoCitasSeeder`). |
| 7 | ¿Qué se reserva: la sala durante todo el bloque, o solo durante cada cita? | **Todo el bloque.** Si un médico atiende en el Consultorio 3 los lunes de 08:00 a 12:00, ningún otro médico puede tener un bloque ahí en ese horario, aunque el primero no tenga citas. Es lo que modela un turno de consultorio, y es lo que permite que la regla viva en los horarios sin tocar `citas`. |

## Consecuencias

**Positivas**

- La doble reserva de un consultorio es imposible a nivel de base de datos.
- Los consultorios se administran: se listan, se renombran y se desactivan sin tocar los horarios.
- Desaparecen las variantes de escritura del mismo consultorio.

**Negativas y límites conocidos**

- Hay una pantalla nueva y, por lo tanto, un caso de uso nuevo (**CU-20 Gestionar consultorios**) y una regla de negocio nueva (**RN18**, sin superposición en un consultorio). El Informe debe actualizarse: casos de uso, matriz de permisos, modelo de datos, reglas de negocio y plan de pruebas.
- La cita no guarda el consultorio. Si después se cambia el consultorio de un bloque, las citas pasadas de ese bloque se verán con el consultorio nuevo. Si esto importara, habría que guardar el consultorio en la cita en una decisión aparte.
- El rechazo de la base ante una carrera debe traducirse a un mensaje de negocio, como ya se hace con RN07 y RN17.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| En Railway la migración corre al arrancar (`migrate --force && serve` en el `Dockerfile`): si falla, la aplicación no arranca | Con la decisión 6 la migración no puede fallar por datos: todos los bloques quedan sin consultorio y la restricción no tiene nada que rechazar. |
| Si el sistema ya tuviera datos reales, la decisión 6 perdería los consultorios cargados | Se acepta solo porque hoy no hay datos reales. Si eso cambia antes de implementar, la migración debe crear un consultorio por cada texto distinto y enlazar los bloques. |
| El `down()` no recupera los textos originales | Es aceptable con datos de prueba: el `down()` vuelve a crear la columna de texto, rellenándola con el nombre del consultorio de cada bloque. |
| Un bloque queda con un consultorio desactivado | La decisión 4 lo impide: no se puede desactivar mientras tenga bloques. |

## Implementación prevista

1. Archivo SQL `database/sql/005_consultorios.sql` y su migración, con el mismo esquema que `001` a `004` (SQL puro ejecutado por una migración).
2. Modelo `Consultorio`, servicio, solicitud de validación y controlador de gestión de consultorios.
3. Cambios en el formulario y el servicio de horarios (selector en lugar de texto, traducción del rechazo de la base).
4. Permiso `consultorios:gestionar` para el Administrador, cargado en `permisos` y `rol_permisos` por la misma migración, y su opción en `config/menu.php`.
5. Pruebas: choque entre médicos, bloques pegados que sí se aceptan, bloque sin consultorio, carrera concurrente y desactivar con bloques.
6. Seeders de demostración: crear el «Consultorio 1» y asignarlo a los bloques por `consultorio_id`.
7. Actualizar el Informe y el README.
