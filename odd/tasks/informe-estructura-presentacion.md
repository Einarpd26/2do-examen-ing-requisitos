# informe-estructura-presentacion

## Objective
Make `documento/Informe-Final.md` follow, heading by heading, the index the exam requires for the presentation (PDF "TABLA DE CONTENIDO EX-PARCIAL-2", pasted by the user on 2026-11-07).

## Problem
Content is complete, but numbering and titles differ from the required index: no 1.4.1/1.4.2, section 4 reordered, no 5.1 Análisis de arquitectura, class analysis is 5.1 instead of 5.3, the cycle table says Ciclo #1 "En curso", and the manual index only lists top-level sections.

## Scope / constraints
- Edit only `documento/Informe-Final.md` and this file. No code changes. Branch `docs/informe-estructura-de-la-presentacion`; no AI attribution in commits or PR.
- Report language: Spanish, neutral/professional.
- Move blocks with a script (no retyping), then fix cross-references.
- TDD: not applicable (documentation). Check: headings diff + cross-reference scan + Mermaid render with mmdc.

## Tasks
- [x] T1 1.4.1 and 1.4.2: move old 4.1 (RF) and 4.2 (RNF) under 1.4.
- [x] T2 Section 4: 4.1 Encontrar actores y CU (old 4.3), 4.2 Priorizar (old 4.5 table), 4.3 Detallar (old 4.6), 4.4 Priorizar por ciclo (criterion + cycle plan), 4.5 Estructurar el modelo (old 4.4 diagram + old 4.7 RBAC).
- [x] T3 Section 5: new 5.1 Análisis de arquitectura; 5.2 Analizar CU (old 5.2); 5.3 Análisis de clases (old 5.1 = 5.3.1, old 5.3 = 5.3.2); 5.4 unchanged.
- [x] T4 Fix "En curso" in 1.4 cycle table; rebuild the manual index with sub-sections.
- [x] T5 Fix cross-references (sección 4.1 -> 1.4.1, 4.5 -> 4.2/4.4, 4.7 -> 4.5.2, 5.1 -> 5.3) and verify: heading list equals the required index; no dangling references.

## Acceptance criteria
- The heading outline matches the pasted index item by item (extras such as 4.x RBAC or 6.4 are kept as sub-sections or extra numbers after the required ones).
- No cross-reference points to a section that moved.
- Word generator still works with the new heading levels (TOC field is `\o "1-3"`).

## Progress / evidence
Done 2026-11-07, verified by running, not assumed:
- Blocks moved by script (reestructurar.py in scratchpad) that aborts if a marker is not unique. Line diff vs HEAD: 23 original lines gone = 10 renamed headings + 13 intended text/reference fixes; nothing else lost.
- Cross-references fixed: sección 4.1 -> 1.4.1 (7), 4.7 -> 4.5.2 (2), 4.5 -> 4.4 (1), 5.1 -> 5.3.1 (1). Index links: 0 broken of 70 entries.
- Cycle table "En curso" -> "Completo".
- Word generator: preparar-md.py runs on the new file (119 headings); TOC field \o "1-3" covers 1.4.1, 4.5.1, 5.3.1.
- Mermaid: all 17 diagrams of section 5 rendered with mmdc 12.2.0 (0 syntax errors); viewed 5.1, one comunicación, 5.4.
- Not done: .docx not regenerated (user's Linux laptop). 4.4 is new text derived from the cycle table; the exam index lists "Priorizar" twice (4.2 and 4.4), assumed typo: 4.2 = priority, 4.4 = plan by cycle.

## Next step
Regenerate the .docx on the Linux laptop and review the section order in Word.
