import { LegalValidationError } from './errors.js'

const fail = (message, sourcePath, code) => {
  throw new LegalValidationError(message, { code, sourcePath })
}

export const slugifyLegalHeading = (value) => value
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .toLowerCase()
  .replace(/[`*_~]/g, '')
  .replace(/[^a-z0-9\s-]/g, '')
  .trim()
  .replace(/[\s-]+/g, '-')

const validateSecurity = (markdown, sourcePath) => {
  const forbidden = [
    [/<\/?[A-Za-z][^>]*>/i, 'no se admite HTML o JSX.', 'SECURITY_HTML'],
    [/\b(?:javascript|vbscript|data)\s*:/i, 'se ha detectado una URL peligrosa.', 'SECURITY_URL'],
    [/^\s*(?:import|export)\s+/m, 'no se admite sintaxis MDX.', 'SECURITY_MDX'],
    [/^\s*(?:```|~~~)/m, 'no se admiten bloques de código.', 'MARKDOWN_CODE_UNSUPPORTED'],
    [/^\s*>/m, 'no se admiten citas de bloque.', 'MARKDOWN_QUOTE_UNSUPPORTED'],
    [/!\[[^\]]*\]/, 'no se admiten imágenes.', 'MARKDOWN_IMAGE_UNSUPPORTED'],
    [/^[ \t]+(?:[-+*]|\d+\.)\s+/m, 'no se admiten listas anidadas.', 'MARKDOWN_LIST_NESTED'],
  ]

  for (const [pattern, message, code] of forbidden) {
    if (pattern.test(markdown)) fail(message, sourcePath, code)
  }
}

const extractHeadings = (markdown, sourcePath) => {
  const headings = []

  for (const [index, line] of markdown.split('\n').entries()) {
    const match = /^(#{1,6})\s+(.+?)\s*#*\s*$/.exec(line)
    if (!match) continue

    const text = match[2].trim()
    const id = slugifyLegalHeading(text)
    if (!id) fail(`heading vacío en la línea ${index + 1}.`, sourcePath, 'HEADING_INVALID')

    headings.push({ level: match[1].length, text, id, line: index + 1 })
  }

  if (headings.length === 0 || headings[0].level !== 1) {
    fail('el documento debe comenzar por un H1.', sourcePath, 'HEADING_H1_REQUIRED')
  }

  if (headings.some((heading, index) => index > 0 && heading.level === 1)) {
    fail('sólo se admite un H1.', sourcePath, 'HEADING_H1_MULTIPLE')
  }

  for (let index = 1; index < headings.length; index += 1) {
    if (headings[index].level > headings[index - 1].level + 1) {
      fail(
        `jerarquía inválida en la línea ${headings[index].line}.`,
        sourcePath,
        'HEADING_HIERARCHY_INVALID',
      )
    }
  }

  const counts = new Map()
  return headings.map(({ level, text, id }) => {
    const occurrence = (counts.get(id) ?? 0) + 1
    counts.set(id, occurrence)
    return { level, text, id: occurrence === 1 ? id : `${id}-${occurrence}` }
  })
}

const appendText = (nodes, value) => {
  if (!value) return
  const previous = nodes.at(-1)
  if (previous?.type === 'text') previous.value += value
  else nodes.push({ type: 'text', value })
}

const parseInline = (value, sourcePath, formattingAllowed = true) => {
  const nodes = []
  let text = ''
  let index = 0

  const flush = () => {
    appendText(nodes, text)
    text = ''
  }

  while (index < value.length) {
    const remaining = value.slice(index)
    const linkMatch = /^\[([^\]]+)]\((https:\/\/[^\s)]+)\)/.exec(remaining)

    if (linkMatch) {
      flush()
      let href
      try {
        href = new URL(linkMatch[2]).href
      } catch {
        fail('enlace externo no válido.', sourcePath, 'LINK_INVALID')
      }
      nodes.push({ type: 'externalLink', label: linkMatch[1], href })
      index += linkMatch[0].length
      continue
    }

    if (remaining.startsWith('[') || /^https?:\/\//.test(remaining)) {
      fail('enlace Markdown no válido o URL sin etiqueta.', sourcePath, 'LINK_INVALID')
    }

    if (remaining.startsWith('***') || remaining.startsWith('___')) {
      fail('formato inline ambiguo.', sourcePath, 'INLINE_AMBIGUOUS')
    }

    if (remaining.startsWith('`')) {
      const closingIndex = value.indexOf('`', index + 1)
      if (closingIndex <= index + 1) {
        fail('código inline incompleto.', sourcePath, 'INLINE_INCOMPLETE')
      }
      flush()
      nodes.push({ type: 'inlineCode', value: value.slice(index + 1, closingIndex) })
      index = closingIndex + 1
      continue
    }

    const marker = remaining.startsWith('**') ? '**' : remaining.startsWith('*') ? '*' : null
    if (marker) {
      if (!formattingAllowed) fail('formato inline anidado.', sourcePath, 'INLINE_NESTING')
      const closingIndex = value.indexOf(marker, index + marker.length)
      if (closingIndex <= index + marker.length) {
        fail('delimitador de formato incompleto.', sourcePath, 'INLINE_INCOMPLETE')
      }
      flush()
      nodes.push({
        type: marker === '**' ? 'strong' : 'emphasis',
        children: parseInline(
          value.slice(index + marker.length, closingIndex),
          sourcePath,
          false,
        ),
      })
      index = closingIndex + marker.length
      continue
    }

    text += value[index]
    index += 1
  }

  flush()
  return nodes
}

const isTableSeparator = (line = '') => {
  if (!line.startsWith('|') || !line.endsWith('|')) return false
  const cells = line.slice(1, -1).split('|').map((cell) => cell.trim())
  return cells.length > 0 && cells.every((cell) => /^:?-{3,}:?$/.test(cell))
}

const splitTableRow = (line, sourcePath) => {
  if (!line.startsWith('|') || !line.endsWith('|')) {
    fail('fila de tabla no válida.', sourcePath, 'TABLE_INVALID')
  }
  return line.slice(1, -1).split('|').map((cell) => cell.trim())
}

const isBlockStart = (line, nextLine) => line === ''
  || /^#{1,6}\s+/.test(line)
  || line === '---'
  || /^[-+*]\s+/.test(line)
  || /^\d+\.\s+/.test(line)
  || (line.startsWith('|') && isTableSeparator(nextLine))

export const parseLegalMarkdown = (markdown, sourcePath) => {
  validateSecurity(markdown, sourcePath)
  const canonicalHeadings = extractHeadings(markdown, sourcePath)
  const lines = markdown.trimEnd().split('\n')
  const blocks = []
  let headingIndex = 0
  let index = 0

  while (index < lines.length) {
    const line = lines[index]
    if (line === '') {
      index += 1
      continue
    }

    if (/^\s+/.test(line)) {
      fail(`indentación no soportada en la línea ${index + 1}.`, sourcePath, 'BLOCK_INDENTED')
    }

    const headingMatch = /^(#{1,6})\s+(.+?)\s*#*\s*$/.exec(line)
    if (headingMatch) {
      const heading = canonicalHeadings[headingIndex]
      headingIndex += 1
      if (heading.level > 1) {
        blocks.push({
          type: 'heading',
          level: heading.level,
          id: heading.id,
          children: parseInline(heading.text, sourcePath),
        })
      }
      index += 1
      continue
    }

    if (line === '---') {
      blocks.push({ type: 'thematicBreak' })
      index += 1
      continue
    }

    if (/^[-+*]\s+/.test(line)) {
      const items = []
      while (index < lines.length) {
        const match = /^[-+*]\s+(.+)$/.exec(lines[index])
        if (!match) break
        items.push({ children: parseInline(match[1], sourcePath) })
        index += 1
      }
      blocks.push({ type: 'unorderedList', items })
      continue
    }

    if (/^\d+\.\s+/.test(line)) {
      const items = []
      const start = Number(/^([0-9]+)\./.exec(line)[1])
      while (index < lines.length) {
        const match = /^(\d+)\.\s+(.+)$/.exec(lines[index])
        if (!match) break
        items.push({ value: Number(match[1]), children: parseInline(match[2], sourcePath) })
        index += 1
      }
      blocks.push({ type: 'orderedList', start, items })
      continue
    }

    if (line.startsWith('|')) {
      const separator = lines[index + 1]
      if (!isTableSeparator(separator)) {
        fail('tabla sin separador válido.', sourcePath, 'TABLE_SEPARATOR_INVALID')
      }
      const headers = splitTableRow(line, sourcePath)
      const separators = splitTableRow(separator, sourcePath)
      if (headers.length !== separators.length) {
        fail('columnas de tabla inconsistentes.', sourcePath, 'TABLE_COLUMNS_INVALID')
      }
      index += 2
      const rows = []
      while (index < lines.length && lines[index].startsWith('|')) {
        const cells = splitTableRow(lines[index], sourcePath)
        if (cells.length !== headers.length) {
          fail('columnas de tabla inconsistentes.', sourcePath, 'TABLE_COLUMNS_INVALID')
        }
        rows.push(cells.map((cell) => parseInline(cell, sourcePath)))
        index += 1
      }
      if (rows.length === 0) fail('tabla sin filas.', sourcePath, 'TABLE_ROWS_REQUIRED')
      blocks.push({
        type: 'table',
        headers: headers.map((cell) => parseInline(cell, sourcePath)),
        rows,
      })
      continue
    }

    const paragraphLines = [line]
    index += 1
    while (index < lines.length && !isBlockStart(lines[index], lines[index + 1])) {
      if (/^\s+/.test(lines[index])) {
        fail(`indentación no soportada en la línea ${index + 1}.`, sourcePath, 'BLOCK_INDENTED')
      }
      paragraphLines.push(lines[index])
      index += 1
    }
    blocks.push({
      type: 'paragraph',
      children: parseInline(paragraphLines.join(' '), sourcePath),
    })
  }

  return {
    titleHeading: canonicalHeadings[0],
    headings: canonicalHeadings.slice(1),
    blocks,
  }
}
