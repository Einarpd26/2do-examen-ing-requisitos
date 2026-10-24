# ADR-001: Identificador de acceso por tipo de cuenta

- **Estado:** aceptada
- **Fecha:** 23/10/2026
- **Relacionada con:** CU-01 (iniciar sesión), RF01, RF02, RN01, RNF15

## Contexto

El enunciado dice (punto 3): *"Cada usuario deberá iniciar sesión con su **nombre de usuario o correo electrónico** y contraseña."*

Al mismo tiempo, el enunciado y nuestras decisiones fijan lo siguiente:

- El **correo del paciente es opcional** (RF02). Una cuenta de paciente puede no tener correo.
- El **CI es único y obligatorio** para toda persona (RF02, RN01) y lo comprueba la base de datos (`personas.ci`, `UNIQUE`).
- Queremos **reducir al máximo la fricción del paciente**: la recepcionista puede crearle la cuenta y entregarle una contraseña temporal sin pedirle datos adicionales.
- El personal (Administrador, Recepcionista, Médico) siempre tiene correo, porque CU-03 lo exige.

Hace falta un identificador que **exista siempre** para cada cuenta, sea único y no obligue al usuario a inventar ni recordar un dato nuevo.

## Decisión

- El **paciente** inicia sesión con su **CI**.
- El **personal** inicia sesión con su **correo**.
- El formulario tiene un solo campo ("CI o correo"). Un valor con `@` se busca como correo; cualquier otro, como CI.
- Cada tipo de cuenta solo entra con su identificador: un paciente no entra con su correo y el personal no entra con su CI.

## Justificación

1. **Es el único dato garantizado.** El correo del paciente es opcional por el propio enunciado (RF02). Exigirlo para entrar obligaría a volverlo obligatorio y contradiría RF02. El CI, en cambio, es `NOT NULL UNIQUE` en `personas`.
2. **Cumple el enunciado.** El texto pide "nombre de usuario o correo". Para el paciente, el CI **es** su nombre de usuario; para el personal se usa el correo, que figura literalmente en el texto.
3. **Menos fricción.** El paciente ya tiene su CI a mano. No debe crear un nombre de usuario, ni recordar qué correo registró. La recepcionista crea la cuenta conociendo solo el CI que ya registró.
4. **Una sola identidad.** El CI ya identifica a la persona (RN01). Un nombre de usuario aparte sería un segundo identificador por persona que mantener, con riesgo de duplicados y errores de tipeo.
5. **La unicidad la garantiza la base de datos**, no solo la aplicación.

## Alternativas consideradas

| Alternativa | Por qué se descartó |
|---|---|
| Correo para todos | Contradice RF02 (correo opcional del paciente) y suma una exigencia al paciente. |
| Nombre de usuario libre | Agrega un campo que inventar y recordar, y un segundo identificador por persona. |
| Celular | El celular puede cambiar o compartirse, y no es único en el modelo. |
| CI o correo para cualquier cuenta | Un correo del paciente no está verificado (no hay servidor de correo): permitirlo como acceso abre la puerta a quien se registre con el correo de otra persona. |

## Consecuencias

- La búsqueda del login usa los índices únicos de `personas` (`ci` y `lower(correo)`), así que es directa.
- El CI queda como dato de acceso, por lo que debe tratarse con cuidado (no se muestra en mensajes de error ni en la bitácora de intentos fallidos sin cuenta).
- La recepcionista, al crear la cuenta, solo debe dar al paciente su contraseña temporal; su usuario ya lo conoce.

## Riesgos y mitigaciones

El CI **no es secreto**: figura en documentos y lo conocen terceros. Eso facilita probar identificadores ajenos.

| Riesgo | Mitigación |
|---|---|
| Descubrir qué CI tienen cuenta | Mensaje de error **genérico** y de igual duración (se verifica una contraseña de relleno si la cuenta no existe). No se revela si el CI o el correo existen. |
| Adivinar la contraseña de una cuenta | Bloqueo de 15 minutos tras 5 intentos fallidos (RNF15). Contraseñas temporales aleatorias de 14 caracteres (RNF18) y hash bcrypt. |
| Bloquear a propósito la cuenta de otra persona (se puede adivinar su CI) | El bloqueo dura 15 minutos y recepción o el Administrador pueden renovar la contraseña. Es un riesgo asumido: es el costo de proteger contra la adivinanza. |
| Registrarse con el CI de otra persona | El registro propio se rechaza si el CI ya existe, y la ficha queda **pendiente de verificación** hasta que la recepcionista compare el documento (sección 4.1). |

## Notas de implementación

- `AutenticacionService::buscarCuenta()` aplica la regla del identificador según el rol de la cuenta.
- Cubierto por `tests/Feature/Acceso/LoginTest.php`: el paciente entra con su CI, no con su correo; el personal entra con su correo, no con su CI; ambos errores usan el mismo mensaje genérico.
