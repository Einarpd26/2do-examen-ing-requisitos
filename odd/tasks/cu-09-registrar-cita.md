# cu-09-registrar-cita

## Objective
Implement CU-09 Registrar cita (RF06, RF07) for the Recepcionista and the Paciente, and bring the report in line with it. It is the base of the Ciclo #2 appointment module and of exam tests 3, 4 and 5.

## Problem
`citas` and the `Cita` model exist, but there are no routes, controllers, services or views for appointments. Exam deadline: Presentación 2 on 2026-11-07 23:59, defense on 2026-11-09 07:00.

## Why
CU-10 to CU-18 depend on it, and it covers most of the pending business rules (RN).

## Decisions taken by the user (2026-11-05)
- D1 A patient with a file pending verification CAN book. Report 4.7 must be corrected to match 4.1.
- D2 A patient cannot have overlapping appointments, even with different doctors. New rule RN17, enforced in the server and in the database.
- D3 Available times use a fixed grid: from each schedule block's start, stepping by the specialty's duration; times that collide are removed, and each time must end within its block. Gaps may remain unused.
- D4 Appointments can be booked up to 60 days ahead, for all roles, from a single configuration value.

## Scope
- In: booking form, availability, server rules, appointment receipt page, menu entries, tests, report updates for CU-09.
- Out: rescheduling, cancelling, attendance, agenda, history, reports, clinical notes, reminders (CU-10 to CU-18). Holidays and schedule blocks. Commits.

## Constraints
- Follow existing patterns: route with `can:` permission, controller, Form Request, service, Blade views, `BitacoraService`, `ReglaDeNegocioException`.
- Code, identifiers and UI copy in Spanish, as in the existing project. Neutral, professional language.
- Do not read `codigo/.env`. Do not install Laravel Boost. No new dependencies.

## TDD
Mode: off (source: no project or session configuration found). Runner: `php artisan test`. Ordinary functional checks apply; tests ship with the feature.

## Tasks
- [x] T1 RN17 in the database: new SQL file `004` and its migration with a working rollback, excluding overlapping non-cancelled appointments per patient.
- [x] T2 Availability and booking services: grid (D3), all rules re-checked on save inside a transaction, database conflicts reported as business messages, audit log entry.
- [x] T3 Routes, Form Request, controller, views and menu entries for both roles. A patient only books for themselves.
- [x] T4 Feature tests for every rule below, including exam tests 3, 4 and 5.
- [x] T5 Report: RN17 in 3.5, decisions in 4.1, CU-09 in 4.6, correction in 4.7, DDL in 6.3.2, CU-09 in 7.3, 8.2.1 tests 3-5, totals and Anexos B and C.

## Acceptance criteria
- RN03: patient, doctor and specialty exist and are active; the doctor practices the specialty.
- RN04: inactive doctors are rejected.
- RN05: the time falls inside a schedule block and on the grid.
- RN06: no past dates, and no times already gone today (America/La_Paz). Over 60 days ahead is rejected (D4).
- RN07 and RN17: no overlap for the doctor or the patient, including when two requests race (database restriction).
- RN08: `hora_fin` is computed in the server from the specialty's duration, never taken from the form.
- RN09: cancelled appointments do not occupy availability.
- RN13: availability is verified again in the server when saving.
- RN16: a doctor cannot book with themselves as the patient.
- RN12: only `citas:gestionar` and `citas:gestionar_propias` can book; a patient sending another `paciente_id` still books for themselves or is rejected.

## Checks
- `php artisan test` from `codigo/`
- `vendor/bin/pint --dirty --test` from `codigo/`
- `php artisan migrate:rollback --step=1` and `migrate` against `clinica_test` only

## Progress
Created 2026-11-05. Baseline: 299 tests passing.
- T1 done: `database/sql/004_citas_sin_superposicion_paciente.sql` (constraint `citas_paciente_sin_superposicion`) and its migration. Against `clinica_test` (DB_DATABASE and DB_PORT as process env): `migrate:rollback --step=1` then `migrate` both DONE.
- T2 done: `app/Services/CitaService.php` (grid, re-check in transaction, 23P01 translated by constraint name, `cita_creada` audit entry). Limit in `config/clinica.php` (`citas.dias_anticipacion_max` = 60).
- T3 done: `CitaController`, `CitaRequest`, views in `resources/views/citas/`, `public/js/citas.js`, routes `citas.*` (citas:gestionar) and `mis-citas.*` (citas:gestionar_propias), two menu entries.
- T4 done: `tests/Feature/Citas/CitaTest.php`, 29 tests (exam tests 3, 4 and 5 included). `php artisan test`: 328 passed, 2542 assertions. `vendor/bin/pint --dirty --test`: passed.
- T5 done: `documento/Informe-Final.md` updated (3.5, 4.1, 4.6, 4.7, 6.3.2, 7.3, 8.2.1, 8.3.1, Anexos B and C, menu table, cronograma). 54 routes. Word file not regenerated.

- Parent verification 2026-11-05: booking driven in Chrome as Recepcionista and as a Paciente with a pending file, against `clinica_test`; development database untouched. Not exercised in the browser: RN17 (demo data has one doctor), covered by tests.
- Independent review: no blockers. Fixed from it: 500 on array query parameters, out-of-range ids, messages now asserted in the overlap tests. Also fixed: green time buttons, PHP version in the report, and a test depending on the sequence value (placeholder `PAC-000042`). `php artisan test`: 330 passed, 2549 assertions, twice. Pint passed.
- Known and left as is: without a Referer header, `back()` in the app-wide handler sends the user to `/`.
- Not done: CU-09 rows in report 8.2.2 and 8.3.4 (not in this brief).

## Next step
User decides on the commit. Word file to be regenerated once the report is complete.
