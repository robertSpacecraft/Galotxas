import { fileURLToPath } from 'node:url'

export const SCHEMA_VERSION = 1

export const REQUIRED_METADATA_FIELDS = [
  'id',
  'slug',
  'titulo',
  'version',
  'estado',
  'ultima_revision',
]

export const ALLOWED_STATUSES = new Set(['Borrador', 'Vigente'])

export const COLLECTIONS = [
  {
    id: 'reglamento',
    title: 'Reglamento',
    sourceDirectory: 'reglamento',
    order: 1,
    idPattern: /^REG-\d{3}$/,
    filenamePattern: /^\d{2}_[a-z0-9_]+\.md$/,
  },
  {
    id: 'conceptos/elementos',
    title: 'Conceptos — Elementos',
    sourceDirectory: 'conceptos/elementos',
    order: 2,
    idPattern: /^CON-ELE-\d{3}$/,
    filenamePattern: /^[a-z0-9_]+\.md$/,
  },
  {
    id: 'conceptos/personas',
    title: 'Conceptos — Personas',
    sourceDirectory: 'conceptos/personas',
    order: 3,
    idPattern: /^CON-PER-\d{3}$/,
    filenamePattern: /^[a-z0-9_]+\.md$/,
  },
  {
    id: 'conceptos/juego',
    title: 'Conceptos — Juego',
    sourceDirectory: 'conceptos/juego',
    order: 4,
    idPattern: /^CON-JUE-\d{3}$/,
    filenamePattern: /^[a-z0-9_]+\.md$/,
  },
]

export const EXCLUDED_MARKDOWN = new Map([
  ['AGENTS.md', 'instrucciones técnicas para agentes'],
  ['README.md', 'README técnico de knowledge'],
  ['conceptos/README.md', 'índice documental de Conceptos'],
  [
    'reglamento/00_metodologia.md',
    'metodología documental que declara no formar parte del reglamento',
  ],
])

export const DEFAULT_KNOWLEDGE_ROOT = fileURLToPath(
  new URL('../../../knowledge/', import.meta.url),
)

export const DEFAULT_OUTPUT_PATH = fileURLToPath(
  new URL('../../src/generated/knowledge/knowledge.json', import.meta.url),
)
