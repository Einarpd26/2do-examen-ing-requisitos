# informe-final-ciclo1

## Objective
Finish `documento/Informe-Final.md` for Presentación 1 (Ciclo #1, due 2026-10-24 23:59), following the exam index, with the real code reflected in it and the repository URL + QR code (and a QR of the link).

## Problem
Sections 7 (Implementación), 8 (Pruebas, results/evidence), Conclusión, Recomendación, Bibliografía, Anexos and 2 (Marco teórico) are empty or placeholders. Cover page has "URL + Código QR (completar)". `diagramas/` and `pruebas/` hold only `.gitkeep`.

## Scope / constraints
- Only edit the report (and add QR images under `documento/`). Do NOT change code. Do NOT commit (user: "no los versiones").
- Report language: Spanish (existing artifact language), neutral/professional.
- Cycle #1 only: CU-01..CU-08, CU-19. Tests 3–8 of the exam belong to Ciclo #2.
- Repo URL is not known yet (user will publish it). QR needs the real URL.
- TDD mode: not applicable (documentation task). Runner: `php artisan test` (288 passed, 2365 assertions, observed 2026-10-24).

## Tasks
- [x] T1 Marco teórico (section 2): written 2.1–2.8 + references; all 19 cited URLs returned HTTP 200 (2026-10-24). Structure approved by user.
- [x] T2 Section 7.1 Herramientas written (PHP 8.4, Laravel 13.38, PostgreSQL 16 Docker, Bootstrap 5.3.8 local, validaciones.js, PHPUnit 12.5). Verified in code: views do not use Vite/Tailwind (no @vite), so 7.1 says so.
- [x] T3 Section 7.2 written by delegated writer; verified by parent: 26/26 excerpts verbatim (own script), prose spot-checked (tables permisos/rol_permisos, triggers bitacora, transactions in 6 services, menu disabled, accesos:renovar, 14 chars).
- [x] T4 Section 7.3 written (CU-01..08, 19), same verification.
- [x] T4b DONE 2026-10-24 (user approved, parent verified against code, not just the writer's claim): 6.1 diagram + table fixed; sequence diagrams CU-01, CU-02, CU-04, CU-19 rewritten (CU-01 and CU-19 also had logic in the controller; CU-01 also had the inactive-account check before the password check, code does it after). Mermaid not rendered (no renderer here) — user should preview. Optional pending code change NOT approved: config/session.php default 120 -> 30. Original drift list: 6.1 middleware list says "rol, cierre por inactividad" (code: auth, cuenta.vigente, can:permiso); 6.1 services list says "Códigos" (code: trait TieneCodigoFormateado); 6.2 CU-02 and CU-04 sequence diagrams put logic in controller (code: RegistroPacienteRequest/Service, PacienteService::crearAcceso).
- [ ] T5 Section 8: plan de pruebas, casos 1, 2, 9, 10 with obtained results from real tests, evidence (suite output).
- [x] T6 Conclusión, Recomendación, Bibliografía, Anexos (A estructura, B rutas = 48, C pruebas por módulo = 288). Self-corrected: "diecisiete requisitos" → "diez CU (CU-09..18)"; recommendation fixed because EXCLUDE constraints already exist in database/sql/001 and 002.
- [ ] T7 Doc-vs-code consistency check (RNF03 says Apache/XAMPP; DDL in 6.3.2 vs `database/sql`; RN list) — report drift, fix the report only.
- [ ] T8 Cover page + 7.4 Repositorio: URL + QR (BLOCKED: needs public repo URL).

## Acceptance criteria
- Every section of the exam's DOCUMENTACION index is filled for Ciclo #1.
- Code excerpts are copied from real files and cite the path.
- Results in 8.2 come from observed test runs, not invented.

## Progress
Created 2026-10-24. Baseline: suite green (288 tests).

## Findings to resolve
- RNF14 (auto logout after 30 min idle): user confirmed `.env` has SESSION_LIFETIME=30 (config/session.php default is 120). RESOLVED: user confirmed committed `.env.example` also has SESSION_LIFETIME=30, so a fresh clone gets 30. Document it in 7.x as env-driven.
- Repo URL (public, HTTP 200): https://github.com/Einarpd26/2do-examen-ing-requisitos
- RNF03 and 6.1.2 reworded to the real environment (embedded server + Docker Postgres). Comment in 7.1 still mentions Apache/XAMPP (replaced when 7.1 is written).

## Next step
T2 (7.1) onward; verify RNF14 first.
