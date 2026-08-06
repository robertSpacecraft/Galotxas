// @vitest-environment node

import {
  mkdir,
  mkdtemp,
  readFile,
  rm,
  writeFile,
} from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'
import {
  DEFAULT_LEGAL_OUTPUT_PATH,
  DEFAULT_LEGAL_ROOT,
  FORM_NOTICES,
  LEGAL_DOCUMENTS,
} from './config.js'
import {
  assertLegalArtifactCurrent,
  compileFormNoticeArtifact,
  compileLegalArtifact,
  serializeLegalArtifact,
} from './compiler.js'

const temporaryRoots = []

const createFixture = async (mutations = {}, extraFiles = {}) => {
  const parent = await mkdtemp(path.join(tmpdir(), 'galotxas-legal-'))
  temporaryRoots.push(parent)
  const legalRoot = path.join(parent, 'legal')
  await mkdir(legalRoot, { recursive: true })

  for (const document of LEGAL_DOCUMENTS) {
    const canonical = await readFile(path.join(DEFAULT_LEGAL_ROOT, document.filename), 'utf8')
    const mutation = mutations[document.filename]
    await writeFile(
      path.join(legalRoot, document.filename),
      typeof mutation === 'function' ? mutation(canonical) : canonical,
      'utf8',
    )
  }

  await writeFile(path.join(legalRoot, 'README.md'), '# README excluido\n', 'utf8')
  for (const [filename, content] of Object.entries(extraFiles)) {
    await writeFile(path.join(legalRoot, filename), content, 'utf8')
  }

  return { parent, legalRoot }
}

afterEach(async () => {
  await Promise.all(temporaryRoots.splice(0).map((root) => rm(root, { recursive: true, force: true })))
})

describe('legal compiler', () => {
  it('projects exactly the three allowlisted documents and excludes README and draft paths', async () => {
    const { artifact, discovery } = await compileLegalArtifact(DEFAULT_LEGAL_ROOT)

    expect(artifact.documents.map(({ id, slug, route }) => ({ id, slug, route }))).toEqual([
      { id: 'LEG-001', slug: 'aviso-legal', route: '/legal/aviso-legal' },
      { id: 'LEG-002', slug: 'privacidad', route: '/legal/privacidad' },
      { id: 'LEG-003', slug: 'cookies', route: '/legal/cookies' },
    ])
    expect(discovery.excluded).toEqual([
      { sourcePath: 'notices', reason: 'avisos de formulario separados' },
      { sourcePath: 'README.md', reason: 'documentación técnica' },
    ])
    expect(JSON.stringify(artifact)).not.toMatch(/legal-drafts|knowledge\//i)
  })

  it('projects the single allowlisted form notice separately from public legal pages', async () => {
    const { artifact } = await compileFormNoticeArtifact(DEFAULT_LEGAL_ROOT)

    expect(artifact.notices).toHaveLength(1)
    expect(artifact.notices[0]).toMatchObject({
      id: 'NOTICE-PUBLIC-IDENTITY-MINORS',
      version: '1.0.0',
      status: 'vigente',
      scope: 'public_competition_identity',
      owner: 'Club Galotxes de Monover',
    })
    expect(JSON.stringify(artifact)).not.toMatch(/knowledge\/|legal-drafts|guardian@example/i)
  })

  it('rejects unknown form notices and a mismatched scope', async () => {
    const parent = await mkdtemp(path.join(tmpdir(), 'galotxas-notices-'))
    temporaryRoots.push(parent)
    const legalRoot = path.join(parent, 'legal')
    const noticesRoot = path.join(legalRoot, 'notices')
    await mkdir(noticesRoot, { recursive: true })
    const contract = FORM_NOTICES[0]
    const canonical = await readFile(
      path.join(DEFAULT_LEGAL_ROOT, 'notices', contract.filename),
      'utf8',
    )
    await writeFile(
      path.join(noticesRoot, contract.filename),
      canonical.replace('scope: public_competition_identity', 'scope: public_images'),
      'utf8',
    )
    await expect(compileFormNoticeArtifact(legalRoot)).rejects.toMatchObject({
      code: 'NOTICE_METADATA_CONTRACT_INVALID',
    })

    await writeFile(path.join(noticesRoot, contract.filename), canonical, 'utf8')
    await writeFile(path.join(noticesRoot, 'unknown.md'), '# Unknown\n', 'utf8')
    await expect(compileFormNoticeArtifact(legalRoot)).rejects.toMatchObject({
      code: 'NOTICE_SOURCE_UNKNOWN',
    })
  })

  it('validates versions, dates, publication metadata and deterministic output', async () => {
    const first = await compileLegalArtifact(DEFAULT_LEGAL_ROOT)
    const second = await compileLegalArtifact(DEFAULT_LEGAL_ROOT)

    expect(first.artifact.documents).toEqual(expect.arrayContaining([
      expect.objectContaining({
        version: '1.0.0',
        status: 'vigente',
        publishedAt: '2026-08-06',
        reviewedAt: '2026-08-06',
        owner: 'Club Galotxes de Monover',
      }),
    ]))
    expect(serializeLegalArtifact(first.artifact)).toBe(serializeLegalArtifact(second.artifact))
    await expect(assertLegalArtifactCurrent(DEFAULT_LEGAL_ROOT, DEFAULT_LEGAL_OUTPUT_PATH))
      .resolves.toEqual(expect.objectContaining({ artifact: first.artifact }))
  })

  it('does not read sibling internal drafts or mutate Knowledge artifacts', async () => {
    const { parent, legalRoot } = await createFixture()
    const draftDirectory = path.join(parent, 'docs', 'legal-drafts')
    await mkdir(draftDirectory, { recursive: true })
    await writeFile(path.join(draftDirectory, 'interno.md'), 'BORRADOR INTERNO — NO PUBLICAR\n', 'utf8')

    const knowledgePaths = [
      path.resolve(DEFAULT_LEGAL_ROOT, '../frontend/src/generated/knowledge/knowledge.json'),
      path.resolve(DEFAULT_LEGAL_ROOT, '../frontend/src/generated/knowledge/public-knowledge.json'),
    ]
    const before = await Promise.all(knowledgePaths.map((file) => readFile(file, 'utf8')))
    const { artifact } = await compileLegalArtifact(legalRoot)
    const after = await Promise.all(knowledgePaths.map((file) => readFile(file, 'utf8')))

    expect(after).toEqual(before)
    expect(JSON.stringify(artifact)).not.toContain('interno.md')
  })

  it('rejects a missing title', async () => {
    const { legalRoot } = await createFixture({
      'aviso-legal.md': (source) => source.replace(/^title:.*\n/m, ''),
    })
    await expect(compileLegalArtifact(legalRoot)).rejects.toMatchObject({ code: 'METADATA_REQUIRED' })
  })

  it('rejects duplicate slugs before applying the closed file contract', async () => {
    const { legalRoot } = await createFixture({
      'cookies.md': (source) => source.replace('slug: cookies', 'slug: privacidad'),
    })
    await expect(compileLegalArtifact(legalRoot)).rejects.toMatchObject({ code: 'SLUG_DUPLICATE' })
  })

  it('rejects an unknown publication status', async () => {
    const { legalRoot } = await createFixture({
      'privacidad.md': (source) => source.replace('status: vigente', 'status: archivado'),
    })
    await expect(compileLegalArtifact(legalRoot)).rejects.toMatchObject({ code: 'STATUS_INVALID' })
  })

  it('rejects an empty body and a document without its title H1', async () => {
    const empty = await createFixture({
      'aviso-legal.md': (source) => source.replace(/\n---\n# Aviso legal[\s\S]*$/, '\n---\n'),
    })
    await expect(compileLegalArtifact(empty.legalRoot)).rejects.toMatchObject({ code: 'MARKDOWN_EMPTY' })

    const noHeading = await createFixture({
      'aviso-legal.md': (source) => source.replace('# Aviso legal', 'Aviso legal'),
    })
    await expect(compileLegalArtifact(noHeading.legalRoot)).rejects.toMatchObject({ code: 'HEADING_H1_REQUIRED' })
  })

  it('fails closed for any fourth file', async () => {
    const { legalRoot } = await createFixture({}, { 'otro.md': '# Otro\n' })
    await expect(compileLegalArtifact(legalRoot)).rejects.toMatchObject({ code: 'SOURCE_UNKNOWN' })
  })

  it('rejects internal markers and phone-shaped data from public content', async () => {
    const marker = await createFixture({
      'cookies.md': (source) => source.replace('## Vigencia', 'PENDIENTE DE CONFIRMACIÓN\n\n## Vigencia'),
    })
    await expect(compileLegalArtifact(marker.legalRoot)).rejects.toMatchObject({
      code: 'PUBLIC_MARKER_FORBIDDEN',
    })

    const phone = ['612', '345', '678'].join(' ')
    const withPhone = await createFixture({
      'cookies.md': (source) => source.replace('## Vigencia', `${phone}\n\n## Vigencia`),
    })
    await expect(compileLegalArtifact(withPhone.legalRoot)).rejects.toMatchObject({
      code: 'PRIVATE_PHONE_FORBIDDEN',
    })
  })

  it('detects a stale generated projection', async () => {
    const { parent, legalRoot } = await createFixture()
    const outputPath = path.join(parent, 'public-legal.json')
    await writeFile(outputPath, '{}\n', 'utf8')
    await expect(assertLegalArtifactCurrent(legalRoot, outputPath)).rejects.toMatchObject({
      code: 'OUTPUT_STALE',
    })
  })
})
