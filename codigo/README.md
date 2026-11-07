# Clínica Vida Salud: sistema web de gestión de citas médicas

Segundo examen parcial de Ingeniería de Requisitos. Aplicación en **Laravel 13** (PHP 8.4.1 o superior), **PostgreSQL 16**, plantillas Blade y Bootstrap 5. El informe con el análisis y el diseño está en `../documento/Informe-Final.md`.

## Requisitos

- PHP 8.4.1 o superior con la extensión `pdo_pgsql` (compruébelo con `php -m | grep pdo_pgsql`). Lo exigen los paquetes de Symfony del `composer.lock`, aunque `composer.json` diga `^8.3`
- Composer
- Docker con Docker Compose (solo para PostgreSQL; la aplicación corre directamente en su máquina)

No hace falta Node ni npm: Bootstrap ya viene incluido en `public/vendor/bootstrap/`.

## Puesta en marcha (solo la primera vez)

Ejecute todo desde esta carpeta (`codigo/`).

```bash
cp .env.example .env
composer install
php artisan key:generate

docker compose up -d                  # PostgreSQL en el puerto 5433
docker exec clinica_db psql -U clinica -d postgres -c "CREATE DATABASE clinica_test;"   # base de las pruebas

php artisan migrate                   # crea el esquema y carga sucursal, roles y permisos
php artisan db:seed                   # crea el administrador inicial
php artisan serve                     # http://localhost:8000
```

La base de datos escucha en el puerto **5433** (y no en el 5432 habitual) para no chocar con otro PostgreSQL del equipo. Verifique que el `.env` tenga `DB_PORT=5433`; las pruebas ya lo usan en `phpunit.xml`. Si su contenedor `clinica_db` se creó con una versión anterior que usaba el 5432, ejecute de nuevo `docker compose up -d`: lo recrea con el puerto correcto y conserva los datos (el volumen no se borra).

`db:seed` muestra **una sola vez** la contraseña temporal del administrador, que vence a las 72 horas y se debe cambiar en el primer ingreso. El correo y el CI del administrador se cambian con `ADMIN_CORREO` y `ADMIN_CI` en `.env` antes de cargar los datos.

Cuando termine `php artisan serve`, abra <http://localhost:8000> en el navegador. Para entrar necesita una cuenta: use la del administrador que muestra `db:seed` o cargue las de demostración (siguiente sección).

## Retomar el trabajo (cada vez que prenda la máquina)

```bash
docker compose up -d     # levanta PostgreSQL (si ya corre, no hace nada)
php artisan serve        # servidor de desarrollo; se detiene con Ctrl+C
```

Si se agregaron migraciones nuevas (por ejemplo, tras un `git pull`), corra además `composer install` y `php artisan migrate`. Para apagar la base: `docker compose stop` (conserva los datos) o `docker compose down` (quita el contenedor, pero también conserva los datos; solo `down -v` los borra).

### Ver la base de datos (pgAdmin)

`docker compose up -d` también levanta **pgAdmin**, una interfaz web para explorar tablas y ejecutar SQL (el equivalente de phpMyAdmin para PostgreSQL; phpMyAdmin no sirve aquí porque solo soporta MySQL). Ábrala en <http://localhost:5050>; no pide inicio de sesión y solo es accesible desde su máquina.

La primera vez registre el servidor: clic derecho en *Servers* → *Register* → *Server*.

| Pestaña | Campo | Valor |
|---|---|---|
| General | Name | `clinica` (el que quiera) |
| Connection | Host name/address | `db` (**no** `127.0.0.1`: pgAdmin corre dentro de Docker) |
| Connection | Port | `5432` (el puerto interno; el `5433` es solo para su máquina) |
| Connection | Maintenance database | `clinica_vida_salud` |
| Connection | Username / Password | `clinica` / `clinica_dev` |

Las tablas están en *Databases → clinica_vida_salud → Schemas → public → Tables*. Para consultar, clic derecho en la base → *Query Tool*. Si prefiere un cliente de escritorio (DBeaver, DataGrip, etc.), conéctese a `127.0.0.1:5433` con las mismas credenciales.

Estas credenciales son solo de desarrollo local; no use este compose en un servidor.

### Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `could not find driver` | Falta la extensión PHP `pdo_pgsql`; instálela (por ejemplo `php8.x-pgsql` en Ubuntu). |
| `Connection refused` a la base | PostgreSQL no está levantado (`docker compose up -d`) o `DB_PORT` del `.env` no coincide con el puerto publicado (`docker ps`). |
| `No application encryption key` | Falta `php artisan key:generate`. |
| `Address already in use` en el 8000 | Otro proceso usa el puerto; use `php artisan serve --port=8001`. |
| Las pruebas no encuentran `clinica_test` | Falta el `CREATE DATABASE clinica_test` del paso anterior. |

## Datos de demostración

Para probar el sistema con cada rol sin crear las cuentas a mano:

```bash
php artisan db:seed --class=DemoSeeder
```

Crea un Administrador, una Recepcionista, dos Médicos con su cuenta, su especialidad y sus horarios en consultorios distintos (para probar que dos médicos no usen la misma sala a la misma hora), y dos Pacientes con cuenta (uno con la ficha verificada y otro con la ficha pendiente de verificación). El comando muestra con qué correo o CI entra cada una. La contraseña de demostración es la constante `CONTRASENA` de `database/seeders/DemoSeeder.php`.

Las cuentas tienen una contraseña conocida, así que el seeder **solo corre con `APP_ENV=local`** y se niega en cualquier otro entorno. No modifica datos existentes: si el CI ya existe, esa cuenta se omite, de modo que se puede ejecutar más de una vez.

## Antes de usarlo en un servidor real

El `.env.example` está pensado para desarrollo: trae `APP_DEBUG=true`, y con eso un error muestra una página técnica con rutas, código y datos internos. En un servidor real hay que cambiar, en el `.env`:

```
APP_ENV=production
APP_DEBUG=false
```

y limpiar la configuración guardada con `php artisan config:clear` (si se usa `php artisan serve`, reiniciarlo). Para comprobarlo, abrir una dirección que no existe: debe verse la página 404 en español con el diseño de la clínica y no una pantalla técnica. Con `APP_ENV=production` el `DemoSeeder` no corre, a propósito, para que las cuentas de prueba no existan en un sistema real; si se necesitan los datos de demostración para una presentación, se deja `APP_ENV=local` y solo se cambia `APP_DEBUG=false`.

## Si el Administrador olvida su contraseña

Si no hay otro Administrador que se la renueve, quien administra el servidor puede generarle una contraseña temporal desde la consola:

```bash
php artisan administrador:renovar-password admin@clinicavidasalud.test
```

Solo sirve para cuentas con rol Administrador, pide confirmación (`--force` la omite), muestra la contraseña una sola vez y deja constancia en la bitácora.

## Pruebas

```bash
php artisan test
```

Las pruebas usan la base `clinica_test` y la recrean con las migraciones.

## Reiniciar la base de desarrollo

```bash
docker exec clinica_db psql -U clinica -d clinica_vida_salud \
  -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
php artisan migrate && php artisan db:seed
```

`migrate:rollback` falla a propósito si ya hay citas o cuentas que dependen del catálogo.

## Estructura

| Ruta | Contenido |
|---|---|
| `database/sql/` | Esquema en SQL puro: `001` (Ciclo #1), `002` (citas y notas, Ciclo #2), `003` (catálogos iniciales), `004` (un paciente no puede tener citas superpuestas) y `005` (consultorios, ADR-002) |
| `database/migrations/` | Migraciones que ejecutan esos archivos SQL |
| `app/Models/` | Modelos Eloquent (las entidades del análisis) |
| `app/Services/` | Reglas transversales: autenticación, contraseñas temporales, bitácora |
| `app/Http/` | Controladores, solicitudes de validación y middleware |
| `config/menu.php` | Opciones del menú y el permiso que exige cada una |
| `resources/views/` | Vistas Blade |
| `tests/` | Pruebas unitarias y de funcionalidad |

## Estado

**Ciclo #1:** base de datos con control de acceso (RBAC) y modelos, más:

- **CU-01 iniciar y cerrar sesión:** sesión con cookie, bloqueo por intentos, contraseña temporal, menú por permisos y bitácora.
- **CU-02 registro propio del paciente:** crea la cuenta y la ficha en estado pendiente de verificación; limitado a 10 solicitudes por minuto por IP.
- **CU-04 gestionar pacientes:** alta, búsqueda, edición, desactivación, cuenta con contraseña temporal (se muestra una sola vez), renovación y verificación de la ficha.

- **CU-03 gestionar usuarios y roles:** alta del personal (Administrador, Recepcionista y Médico vinculado a un médico registrado) con contraseña temporal de 72 horas que se muestra una sola vez, edición de datos y rol, desactivación y reactivación, y renovación de contraseña con verificación de identidad. Siempre queda al menos un Administrador activo y nadie se desactiva ni se cambia el rol a sí mismo.
- **CU-08 gestionar mi perfil:** el paciente ve sus datos y, con la ficha verificada, su información médica y contacto de emergencia en solo lectura; edita únicamente su teléfono y cambia su contraseña desde un enlace del perfil.
- **CU-05 gestionar médicos:** alta con sus especialidades (reutiliza la persona si el CI ya existe), edición, desactivación y reactivación (nunca se eliminan). Búsqueda por nombre, especialidad, CI, registro o código, con pestañas Activos, Inactivos y Todos; la búsqueda incluye a los inactivos. No se puede quitar una especialidad con la que el médico ya tiene citas.
- **CU-07 gestionar horarios de atención:** bloques por día de la semana para cada médico activo, con un consultorio opcional elegido de la lista de consultorios activos. No admite bloques superpuestos del mismo médico (los pegados sí), ni dos médicos en el mismo consultorio a la misma hora (RN18) ni cambios que dejen fuera de la atención a una cita ya agendada. La sucursal se asigna sola (hay una única).
- **CU-06 gestionar especialidades:** alta, edición, desactivación y reactivación (nunca se eliminan), con nombre único sin distinguir mayúsculas ni espacios y duración de la cita entre 5 y 240 minutos. El listado muestra por defecto las activas, con pestañas Activas, Inactivas y Todas y su contador.
- **CU-19 renovar contraseña temporal:** la recepcionista renueva la contraseña del paciente que la olvidó, indicando cómo verificó su identidad (queda en la bitácora). No usa correo electrónico.

**Ciclo #2:** citas, asistencia, consultas y notas clínicas.

- **CU-09 registrar cita:** la registran la recepcionista (a cualquier paciente) y el paciente (a su nombre), eligiendo especialidad, médico y un horario disponible según los bloques de atención. Un médico no puede tener dos citas a la misma hora ni un paciente dos citas superpuestas, aunque sean con médicos distintos (restricción de exclusión en PostgreSQL, `database/sql/004`). Emite un comprobante.
- **CU-10 reprogramar cita:** cambia fecha y hora con las mismas reglas de disponibilidad; el paciente solo puede hacerlo con al menos 24 horas de anticipación.
- **CU-11 cancelar cita:** exige un motivo y conserva la cita en el historial; la cancelada libera el horario. El paciente solo puede cancelar con al menos 24 horas de anticipación.
- **CU-12 controlar asistencia:** el paciente confirma su cita, la recepción registra la confirmación o la inasistencia y el médico marca la cita como atendida, con transiciones de estado validadas.
- **CU-13 consultar agenda médica:** el médico ve su propia agenda y la recepción la de cualquier médico, filtrando por fecha y estado. El médico ve la ficha del paciente en solo lectura.
- **CU-14 consultar historial de citas:** la recepción consulta el de cualquier paciente y el paciente el suyo (`/mi-historial`).
- **CU-15 generar reportes:** reportes administrativos de citas y de pacientes con rango de fechas, filtros y totales.
- **CU-16 y CU-17 notas clínicas:** el médico registra la nota de una cita atendida; el paciente consulta las suyas en `/mis-notas` y el personal autorizado las de un paciente.
- **CU-18 recordatorio de citas:** al iniciar sesión, el paciente ve un aviso con sus próximas citas (por defecto, dentro de 2 días; se ajusta en `config/clinica.php`). Solo muestra fecha, hora y médico.

**Después del Ciclo #2:**

- **CU-20 gestionar consultorios** ([ADR-002](../documento/adr/ADR-002-consultorios-como-entidad.md)): el Administrador registra, renombra, desactiva y reactiva consultorios (nunca se eliminan), con nombre único sin distinguir mayúsculas ni espacios. Un consultorio que usan bloques de atención no se puede desactivar. La base de datos impide que dos médicos tengan bloques superpuestos en el mismo consultorio el mismo día: el consultorio se reserva durante todo el bloque, tenga citas o no.

Las fechas se muestran en hora de Bolivia (`America/La_Paz`), tanto en la aplicación como en la conexión a PostgreSQL.
