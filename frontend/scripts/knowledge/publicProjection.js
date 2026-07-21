import { KnowledgeValidationError } from './errors.js'
import { slugifyHeading, validateMarkdownSecurity } from './markdown.js'
import {
  getKnowledgeDocumentGroup,
  knowledgeDocumentPath,
} from '../../src/features/knowledge/knowledgeRoutes.js'

const PUBLIC_STATUS = 'Vigente'
const EXPLICIT_REFERENCE_PATTERN = /^(REG-\d{3}|CON-(?:ELE|JUE|PER)-\d{3}) – ([^*[\]`,;.\n]+?)(?=(?:\*\*|\*|,|;|\.(?:\s|$)|$))/
const FORBIDDEN_PUBLIC_KEYS = new Set(['status', 'sourcePath', 'markdown', 'outputPath'])

function fail(message, sourcePath = null, code = 'PUBLIC_KNOWLEDGE_INVALID') {
  throw new KnowledgeValidationError(message, { code, sourcePath })
}

function appendText(nodes, value) {
  if (!value) return

  const previous = nodes.at(-1)

  if (previous?.type === 'text') {
    previous.value += value
  } else {
    nodes.push({ type: 'text', value })
  }
}

function parseInline(value, context, formattingAllowed = true) {
  const nodes = []
  let text = ''
  let index = 0

  const flushText = () => {
    appendText(nodes, text)
    text = ''
  }

  while (index < value.length) {
    const remaining = value.slice(index)
    const referenceMatch = EXPLICIT_REFERENCE_PATTERN.exec(remaining)

    const hasReferenceBoundary = index === 0 || !/[A-Za-z0-9-]/.test(value[index - 1])

    if (referenceMatch && hasReferenceBoundary) {
      flushText()
      const [, targetId, referenceTitle] = referenceMatch
      const target = context.publicDocumentsById.get(targetId)

      if (!target) {
        const canonicalTarget = context.allDocumentsById.get(targetId)
        const reason = canonicalTarget
          ? `no es público porque su estado es "${canonicalTarget.status}"`
          : 'no existe en el corpus compilable'

        fail(
          `la referencia pública "${targetId}" ${reason}.`,
          context.document.sourcePath,
          canonicalTarget ? 'PUBLIC_REFERENCE_UNPUBLISHABLE' : 'PUBLIC_REFERENCE_MISSING',
        )
      }

      const label = `${targetId} – ${referenceTitle.trim()}`
      const reference = { targetId, label, href: target.route }
      nodes.push({ type: 'reference', ...reference })
      context.references.push(reference)
      index += referenceMatch[0].length
      continue
    }

    if (remaining.startsWith('***') || remaining.startsWith('___')) {
      fail(
        'el anidamiento inline ambiguo no forma parte del contrato público.',
        context.document.sourcePath,
        'PUBLIC_INLINE_AMBIGUOUS',
      )
    }

    if (remaining.startsWith('**')) {
      if (!formattingAllowed) {
        fail(
          'el anidamiento de negrita no forma parte del contrato público.',
          context.document.sourcePath,
          'PUBLIC_INLINE_NESTING',
        )
      }

      const closingIndex = value.indexOf('**', index + 2)

      if (closingIndex === -1 || closingIndex === index + 2) {
        fail(
          'se ha detectado un delimitador de negrita incompleto.',
          context.document.sourcePath,
          'PUBLIC_INLINE_INCOMPLETE',
        )
      }

      flushText()
      nodes.push({
        type: 'strong',
        children: parseInline(value.slice(index + 2, closingIndex), context, false),
      })
      index = closingIndex + 2
      continue
    }

    if (remaining.startsWith('*')) {
      if (!formattingAllowed) {
        fail(
          'el anidamiento de énfasis no forma parte del contrato público.',
          context.document.sourcePath,
          'PUBLIC_INLINE_NESTING',
        )
      }

      const closingIndex = value.indexOf('*', index + 1)

      if (closingIndex === -1 || closingIndex === index + 1) {
        fail(
          'se ha detectado un delimitador de énfasis incompleto.',
          context.document.sourcePath,
          'PUBLIC_INLINE_INCOMPLETE',
        )
      }

      flushText()
      nodes.push({
        type: 'emphasis',
        children: parseInline(value.slice(index + 1, closingIndex), context, false),
      })
      index = closingIndex + 1
      continue
    }

    if (remaining.startsWith('`') || remaining.startsWith('~~')) {
      fail(
        'se ha detectado sintaxis inline no soportada.',
        context.document.sourcePath,
        'PUBLIC_INLINE_UNSUPPORTED',
      )
    }

    text += value[index]
    index += 1
  }

  flushText()
  return nodes
}

function splitTableRow(line, context) {
  if (!line.startsWith('|') || !line.endsWith('|')) {
    fail(
      'las filas de tabla deben comenzar y terminar con "|".',
      context.document.sourcePath,
      'PUBLIC_TABLE_INVALID',
    )
  }

  return line.slice(1, -1).split('|').map((cell) => cell.trim())
}

function isTableSeparator(line) {
  if (!line.startsWith('|') || !line.endsWith('|')) return false

  const cells = line.slice(1, -1).split('|').map((cell) => cell.trim())
  return cells.length > 0 && cells.every((cell) => /^:?-{3,}:?$/.test(cell))
}

function isBlockStart(line, nextLine) {
  return line === ''
    || /^#{1,6}\s+/.test(line)
    || line === '---'
    || /^[-+*]\s+/.test(line)
    || /^\d+\.\s+/.test(line)
    || (line.startsWith('|') && isTableSeparator(nextLine ?? ''))
}

function validateSupportedMarkdown(markdown, sourcePath) {
  validateMarkdownSecurity(markdown, sourcePath)

  const rejectionPatterns = [
    [/^\s*>/m, 'los blockquotes no forman parte del contrato público', 'PUBLIC_BLOCKQUOTE_UNSUPPORTED'],
    [/^\s*(?:```|~~~)/m, 'los bloques de código no forman parte del contrato público', 'PUBLIC_CODE_BLOCK_UNSUPPORTED'],
    [/!\[[^\]]*\]/, 'las imágenes no forman parte del contrato público', 'PUBLIC_IMAGE_UNSUPPORTED'],
    [/\[[^\]]*\]\([^)]*\)/, 'los enlaces Markdown no forman parte del contrato público', 'PUBLIC_LINK_UNSUPPORTED'],
    [/\b(?:javascript|vbscript|data)\s*:/i, 'se ha detectado una URL peligrosa', 'PUBLIC_URL_UNSAFE'],
    [/^[ \t]+[-+*]\s+/m, 'las listas anidadas no forman parte del contrato público', 'PUBLIC_LIST_NESTED'],
    [/^[ \t]+\d+\.\s+/m, 'las listas anidadas no forman parte del contrato público', 'PUBLIC_LIST_NESTED'],
  ]

  for (const [pattern, message, code] of rejectionPatterns) {
    if (pattern.test(markdown)) fail(`${message}.`, sourcePath, code)
  }
}

export function parsePublicMarkdown(document, allDocumentsById, publicDocumentsById) {
  validateSupportedMarkdown(document.markdown, document.sourcePath)

  const lines = document.markdown.trimEnd().split('\n')
  const blocks = []
  const references = []
  const headingCounts = new Map()
  const headings = []
  const context = { document, allDocumentsById, publicDocumentsById, references }
  let headingIndex = 0
  let index = 0

  while (index < lines.length) {
    const line = lines[index]

    if (line === '') {
      index += 1
      continue
    }

    if (/^\s+/.test(line)) {
      fail(
        `la línea ${index + 1} usa indentación no soportada.`,
        document.sourcePath,
        'PUBLIC_BLOCK_INDENTED',
      )
    }

    const headingMatch = /^(#{1,6})\s+(.+?)\s*#*\s*$/.exec(line)

    if (headingMatch) {
      const level = headingMatch[1].length
      const text = headingMatch[2].trim()
      const canonicalHeading = document.headings[headingIndex]

      if (!canonicalHeading || canonicalHeading.level !== level || canonicalHeading.text !== text) {
        fail(
          'los headings públicos no coinciden con el análisis canónico.',
          document.sourcePath,
          'PUBLIC_HEADING_MISMATCH',
        )
      }

      headingIndex += 1

      if (level !== 1) {
        const baseId = slugifyHeading(text)
        const occurrence = (headingCounts.get(baseId) ?? 0) + 1
        const id = occurrence === 1 ? baseId : `${baseId}-${occurrence}`
        headingCounts.set(baseId, occurrence)
        const heading = { level, text, id }
        headings.push(heading)
        blocks.push({
          type: 'heading',
          level,
          id,
          children: parseInline(text, context),
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

    const unorderedMatch = /^[-+*]\s+(.+)$/.exec(line)

    if (unorderedMatch) {
      const items = []

      while (index < lines.length) {
        const itemMatch = /^[-+*]\s+(.+)$/.exec(lines[index])
        if (!itemMatch) break
        items.push({ type: 'listItem', children: parseInline(itemMatch[1], context) })
        index += 1
      }

      blocks.push({ type: 'unorderedList', items })
      continue
    }

    const orderedMatch = /^(\d+)\.\s+(.+)$/.exec(line)

    if (orderedMatch) {
      const items = []
      const start = Number(orderedMatch[1])

      while (index < lines.length) {
        const itemMatch = /^(\d+)\.\s+(.+)$/.exec(lines[index])
        if (!itemMatch) break
        items.push({
          type: 'listItem',
          value: Number(itemMatch[1]),
          children: parseInline(itemMatch[2], context),
        })
        index += 1
      }

      blocks.push({ type: 'orderedList', start, items })
      continue
    }

    if (line.startsWith('|')) {
      const separator = lines[index + 1]

      if (!separator || !isTableSeparator(separator)) {
        fail(
          'la tabla no contiene un separador de encabezado válido.',
          document.sourcePath,
          'PUBLIC_TABLE_SEPARATOR_INVALID',
        )
      }

      const headerCells = splitTableRow(line, context)
      const separatorCells = splitTableRow(separator, context)

      if (headerCells.length !== separatorCells.length) {
        fail(
          'el separador de tabla no coincide con el número de columnas.',
          document.sourcePath,
          'PUBLIC_TABLE_COLUMNS_INVALID',
        )
      }

      index += 2
      const rows = []

      while (index < lines.length && lines[index].startsWith('|')) {
        const cells = splitTableRow(lines[index], context)

        if (cells.length !== headerCells.length) {
          fail(
            'una fila de tabla no coincide con el número de columnas.',
            document.sourcePath,
            'PUBLIC_TABLE_COLUMNS_INVALID',
          )
        }

        rows.push(cells.map((cell) => parseInline(cell, context)))
        index += 1
      }

      if (rows.length === 0) {
        fail(
          'la tabla pública debe contener al menos una fila.',
          document.sourcePath,
          'PUBLIC_TABLE_ROWS_REQUIRED',
        )
      }

      blocks.push({
        type: 'table',
        headers: headerCells.map((cell) => parseInline(cell, context)),
        rows,
      })
      continue
    }

    if (/^(?:={3,}|_{3,}|\*{3,}|#{7,})/.test(line)) {
      fail(
        `la línea ${index + 1} contiene un bloque no soportado.`,
        document.sourcePath,
        'PUBLIC_BLOCK_UNSUPPORTED',
      )
    }

    const paragraphLines = [line]
    index += 1

    while (
      index < lines.length
      && !isBlockStart(lines[index], lines[index + 1])
    ) {
      if (/^\s+/.test(lines[index])) {
        fail(
          `la línea ${index + 1} usa indentación no soportada.`,
          document.sourcePath,
          'PUBLIC_BLOCK_INDENTED',
        )
      }

      paragraphLines.push(lines[index])
      index += 1
    }

    blocks.push({
      type: 'paragraph',
      children: parseInline(paragraphLines.join(' '), context),
    })
  }

  if (headingIndex !== document.headings.length) {
    fail(
      'no se han proyectado todos los headings canónicos.',
      document.sourcePath,
      'PUBLIC_HEADING_MISMATCH',
    )
  }

  const uniqueReferences = []
  const seenReferences = new Set()

  for (const reference of references) {
    const key = JSON.stringify(reference)
    if (seenReferences.has(key)) continue
    seenReferences.add(key)
    uniqueReferences.push(reference)
  }

  return { headings, blocks, references: uniqueReferences }
}

function assertNoForbiddenData(value, path = 'artifact') {
  if (typeof value === 'function') {
    fail(`la proyección contiene una función en ${path}.`, null, 'PUBLIC_DATA_UNSAFE')
  }

  if (!value || typeof value !== 'object') return

  for (const [key, child] of Object.entries(value)) {
    if (FORBIDDEN_PUBLIC_KEYS.has(key)) {
      fail(`la proyección contiene el campo prohibido "${key}".`, null, 'PUBLIC_DATA_UNSAFE')
    }

    assertNoForbiddenData(child, `${path}.${key}`)
  }
}

export function validatePublicArtifact(artifact) {
  if (artifact?.schemaVersion !== 1) {
    fail('schemaVersion público no soportado.', null, 'PUBLIC_SCHEMA_INVALID')
  }

  if (!Array.isArray(artifact.documents) || artifact.documents.length === 0) {
    fail('la proyección pública debe contener documentos.', null, 'PUBLIC_DOCUMENTS_REQUIRED')
  }

  assertNoForbiddenData(artifact)

  const documentsById = new Map(artifact.documents.map((document) => [document.id, document]))

  for (const document of artifact.documents) {
    if (document.route !== knowledgeDocumentPath(document)) {
      fail(`la ruta pública de "${document.id}" no es válida.`, null, 'PUBLIC_ROUTE_INVALID')
    }

    for (const reference of document.references) {
      const target = documentsById.get(reference.targetId)

      if (!target || reference.href !== target.route) {
        fail(
          `la referencia pública de "${document.id}" a "${reference.targetId}" no resuelve.`,
          null,
          'PUBLIC_REFERENCE_INVALID',
        )
      }
    }
  }

  return artifact
}

export function createPublicKnowledgeArtifact(canonicalArtifact) {
  const publicSourceDocuments = canonicalArtifact.documents.filter(
    (document) => document.status === PUBLIC_STATUS,
  )

  if (publicSourceDocuments.length === 0) {
    fail(
      'el corpus no contiene documentos públicos con estado Vigente.',
      null,
      'PUBLIC_DOCUMENTS_REQUIRED',
    )
  }

  const allDocumentsById = new Map(
    canonicalArtifact.documents.map((document) => [document.id, document]),
  )
  const publicDocumentsById = new Map()

  for (const document of publicSourceDocuments) {
    const route = knowledgeDocumentPath(document)

    if (!route) {
      fail(
        `el documento "${document.id}" no pertenece a una ruta pública soportada.`,
        document.sourcePath,
        'PUBLIC_ROUTE_UNSUPPORTED',
      )
    }

    publicDocumentsById.set(document.id, { ...document, route })
  }

  const documents = publicSourceDocuments.map((document) => {
    const routeDocument = publicDocumentsById.get(document.id)
    const group = getKnowledgeDocumentGroup(document)
    const parsed = parsePublicMarkdown(document, allDocumentsById, publicDocumentsById)

    return {
      id: document.id,
      slug: document.slug,
      title: document.title,
      version: document.version,
      lastRevision: document.lastRevision,
      collection: document.collection,
      ...(group ? { group } : {}),
      order: document.order,
      route: routeDocument.route,
      headings: parsed.headings,
      blocks: parsed.blocks,
      references: parsed.references,
    }
  })

  const collections = canonicalArtifact.collections
    .map((collection) => {
      const documentCount = documents.filter(
        (document) => document.collection === collection.id,
      ).length

      return { id: collection.id, title: collection.title, order: collection.order, documentCount }
    })
    .filter((collection) => collection.documentCount > 0)

  return validatePublicArtifact({ schemaVersion: 1, collections, documents })
}
