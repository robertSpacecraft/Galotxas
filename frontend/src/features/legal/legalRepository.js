import publicLegal from '../../generated/legal/public-legal.json';
import { legalPages } from './legalRoutes';

const SCHEMA_VERSION = 1;
const PUBLIC_DOCUMENT_KEYS = new Set([
  'id',
  'title',
  'slug',
  'version',
  'status',
  'publishedAt',
  'reviewedAt',
  'owner',
  'summary',
  'route',
  'order',
  'headings',
  'blocks',
]);

const assertLegalArtifact = (artifact) => {
  const contracts = Object.values(legalPages);

  if (
    artifact?.schemaVersion !== SCHEMA_VERSION
    || !Array.isArray(artifact.documents)
    || artifact.documents.length !== contracts.length
  ) {
    throw new Error('El artefacto legal público no cumple schemaVersion 1.');
  }

  for (const [index, contract] of contracts.entries()) {
    const document = artifact.documents[index];
    if (
      document?.id !== contract.id
      || document.route !== contract.path
      || document.order !== index + 1
      || document.status !== 'vigente'
      || typeof document.title !== 'string'
      || typeof document.summary !== 'string'
      || typeof document.version !== 'string'
      || typeof document.publishedAt !== 'string'
      || !Array.isArray(document.headings)
      || !Array.isArray(document.blocks)
      || Object.keys(document).some((key) => !PUBLIC_DOCUMENT_KEYS.has(key))
    ) {
      throw new Error(`El documento legal ${contract.id} no cumple el contrato público.`);
    }
  }
};

export const createLegalRepository = (artifact) => {
  assertLegalArtifact(artifact);
  const documents = [...artifact.documents];
  const documentsById = new Map(documents.map((document) => [document.id, document]));

  return Object.freeze({
    schemaVersion: artifact.schemaVersion,
    getDocuments: () => [...documents],
    getDocumentById: (pageId) => documentsById.get(pageId) ?? null,
  });
};

export const legalRepository = createLegalRepository(publicLegal);
