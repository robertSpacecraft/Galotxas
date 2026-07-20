import { mkdir, readFile, readdir, rename, rm, writeFile } from 'node:fs/promises'
import path from 'node:path'
import {
  COLLECTIONS,
  EXCLUDED_MARKDOWN,
  SCHEMA_VERSION,
} from './config.js'
import { KnowledgeValidationError } from './errors.js'
import { parseFrontMatter } from './frontMatter.js'
import { analyzeMarkdown, resolveReferences } from './markdown.js'

function fail(message, sourcePath = null, code = 'KNOWLEDGE_INVALID') {
  throw new KnowledgeValidationError(message, { code, sourcePath })
}

function toSourcePath(root, absolutePath) {
  return path.relative(root, absolutePath).split(path.sep).join('/')
}

function compareText(left, right) {
  if (left < right) return -1
  if (left > right) return 1
  return 0
}

async function walk(root, directory = root) {
  const entries = await readdir(directory, { withFileTypes: true })
  const paths = []

  for (const entry of entries.sort((left, right) => compareText(left.name, right.name))) {
    const absolutePath = path.join(directory, entry.name)
    const sourcePath = toSourcePath(root, absolutePath)

    if (entry.isSymbolicLink()) {
      fail('no se admiten enlaces simbólicos dentro de knowledge/.', sourcePath, 'SOURCE_SYMLINK')
    }

    if (entry.isDirectory()) {
      paths.push(...(await walk(root, absolutePath)))
    } else if (entry.isFile()) {
      paths.push(absolutePath)
    }
  }

  return paths
}

export async function discoverKnowledge(knowledgeRoot) {
  let absolutePaths

  try {
    absolutePaths = await walk(knowledgeRoot)
  } catch (error) {
    if (error instanceof KnowledgeValidationError) {
      throw error
    }

    fail(`no se puede leer el corpus: ${error.message}`, null, 'SOURCE_READ')
  }

  const included = []
  const excluded = []
  const ignored = []

  for (const absolutePath of absolutePaths) {
    const sourcePath = toSourcePath(knowledgeRoot, absolutePath)

    if (!sourcePath.endsWith('.md')) {
      ignored.push(sourcePath)
      continue
    }

    if (EXCLUDED_MARKDOWN.has(sourcePath)) {
      excluded.push({ sourcePath, reason: EXCLUDED_MARKDOWN.get(sourcePath) })
      continue
    }

    const collection = COLLECTIONS.find(
      (candidate) => path.posix.dirname(sourcePath) === candidate.sourceDirectory,
    )

    if (!collection) {
      fail(
        'documento Markdown fuera de las colecciones aprobadas.',
        sourcePath,
        'COLLECTION_UNKNOWN',
      )
    }

    const filename = path.posix.basename(sourcePath)

    if (!collection.filenamePattern.test(filename)) {
      fail(
        `el nombre "${filename}" no cumple el contrato snake_case de su colección.`,
        sourcePath,
        'FILENAME_INVALID',
      )
    }

    included.push({ absolutePath, sourcePath, collection })
  }

  return { included, excluded, ignored }
}

async function readUtf8Document(absolutePath, sourcePath) {
  const buffer = await readFile(absolutePath)
  let source

  try {
    source = new TextDecoder('utf-8', { fatal: true }).decode(buffer)
  } catch {
    fail('el archivo no es UTF-8 válido.', sourcePath, 'ENCODING_INVALID')
  }

  if (source.charCodeAt(0) === 0xfeff) {
    fail('el archivo no debe incluir BOM.', sourcePath, 'ENCODING_BOM')
  }

  if (source.includes('\r')) {
    fail('el archivo debe usar finales de línea LF.', sourcePath, 'LINE_ENDING_INVALID')
  }

  const trailingWhitespaceLine = source
    .split('\n')
    .findIndex((line) => /[ \t]+$/.test(line))

  if (trailingWhitespaceLine !== -1) {
    fail(
      `se ha detectado espacio final en la línea ${trailingWhitespaceLine + 1}.`,
      sourcePath,
      'TRAILING_WHITESPACE',
    )
  }

  return source
}

async function compileDocument(entry) {
  const source = await readUtf8Document(entry.absolutePath, entry.sourcePath)
  const { metadata, markdown } = parseFrontMatter(source, entry.sourcePath)

  if (!entry.collection.idPattern.test(metadata.id)) {
    fail(
      `el ID "${metadata.id}" no pertenece a ${entry.collection.id}.`,
      entry.sourcePath,
      'ID_NAMESPACE_INVALID',
    )
  }

  const { headings, referenceCandidates } = analyzeMarkdown(markdown, entry.sourcePath)

  if (headings[0].text !== metadata.titulo) {
    fail(
      'el primer H1 debe coincidir exactamente con "titulo".',
      entry.sourcePath,
      'TITLE_HEADING_MISMATCH',
    )
  }

  const order = Number(metadata.id.slice(-3))

  return {
    id: metadata.id,
    slug: metadata.slug,
    title: metadata.titulo,
    version: metadata.version,
    status: metadata.estado,
    lastRevision: metadata.ultima_revision,
    collection: entry.collection.id,
    sourcePath: entry.sourcePath,
    outputPath: `${entry.collection.id}/${metadata.slug}`,
    order,
    markdown,
    headings,
    referenceCandidates,
  }
}

export function validateCorpusUniqueness(documents) {
  const ids = new Map()
  const slugs = new Map()
  const outputPaths = new Map()
  const orders = new Map()

  for (const document of documents) {
    const keys = [
      [ids, document.id, 'ID', 'ID_DUPLICATE'],
      [slugs, `${document.collection}:${document.slug}`, 'slug del namespace', 'SLUG_DUPLICATE'],
      [outputPaths, document.outputPath, 'ruta de salida', 'OUTPUT_PATH_DUPLICATE'],
      [orders, `${document.collection}:${document.order}`, 'orden de colección', 'ORDER_DUPLICATE'],
    ]

    for (const [registry, key, label, code] of keys) {
      if (registry.has(key)) {
        fail(
          `${label} duplicado "${key}"; también aparece en "${registry.get(key)}".`,
          document.sourcePath,
          code,
        )
      }

      registry.set(key, document.sourcePath)
    }
  }
}

function sortDocuments(documents) {
  const collectionOrder = new Map(COLLECTIONS.map((collection) => [collection.id, collection.order]))

  return [...documents].sort(
    (left, right) =>
      collectionOrder.get(left.collection) - collectionOrder.get(right.collection) ||
      left.order - right.order ||
      compareText(left.id, right.id) ||
      compareText(left.sourcePath, right.sourcePath),
  )
}

export async function compileKnowledge(knowledgeRoot) {
  const discovery = await discoverKnowledge(knowledgeRoot)
  const documents = sortDocuments(
    await Promise.all(discovery.included.map((entry) => compileDocument(entry))),
  )

  validateCorpusUniqueness(documents)

  const documentsById = new Map(documents.map((document) => [document.id, document]))
  const documentsBySourcePath = new Map(
    documents.map((document) => [document.sourcePath, document]),
  )

  const normalizedDocuments = documents.map((document) => {
    const references = resolveReferences(document, documentsById, documentsBySourcePath)
    const { referenceCandidates, ...normalizedDocument } = document
    void referenceCandidates

    return { ...normalizedDocument, references }
  })

  const collections = COLLECTIONS.map((collection) => ({
    id: collection.id,
    title: collection.title,
    order: collection.order,
    documentCount: normalizedDocuments.filter(
      (document) => document.collection === collection.id,
    ).length,
  }))

  return {
    artifact: {
      schemaVersion: SCHEMA_VERSION,
      collections,
      documents: normalizedDocuments,
    },
    discovery,
  }
}

export function serializeArtifact(artifact) {
  return `${JSON.stringify(artifact, null, 2)}\n`
}

export async function buildKnowledge(knowledgeRoot, outputPath) {
  const result = await compileKnowledge(knowledgeRoot)
  const bytes = serializeArtifact(result.artifact)
  const outputDirectory = path.dirname(outputPath)
  const temporaryPath = path.join(
    outputDirectory,
    `.${path.basename(outputPath)}.${process.pid}.tmp`,
  )

  await mkdir(outputDirectory, { recursive: true })

  try {
    await writeFile(temporaryPath, bytes, 'utf8')
    await rename(temporaryPath, outputPath)
  } finally {
    await rm(temporaryPath, { force: true })
  }

  return { ...result, bytes, outputPath }
}
