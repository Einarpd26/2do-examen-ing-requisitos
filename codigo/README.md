# Clínica Vida Salud: sistema web de gestión de citas médicas

Segundo examen parcial de Ingeniería de Requisitos. Aplicación en **Laravel 13** (PHP 8.3 o superior), **PostgreSQL 16**, plantillas Blade y Bootstrap 5. El informe con el análisis y el diseño está en `../documento/Informe-Final.md`.

## Requisitos

- PHP 8.3 o superior con la extensión `pdo_pgsql`
- Composer
- Docker (para PostgreSQL)

## Puesta en marcha

```bash
cp .env.example .env
composer install
php artisan key:generate

docker compose up -d                  # PostgreSQL en el puerto 5432
docker exec clinica_db psql -U clinica -d postgres -c "CREATE DATABASE clinica_test;"   # base de las pruebas

php artisan migrate                   # crea el esquema y carga sucursal, roles y permisos
php artisan db:seed                   # crea el administrador inicial
php artisan serve                     # http://localhost:8000
```

`db:seed` muestra **una sola vez** la contraseña temporal del administrador, que vence a las 72 horas y se debe cambiar en el primer ingreso. El correo y el CI del administrador se cambian con `ADMIN_CORREO` y `ADMIN_CI` en `.env` antes de cargar los datos.

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
| `database/sql/` | Esquema en SQL puro: `001` (Ciclo #1), `002` (citas y notas, Ciclo #2) y `003` (catálogos iniciales) |
| `database/migrations/` | Migraciones que ejecutan esos archivos SQL |
| `app/Models/` | Modelos Eloquent (las entidades del análisis) |
| `app/Services/` | Reglas transversales: autenticación, contraseñas temporales, bitácora |
| `app/Http/` | Controladores, solicitudes de validación y middleware |
| `config/menu.php` | Opciones del menú y el permiso que exige cada una |
| `resources/views/` | Vistas Blade |
| `tests/` | Pruebas unitarias y de funcionalidad |

## Estado

**Ciclo #1:** base de datos con control de acceso (RBAC), modelos y **CU-01 iniciar y cerrar sesión** (sesión con cookie, bloqueo por intentos, contraseña temporal, menú por permisos y bitácora).
