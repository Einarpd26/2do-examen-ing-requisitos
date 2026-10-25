"""Crea reference.docx: la plantilla por defecto de pandoc con bordes en las tablas.

Uso: python3 -I crear-reference.py <carpeta de salida>
"""
import re
import subprocess
import sys
import zipfile

out_dir = sys.argv[1]
base = f"{out_dir}/reference-base.docx"
subprocess.run(["pandoc", "-o", base, "--print-default-data-file", "reference.docx"], check=True)

bordes = "<w:tblBorders>" + "".join(
    f'<w:{lado} w:val="single" w:sz="4" w:space="0" w:color="808080"/>'
    for lado in ["top", "left", "bottom", "right", "insideH", "insideV"]
) + "</w:tblBorders>"

src = zipfile.ZipFile(base)
out = zipfile.ZipFile(f"{out_dir}/reference.docx", "w", zipfile.ZIP_DEFLATED)
for item in src.infolist():
    data = src.read(item.filename)
    if item.filename == "word/styles.xml":
        xml = data.decode()
        estilo = re.search(r'<w:style w:type="table"[^>]*w:styleId="Table".*?</w:style>', xml, re.S).group(0)
        nuevo = (estilo.replace("<w:tblPr>", "<w:tblPr>" + bordes, 1) if "<w:tblPr>" in estilo
                 else estilo.replace("</w:style>", f"<w:tblPr>{bordes}</w:tblPr></w:style>"))
        xml = xml.replace(estilo, nuevo)
        # Portada: títulos en negrita de 16 pt, subtítulos en negrita de 12 pt (como el Word de referencia),
        # párrafos centrados (Docente, Lugar y fecha, logo, QR) y datos del grupo de 11 pt.
        def estilo(id_):
            return re.search(r'<w:style w:type="paragraph" w:styleId="%s".*?</w:style>' % id_, xml, re.S).group(0)
        xml = xml.replace(estilo("Title"),
            '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/>'
            '<w:next w:val="BodyText"/><w:uiPriority w:val="10"/><w:qFormat/>'
            '<w:pPr><w:spacing w:before="0" w:after="120" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr>'
            '<w:rPr><w:b/><w:bCs/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style>')
        xml = xml.replace(estilo("Subtitle"),
            '<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/>'
            '<w:next w:val="BodyText"/><w:uiPriority w:val="11"/><w:qFormat/>'
            '<w:pPr><w:spacing w:before="0" w:after="120" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr>'
            '<w:rPr><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style>')
        portada = ('<w:style w:type="paragraph" w:customStyle="1" w:styleId="PortadaCentro">'
                   '<w:name w:val="Portada Centro"/><w:basedOn w:val="Normal"/><w:qFormat/>'
                   '<w:pPr><w:spacing w:before="0" w:after="120"/><w:jc w:val="center"/></w:pPr></w:style>'
                   '<w:style w:type="paragraph" w:customStyle="1" w:styleId="PortadaDato">'
                   '<w:name w:val="Portada Dato"/><w:basedOn w:val="Normal"/><w:qFormat/>'
                   '<w:pPr><w:spacing w:before="0" w:after="60"/></w:pPr>'
                   '<w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:style>')
        data = xml.replace("</w:styles>", portada + "</w:styles>").encode()
    out.writestr(item, data)
out.close()
