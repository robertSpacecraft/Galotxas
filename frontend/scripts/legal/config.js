import { fileURLToPath } from 'node:url'

export const LEGAL_SCHEMA_VERSION = 1

export const REQUIRED_METADATA_FIELDS = Object.freeze([
  'id',
  'title',
  'slug',
  'version',
  'status',
  'published_at',
  'reviewed_at',
  'owner',
  'source_draft',
  'summary',
])

export const ALLOWED_LEGAL_STATUSES = new Set(['vigente'])

export const LEGAL_DOCUMENTS = Object.freeze([
  Object.freeze({
    id: 'LEG-001',
    filename: 'aviso-legal.md',
    title: 'Aviso legal',
    slug: 'aviso-legal',
    route: '/legal/aviso-legal',
    sourceDraft: 'docs/legal-drafts/aviso-legal.borrador.md',
    order: 1,
  }),
  Object.freeze({
    id: 'LEG-002',
    filename: 'privacidad.md',
    title: 'Política de privacidad',
    slug: 'privacidad',
    route: '/legal/privacidad',
    sourceDraft: 'docs/legal-drafts/privacidad.borrador.md',
    order: 2,
  }),
  Object.freeze({
    id: 'LEG-003',
    filename: 'cookies.md',
    title: 'Política de cookies y almacenamiento local',
    slug: 'cookies',
    route: '/legal/cookies',
    sourceDraft: 'docs/legal-drafts/cookies.borrador.md',
    order: 3,
  }),
])

export const DEFAULT_LEGAL_ROOT = fileURLToPath(
  new URL('../../../legal/', import.meta.url),
)

export const DEFAULT_LEGAL_OUTPUT_PATH = fileURLToPath(
  new URL('../../src/generated/legal/public-legal.json', import.meta.url),
)
