// @vitest-environment node

import * as fs from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'
import {
  compileKnowledgeArtifacts,
  serializeArtifact,
  writeArtifactPair,
} from './compiler.js'
import { DEFAULT_KNOWLEDGE_ROOT, DEFAULT_PUBLIC_OUTPUT_PATH } from './config.js'
import {
  createPublicKnowledgeArtifact,
  parsePublicMarkdown,
} from './publicProjection.js'

const temporaryDirectories = []

async function createTemporaryDirectory() {
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'galotxas-public-knowledge-'))
  temporaryDirectories.push(directory)
  return directory
}

async function writeFiles(root, files) {
  for (const [sourcePath, contents] of Object.entries(files)) {
    const absolutePath = path.join(root, sourcePath)
    await fs.mkdir(path.dirname(absolutePath), { recursive: true })
    await fs.writeFile(absolutePath, contents, 'utf8')
  }
}

function documentSource({
  id = 'REG-001',
  slug = 'documento',
  title = 'Documento',
  status = 'Vigente',
  markdown = '# Documento\n\nContenido.\n',
} = {}) {
  return [
    '---',
    `id: ${id}`,
    `slug: ${slug}`,
    `titulo: ${title}`,
    'version: 1.0.0',
    `estado: ${status}`,
    'ultima_revision: 2026-07-20',
    '---',
    '',
    markdown.trimEnd(),
    '',
  ].join('\n')
}

function publicParserDocument(markdown) {
  return {
    id: 'REG-001',
    title: 'Documento',
    status: 'Vigente',
    sourcePath: 'reglamento/01_documento.md',
    markdown,
    headings: [
      { level: 1, text: 'Documento', anchor: 'documento' },
    ],
  }
}

afterEach(async () => {
  await Promise.all(
    temporaryDirectories.splice(0).map((directory) =>
      fs.rm(directory, { recursive: true, force: true }),
    ),
  )
})

describe('proyección pública', () => {
  it('conserva Vigente, elimina por completo Borrador y omite colecciones vacías', async () => {
    const root = await createTemporaryDirectory()
    await writeFiles(root, {
      'reglamento/01_publico.md': documentSource({
        slug: 'publico',
        title: 'Documento público',
        markdown: '# Documento público\n\nContenido visible.\n',
      }),
      'conceptos/juego/secreto.md': documentSource({
        id: 'CON-JUE-001',
        slug: 'slug-secreto',
        title: 'Título secreto',
        status: 'Borrador',
        markdown: '# Título secreto\n\nContenido secreto irrepetible.\n',
      }),
    })

    const { artifact, publicArtifact } = await compileKnowledgeArtifacts(root)
    const publicBytes = serializeArtifact(publicArtifact)

    expect(artifact.documents).toHaveLength(2)
    expect(artifact.documents.find(({ id }) => id === 'CON-JUE-001')?.status).toBe('Borrador')
    expect(publicArtifact.documents.map(({ id }) => id)).toEqual(['REG-001'])
    expect(publicArtifact.collections.map(({ id }) => id)).toEqual(['reglamento'])
    expect(publicBytes).not.toContain('CON-JUE-001')
    expect(publicBytes).not.toContain('slug-secreto')
    expect(publicBytes).not.toContain('Título secreto')
    expect(publicBytes).not.toContain('Contenido secreto irrepetible')
  })

  it('falla si no queda ningún documento público', async () => {
    const root = await createTemporaryDirectory()
    await writeFiles(root, {
      'reglamento/01_borrador.md': documentSource({ status: 'Borrador' }),
    })

    await expect(compileKnowledgeArtifacts(root)).rejects.toMatchObject({
      code: 'PUBLIC_DOCUMENTS_REQUIRED',
    })
  })

  it('preserva el orden canónico y resuelve sólo referencias explícitas', async () => {
    const root = await createTemporaryDirectory()
    await writeFiles(root, {
      'reglamento/02_destino.md': documentSource({
        id: 'REG-002',
        slug: 'destino',
        title: 'Destino',
        markdown: '# Destino\n\nSegundo.\n',
      }),
      'reglamento/01_origen.md': documentSource({
        id: 'REG-001',
        slug: 'origen',
        title: 'Origen',
        markdown: '# Origen\n\nConsulta **REG-002 – Destino**. REG-002 queda como texto.\n',
      }),
    })

    const { publicArtifact } = await compileKnowledgeArtifacts(root)
    const [origin, destination] = publicArtifact.documents
    const referenceNode = origin.blocks[0].children[1].children[0]

    expect(publicArtifact.documents.map(({ id }) => id)).toEqual(['REG-001', 'REG-002'])
    expect(referenceNode).toEqual({
      type: 'reference',
      targetId: 'REG-002',
      label: 'REG-002 – Destino',
      href: '/aprende-a-jugar/manual/reglamento/destino',
    })
    expect(origin.references).toEqual([{
      targetId: 'REG-002',
      label: 'REG-002 – Destino',
      href: destination.route,
    }])
    expect(origin.blocks[0].children.at(-1)).toMatchObject({
      type: 'text',
      value: '. REG-002 queda como texto.',
    })
  })

  it('rechaza una referencia pública a Borrador o a un destino inexistente', () => {
    const origin = {
      id: 'REG-001',
      slug: 'origen',
      title: 'Origen',
      version: '1.0.0',
      status: 'Vigente',
      lastRevision: '2026-07-20',
      collection: 'reglamento',
      sourcePath: 'reglamento/01_origen.md',
      outputPath: 'reglamento/origen',
      order: 1,
      markdown: '# Origen\n\nREG-002 – Privado.\n',
      headings: [{ level: 1, text: 'Origen', anchor: 'origen' }],
      references: [],
    }
    const draft = {
      ...origin,
      id: 'REG-002',
      slug: 'privado',
      title: 'Privado',
      status: 'Borrador',
      sourcePath: 'reglamento/02_privado.md',
      outputPath: 'reglamento/privado',
      order: 2,
      markdown: '# Privado\n\nSecreto.\n',
      headings: [{ level: 1, text: 'Privado', anchor: 'privado' }],
    }
    const artifact = {
      schemaVersion: 1,
      collections: [{ id: 'reglamento', title: 'Reglamento', order: 1, documentCount: 2 }],
      documents: [origin, draft],
    }

    expect(() => createPublicKnowledgeArtifact(artifact)).toThrowError(
      expect.objectContaining({ code: 'PUBLIC_REFERENCE_UNPUBLISHABLE' }),
    )

    artifact.documents = [{ ...origin, markdown: '# Origen\n\nREG-999 – Ausente.\n' }]
    expect(() => createPublicKnowledgeArtifact(artifact)).toThrowError(
      expect.objectContaining({ code: 'PUBLIC_REFERENCE_MISSING' }),
    )
  })

  it('genera bloques seguros, anchors estables y UTF-8 sin incluir el H1', async () => {
    const root = await createTemporaryDirectory()
    await writeFiles(root, {
      'reglamento/01_documento.md': documentSource({
        markdown: [
          '# Documento',
          '',
          '## Àrea de joc',
          '',
          'Párrafo con **negrita** y *èmfasi*.',
          '',
          '- Pilota',
          '- Quinse',
          '',
          '3. Tercer pas',
          '4. Quart pas',
          '',
          '| Puntuació | Nom |',
          '|---|---|',
          '| 15 | Quinse |',
          '',
          '---',
          '',
          '## Àrea de joc',
          '',
          'Final.',
        ].join('\n'),
      }),
    })

    const { publicArtifact } = await compileKnowledgeArtifacts(root)
    const [document] = publicArtifact.documents

    expect(document.headings).toEqual([
      { level: 2, text: 'Àrea de joc', id: 'area-de-joc' },
      { level: 2, text: 'Àrea de joc', id: 'area-de-joc-2' },
    ])
    expect(document.blocks.map(({ type }) => type)).toEqual([
      'heading',
      'paragraph',
      'unorderedList',
      'orderedList',
      'table',
      'thematicBreak',
      'heading',
      'paragraph',
    ])
    expect(document.blocks.some((block) => block.type === 'heading' && block.level === 1)).toBe(false)
    expect(document.blocks[1].children).toEqual(expect.arrayContaining([
      expect.objectContaining({ type: 'strong' }),
      expect.objectContaining({ type: 'emphasis' }),
    ]))
    expect(document.blocks[3]).toMatchObject({
      type: 'orderedList',
      start: 3,
      items: [{ value: 3 }, { value: 4 }],
    })
    expect(document.blocks[4]).toMatchObject({ type: 'table' })
    expect(serializeArtifact(publicArtifact)).not.toContain('"markdown"')
  })
})

describe('rechazos del parser público', () => {
  it.each([
    ['HTML', '# Documento\n\n<div>Texto</div>\n', 'SECURITY_HTML'],
    ['imagen', '# Documento\n\n![Alt](imagen.png)\n', 'PUBLIC_IMAGE_UNSUPPORTED'],
    ['blockquote', '# Documento\n\n> Cita\n', 'PUBLIC_BLOCKQUOTE_UNSUPPORTED'],
    ['código', '# Documento\n\n```text\ncontenido\n```\n', 'PUBLIC_CODE_BLOCK_UNSUPPORTED'],
    ['lista anidada', '# Documento\n\n- Uno\n  - Dos\n', 'PUBLIC_LIST_NESTED'],
    ['tabla inconsistente', '# Documento\n\n| A | B |\n|---|---|\n| Uno |\n', 'PUBLIC_TABLE_COLUMNS_INVALID'],
    ['inline incompleto', '# Documento\n\nTexto **incompleto.\n', 'PUBLIC_INLINE_INCOMPLETE'],
    ['URL peligrosa', '# Documento\n\njavascript:alert(1)\n', 'SECURITY_URL'],
    ['nesting ambiguo', '# Documento\n\nTexto ***ambiguo***.\n', 'PUBLIC_INLINE_AMBIGUOUS'],
  ])('rechaza %s', (_label, markdown, code) => {
    const document = publicParserDocument(markdown)
    const documents = new Map([[document.id, { ...document, route: '/documento' }]])

    expect(() => parsePublicMarkdown(document, documents, documents)).toThrowError(
      expect.objectContaining({ code }),
    )
  })
})

describe('determinismo y escritura coordinada', () => {
  it('produce bytes deterministas para los dos artefactos del corpus real', async () => {
    const first = await compileKnowledgeArtifacts(DEFAULT_KNOWLEDGE_ROOT)
    const second = await compileKnowledgeArtifacts(DEFAULT_KNOWLEDGE_ROOT)

    expect(serializeArtifact(first.artifact)).toBe(serializeArtifact(second.artifact))
    expect(serializeArtifact(first.publicArtifact)).toBe(serializeArtifact(second.publicArtifact))
  })

  it('restaura ambas salidas si falla la promoción del segundo artefacto', async () => {
    const root = await createTemporaryDirectory()
    const canonicalPath = path.join(root, 'knowledge.json')
    const publicPath = path.join(root, 'public-knowledge.json')
    await fs.writeFile(canonicalPath, 'canónico anterior\n', 'utf8')
    await fs.writeFile(publicPath, 'público anterior\n', 'utf8')

    const operations = {
      ...fs,
      rename: async (from, to) => {
        if (from.endsWith('.tmp') && to === publicPath) {
          throw Object.assign(new Error('fallo simulado'), { code: 'EIO' })
        }

        return fs.rename(from, to)
      },
    }

    await expect(writeArtifactPair([
      { outputPath: canonicalPath, bytes: 'canónico nuevo\n' },
      { outputPath: publicPath, bytes: 'público nuevo\n' },
    ], operations)).rejects.toThrow('fallo simulado')

    expect(await fs.readFile(canonicalPath, 'utf8')).toBe('canónico anterior\n')
    expect(await fs.readFile(publicPath, 'utf8')).toBe('público anterior\n')
    expect((await fs.readdir(root)).sort()).toEqual(['knowledge.json', 'public-knowledge.json'])
  })

  it('mantiene sincronizada la proyección real con 40 documentos y la tabla REG-006', async () => {
    const { publicArtifact } = await compileKnowledgeArtifacts(DEFAULT_KNOWLEDGE_ROOT)
    const committedBytes = await fs.readFile(DEFAULT_PUBLIC_OUTPUT_PATH, 'utf8')
    const scoring = publicArtifact.documents.find(({ id }) => id === 'REG-006')

    expect(committedBytes).toBe(serializeArtifact(publicArtifact))
    expect(publicArtifact.documents).toHaveLength(40)
    expect(publicArtifact.collections.map(({ documentCount }) => documentCount)).toEqual([8, 12, 4, 16])
    expect(publicArtifact.documents.filter(({ id }) => /^REG-00[1-8]$/.test(id))).toHaveLength(8)
    expect(scoring.blocks.some(({ type }) => type === 'table')).toBe(true)
    expect(publicArtifact.documents.flatMap(({ references }) => references).length).toBeGreaterThan(0)
    expect(committedBytes).not.toContain('"status"')
    expect(committedBytes).not.toContain('"sourcePath"')
    expect(committedBytes).not.toContain('"markdown"')
    expect(committedBytes).not.toContain('generatedAt')
  })
})
