import path from 'node:path'
import { KnowledgeValidationError } from './errors.js'

const DOCUMENT_ID_PATTERN = /\b(?:REG-\d{3}|CON-(?:ELE|JUE|PER)-\d{3})\b/g
const MARKDOWN_LINK_PATTERN = /(!?)\[([^\]]*)\]\(([^\s)]+)(?:\s+["'][^"']*["'])?\)/g
const EXECUTABLE_FENCE_PATTERN = /^\s*(?:```|~~~)\s*(?:js|jsx|javascript|ts|tsx|mdx|html)\b/im

function fail(message, sourcePath, code) {
  throw new KnowledgeValidationError(message, { code, sourcePath })
}

export function slugifyHeading(value) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[`*_~]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/[\s-]+/g, '-')
}

export function validateMarkdownSecurity(markdown, sourcePath) {
  if (/<\s*script\b/i.test(markdown)) {
    fail('se ha detectado una etiqueta <script>.', sourcePath, 'SECURITY_SCRIPT')
  }

  if (/<\s*iframe\b/i.test(markdown)) {
    fail('se ha detectado una etiqueta <iframe>.', sourcePath, 'SECURITY_IFRAME')
  }

  if (/\bon[a-z]+\s*=/i.test(markdown)) {
    fail('se ha detectado un evento HTML.', sourcePath, 'SECURITY_EVENT_HANDLER')
  }

  if (/\b(?:javascript|vbscript)\s*:/i.test(markdown)) {
    fail('se ha detectado una URL con un esquema peligroso.', sourcePath, 'SECURITY_URL')
  }

  if (/^\s*(?:import|export)\s+/m.test(markdown)) {
    fail('se ha detectado sintaxis import/export propia de MDX.', sourcePath, 'SECURITY_MDX_MODULE')
  }

  if (EXECUTABLE_FENCE_PATTERN.test(markdown)) {
    fail('no se admiten bloques de código ejecutable JavaScript, MDX o HTML.', sourcePath, 'SECURITY_CODE')
  }

  if (/<\/?[A-Za-z][^>]*>/i.test(markdown)) {
    fail('no se admite HTML o JSX embebido.', sourcePath, 'SECURITY_HTML')
  }

  const mdxExpressionPattern = /\{[^{}\n]*(?:=>|\b(?:new|function|await|return)\b|[A-Za-z_$][\w$]*\s*\()[^{}\n]*\}/

  if (mdxExpressionPattern.test(markdown)) {
    fail('se ha detectado una expresión ejecutable propia de MDX.', sourcePath, 'SECURITY_MDX_EXPRESSION')
  }
}

function extractHeadings(markdown, sourcePath) {
  const headings = []
  const anchorCounts = new Map()

  for (const line of markdown.split('\n')) {
    const match = /^(#{1,6})\s+(.+?)\s*#*\s*$/.exec(line)

    if (!match) {
      continue
    }

    const text = match[2].trim()
    const baseAnchor = slugifyHeading(text)

    if (!baseAnchor) {
      fail('un heading no puede generar un anchor vacío.', sourcePath, 'HEADING_INVALID')
    }

    const count = (anchorCounts.get(baseAnchor) ?? 0) + 1
    anchorCounts.set(baseAnchor, count)

    headings.push({
      level: match[1].length,
      text,
      anchor: count === 1 ? baseAnchor : `${baseAnchor}-${count}`,
    })
  }

  if (headings.length === 0 || headings[0].level !== 1) {
    fail('el cuerpo debe comenzar su jerarquía con un heading H1.', sourcePath, 'HEADING_REQUIRED')
  }

  return headings
}

function extractReferenceCandidates(markdown, sourcePath) {
  const candidates = []

  if (
    /^\s{0,3}\[[^\]]+\]:\s+\S+/m.test(markdown) ||
    /\[[^\]]+\]\[[^\]]*\]/.test(markdown)
  ) {
    fail(
      'las referencias Markdown por etiqueta no forman parte del contrato v1; usa enlaces inline.',
      sourcePath,
      'REFERENCE_STYLE_UNSUPPORTED',
    )
  }

  for (const match of markdown.matchAll(MARKDOWN_LINK_PATTERN)) {
    if (match[1] === '!') {
      fail('las imágenes Markdown no forman parte del contrato v1.', sourcePath, 'IMAGE_UNSUPPORTED')
    }

    candidates.push({
      index: match.index,
      type: 'link',
      target: match[3],
    })
  }

  if (/!?\[[^\]]*\]\(/.test(markdown.replace(MARKDOWN_LINK_PATTERN, ''))) {
    fail(
      'se ha detectado un enlace Markdown inline no válido o no soportado.',
      sourcePath,
      'REFERENCE_SYNTAX_INVALID',
    )
  }

  for (const match of markdown.matchAll(DOCUMENT_ID_PATTERN)) {
    candidates.push({
      index: match.index,
      type: 'id',
      target: match[0],
    })
  }

  return candidates.sort((left, right) => left.index - right.index)
}

export function analyzeMarkdown(markdown, sourcePath) {
  validateMarkdownSecurity(markdown, sourcePath)

  return {
    headings: extractHeadings(markdown, sourcePath),
    referenceCandidates: extractReferenceCandidates(markdown, sourcePath),
  }
}

function splitReferenceTarget(target, sourcePath) {
  try {
    const decoded = decodeURIComponent(target)
    const hashIndex = decoded.indexOf('#')

    if (hashIndex === -1) {
      return { pathname: decoded, fragment: null }
    }

    return {
      pathname: decoded.slice(0, hashIndex),
      fragment: decoded.slice(hashIndex + 1),
    }
  } catch {
    fail(`referencia con codificación inválida: "${target}".`, sourcePath, 'REFERENCE_ENCODING')
  }
}

function ensureKnownAnchor(document, fragment, sourcePath) {
  if (!fragment) {
    return null
  }

  if (!document.headings.some((heading) => heading.anchor === fragment)) {
    fail(
      `el anchor "#${fragment}" no existe en "${document.sourcePath}".`,
      sourcePath,
      'REFERENCE_ANCHOR_MISSING',
    )
  }

  return fragment
}

export function resolveReferences(document, documentsById, documentsBySourcePath) {
  const references = []
  const seen = new Set()

  for (const candidate of document.referenceCandidates) {
    let reference

    if (candidate.type === 'id') {
      if (!documentsById.has(candidate.target)) {
        fail(
          `la referencia por ID "${candidate.target}" no existe.`,
          document.sourcePath,
          'REFERENCE_ID_MISSING',
        )
      }

      reference = { type: 'document', targetId: candidate.target, fragment: null }
    } else if (/^[A-Za-z][A-Za-z0-9+.-]*:/.test(candidate.target)) {
      const url = new URL(candidate.target)

      if (!['http:', 'https:'].includes(url.protocol)) {
        fail(
          `el esquema de la URL "${candidate.target}" no está permitido.`,
          document.sourcePath,
          'REFERENCE_URL_SCHEME',
        )
      }

      reference = { type: 'external', href: url.href }
    } else {
      if (candidate.target.startsWith('//') || candidate.target.startsWith('/')) {
        fail(
          `la referencia "${candidate.target}" debe ser relativa a knowledge/.`,
          document.sourcePath,
          'REFERENCE_ABSOLUTE',
        )
      }

      const { pathname, fragment } = splitReferenceTarget(candidate.target, document.sourcePath)
      const targetSourcePath = pathname
        ? path.posix.normalize(path.posix.join(path.posix.dirname(document.sourcePath), pathname))
        : document.sourcePath

      if (targetSourcePath === '..' || targetSourcePath.startsWith('../')) {
        fail(
          `la referencia "${candidate.target}" sale de knowledge/.`,
          document.sourcePath,
          'REFERENCE_TRAVERSAL',
        )
      }

      const targetDocument = documentsBySourcePath.get(targetSourcePath)

      if (!targetDocument) {
        fail(
          `el destino "${candidate.target}" no existe o no es compilable.`,
          document.sourcePath,
          'REFERENCE_FILE_MISSING',
        )
      }

      reference = {
        type: 'document',
        targetId: targetDocument.id,
        fragment: ensureKnownAnchor(targetDocument, fragment, document.sourcePath),
      }
    }

    const key = JSON.stringify(reference)

    if (!seen.has(key)) {
      seen.add(key)
      references.push(reference)
    }
  }

  return references
}
