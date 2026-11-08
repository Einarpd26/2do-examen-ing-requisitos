"""Agranda en el Word las imágenes de los diagramas de secuencia, que van en páginas horizontales.

Uso: python3 -I ajustar-docx.py <archivo.docx>

Pandoc recorta toda imagen al ancho de texto de la hoja vertical (6,5 in). Las imágenes cuyo texto
alternativo es «Diagrama-de-secuencia» se redimensionan al área útil de la hoja horizontal.
"""
import re
import shutil
import sys
import zipfile

ANCHO_MAX, ALTO_MAX = 10.0, 6.5  # pulgadas: hoja horizontal con márgenes de 0,5 in y lugar para el título
EMU = 914400

ruta = sys.argv[1]
origen = zipfile.ZipFile(ruta)
xml = origen.read("word/document.xml").decode("utf-8")
cambiadas = 0


def ajustar(m):
    global cambiadas
    dibujo = m.group(0)
    if 'descr="Diagrama-de-secuencia"' not in dibujo:
        return dibujo
    cx, cy = (int(v) for v in re.search(r'<wp:extent cx="(\d+)" cy="(\d+)"', dibujo).groups())
    proporcion = cy / cx
    ancho = min(ANCHO_MAX, ALTO_MAX / proporcion)
    nuevo_cx, nuevo_cy = round(ancho * EMU), round(ancho * proporcion * EMU)
    dibujo = re.sub(r'(<wp:extent cx=")\d+(" cy=")\d+(")', rf"\g<1>{nuevo_cx}\g<2>{nuevo_cy}\g<3>", dibujo)
    dibujo = re.sub(r'(<a:ext cx=")\d+(" cy=")\d+(")', rf"\g<1>{nuevo_cx}\g<2>{nuevo_cy}\g<3>", dibujo)
    cambiadas += 1
    return dibujo


xml = re.sub(r"<w:drawing>.*?</w:drawing>", ajustar, xml, flags=re.S)


def tabla_de_portada(m):
    """La primera tabla es la de los códigos QR de la portada: sin bordes, centrada y sin fila de título."""
    tabla = m.group(0)
    sin_bordes = "<w:tblBorders>" + "".join(
        f'<w:{lado} w:val="nil"/>' for lado in ("top", "left", "bottom", "right", "insideH", "insideV")
    ) + "</w:tblBorders>"
    tabla = re.sub(r'<w:tblW [^>]*/>', '<w:tblW w:type="pct" w:w="3500" /><w:jc w:val="center" />' + sin_bordes, tabla, count=1)
    tabla = tabla.replace('w:firstRow="1"', 'w:firstRow="0"', 1)
    return tabla.replace('<w:trPr><w:tblHeader w:val="on" /></w:trPr>', "", 1)


xml = re.sub(r"<w:tbl>.*?</w:tbl>", tabla_de_portada, xml, count=1, flags=re.S)

temporal = ruta + ".tmp"
destino = zipfile.ZipFile(temporal, "w", zipfile.ZIP_DEFLATED)
for item in origen.infolist():
    datos = xml.encode("utf-8") if item.filename == "word/document.xml" else origen.read(item.filename)
    destino.writestr(item, datos)
destino.close()
origen.close()
shutil.move(temporal, ruta)
print(f"ajustar-docx: {cambiadas} diagramas de secuencia agrandados")
