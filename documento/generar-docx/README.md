# Generar el Word del informe

Convierte `Informe-Final.md` en un `.docx`, con la portada de la facultad, un índice y los diagramas Mermaid como imágenes. Se adaptó de la herramienta del repositorio del proyecto anterior.

Requisitos: `pandoc` 3.7 o superior, Node.js con `npx` (para `@mermaid-js/mermaid-cli`), Google Chrome en `/usr/bin/google-chrome` y Python 3.

```bash
documento/generar-docx/generar.sh documento/Informe-Final.md documento/Informe-Final.docx
```

Qué hace:

1. `preparar-md.py` arma un Markdown intermedio: la portada de `portada.md` más el cuerpo del informe (desde `## 1. Perfil`). Descarta la portada en tabla y la tabla de contenido escritas a mano, y sube un nivel los títulos.
2. `informe.lua` renderiza los bloques Mermaid como PNG, acota imágenes y tablas al ancho de la página, convierte cada `---` en un salto de página y centra los datos de la portada.
3. `crear-reference.py` genera la plantilla de estilos (portada con títulos en negrita, bordes en las tablas).

La portada se edita en `portada.md` (el logo está en `img/logo-uagrm.png`). Al abrir el Word hay que actualizar el índice: clic derecho sobre el índice y «Actualizar campo». Los diagramas quedan en `/tmp/generar-docx/mermaid` y se reutilizan si no cambian.
