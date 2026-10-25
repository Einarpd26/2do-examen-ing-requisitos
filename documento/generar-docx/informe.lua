-- Filtro de pandoc para el Informe Final:
--  * bloques mermaid -> PNG (mermaid-cli), con tamano acotado a la pagina
--  * imagenes (capturas, QR) acotadas a la pagina; dentro de tablas, a media pagina
--  * tablas a todo el ancho, con columnas proporcionales a su contenido
--  * links relativos -> URL de GitHub
--  * portada (todo lo que va antes de <!-- fin-portada -->): titulos -> Title/Subtitle, fuera del indice;
--    parrafos de Docente, Empresa, Lugar, Repositorio y las imagenes -> centrados
--  * indice de contenido despues de la portada
--  * linea horizontal -> salto de pagina
-- Variables de entorno:
--  SCRATCH     carpeta de trabajo (diagramas renderizados y pptr.json)
--  FRAGMENTO   prefijo de un fragmento: sin portada ni indice (para insertar en el Word)

local SCRATCH = os.getenv("SCRATCH")
local FRAGMENTO = os.getenv("FRAGMENTO")
local REPO = "https://github.com/Einarpd26/2do-examen-ing-requisitos"
local BASE = { "documento" }                    -- carpeta del .md dentro del repo
local MAX_W, MAX_H, DPI = 6.3, 8.6, 192         -- pulgadas; mmdc -s 2 => 192 dpi efectivos
local n_mermaid = 0

local function png_size(path)
  local f = assert(io.open(path, "rb"))
  local h = f:read(24); f:close()
  local function u32(i)
    local a, b, c, d = h:byte(i, i + 3)
    return ((a * 256 + b) * 256 + c) * 256 + d
  end
  return u32(17), u32(21)
end

local function render_mermaid(name, text)
  local old = io.open(name .. ".mmd", "r")
  local same = old and old:read("a") == text
  if old then old:close() end
  local png = io.open(name .. ".png", "rb")
  if png then png:close() end
  if same and png then return end
  local src = io.open(name .. ".mmd", "w"); src:write(text); src:close()
  pandoc.pipe("npx", { "-y", "-p", "@mermaid-js/mermaid-cli", "mmdc",
    "-p", SCRATCH .. "/pptr.json", "-i", name .. ".mmd", "-o", name .. ".png",
    "-s", "2", "-b", "white" }, "")
end

function CodeBlock(cb)
  if not cb.classes:includes("mermaid") then return nil end
  n_mermaid = n_mermaid + 1
  local name = string.format("%s/mermaid/%sdiagrama-%02d", SCRATCH, FRAGMENTO or "", n_mermaid)
  render_mermaid(name, cb.text)
  local w, h = png_size(name .. ".png")
  local wi, hi = w / DPI, h / DPI
  local k = math.min(1, MAX_W / wi, MAX_H / hi)
  local img = pandoc.Image({}, name .. ".png", "",
    { width = string.format("%.2fin", wi * k), height = string.format("%.2fin", hi * k) })
  return pandoc.Para({ img })
end

local function fit(img, max_w, max_h)
  if img.attributes.width or not img.src:match("%.png$") then return img end
  local w, h = png_size(img.src)
  local wi, hi = w / 96, h / 96
  local k = math.min(1, max_w / wi, max_h / hi)
  img.attributes.width = string.format("%.2fin", wi * k)
  img.attributes.height = string.format("%.2fin", hi * k)
  return img
end

-- Las imagenes de la portada llevan un tamano fijo (ancho en pulgadas; el alto sale de la proporcion).
local PORTADA_ANCHO = { ["Logo"] = 1.5, ["Código QR"] = 1.25 }

function Image(img)
  local alt = pandoc.utils.stringify(img.caption)
  for prefijo, ancho in pairs(PORTADA_ANCHO) do
    if alt:sub(1, #prefijo) == prefijo then
      local w, h = png_size(img.src)
      img.attributes.width = string.format("%.2fin", ancho)
      img.attributes.height = string.format("%.2fin", ancho * h / w)
      return img
    end
  end
  return fit(img, MAX_W, MAX_H)
end

function Table(t)
  t = t:walk({ Image = function(img) return fit(img, 3.0, 6.0) end })
  local n = #t.colspecs
  local len, word_min = {}, {}
  for i = 1, n do len[i], word_min[i] = 4, 0 end
  local function measure(rows)
    for _, row in ipairs(rows) do
      for i, cell in ipairs(row.cells) do
        if i <= n then
          local text = pandoc.utils.stringify(cell.contents)
          local l = utf8.len(text) or 0
          local word = 0 -- la palabra mas larga no debe partirse
          for wd in text:gmatch("%S+") do word = math.max(word, utf8.len(wd) or 0) end
          word_min[i] = math.max(word_min[i], math.min(word, 24))
          len[i] = math.max(len[i], math.min(l, 60))
        end
      end
    end
  end
  measure(t.head.rows)
  for _, body in ipairs(t.bodies) do measure(body.body) end
  -- ~85 caracteres por linea a todo el ancho; cada columna recibe al menos su palabra mas larga
  local CHARS = 85
  local min, fixed, rest = {}, 0, 0
  for i = 1, n do
    min[i] = (word_min[i] + 7) / CHARS
    fixed = fixed + min[i]; rest = rest + len[i]
  end
  local spare = math.max(1 - fixed, 0)
  local w, total = {}, 0
  for i = 1, n do w[i] = min[i] + spare * len[i] / rest; total = total + w[i] end
  for i = 1, n do t.colspecs[i] = { t.colspecs[i][1], w[i] / total } end
  return t
end

local function resolve(target)
  local path, frag = target:match("^([^#]*)(#?.*)$")
  local parts = {}
  for _, p in ipairs(BASE) do parts[#parts + 1] = p end
  for seg in path:gmatch("[^/]+") do
    if seg == ".." then table.remove(parts)
    elseif seg ~= "." then parts[#parts + 1] = seg end
  end
  local kind = path:sub(-1) == "/" and "tree" or "blob"
  local encoded = table.concat(parts, "/"):gsub(" ", "%%20")
  return string.format("%s/%s/main/%s%s", REPO, kind, encoded, frag)
end

function Link(l)
  local t = l.target
  if t:match("^%a[%w+.-]*:") or t:sub(1, 1) == "#" then return nil end
  l.target = resolve(t)
  return l
end

function HorizontalRule()
  return pandoc.RawBlock("openxml", '<w:p><w:r><w:br w:type="page"/></w:r></w:p>')
end

local TOC = [[<w:p><w:r><w:br w:type="page"/></w:r></w:p>
<w:p><w:pPr><w:pStyle w:val="TOCHeading"/></w:pPr><w:r><w:t>Índice</w:t></w:r></w:p>
<w:p><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r><w:r><w:instrText xml:space="preserve"> TOC \o "1-3" \h \z \u </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>Actualice el índice (clic derecho → Actualizar campo).</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>
<w:p><w:r><w:br w:type="page"/></w:r></w:p>]]

function Pandoc(doc)
  if FRAGMENTO then return nil end
  local out, cover = {}, true
  for _, b in ipairs(doc.blocks) do
    if cover and b.t == "RawBlock" and b.text:find("fin%-portada") then
      cover = false
      out[#out + 1] = pandoc.RawBlock("openxml", TOC)
    elseif cover and b.t == "Header" then
      local style = b.level == 1 and "Title" or "Subtitle"
      out[#out + 1] = pandoc.Div({ pandoc.Para(b.content) }, { ["custom-style"] = style })
    elseif cover and b.t == "Para" then
      local texto = pandoc.utils.stringify(b)
      local centrado = texto:match("^Docente") or texto:match("^Empresa") or texto:match("^Lugar")
        or texto:match("^Repositorio") or b.content[1] and b.content[1].t == "Image"
      out[#out + 1] = pandoc.Div({ b }, { ["custom-style"] = centrado and "PortadaCentro" or "PortadaDato" })
    elseif cover and b.t == "RawBlock" then
      out[#out + 1] = pandoc.Para({}) -- sin saltos de pagina dentro de la portada
    else
      out[#out + 1] = b
    end
  end
  doc.blocks = out
  return doc
end

return {
  { CodeBlock = CodeBlock, Link = Link, HorizontalRule = HorizontalRule, Table = Table },
  { Image = Image },
  { Pandoc = Pandoc },
}
