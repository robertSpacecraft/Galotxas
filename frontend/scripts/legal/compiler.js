import { mkdir, readFile, readdir, rename, rm, writeFile } from 'node:fs/promises'
import path from 'node:path'
import {
  FORM_NOTICES,
  LEGAL_DOCUMENTS,
  LEGAL_SCHEMA_VERSION,
} from './config.js'
import { LegalValidationError } from './errors.js'
import { parseLegalFrontMatter, parseNoticeFrontMatter } from './frontMatter.js'
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
    if (entry.name === 'notices' && entry.isDirectory()) {
      excluded.push({ sourcePath: entry.name, reason: 'avisos de formulario separados' })
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

export const discoverFormNotices = async (legalRoot) => {
  const noticesRoot = path.join(legalRoot, 'notices')
  let entries
  try {
    entries = await readdir(noticesRoot, { withFileTypes: true })
  } catch (error) {
    fail(`no se puede leer legal/notices/: ${error.message}`, 'notices', 'NOTICE_SOURCE_READ')
  }

  const allowed = new Set(FORM_NOTICES.map(({ filename }) => filename))
  const included = []

  for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name))) {
    const sourcePath = `notices/${entry.name}`
    if (entry.isSymbolicLink()) fail('no se admiten enlaces simbólicos.', sourcePath, 'SOURCE_SYMLINK')
    if (!entry.isFile() || !allowed.has(entry.name)) {
      fail('aviso fuera de la allowlist cerrada.', sourcePath, 'NOTICE_SOURCE_UNKNOWN')
    }
    included.push({ sourcePath, absolutePath: path.join(noticesRoot, entry.name) })
  }

  if (included.length !== FORM_NOTICES.length) {
    fail(
      `se requieren exactamente ${FORM_NOTICES.length} avisos de formulario.`,
      'notices',
      'NOTICE_SOURCE_COUNT_INVALID',
    )
  }

  return { included }
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

const compileNotice = async (entry) => {
  const source = await readUtf8(entry.absolutePath, entry.sourcePath)
  const { metadata, markdown } = parseNoticeFrontMatter(source, entry.sourcePath)
  const publicSource = `${Object.values(metadata).join('\n')}\n${markdown}`

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

const ensureUnique = (compiled, fields = [['id', 'ID_DUPLICATE'], ['slug', 'SLUG_DUPLICATE']]) => {
  for (const [field, code] of fields) {
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

export const validateFormNoticeArtifact = (artifact) => {
  if (artifact?.schemaVersion !== LEGAL_SCHEMA_VERSION) {
    fail('schemaVersion de avisos no soportado.', null, 'NOTICE_ARTIFACT_SCHEMA_INVALID')
  }
  if (!Array.isArray(artifact.notices) || artifact.notices.length !== FORM_NOTICES.length) {
    fail('el artefacto de avisos no coincide con la allowlist.', null, 'NOTICE_ARTIFACT_COUNT_INVALID')
  }
  for (const [index, contract] of FORM_NOTICES.entries()) {
    const notice = artifact.notices[index]
    if (
      notice?.id !== contract.id
      || notice.title !== contract.title
      || notice.scope !== contract.scope
      || (contract.privacyUrl
        ? notice.privacyUrl !== contract.privacyUrl
        : Object.hasOwn(notice, 'privacyUrl'))
      || notice.order !== contract.order
      || !Array.isArray(notice.blocks)
      || !Array.isArray(notice.headings)
    ) {
      fail(`proyección inválida para "${contract.id}".`, null, 'NOTICE_ARTIFACT_INVALID')
    }
  }
  return artifact
}

export const compileFormNoticeArtifact = async (legalRoot) => {
  const discovery = await discoverFormNotices(legalRoot)
  const compiled = await Promise.all(discovery.included.map(compileNotice))
  ensureUnique(compiled, [['id', 'NOTICE_ID_DUPLICATE']])
  const byFilename = new Map(compiled.map((notice) => [path.basename(notice.entry.sourcePath), notice]))

  const notices = FORM_NOTICES.map((contract) => {
    const source = byFilename.get(contract.filename)
    if (!source) fail('aviso permitido ausente.', contract.filename, 'NOTICE_SOURCE_MISSING')
    const { metadata, parsed } = source
    if (
      metadata.id !== contract.id
      || metadata.title !== contract.title
      || metadata.scope !== contract.scope
      || (contract.privacyUrl
        ? metadata.privacy_url !== contract.privacyUrl
        : Object.hasOwn(metadata, 'privacy_url'))
    ) {
      fail('los metadatos no coinciden con el contrato cerrado.', contract.filename, 'NOTICE_METADATA_CONTRACT_INVALID')
    }

    return {
      id: metadata.id,
      title: metadata.title,
      version: metadata.version,
      status: metadata.status,
      publishedAt: metadata.published_at,
      reviewedAt: metadata.reviewed_at,
      owner: metadata.owner,
      scope: metadata.scope,
      summary: metadata.summary,
      order: contract.order,
      headings: parsed.headings,
      blocks: parsed.blocks,
      ...(contract.privacyUrl ? { privacyUrl: metadata.privacy_url } : {}),
    }
  })

  return {
    artifact: validateFormNoticeArtifact({ schemaVersion: LEGAL_SCHEMA_VERSION, notices }),
    discovery,
  }
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

export const assertFormNoticeArtifactCurrent = async (legalRoot, outputPaths) => {
  const { artifact } = await compileFormNoticeArtifact(legalRoot)
  const expected = serializeLegalArtifact(artifact)
  for (const outputPath of outputPaths) {
    let actual
    try {
      actual = await readFile(outputPath, 'utf8')
    } catch (error) {
      fail(`no se puede leer el artefacto de avisos: ${error.message}`, null, 'NOTICE_OUTPUT_READ')
    }
    if (actual !== expected) {
      fail('el artefacto de avisos no coincide con la fuente; ejecuta legal:build.', null, 'NOTICE_OUTPUT_STALE')
    }
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

export const buildFormNoticeArtifacts = async (legalRoot, outputPaths) => {
  const { artifact, discovery } = await compileFormNoticeArtifact(legalRoot)
  const bytes = serializeLegalArtifact(artifact)
  for (const outputPath of outputPaths) {
    const temporaryPath = `${outputPath}.${process.pid}.tmp`
    await mkdir(path.dirname(outputPath), { recursive: true })
    try {
      await writeFile(temporaryPath, bytes, 'utf8')
      await rename(temporaryPath, outputPath)
    } finally {
      await rm(temporaryPath, { force: true })
    }
  }
  return { artifact, discovery, bytes, outputPaths }
}
