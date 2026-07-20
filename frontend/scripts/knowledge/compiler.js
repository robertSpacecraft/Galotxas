import { mkdir, readFile, readdir, rename, rm, stat, writeFile } from 'node:fs/promises'
import path from 'node:path'
import {
  COLLECTIONS,
  EXCLUDED_MARKDOWN,
  SCHEMA_VERSION,
} from './config.js'
import { KnowledgeValidationError } from './errors.js'
import { parseFrontMatter } from './frontMatter.js'
import { analyzeMarkdown, resolveReferences } from './markdown.js'
import { createPublicKnowledgeArtifact } from './publicProjection.js'

let writeTransaction = 0

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

  const { headings, referenceCandidates } = analyzeMarkdown(
    markdown,
    entry.sourcePath,
    metadata.id,
  )

  if (headings[0].text !== metadata.titulo) {
    fail(
      `el documento "${metadata.id}" usa el H1 "${headings[0].text}", que debe coincidir exactamente con "titulo" ("${metadata.titulo}").`,
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

export function validateReferencePublication(documents) {
  const documentsById = new Map(documents.map((document) => [document.id, document]))

  for (const document of documents) {
    if (document.status !== 'Vigente') {
      continue
    }

    for (const reference of document.references) {
      if (reference.type !== 'document') {
        continue
      }

      const target = documentsById.get(reference.targetId)

      if (!target) {
        fail(
          `el documento Vigente "${document.id}" referencia el destino inexistente "${reference.targetId}".`,
          document.sourcePath,
          'REFERENCE_ID_MISSING',
        )
      }

      if (target.status !== 'Vigente') {
        fail(
          `el documento Vigente "${document.id}" referencia "${target.id}" con estado "${target.status}", que no es publicable.`,
          document.sourcePath,
          'REFERENCE_STATUS_UNPUBLISHABLE',
        )
      }
    }
  }
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

  validateReferencePublication(normalizedDocuments)

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

export async function compileKnowledgeArtifacts(knowledgeRoot) {
  const result = await compileKnowledge(knowledgeRoot)

  return {
    ...result,
    publicArtifact: createPublicKnowledgeArtifact(result.artifact),
  }
}

export function serializeArtifact(artifact) {
  return `${JSON.stringify(artifact, null, 2)}\n`
}

async function pathExists(absolutePath, operations) {
  try {
    await operations.stat(absolutePath)
    return true
  } catch (error) {
    if (error.code === 'ENOENT') return false
    throw error
  }
}

export async function writeArtifactPair(outputs, operations = {
  mkdir,
  rename,
  rm,
  stat,
  writeFile,
}) {
  if (outputs.length !== 2 || outputs[0].outputPath === outputs[1].outputPath) {
    fail('la escritura coordinada requiere dos destinos distintos.', null, 'OUTPUT_PAIR_INVALID')
  }

  writeTransaction += 1
  const suffix = `${process.pid}.${writeTransaction}`
  const entries = outputs.map(({ outputPath, bytes }, index) => ({
    outputPath,
    bytes,
    temporaryPath: `${outputPath}.${suffix}.${index}.tmp`,
    backupPath: `${outputPath}.${suffix}.${index}.bak`,
    backedUp: false,
    promoted: false,
  }))

  try {
    await Promise.all(entries.map((entry) =>
      operations.mkdir(path.dirname(entry.outputPath), { recursive: true }),
    ))

    await Promise.all(entries.map((entry) =>
      operations.writeFile(entry.temporaryPath, entry.bytes, 'utf8'),
    ))

    for (const entry of entries) {
      if (await pathExists(entry.outputPath, operations)) {
        await operations.rename(entry.outputPath, entry.backupPath)
        entry.backedUp = true
      }
    }

    for (const entry of entries) {
      await operations.rename(entry.temporaryPath, entry.outputPath)
      entry.promoted = true
    }

    await Promise.all(entries.map((entry) => operations.rm(entry.backupPath, { force: true })))
  } catch (error) {
    for (const entry of [...entries].reverse()) {
      if (entry.promoted) {
        await operations.rm(entry.outputPath, { force: true })
      }

      if (entry.backedUp) {
        await operations.rename(entry.backupPath, entry.outputPath)
        entry.backedUp = false
      }
    }

    throw error
  } finally {
    await Promise.all(entries.flatMap((entry) => [
      operations.rm(entry.temporaryPath, { force: true }),
      operations.rm(entry.backupPath, { force: true }),
    ]))
  }
}

export async function buildKnowledge(knowledgeRoot, outputPath, publicOutputPath) {
  const result = await compileKnowledgeArtifacts(knowledgeRoot)
  const bytes = serializeArtifact(result.artifact)
  const publicBytes = serializeArtifact(result.publicArtifact)

  await writeArtifactPair([
    { outputPath, bytes },
    { outputPath: publicOutputPath, bytes: publicBytes },
  ])

  return { ...result, bytes, publicBytes, outputPath, publicOutputPath }
}
