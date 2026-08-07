import {
  ALLOWED_LEGAL_STATUSES,
  REQUIRED_NOTICE_METADATA_FIELDS,
  REQUIRED_METADATA_FIELDS,
} from './config.js'
import { LegalValidationError } from './errors.js'

const VERSION_PATTERN = /^\d+\.\d+\.\d+$/
const DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/
const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

const fail = (message, sourcePath, code) => {
  throw new LegalValidationError(message, { code, sourcePath })
}

const isValidIsoDate = (value) => {
  const match = DATE_PATTERN.exec(value)
  if (!match) return false

  const [, year, month, day] = match
  const date = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)))

  return date.getUTCFullYear() === Number(year)
    && date.getUTCMonth() === Number(month) - 1
    && date.getUTCDate() === Number(day)
}

export const parseLegalFrontMatter = (source, sourcePath) => {
  return parseFrontMatter(source, sourcePath, {
    requiredFields: REQUIRED_METADATA_FIELDS,
    requireSlug: true,
  })
}

export const parseNoticeFrontMatter = (source, sourcePath) => {
  return parseFrontMatter(source, sourcePath, {
    requiredFields: REQUIRED_NOTICE_METADATA_FIELDS,
    allowedFields: [...REQUIRED_NOTICE_METADATA_FIELDS, 'privacy_url'],
    requireSlug: false,
  })
}

const parseFrontMatter = (
  source,
  sourcePath,
  { requiredFields, allowedFields = requiredFields, requireSlug },
) => {
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
    const separatorIndex = line.indexOf(':')

    if (separatorIndex <= 0) {
      fail(`línea de front matter no válida: "${line}".`, sourcePath, 'FRONT_MATTER_SYNTAX')
    }

    const key = line.slice(0, separatorIndex).trim()
    const value = line.slice(separatorIndex + 1).trim()

    if (!allowedFields.includes(key)) {
      fail(`campo de metadatos no admitido: "${key}".`, sourcePath, 'METADATA_UNKNOWN')
    }

    if (Object.hasOwn(metadata, key)) {
      fail(`campo de metadatos duplicado: "${key}".`, sourcePath, 'METADATA_DUPLICATE')
    }

    if (!value || /^(?:>|\||\[|\{|\})/.test(value)) {
      fail(`el campo "${key}" debe ser un valor escalar no vacío.`, sourcePath, 'METADATA_VALUE')
    }

    metadata[key] = value
  }

  for (const field of requiredFields) {
    if (!Object.hasOwn(metadata, field)) {
      fail(`falta el campo obligatorio "${field}".`, sourcePath, 'METADATA_REQUIRED')
    }
  }

  if (!VERSION_PATTERN.test(metadata.version)) {
    fail('"version" debe usar SemVer X.Y.Z.', sourcePath, 'VERSION_INVALID')
  }

  if (!ALLOWED_LEGAL_STATUSES.has(metadata.status)) {
    fail(
      `"status" debe ser uno de: ${[...ALLOWED_LEGAL_STATUSES].join(', ')}.`,
      sourcePath,
      'STATUS_INVALID',
    )
  }

  for (const field of ['published_at', 'reviewed_at']) {
    if (!isValidIsoDate(metadata[field])) {
      fail(`"${field}" debe ser una fecha ISO válida YYYY-MM-DD.`, sourcePath, 'DATE_INVALID')
    }
  }

  if (metadata.reviewed_at < metadata.published_at) {
    fail('"reviewed_at" no puede ser anterior a "published_at".', sourcePath, 'DATE_ORDER_INVALID')
  }

  if (requireSlug && !SLUG_PATTERN.test(metadata.slug)) {
    fail('"slug" debe usar kebab-case ASCII.', sourcePath, 'SLUG_INVALID')
  }

  const rawMarkdown = lines.slice(closingIndex + 1).join('\n').replace(/^\n/, '')
  const markdown = `${rawMarkdown.replace(/\n*$/, '')}\n`

  if (!markdown.trim()) {
    fail('el documento no contiene cuerpo Markdown.', sourcePath, 'MARKDOWN_EMPTY')
  }

  return { metadata, markdown }
}
