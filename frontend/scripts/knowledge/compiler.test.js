// @vitest-environment node

import { mkdtemp, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'
import {
  DEFAULT_KNOWLEDGE_ROOT,
  DEFAULT_OUTPUT_PATH,
} from './config.js'
import {
  buildKnowledge,
  compileKnowledge,
  discoverKnowledge,
  serializeArtifact,
  validateCorpusUniqueness,
} from './compiler.js'
import { parseFrontMatter } from './frontMatter.js'
import { analyzeMarkdown } from './markdown.js'

const temporaryDirectories = []

async function createTemporaryDirectory() {
  const directory = await mkdtemp(path.join(os.tmpdir(), 'galotxas-knowledge-'))
  temporaryDirectories.push(directory)
  return directory
}

async function writeFiles(root, files) {
  for (const [sourcePath, contents] of Object.entries(files)) {
    const absolutePath = path.join(root, sourcePath)
    await mkdir(path.dirname(absolutePath), { recursive: true })
    await writeFile(absolutePath, contents, 'utf8')
  }
}

function documentSource({
  id = 'REG-001',
  slug = 'documento',
  title = 'Documento',
  version = '1.0.0',
  status = 'Vigente',
  revision = '2026-07-20',
  markdown = '# Documento\n\nContenido.\n',
} = {}) {
  return [
    '---',
    `id: ${id}`,
    `slug: ${slug}`,
    `titulo: ${title}`,
    `version: ${version}`,
    `estado: ${status}`,
    `ultima_revision: ${revision}`,
    '---',
    '',
    markdown.trimEnd(),
    '',
  ].join('\n')
}

async function expectCorpusError(files, code) {
  const root = await createTemporaryDirectory()
  await writeFiles(root, files)

  await expect(compileKnowledge(root)).rejects.toMatchObject({ code })
}

afterEach(async () => {
  await Promise.all(
    temporaryDirectories.splice(0).map((directory) =>
      rm(directory, { recursive: true, force: true }),
    ),
  )
})

describe('descubrimiento del corpus', () => {
  it('incluye colecciones aprobadas, excluye documentación técnica e ignora otros formatos', async () => {
    const root = await createTemporaryDirectory()
    await writeFiles(root, {
      'README.md': '# README\n',
      'AGENTS.md': '# AGENTS\n',
      'conceptos/README.md': '# Índice\n',
      'reglamento/00_metodologia.md': '# Metodología\n',
      'reglamento/02_segundo.md': documentSource({ id: 'REG-002', slug: 'segundo' }),
      'reglamento/01_primero.md': documentSource({ id: 'REG-001', slug: 'primero' }),
      'reglamento/notas.txt': 'no Markdown',
    })

    const discovery = await discoverKnowledge(root)

    expect(discovery.included.map(({ sourcePath }) => sourcePath)).toEqual([
      'reglamento/01_primero.md',
      'reglamento/02_segundo.md',
    ])
    expect(discovery.excluded.map(({ sourcePath }) => sourcePath)).toEqual([
      'AGENTS.md',
      'README.md',
      'conceptos/README.md',
      'reglamento/00_metodologia.md',
    ])
    expect(discovery.ignored).toEqual(['reglamento/notas.txt'])
  })

  it('rechaza Markdown en una colección no aprobada', async () => {
    await expectCorpusError(
      { 'conceptos/instalaciones/cancha.md': documentSource() },
      'COLLECTION_UNKNOWN',
    )
  })
})

describe('front matter', () => {
  it('parsea el subconjunto escalar válido y normaliza el LF final del cuerpo', () => {
    const parsed = parseFrontMatter(documentSource().trimEnd(), 'reglamento/01_doc.md')

    expect(parsed.metadata).toMatchObject({
      id: 'REG-001',
      slug: 'documento',
      titulo: 'Documento',
      version: '1.0.0',
      estado: 'Vigente',
      ultima_revision: '2026-07-20',
    })
    expect(parsed.markdown).toBe('# Documento\n\nContenido.\n')
  })

  it('rechaza un campo obligatorio ausente', () => {
    const source = documentSource().replace('slug: documento\n', '')
    expect(() => parseFrontMatter(source, 'doc.md')).toThrowError(/slug/)
  })

  it('rechaza delimitadores incompletos', () => {
    const source = documentSource().replace('\n---\n\n# Documento', '\n\n# Documento')
    expect(() => parseFrontMatter(source, 'doc.md')).toThrowError(/delimitador final/)
  })

  it('rechaza claves duplicadas y valores YAML fuera del subconjunto', () => {
    const duplicate = documentSource().replace('slug: documento', 'slug: documento\nslug: otro')
    const complex = documentSource().replace('titulo: Documento', 'titulo: [Documento]')

    expect(() => parseFrontMatter(duplicate, 'doc.md')).toThrowError(/duplicado/)
    expect(() => parseFrontMatter(complex, 'doc.md')).toThrowError(/escalar simple/)
  })

  it.each([
    ['version', 'version: 1.0', 'VERSION_INVALID'],
    ['estado', 'estado: Archivado', 'STATUS_INVALID'],
    ['fecha', 'ultima_revision: 2026-02-30', 'DATE_INVALID'],
    ['slug', 'slug: No válido', 'SLUG_INVALID'],
  ])('rechaza %s no válido', (_field, replacement, code) => {
    const originals = {
      VERSION_INVALID: 'version: 1.0.0',
      STATUS_INVALID: 'estado: Vigente',
      DATE_INVALID: 'ultima_revision: 2026-07-20',
      SLUG_INVALID: 'slug: documento',
    }
    const source = documentSource().replace(originals[code], replacement)

    expect(() => parseFrontMatter(source, 'doc.md')).toThrowError(
      expect.objectContaining({ code }),
    )
  })
})

describe('unicidad y namespaces', () => {
  it('rechaza IDs globales duplicados', async () => {
    await expectCorpusError(
      {
        'reglamento/01_uno.md': documentSource({ slug: 'uno' }),
        'reglamento/02_dos.md': documentSource({ slug: 'dos' }),
      },
      'ID_DUPLICATE',
    )
  })

  it('rechaza slugs duplicados dentro del namespace', async () => {
    await expectCorpusError(
      {
        'reglamento/01_uno.md': documentSource({ id: 'REG-001', slug: 'igual' }),
        'reglamento/02_dos.md': documentSource({ id: 'REG-002', slug: 'igual' }),
      },
      'SLUG_DUPLICATE',
    )
  })

  it('admite el mismo slug en namespaces distintos', async () => {
    const root = await createTemporaryDirectory()
    await writeFiles(root, {
      'reglamento/01_uno.md': documentSource({ id: 'REG-001', slug: 'comun' }),
      'conceptos/juego/uno.md': documentSource({
        id: 'CON-JUE-001',
        slug: 'comun',
      }),
    })

    const { artifact } = await compileKnowledge(root)
    expect(artifact.documents.map(({ outputPath }) => outputPath)).toEqual([
      'reglamento/comun',
      'conceptos/juego/comun',
    ])
  })

  it('mantiene una defensa independiente frente a rutas de salida duplicadas', () => {
    const base = {
      id: 'REG-001',
      slug: 'uno',
      collection: 'reglamento',
      order: 1,
      sourcePath: 'reglamento/01_uno.md',
      outputPath: 'reglamento/repetida',
    }

    expect(() =>
      validateCorpusUniqueness([
        base,
        {
          ...base,
          id: 'REG-002',
          slug: 'dos',
          order: 2,
          sourcePath: 'reglamento/02_dos.md',
        },
      ]),
    ).toThrowError(expect.objectContaining({ code: 'OUTPUT_PATH_DUPLICATE' }))
  })
})

describe('seguridad Markdown', () => {
  it.each([
    ['script', '<script>alert(1)</script>', 'SECURITY_SCRIPT'],
    ['iframe', '<iframe src="https://example.test"></iframe>', 'SECURITY_IFRAME'],
    ['evento HTML', '<div onclick="alert(1)">texto</div>', 'SECURITY_EVENT_HANDLER'],
    ['javascript URL', '[x](javascript:alert(1))', 'SECURITY_URL'],
    ['MDX import', "import Widget from './Widget.jsx'", 'SECURITY_MDX_MODULE'],
    ['JSX', '<Widget />', 'SECURITY_HTML'],
    ['expresión MDX', '{danger()}', 'SECURITY_MDX_EXPRESSION'],
  ])('rechaza %s', (_name, unsafeMarkdown, code) => {
    expect(() =>
      analyzeMarkdown(`# Documento\n\n${unsafeMarkdown}\n`, 'reglamento/01_doc.md'),
    ).toThrowError(expect.objectContaining({ code }))
  })

  it('no confunde llaves de texto simples con una expresión ejecutable', () => {
    expect(() =>
      analyzeMarkdown('# Documento\n\nEl conjunto {pilota, red} es descriptivo.\n', 'doc.md'),
    ).not.toThrow()
  })
})

describe('referencias internas', () => {
  it('normaliza un enlace relativo, su anchor y una referencia por ID', async () => {
    const root = await createTemporaryDirectory()
    await writeFiles(root, {
      'reglamento/01_uno.md': documentSource({
        id: 'REG-001',
        slug: 'uno',
        markdown: '# Documento\n\n[Detalle](02_dos.md#detalle) y REG-002.\n',
      }),
      'reglamento/02_dos.md': documentSource({
        id: 'REG-002',
        slug: 'dos',
        markdown: '# Documento\n\n## Detalle\n\nContenido.\n',
      }),
    })

    const { artifact } = await compileKnowledge(root)
    expect(artifact.documents[0].references).toEqual([
      { type: 'document', targetId: 'REG-002', fragment: 'detalle' },
      { type: 'document', targetId: 'REG-002', fragment: null },
    ])
  })

  it('rechaza un archivo relativo inexistente y referencias por etiqueta no soportadas', async () => {
    await expectCorpusError(
      {
        'reglamento/01_uno.md': documentSource({
          markdown: '# Documento\n\n[Falta](02_inexistente.md).\n',
        }),
      },
      'REFERENCE_FILE_MISSING',
    )

    expect(() =>
      analyzeMarkdown('# Documento\n\n[Destino][regla]\n\n[regla]: 02_dos.md\n', 'doc.md'),
    ).toThrowError(expect.objectContaining({ code: 'REFERENCE_STYLE_UNSUPPORTED' }))
  })

  it('rechaza un anchor inexistente', async () => {
    await expectCorpusError(
      {
        'reglamento/01_uno.md': documentSource({
          id: 'REG-001',
          markdown: '# Documento\n\n[No existe](02_dos.md#ausente).\n',
        }),
        'reglamento/02_dos.md': documentSource({ id: 'REG-002', slug: 'dos' }),
      },
      'REFERENCE_ANCHOR_MISSING',
    )
  })

  it('rechaza una referencia por ID inexistente', async () => {
    await expectCorpusError(
      {
        'reglamento/01_uno.md': documentSource({ markdown: '# Documento\n\nREG-999.\n' }),
      },
      'REFERENCE_ID_MISSING',
    )
  })

  it('rechaza traversal fuera de knowledge', async () => {
    await expectCorpusError(
      {
        'reglamento/01_uno.md': documentSource({
          markdown: '# Documento\n\n[Fuera](../../fuera.md).\n',
        }),
      },
      'REFERENCE_TRAVERSAL',
    )
  })
})

describe('determinismo', () => {
  it('produce los mismos bytes con independencia del orden de escritura', async () => {
    const firstRoot = await createTemporaryDirectory()
    const secondRoot = await createTemporaryDirectory()
    const first = documentSource({ id: 'REG-001', slug: 'primero' })
    const second = documentSource({ id: 'REG-002', slug: 'segundo' })

    await writeFiles(firstRoot, {
      'reglamento/02_segundo.md': second,
      'reglamento/01_primero.md': first,
    })
    await writeFiles(secondRoot, {
      'reglamento/01_primero.md': first,
      'reglamento/02_segundo.md': second,
    })

    const firstBytes = serializeArtifact((await compileKnowledge(firstRoot)).artifact)
    const secondBytes = serializeArtifact((await compileKnowledge(secondRoot)).artifact)

    expect(firstBytes).toBe(secondBytes)
    expect(firstBytes).not.toContain(firstRoot)
    expect(firstBytes).not.toContain(secondRoot)
    expect(firstBytes).not.toContain('generatedAt')
  })
})

describe('corpus real y artefacto', () => {
  it('valida el corpus completo, sus colecciones y exclusiones', async () => {
    const { artifact, discovery } = await compileKnowledge(DEFAULT_KNOWLEDGE_ROOT)

    expect(artifact.schemaVersion).toBe(1)
    expect(artifact.collections.map(({ id, documentCount }) => [id, documentCount])).toEqual([
      ['reglamento', 8],
      ['conceptos/elementos', 12],
      ['conceptos/personas', 4],
      ['conceptos/juego', 16],
    ])
    expect(artifact.documents).toHaveLength(40)
    expect(artifact.documents.map(({ id }) => id)).toEqual(
      expect.arrayContaining(['REG-001', 'REG-008', 'CON-ELE-001', 'CON-JUE-016', 'CON-PER-004']),
    )
    expect(artifact.documents.map(({ id }) => id)).not.toContain('REG-000')
    expect(artifact.documents.every(({ sourcePath }) => !sourcePath.endsWith('README.md'))).toBe(true)
    expect(discovery.excluded).toHaveLength(4)
  })

  it('mantiene versionado el artefacto exactamente sincronizado con el corpus', async () => {
    const { artifact } = await compileKnowledge(DEFAULT_KNOWLEDGE_ROOT)
    const committedBytes = await readFile(DEFAULT_OUTPUT_PATH, 'utf8')

    expect(committedBytes).toBe(serializeArtifact(artifact))
  })
})

describe('escritura segura', () => {
  it('crea el directorio de salida y escribe el artefacto completo', async () => {
    const root = await createTemporaryDirectory()
    const outputRoot = await createTemporaryDirectory()
    const outputPath = path.join(outputRoot, 'nested', 'knowledge.json')
    await writeFiles(root, {
      'reglamento/01_uno.md': documentSource(),
    })

    const result = await buildKnowledge(root, outputPath)

    expect(await readFile(outputPath, 'utf8')).toBe(result.bytes)
    expect(JSON.parse(result.bytes).documents).toHaveLength(1)
  })

  it('no sobrescribe la salida ni deja parciales cuando la validación falla', async () => {
    const root = await createTemporaryDirectory()
    const outputRoot = await createTemporaryDirectory()
    const outputPath = path.join(outputRoot, 'knowledge.json')
    await writeFiles(root, {
      'reglamento/01_uno.md': documentSource({ status: 'Archivado' }),
    })
    await writeFile(outputPath, 'salida anterior\n', 'utf8')

    await expect(buildKnowledge(root, outputPath)).rejects.toMatchObject({
      code: 'STATUS_INVALID',
    })
    expect(await readFile(outputPath, 'utf8')).toBe('salida anterior\n')
    expect((await readdir(outputRoot)).filter((name) => name.endsWith('.tmp'))).toEqual([])
  })
})
