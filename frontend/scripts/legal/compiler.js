import { mkdir, readFile, readdir, rename, rm, writeFile } from 'node:fs/promises'
import path from 'node:path'
import {
  LEGAL_DOCUMENTS,
  LEGAL_SCHEMA_VERSION,
} from './config.js'
import { LegalValidationError } from './errors.js'
import { parseLegalFrontMatter } from './frontMatter.js'
import { parseLegalMarkdown } from './markdown.js'

const LEGAL_OWNER = 'Club Galotxes de Monover'
const PRIVATE_PHONE_PATTERN = /(?<!\d)(?:(?:\+34|0034)[ .-]?)?(?:[6789]\d{8}|[6789]\d{2}[ .-]\d{3}[ .-]\d{3})(?!\d)/
const FORBIDDEN_PUBLIC_MARKERS = [
  /BORRADOR(?:\s+INTERNO)?/i,
  /NO\s+PUBLICAR/i,
  /PENDIENTE\s+DE\s+CONFIRMACI[ÓO]N/i,
]

const fail = (message, sourcePath = null, code = 'LEGAL_INVALID') => {
  throw new LegalValidationError(message, { code, sourcePath })
}

const readUtf8 = async (absolutePath, sourcePath) => {
  const buffer = await readFile(absolutePath)
  let source
  try {
    source = new TextDecoder('utf-8', { fatal: true }).decode(buffer)
  } catch {
    fail('el archivo no es UTF-8 válido.', sourcePath, 'ENCODING_INVALID')
  }

  if (source.charCodeAt(0) === 0xfeff) fail('no se admite BOM.', sourcePath, 'ENCODING_BOM')
  if (source.includes('\r')) fail('se requieren finales LF.', sourcePath, 'LINE_ENDING_INVALID')
  const trailingLine = source.split('\n').findIndex((line) => /[ \t]+$/.test(line))
  if (trailingLine !== -1) {
    fail(`espacio final en la línea ${trailingLine + 1}.`, sourcePath, 'TRAILING_WHITESPACE')
  }
  return source
}

export const discoverLegalDocuments = async (legalRoot) => {
  let entries
  try {
    entries = await readdir(legalRoot, { withFileTypes: true })
  } catch (error) {
    fail(`no se puede leer legal/: ${error.message}`, null, 'SOURCE_READ')
  }

  const allowed = new Set(LEGAL_DOCUMENTS.map(({ filename }) => filename))
  const included = []
  const excluded = []

  for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name))) {
    if (entry.isSymbolicLink()) fail('no se admiten enlaces simbólicos.', entry.name, 'SOURCE_SYMLINK')
    if (entry.name === 'README.md' && entry.isFile()) {
      excluded.push({ sourcePath: entry.name, reason: 'documentación técnica' })
      continue
    }
    if (!entry.isFile() || !allowed.has(entry.name)) {
      fail('elemento fuera de la allowlist legal cerrada.', entry.name, 'SOURCE_UNKNOWN')
    }
    included.push({ sourcePath: entry.name, absolutePath: path.join(legalRoot, entry.name) })
  }

  if (included.length !== LEGAL_DOCUMENTS.length) {
    fail(
      `se requieren exactamente ${LEGAL_DOCUMENTS.length} documentos legales.`,
      null,
      'SOURCE_COUNT_INVALID',
    )
  }

  return { included, excluded }
}

const compileDocument = async (entry) => {
  const source = await readUtf8(entry.absolutePath, entry.sourcePath)
  const { metadata, markdown } = parseLegalFrontMatter(source, entry.sourcePath)
  const publicSource = `${Object.entries(metadata)
    .filter(([key]) => key !== 'source_draft')
    .map(([, value]) => value)
    .join('\n')}\n${markdown}`

  for (const marker of FORBIDDEN_PUBLIC_MARKERS) {
    if (marker.test(publicSource)) {
      fail('se ha detectado un marcador interno no publicable.', entry.sourcePath, 'PUBLIC_MARKER_FORBIDDEN')
    }
  }
  if (PRIVATE_PHONE_PATTERN.test(publicSource)) {
    fail('se ha detectado un número de teléfono no admitido.', entry.sourcePath, 'PRIVATE_PHONE_FORBIDDEN')
  }

  const parsed = parseLegalMarkdown(markdown, entry.sourcePath)

  if (parsed.titleHeading.text !== metadata.title) {
    fail('el H1 debe coincidir exactamente con "title".', entry.sourcePath, 'TITLE_HEADING_MISMATCH')
  }
  if (metadata.owner !== LEGAL_OWNER) {
    fail('"owner" no coincide con la denominación jurídica confirmada.', entry.sourcePath, 'OWNER_INVALID')
  }

  return { entry, metadata, parsed }
}

const ensureUnique = (compiled) => {
  for (const [field, code] of [['id', 'ID_DUPLICATE'], ['slug', 'SLUG_DUPLICATE']]) {
    const seen = new Set()
    for (const document of compiled) {
      const value = document.metadata[field]
      if (seen.has(value)) fail(`valor duplicado de "${field}": "${value}".`, document.entry.sourcePath, code)
      seen.add(value)
    }
  }
}

export const validateLegalArtifact = (artifact) => {
  if (artifact?.schemaVersion !== LEGAL_SCHEMA_VERSION) {
    fail('schemaVersion legal no soportado.', null, 'ARTIFACT_SCHEMA_INVALID')
  }
  if (!Array.isArray(artifact.documents) || artifact.documents.length !== LEGAL_DOCUMENTS.length) {
    fail('el artefacto debe contener exactamente tres documentos.', null, 'ARTIFACT_COUNT_INVALID')
  }

  for (const [index, contract] of LEGAL_DOCUMENTS.entries()) {
    const document = artifact.documents[index]
    if (
      document?.id !== contract.id
      || document.slug !== contract.slug
      || document.route !== contract.route
      || document.order !== contract.order
      || !Array.isArray(document.blocks)
      || !Array.isArray(document.headings)
    ) {
      fail(`proyección inválida para "${contract.id}".`, null, 'ARTIFACT_DOCUMENT_INVALID')
    }
  }

  const serialized = JSON.stringify(artifact)
  if (/docs\/legal-drafts|knowledge\//i.test(serialized)) {
    fail('la proyección contiene una fuente excluida.', null, 'ARTIFACT_SOURCE_LEAK')
  }
  if (PRIVATE_PHONE_PATTERN.test(serialized)) {
    fail('la proyección contiene un teléfono.', null, 'PRIVATE_PHONE_FORBIDDEN')
  }
  for (const marker of FORBIDDEN_PUBLIC_MARKERS) {
    if (marker.test(serialized)) fail('la proyección contiene un marcador interno.', null, 'PUBLIC_MARKER_FORBIDDEN')
  }

  return artifact
}

export const compileLegalArtifact = async (legalRoot) => {
  const discovery = await discoverLegalDocuments(legalRoot)
  const compiled = await Promise.all(discovery.included.map(compileDocument))
  ensureUnique(compiled)

  const byFilename = new Map(compiled.map((document) => [document.entry.sourcePath, document]))
  const documents = LEGAL_DOCUMENTS.map((contract) => {
    const source = byFilename.get(contract.filename)
    if (!source) fail('documento permitido ausente.', contract.filename, 'SOURCE_MISSING')

    const { metadata, parsed } = source
    if (
      metadata.id !== contract.id
      || metadata.title !== contract.title
      || metadata.slug !== contract.slug
      || metadata.source_draft !== contract.sourceDraft
    ) {
      fail('los metadatos no coinciden con el contrato cerrado.', contract.filename, 'METADATA_CONTRACT_INVALID')
    }

    return {
      id: metadata.id,
      title: metadata.title,
      slug: metadata.slug,
      version: metadata.version,
      status: metadata.status,
      publishedAt: metadata.published_at,
      reviewedAt: metadata.reviewed_at,
      owner: metadata.owner,
      summary: metadata.summary,
      route: contract.route,
      order: contract.order,
      headings: parsed.headings,
      blocks: parsed.blocks,
    }
  })

  return { artifact: validateLegalArtifact({ schemaVersion: LEGAL_SCHEMA_VERSION, documents }), discovery }
}

export const serializeLegalArtifact = (artifact) => `${JSON.stringify(artifact, null, 2)}\n`

export const assertLegalArtifactCurrent = async (legalRoot, outputPath) => {
  const { artifact } = await compileLegalArtifact(legalRoot)
  const expected = serializeLegalArtifact(artifact)
  let actual
  try {
    actual = await readFile(outputPath, 'utf8')
  } catch (error) {
    fail(`no se puede leer el artefacto generado: ${error.message}`, null, 'OUTPUT_READ')
  }
  if (actual !== expected) {
    fail('el artefacto generado no coincide con la fuente; ejecuta legal:build.', null, 'OUTPUT_STALE')
  }
  return { artifact, bytes: expected }
}

export const buildLegalArtifact = async (legalRoot, outputPath) => {
  const { artifact, discovery } = await compileLegalArtifact(legalRoot)
  const bytes = serializeLegalArtifact(artifact)
  const temporaryPath = `${outputPath}.${process.pid}.tmp`
  await mkdir(path.dirname(outputPath), { recursive: true })
  try {
    await writeFile(temporaryPath, bytes, 'utf8')
    await rename(temporaryPath, outputPath)
  } finally {
    await rm(temporaryPath, { force: true })
  }
  return { artifact, discovery, bytes, outputPath }
}
