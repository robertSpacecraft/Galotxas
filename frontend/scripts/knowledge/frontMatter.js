import {
  ALLOWED_STATUSES,
  REQUIRED_METADATA_FIELDS,
} from './config.js'
import { KnowledgeValidationError } from './errors.js'

const VERSION_PATTERN = /^\d+\.\d+\.\d+$/
const DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/
const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

function fail(message, sourcePath, code) {
  throw new KnowledgeValidationError(message, { code, sourcePath })
}

function isValidIsoDate(value) {
  const match = DATE_PATTERN.exec(value)

  if (!match) {
    return false
  }

  const [, year, month, day] = match
  const date = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)))

  return (
    date.getUTCFullYear() === Number(year) &&
    date.getUTCMonth() === Number(month) - 1 &&
    date.getUTCDate() === Number(day)
  )
}

export function parseFrontMatter(source, sourcePath) {
  const lines = source.split('\n')

  if (lines[0] !== '---') {
    fail('falta el delimitador inicial de front matter.', sourcePath, 'FRONT_MATTER_OPEN')
  }

  const closingIndex = lines.indexOf('---', 1)

  if (closingIndex === -1) {
    fail('falta el delimitador final de front matter.', sourcePath, 'FRONT_MATTER_CLOSE')
  }

  const metadata = {}

  for (const line of lines.slice(1, closingIndex)) {
    if (line.trim() === '') {
      fail('el front matter no admite líneas vacías.', sourcePath, 'FRONT_MATTER_SYNTAX')
    }

    const separatorIndex = line.indexOf(':')

    if (separatorIndex <= 0) {
      fail(`línea de front matter no válida: "${line}".`, sourcePath, 'FRONT_MATTER_SYNTAX')
    }

    const key = line.slice(0, separatorIndex).trim()
    const value = line.slice(separatorIndex + 1).trim()

    if (!REQUIRED_METADATA_FIELDS.includes(key)) {
      fail(`campo de metadatos no admitido: "${key}".`, sourcePath, 'METADATA_UNKNOWN')
    }

    if (Object.hasOwn(metadata, key)) {
      fail(`campo de metadatos duplicado: "${key}".`, sourcePath, 'METADATA_DUPLICATE')
    }

    if (value === '' || /^(?:>|\||\[|\{|\})/.test(value)) {
      fail(`el campo "${key}" debe ser un valor escalar simple.`, sourcePath, 'METADATA_VALUE')
    }

    metadata[key] = value
  }

  for (const field of REQUIRED_METADATA_FIELDS) {
    if (!Object.hasOwn(metadata, field)) {
      fail(`falta el campo obligatorio "${field}".`, sourcePath, 'METADATA_REQUIRED')
    }
  }

  if (!VERSION_PATTERN.test(metadata.version)) {
    fail('"version" debe usar el formato SemVer X.Y.Z.', sourcePath, 'VERSION_INVALID')
  }

  if (!ALLOWED_STATUSES.has(metadata.estado)) {
    fail(
      `"estado" debe ser uno de: ${[...ALLOWED_STATUSES].join(', ')}.`,
      sourcePath,
      'STATUS_INVALID',
    )
  }

  if (!isValidIsoDate(metadata.ultima_revision)) {
    fail(
      '"ultima_revision" debe ser una fecha ISO válida YYYY-MM-DD.',
      sourcePath,
      'DATE_INVALID',
    )
  }

  if (!SLUG_PATTERN.test(metadata.slug)) {
    fail('"slug" debe usar kebab-case ASCII.', sourcePath, 'SLUG_INVALID')
  }

  const rawMarkdown = lines.slice(closingIndex + 1).join('\n').replace(/^\n/, '')
  const markdown = `${rawMarkdown.replace(/\n*$/, '')}\n`

  if (markdown.trim() === '') {
    fail('el documento no contiene cuerpo Markdown.', sourcePath, 'MARKDOWN_EMPTY')
  }

  return { metadata, markdown }
}
